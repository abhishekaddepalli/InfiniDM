<?php

namespace App\Services\Instagram;

use App\Models\Deal;
use App\Models\InstagramAccount;
use App\Models\InstagramLead;
use App\Models\Pipeline;
use Illuminate\Support\Facades\Log;

/**
 * Captures Instagram leads gathered in a DM flow (the "Capture lead" node) and,
 * optionally, drops them into the Sales Pipeline as a Deal — so an influencer or
 * company runs a DM funnel with zero ads and every lead lands in one place.
 */
class InstagramLeadService
{
    /**
     * Save a DM-captured lead. $fields carries already-resolved values:
     *   ['full_name'=>, 'email'=>, 'phone'=>, 'notes'=>, 'extra'=>[label=>value,…]]
     * $createDeal drops it into the default pipeline. Idempotent-ish: re-running
     * the same funnel turn updates the existing open lead for this igsid rather
     * than piling up duplicates. Returns the InstagramLead (or null on failure).
     */
    public function capture(InstagramAccount $account, string $igsid, array $fields, bool $createDeal = true): ?InstagramLead
    {
        try {
            $wsId  = (int) $account->workspace_id;
            $name  = trim((string) ($fields['full_name'] ?? ''));
            $email = trim((string) ($fields['email'] ?? ''));
            $phone = trim((string) ($fields['phone'] ?? ''));
            $notes = trim((string) ($fields['notes'] ?? ''));
            $extra = is_array($fields['extra'] ?? null) ? $fields['extra'] : [];

            // Reuse an in-flight lead for this conversation (same funnel, more
            // answers) instead of creating a fresh row on every Capture node.
            $lead = InstagramLead::forWorkspace($wsId)
                ->where('instagram_account_id', $account->id)
                ->where('igsid', $igsid)
                ->where('source', 'dm')
                ->whereIn('status', ['new', 'contacted'])
                ->latest('id')
                ->first();

            $payload = array_filter([
                'workspace_id'         => $wsId,
                'instagram_account_id' => $account->id,
                'source'               => 'dm',
                'igsid'                => $igsid,
                'full_name'            => $name !== '' ? mb_substr($name, 0, 191) : null,
                'email'                => $email !== '' ? mb_substr($email, 0, 191) : null,
                'phone'                => $phone !== '' ? mb_substr($phone, 0, 64) : null,
                'notes'                => $notes !== '' ? $notes : null,
                'field_data'           => $extra ?: null,
                'lead_created_at'      => now(),
            ], fn ($v) => $v !== null);

            if ($lead) {
                $lead->fill($payload)->save();
            } else {
                $payload['status'] = 'new';
                $lead = InstagramLead::create($payload);
            }

            if ($createDeal && ! $lead->deal_id) {
                $deal = $this->maybeCreateDeal($lead);
                if ($deal) $lead->forceFill(['deal_id' => $deal->id])->save();
            }

            Log::info('[IG-LEAD] captured', [
                'account' => $account->id, 'lead' => $lead->id,
                'deal'    => $lead->deal_id, 'igsid' => $igsid,
            ]);
            return $lead;
        } catch (\Throwable $e) {
            Log::warning('[IG-LEAD] capture failed: ' . $e->getMessage(), ['account' => $account->id]);
            return null;
        }
    }

    /**
     * Capture a Meta Lead Ad submission (from the Page `leadgen` webhook).
     * $webhook = the webhook value ([leadgen_id, page_id, form_id, ad_id, …]);
     * $lead = the hydrated GET /{leadgen_id} response (field_data, …).
     * De-duped by the unique leadgen_id so Meta retries can't double-insert.
     */
    public function captureFromLeadAd(InstagramAccount $account, array $webhook, array $lead, bool $createDeal = true): ?InstagramLead
    {
        try {
            $leadgenId = trim((string) ($webhook['leadgen_id'] ?? $lead['id'] ?? ''));
            if ($leadgenId === '') return null;

            $existing = InstagramLead::where('leadgen_id', $leadgenId)->first();
            if ($existing) return $existing;

            // Flatten field_data ([{name, values:[…]}, …]) into name→value.
            $fields = [];
            foreach ((array) ($lead['field_data'] ?? []) as $f) {
                $key = strtolower(trim((string) ($f['name'] ?? '')));
                if ($key === '') continue;
                $vals = $f['values'] ?? null;
                $fields[$key] = is_array($vals) ? implode(', ', array_map('strval', $vals)) : (string) $vals;
            }

            $full = trim((string) ($fields['full_name']
                ?? trim(($fields['first_name'] ?? '') . ' ' . ($fields['last_name'] ?? ''))));
            $email = trim((string) ($fields['email'] ?? $fields['work_email'] ?? ''));
            $phone = trim((string) ($fields['phone_number'] ?? $fields['phone'] ?? ''));

            $row = InstagramLead::create([
                'workspace_id'         => (int) $account->workspace_id,
                'instagram_account_id' => $account->id,
                'source'               => 'lead_ad',
                'leadgen_id'           => $leadgenId,
                'page_id'              => (string) ($webhook['page_id'] ?? '') ?: null,
                'form_id'              => (string) ($webhook['form_id'] ?? $lead['form_id'] ?? '') ?: null,
                'ad_id'                => (string) ($webhook['ad_id'] ?? $lead['ad_id'] ?? '') ?: null,
                'full_name'            => $full !== '' ? mb_substr($full, 0, 191) : null,
                'email'                => $email !== '' ? mb_substr($email, 0, 191) : null,
                'phone'                => $phone !== '' ? mb_substr($phone, 0, 64) : null,
                'status'               => 'new',
                'field_data'           => $fields ?: null,
                'lead_created_at'      => now(),
            ]);

            if ($createDeal && ! $row->deal_id) {
                $deal = $this->maybeCreateDeal($row);
                if ($deal) $row->forceFill(['deal_id' => $deal->id])->save();
            }

            Log::info('[IG-LEAD] lead-ad captured', [
                'account' => $account->id, 'lead' => $row->id, 'leadgen' => $leadgenId, 'deal' => $row->deal_id,
            ]);
            return $row;
        } catch (\Throwable $e) {
            Log::warning('[IG-LEAD] lead-ad capture failed: ' . $e->getMessage(), ['account' => $account->id]);
            return null;
        }
    }

    /** Create a deal for the lead at the "Lead" stage of the Instagram funnel. */
    private function maybeCreateDeal(InstagramLead $lead): ?Deal
    {
        try {
            $pipe     = app(InstagramPipelineService::class);
            $pipeline = $pipe->pipeline((int) $lead->workspace_id);
            if (! $pipeline) return null;                  // no CRM in this install
            $stageId  = $pipe->stageId($pipeline, 'lead')
                ?? optional($pipeline->stages()->orderBy('sort_order')->first())->id;
            if (! $stageId) return null;

            $title = trim(($lead->label() ?: 'Instagram lead') . ' — IG');

            return Deal::create([
                'workspace_id' => $lead->workspace_id,
                'pipeline_id'  => $pipeline->id,
                'stage_id'     => $stageId,
                'contact_id'   => null,
                'title'        => mb_substr($title, 0, 191),
                'value_minor'  => 0,
                'currency'     => $pipeline->currency,
                'source'       => 'ig_lead',
                'meta'         => ['ig_lead_id' => (int) $lead->id, 'igsid' => (string) $lead->igsid],
            ]);
        } catch (\Throwable $e) {
            Log::warning('[IG-LEAD] deal create failed: ' . $e->getMessage());
            return null;
        }
    }
}
