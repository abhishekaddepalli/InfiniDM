<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Platform-owned global AI provider keys. The fallback every AI feature
 * resolves to when a user hasn't set their own key (the flow "AI" node).
 *
 * One row per provider — `provider` is unique. Encrypted at rest via the
 * Crypt mutators on `api_key` below (NOT the framework `encrypted` cast, so a
 * stale blob from a rotated APP_KEY renders as null instead of 500-ing the
 * whole admin page).
 */
class AdminAiKey extends Model
{
    protected $table = 'admin_ai_keys';

    protected $fillable = [
        'provider', 'name', 'api_key', 'default_model',
        'extra_config', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Decrypt on read. Returns null if the stored blob can't be decrypted
     * (usually because APP_KEY was rotated since the row was saved) so the
     * admin page still renders and the operator can re-paste a fresh key.
     */
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

    /** Decode extra_config JSON to an array. Never throws. */
    public function getExtraConfigArrayAttribute(): array
    {
        if (empty($this->extra_config)) return [];
        $arr = json_decode($this->extra_config, true);
        return is_array($arr) ? $arr : [];
    }

    public function setExtraConfigArrayAttribute(array $value): void
    {
        $this->extra_config = json_encode($value, JSON_UNESCAPED_UNICODE);
    }

    /** Read one extra_config key. */
    public function getExtra(string $key, $default = null)
    {
        return $this->extra_config_array[$key] ?? $default;
    }

    /** True if this provider is fully configured (active + has key). */
    public function isReady(): bool
    {
        return $this->is_active && !empty($this->api_key);
    }

    public static function activeFor(string $provider): ?self
    {
        return static::query()
            ->where('provider', $provider)
            ->where('is_active', true)
            ->first();
    }
}
