<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Per-user "bring your own key" (BYOK) AI provider credentials.
 *
 * IgDesk has no workspaces — every row is workspace_id = 0 and ownership is
 * by user_id — so a customer's own key is scoped to their user_id (one row per
 * user + provider). When set, it takes priority over the platform AdminAiKey.
 *
 * `api_key` is encrypted at rest via the Crypt mutators below (same posture as
 * AdminAiKey: a stale blob from a rotated APP_KEY decrypts to null rather than
 * throwing).
 */
class UserAiKey extends Model
{
    protected $table = 'user_ai_keys';

    protected $fillable = ['user_id', 'provider', 'api_key', 'default_model'];

    /** Decrypt on read; null when the blob can't be decrypted. */
    public function getApiKeyAttribute($value): ?string
    {
        if ($value === null || $value === '') return null;
        try {
            return \Crypt::decryptString($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /** Encrypt on write. Empty string / null clears the key. */
    public function setApiKeyAttribute($value): void
    {
        $this->attributes['api_key'] = ($value === null || $value === '')
            ? null
            : \Crypt::encryptString((string) $value);
    }

    /** The user's decrypted key for a provider, or null. */
    public static function forUser(int $userId, string $provider): ?string
    {
        if ($userId <= 0) return null;
        $row = static::query()
            ->where('user_id', $userId)
            ->where('provider', strtolower(trim($provider)))
            ->first();
        return $row?->api_key ?: null;
    }
}
