<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Admin-editable global config, one row per key.
 *
 * Standalone ships no SystemSetting, and config/env cannot be edited from a UI,
 * so this is where the operator's choices (site name, logo, Meta credentials)
 * live. Read through the cached get(); write through set(), which busts it.
 */
class Setting extends Model
{
    protected $fillable = ['key', 'value', 'type'];
    public $timestamps = true;

    private const CACHE = 'instaflow.settings.all';

    /**
     * All settings as a flat, type-cast key => value map (cached).
     *
     * NOT named all(): Eloquent's Model::all($columns = ['*']) is inherited with
     * a fixed signature, and overriding it with a no-arg array return is a fatal
     * incompatible-declaration error — which 500'd every admin page.
     */
    public static function map(): array
    {
        return Cache::rememberForever(self::CACHE, function () {
            return static::query()->get()->mapWithKeys(fn ($s) => [
                $s->key => static::castOut($s->value, $s->type),
            ])->all();
        });
    }

    public static function get(string $key, $default = null)
    {
        return static::map()[$key] ?? $default;
    }

    public static function set(string $key, $value, string $type = 'string'): void
    {
        static::updateOrCreate(
            ['key' => $key],
            ['value' => static::castIn($value, $type), 'type' => $type],
        );
        Cache::forget(self::CACHE);
    }

    private static function castOut($value, string $type)
    {
        return match ($type) {
            'bool' => (bool) $value,
            'int'  => (int) $value,
            'json' => json_decode((string) $value, true) ?: [],
            default => $value,
        };
    }

    private static function castIn($value, string $type)
    {
        return match ($type) {
            'bool' => $value ? '1' : '0',
            'json' => json_encode($value),
            default => (string) $value,
        };
    }
}
