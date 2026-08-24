<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A connected Instagram Professional/Creator account. The access token is
 * encrypted at rest. One workspace can connect several accounts.
 */
class InstagramAccount extends Model
{
    protected $fillable = [
        'workspace_id', 'user_id',
        'ig_user_id', 'username', 'name', 'profile_pic_url', 'page_id', 'login_type',
        'access_token', 'token_expires_at', 'scopes',
        'status', 'followers_count', 'last_error', 'meta_json',
        'catalog_id', 'business_id', 'catalog_synced_at',
        // Meta Ads: the ad account (act_XXXX, prefix-stripped) this IG account
        // runs campaigns through. Discovered + saved on /instagram/ads/connect.
        'ad_account_id',
        // The WaDesk workspace this account is linked to (nullable). The ONLY
        // thing tying an IgDesk IG account to a WaDesk workspace; stamped by
        // the WaDesk-initiated link / popup-connect flow.
        'wadesk_workspace_id',
    ];

    protected $casts = [
        'access_token'      => 'encrypted',
        'token_expires_at'  => 'datetime',
        'scopes'            => 'array',
        'meta_json'         => 'array',
        'catalog_synced_at' => 'datetime',
    ];

    protected $hidden = ['access_token'];

    public function scopeForWorkspace($q, int $workspaceId)
    {
        return $q->where('workspace_id', $workspaceId);
    }

    public function isLive(): bool
    {
        return $this->status === 'connected'
            && (!$this->token_expires_at || $this->token_expires_at->isFuture());
    }
}
