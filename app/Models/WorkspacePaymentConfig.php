<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A merchant's native in-chat "Direct Pay Method" config (created by them in
 * the platform's business manager — provider-side, we can't make it via API).
 * We store the config NAME and reference it on payment sends. Region-gated:
 * native in-chat pay is only usable in a supported country (India today).
 *
 * Ported from WaDesk's WhatsApp Pay foundation. IgDesk does not ship the
 * WhatsApp provider config layer, so the providerConfig() relation is omitted
 * (see TODO below). The table + config storage are kept so a later phase can
 * wire native pay without another migration.
 */
class WorkspacePaymentConfig extends Model
{
    protected $fillable = [
        'workspace_id', 'provider_config_id', 'config_name', 'payment_type',
        'country', 'currency', 'merchant_category', 'is_active', 'meta_json',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'meta_json' => 'encrypted:array',
    ];

    /** ISO-2 countries where native in-chat order_details pay is usable today. */
    public const SUPPORTED_COUNTRIES = ['IN']; // India (full). Brazil=Pix-only, ID/MX=testing → excluded.

    // Bare payment_gateway.type values accepted in order_details.payment_settings.
    public const PAYMENT_TYPES = ['razorpay', 'payu', 'billdesk', 'zaakpay'];

    public static function isCountrySupported(?string $iso2): bool
    {
        return $iso2 !== null && in_array(strtoupper($iso2), self::SUPPORTED_COUNTRIES, true);
    }

    // TODO(instaflow): WaDesk related this to App\Models\WaProviderConfig (the
    // WABA row). IgDesk has no WhatsApp provider-config model, so the
    // belongsTo relation is omitted. provider_config_id is still stored as a
    // plain column for a future native-pay wiring phase.

    public function scopeForWorkspace($q, ?int $workspaceId)
    {
        return $workspaceId ? $q->where('workspace_id', $workspaceId) : $q;
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }
}
