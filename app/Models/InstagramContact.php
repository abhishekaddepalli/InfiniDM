<?php

namespace App\Models;

use App\Services\Instagram\InstagramService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * A person who has DMed a connected Instagram account. Keyed by the sender's
 * IGSID; carries their fetched @username / name / avatar so the inbox can show
 * WHO messaged instead of the account's own handle.
 */
class InstagramContact extends Model
{
    protected $fillable = [
        'workspace_id', 'instagram_account_id', 'igsid',
        'username', 'name', 'email', 'phone', 'attributes',
        'avatar_url', 'last_message_at', 'ai_enabled',
        // Which AI agent (instagram_automations row, type=ai_agent) replies to
        // THIS conversation. null = use the account's default (first active),
        // exactly like the Team Inbox per-conversation agent picker.
        'ai_agent_id',
        // Per-conversation inbox controls (local; Instagram has no such API).
        'muted_at', 'archived_at', 'hidden_at',
        // Read cursor — highest message id the operator has seen in this thread.
        'last_read_id',
    ];

    protected $casts = [
        'last_message_at' => 'datetime', 'ai_enabled' => 'boolean', 'ai_agent_id' => 'integer',
        'muted_at' => 'datetime', 'archived_at' => 'datetime', 'hidden_at' => 'datetime',
        'attributes' => 'array',
    ];

    /**
     * Stable, self-healing avatar URL for display. The raw `avatar_url` is an
     * IG CDN signed link that expires in ~1–2 days and then 403s; routing it
     * through the ig-avatar proxy means callers (the inbox AND the WaDesk
     * mirror) render a url that never expires. Null when no avatar was ever
     * resolved → the UI shows initials / the IG silhouette.
     */
    public function getAvatarProxyUrlAttribute(): ?string
    {
        if (empty($this->avatar_url)) return null;
        if (str_contains((string) $this->avatar_url, '/ig-avatar/')) return $this->avatar_url;
        return url('ig-avatar/' . $this->instagram_account_id . '/' . $this->igsid);
    }

    /**
     * Persist an attribute answer captured by a flow. name/email/phone are real
     * columns; anything else lands in the JSON `attributes` bag under its key.
     * Empty values are ignored so a skipped question never wipes known data.
     */
    public function setAttributeValue(string $key, string $value): void
    {
        $key   = trim($key);
        $value = trim($value);
        if ($key === '' || $value === '') return;

        if (in_array($key, ['name', 'email', 'phone'], true)) {
            $this->{$key} = mb_substr($value, 0, $key === 'phone' ? 32 : 191);
        } else {
            $bag = is_array($this->attributes) ? $this->attributes : [];
            $bag[$key] = mb_substr($value, 0, 500);
            $this->attributes = $bag;
        }
        $this->save();
    }

    /**
     * Everything we know about this contact as a flat {{key}} => value map, so a
     * flow can pre-load it into its variables (built-ins + custom bag).
     */
    public function attributeMap(): array
    {
        $map = array_filter([
            'name'  => (string) ($this->name ?? ''),
            'email' => (string) ($this->email ?? ''),
            'phone' => (string) ($this->phone ?? ''),
        ], fn ($v) => $v !== '');

        foreach ((array) ($this->attributes ?? []) as $k => $v) {
            if (is_scalar($v) && (string) $v !== '') $map[(string) $k] = (string) $v;
        }
        return $map;
    }

    /** Best display label for this sender. */
    public function displayName(): string
    {
        if (!empty($this->username)) return '@' . ltrim($this->username, '@');
        if (!empty($this->name))     return (string) $this->name;
        return 'Instagram user';
    }

    /**
     * Upsert the contact for an inbound sender and fill in whatever we're still
     * missing (username, name, avatar) from the IG Graph API. Best-effort — a
     * failure never blocks message handling. Safe to call on every inbound DM.
     *
     * $knownUsername lets a caller that ALREADY has the handle (the inbox sync
     * gets it on the conversation participant) seed it without spending a call.
     *
     * The fetch is gated on avatar_url too, not username alone: the sync
     * pre-created rows WITH a username and no picture, so a username-only guard
     * meant the avatar was never fetched for them — every thread showed the grey
     * silhouette. Same trap when getSenderProfile()'s `profile_pic` retry saved
     * the username but no picture: the miss became permanent.
     *
     * Throttled per contact so a persistently failing IGSID can't hammer Graph
     * on every DM. The window doubles as a refresh — IG avatar URLs are signed
     * and expire, so re-fetching an already-known contact is a feature.
     */
    public static function touchFromInbound(InstagramAccount $account, string $igsid, string $knownUsername = ''): self
    {
        $contact = static::firstOrNew([
            'instagram_account_id' => $account->id,
            'igsid'                => $igsid,
        ]);
        $contact->workspace_id    = $account->workspace_id;
        $contact->last_message_at = now();

        if ($knownUsername !== '' && empty($contact->username)) {
            $contact->username = ltrim($knownUsername, '@');
        }

        $needsProfile = empty($contact->username) || empty($contact->avatar_url);
        $throttleKey  = 'ig-profile:' . $account->id . ':' . $igsid;
        // Cache::add() is atomic and only succeeds when the key is absent, so
        // the first call in the window wins and the rest are no-ops.
        if ($needsProfile && Cache::add($throttleKey, 1, now()->addHours(6))) {
            try {
                $p = (new InstagramService($account))->getSenderProfile($igsid);
                if (!empty($p['username']))    $contact->username   = (string) $p['username'];
                if (!empty($p['name']))        $contact->name       = (string) $p['name'];
                if (!empty($p['profile_pic'])) $contact->avatar_url = (string) $p['profile_pic'];
            } catch (\Throwable $e) {
                Log::info('[IG-CONTACT] profile fetch failed: ' . $e->getMessage(), ['igsid' => $igsid]);
            }
        }

        $contact->save();
        return $contact;
    }
}
