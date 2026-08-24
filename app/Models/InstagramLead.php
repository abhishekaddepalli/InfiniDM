<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A captured Instagram lead. Two sources:
 *   - 'lead_ad'  — Meta Lead Ad instant form, via the Page `leadgen` webhook,
 *                  hydrated from GET /{leadgen_id} (leadgen_id de-dups it).
 *   - 'dm'       — a DM lead-capture flow (Ask questions → Capture lead node);
 *                  no leadgen_id, tied to the conversation by igsid.
 * deal_id links it into the Sales Pipeline once auto-created.
 */
class InstagramLead extends Model
{
    protected $fillable = [
        'workspace_id', 'instagram_account_id', 'source', 'igsid',
        'leadgen_id', 'page_id', 'form_id', 'ad_id',
        'full_name', 'email', 'phone', 'status', 'notes',
        'field_data', 'deal_id', 'lead_created_at',
    ];

    protected $casts = [
        'field_data'      => 'array',
        'lead_created_at' => 'datetime',
    ];

    public function scopeForWorkspace($q, int $wsId)
    {
        return $q->where('workspace_id', $wsId);
    }

    public function account()
    {
        return $this->belongsTo(InstagramAccount::class, 'instagram_account_id');
    }

    /** Best-effort display label for the lead. */
    public function label(): string
    {
        return trim((string) ($this->full_name ?: $this->email ?: $this->phone ?: ('Lead #' . $this->id)));
    }
}
