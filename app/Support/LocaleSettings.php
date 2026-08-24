<?php

namespace App\Support;

use App\Models\Language;
use Illuminate\Support\Facades\Cache;

/**
 * The single source of truth for "which languages can this install use, and
 * which way does each one read". Everything else — the SetLocale middleware,
 * the header dropdown, the <html dir> attribute — asks this class rather than
 * touching the languages table directly, so the fallback story (fresh install
 * with no table yet) lives in exactly one place.
 *
 * Mirrors WaDesk's LocaleSettings so the two apps behave identically.
 */
class LocaleSettings
{
    /** Active languages, ordered for display. Empty collection if the table isn't there yet. */
    public static function active()
    {
        if (! \App\Http\Controllers\InstallController::isInstalled()) {
            return collect();
        }

        try {
            return Cache::remember('locale.active', 300, function () {
                return Language::where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->get(['code', 'name', 'native_name', 'is_rtl', 'is_default']);
            });
        } catch (\Throwable $e) {
            return collect();
        }
    }

    /** Bust the cache after an admin toggles/activates a language. */
    public static function forget(): void
    {
        try { Cache::forget('locale.active'); } catch (\Throwable $e) {}
    }

    /** Is this code one a user is allowed to switch to right now? */
    public static function isAvailable(string $code): bool
    {
        $code = trim($code);
        if ($code === '') return false;
        if ($code === (string) config('app.locale', 'en')) return true; // base bundle always ships

        return self::active()->contains(fn ($l) => $l->code === $code);
    }

    /** Platform default — the language flagged default, else config('app.locale'). */
    public static function defaultLocale(): string
    {
        $def = self::active()->firstWhere('is_default', true);
        if ($def) return (string) $def->code;

        return (string) config('app.locale', 'en');
    }

    /** 'rtl' | 'ltr' for the given code. Defaults to ltr for anything unknown. */
    public static function directionFor(string $code): string
    {
        $lang = self::active()->firstWhere('code', $code);
        if ($lang) return $lang->is_rtl ? 'rtl' : 'ltr';

        // Not in the active set (e.g. the base bundle) — fall back to a known
        // list of right-to-left scripts so Arabic still flips even before an
        // admin adds the row.
        return in_array($code, ['ar', 'he', 'fa', 'ur', 'ps', 'sd', 'ug', 'yi'], true) ? 'rtl' : 'ltr';
    }
}
