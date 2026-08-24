<?php

namespace App\Instagram\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * The `flows` table, seen from the STANDALONE Instagram product.
 *
 * Namespaced under App\Instagram\Models on purpose. The extension ZIP merges
 * into WaDesk's file tree, so it can never ship `App\Models\Flow` — that would
 * overwrite core's model and break WhatsApp and call flows for every existing
 * customer. A separate class at a separate path can be shipped safely and
 * simply goes unused in WaDesk, where core's Flow model is the one in charge.
 *
 * Same table, same columns, same runtime contract — so a flow authored in
 * either product is read identically by node/services/instagramFlowService.js.
 *
 * Anything WhatsApp is absent by design: no keyword_replies mirroring, no
 * devices, no engines. Standalone has none of those tables, and a dormant
 * branch referencing one is how a "works on my machine" bug ships.
 */
class InstagramFlow extends Model
{
    use SoftDeletes;

    protected $table = 'flows';

    protected $fillable = [
        'user_id', 'workspace_id',
        'flow_name', 'category',
        'flow_type', 'provider',
        'flow_data', 'flow_file_path',
        'trigger_kind', 'trigger_value', 'trigger_keywords', 'trigger_device_id',
        'is_published', 'is_active', 'published_at',
    ];

    protected $casts = [
        'flow_data'    => 'encrypted',
        'is_published' => 'boolean',
        'is_active'    => 'boolean',
        'published_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // Standalone only ever authors Instagram flows. Forcing it here rather
        // than trusting the request means a crafted POST cannot create a 'chat'
        // flow that no runtime in this product would ever execute.
        static::saving(function (self $flow) {
            $flow->flow_type = 'instagram';
            $flow->provider  = 'instagram';
        });

        // Mirror the trigger into a managed instagram_automations row, exactly
        // as core's Flow::syncInstagramTriggerAutomation() does — same table,
        // same shape, so the Node runtime cannot tell which product wrote it.
        static::saved(fn (self $flow) => $flow->syncTriggerAutomation());

        static::deleted(function (self $flow) {
            try {
                \App\Models\InstagramAutomation::where('flow_id', $flow->id)
                    ->where('type', 'flow')->delete();
            } catch (\Throwable $e) {
                \Log::warning('[IG-FLOW] automation cleanup failed: ' . $e->getMessage());
            }
        });
    }

    /**
     * flow_data decoded to an array, matching core Flow::decoded_flow_data.
     *
     * The Node runtime and four PHP call sites (the webhook, the bridge and the
     * runner) all read `$flow->decoded_flow_data` and every one does
     * `if (! is_array($data)) return`. Without this accessor Eloquent has no
     * `decoded_flow_data` attribute to resolve, so it returns null, the guard
     * trips, and the flow silently never runs — the hardest failure to notice
     * because nothing errors. Both products author into this same model, so the
     * accessor was missing in WaDesk too; adding it here fixes both.
     *
     * `flow_data` is cast `encrypted`, so `$this->flow_data` is already the
     * decrypted JSON string by the time it reaches here. The floor return keeps
     * the is_array() guards satisfied for an empty or unsaved flow.
     */
    public function getDecodedFlowDataAttribute(): array
    {
        $raw = $this->flow_data;
        if (is_array($raw)) return $raw;
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) return $decoded;
        }

        // Legacy/mirror fallback: rows whose graph lives on disk rather than in
        // the column. New rows write the column, so this only matters for a flow
        // imported as a file. Path shape mirrors core's storage/app/flows layout.
        $path = (string) ($this->flow_file_path ?? '');
        if ($path !== '') {
            $abs = str_starts_with($path, 'flows/')
                ? storage_path('app/' . $path)
                : public_path($path);
            if (is_file($abs)) {
                $decoded = json_decode((string) file_get_contents($abs), true);
                if (is_array($decoded)) return $decoded;
            }
        }

        return ['flowNodes' => [], 'flowEdges' => []];
    }

    /**
     * Keep the managed instagram_automations row in step with this flow.
     *
     * Intentionally a near-copy of core's version rather than a call into it:
     * core's method lives on a class this product does not ship, and importing
     * it would reintroduce exactly the coupling this split exists to remove.
     */
    public function syncTriggerAutomation(): void
    {
        try {
            \App\Models\InstagramAutomation::where('flow_id', $this->id)
                ->where('type', 'flow')->delete();

            if ($this->trigger_kind !== 'keyword') return;

            $keywords = trim((string) ($this->trigger_keywords ?? ''));
            $acctId   = (int) ($this->trigger_device_id ?? 0);
            if ($keywords === '' || $acctId <= 0) return;

            // The account id arrives from flow_data, so never trust it for
            // cross-tenant reach — confirm this workspace owns it first.
            $acct = \App\Models\InstagramAccount::where('id', $acctId)
                ->when($this->workspace_id, fn ($q) => $q->where('workspace_id', $this->workspace_id))
                ->first();
            if (! $acct) return;

            $isCatchAll = in_array($keywords, ['*', '.*', '.+', 'any'], true);

            \App\Models\InstagramAutomation::create([
                'workspace_id'         => $this->workspace_id,
                'instagram_account_id' => $acct->id,
                'type'                 => 'flow',
                'name'                 => $this->flow_name,
                'trigger_keyword'      => $isCatchAll ? '' : $keywords,
                'match_mode'           => $isCatchAll ? 'any' : 'contains',
                'flow_id'              => $this->id,
                'is_active'            => (bool) ($this->is_published && $this->is_active),
            ]);
        } catch (\Throwable $e) {
            \Log::warning('[IG-FLOW] trigger sync failed: ' . $e->getMessage());
        }
    }
}
