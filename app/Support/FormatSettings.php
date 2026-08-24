<?php

namespace App\Support;

use App\Models\Currency;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Currency display + conversion helpers. Ported from WaDesk's
 * App\Support\FormatSettings and adapted to IgDesk: there is no Workspace
 * model here, so there is no per-tenant currency — everything resolves against
 * the single global default currency (Setting 'default_currency', falling back
 * to USD). Exchange rates live on the `currencies` table (vs USD), managed by
 * Admin\LocalizationController.
 *
 * Usage:
 *   FormatSettings::currency(199.99)          → "$199.99"  (global default)
 *   FormatSettings::display($amount, 'USD')   → converts to the display ccy
 *   FormatSettings::formatIn($amount, 'INR')  → "₹16,732.50" (no conversion)
 *   FormatSettings::convert(100, 'USD', 'INR')→ 8366.25
 */
class FormatSettings
{
    /**
     * Resolve the currency that applies globally (the admin default). The
     * $workspace argument is accepted for call-site parity with WaDesk but
     * ignored — IgDesk has no per-workspace currency.
     */
    public static function currencyFor($workspace = null): ?Currency
    {
        $code = strtoupper((string) Setting::get('default_currency', 'USD'));
        return Cache::remember('currency_by_code_' . $code, 60, function () use ($code) {
            return Currency::where('code', $code)->first();
        });
    }

    /**
     * Format an amount for display. Amount is assumed to be in the target
     * currency already — for cross-currency conversion call `convert()` first
     * or use `display()` which combines both.
     */
    public static function currency(float|int $amount, $workspace = null): string
    {
        $c = self::currencyFor($workspace);
        if (!$c) return number_format((float) $amount, 2);
        $formatted = number_format((float) $amount, (int) $c->precision, '.', ',');
        return ($c->symbol ?: ($c->code . ' ')) . $formatted;
    }

    /**
     * Convert + format in one call. The canonical pattern for displaying money
     * stored in one currency in the display currency:
     *
     *   FormatSettings::display($order->amount, $order->currency)
     *
     * If $fromCode is null we assume the amount is already in the display
     * currency and skip conversion. If $fromCode equals the target we also skip.
     */
    public static function display(float|int $amount, ?string $fromCode = null, $workspace = null): string
    {
        $target = self::currencyFor($workspace);
        if (!$target) return number_format((float) $amount, 2);

        $fromCode = $fromCode ? strtoupper($fromCode) : null;
        $converted = ($fromCode && $fromCode !== $target->code)
            ? self::convert($amount, $fromCode, $target->code)
            : (float) $amount;

        return self::currency($converted, $workspace);
    }

    /**
     * Format an amount STRICTLY in the given currency code, no conversion.
     * For invoices, receipts, and any other "historical document" where the
     * displayed currency must match what was actually paid.
     */
    public static function formatIn(float|int $amount, ?string $code): string
    {
        $code = $code ? strtoupper($code) : null;
        if (!$code) return number_format((float) $amount, 2);

        $c = Cache::remember('currency_by_code_' . $code, 60, function () use ($code) {
            return Currency::where('code', $code)->first();
        });
        if (!$c) return strtoupper($code) . ' ' . number_format((float) $amount, 2);
        $formatted = number_format((float) $amount, (int) $c->precision, '.', ',');
        return ($c->symbol ?: ($c->code . ' ')) . $formatted;
    }

    /**
     * Convert between currencies using stored exchange rates (vs USD).
     *   convert(100, 'USD', 'INR') → INR value of 100 USD
     */
    public static function convert(float|int $amount, string $fromCode, string $toCode): float
    {
        $fromCode = strtoupper($fromCode);
        $toCode   = strtoupper($toCode);
        if ($fromCode === $toCode) return (float) $amount;

        $rates = Cache::remember('currency_rates_map', 60, function () {
            return Currency::query()->where('is_active', true)->pluck('exchange_rate', 'code')->toArray();
        });
        $fromRate = (float) ($rates[$fromCode] ?? 1.0);
        $toRate   = (float) ($rates[$toCode]   ?? 1.0);
        if ($fromRate <= 0) return (float) $amount; // bad config, refuse to divide by zero
        $usd = (float) $amount / $fromRate;
        return round($usd * $toRate, 2);
    }

    /**
     * The active default currency's symbol (or code + space if no symbol).
     * Falls back to '$' when nothing configured.
     */
    public static function symbol($workspace = null): string
    {
        $c = self::currencyFor($workspace);
        return $c ? ($c->symbol ?: ($c->code . ' ')) : '$';
    }

    /** Clear the cache after admin saves a currency or fetches rates. */
    public static function flushCache(): void
    {
        Cache::forget('currency_rates_map');
        foreach (Currency::query()->pluck('code') as $code) {
            Cache::forget('currency_by_code_' . strtoupper($code));
        }
    }
}
