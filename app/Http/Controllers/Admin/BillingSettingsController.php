<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

/**
 * Billing + Wallet/affiliate settings — both persist into the Setting table
 * (the same admin-editable store the general Settings and Security pages use).
 *
 * Mirrors WaDesk's checkout/billing settings, recolored to IgDesk: tax,
 * invoice identity, refund window, yearly discount, auto-renew; plus an
 * optional wallet + affiliate program. No secrets here, so every value is read
 * straight back into the form.
 */
class BillingSettingsController extends Controller
{
    // ───────────────────────── Billing ─────────────────────────

    public function billing()
    {
        $defaults = [
            'tax_label'                => 'VAT',
            'invoice_prefix'           => 'INV-',
            'refund_window_days'       => 14,
        ];
        $keys = [
            'tax_enabled', 'tax_rate', 'tax_label', 'invoice_prefix',
            'company_name', 'company_address', 'company_tax_id',
            'refund_window_days',
        ];

        $values = [];
        foreach ($keys as $k) {
            $values[$k] = Setting::get($k, $defaults[$k] ?? '');
        }
        $values['tax_enabled'] = (bool) $values['tax_enabled'];

        return view('admin.billing-settings', compact('values'));
    }

    public function billingSave(Request $r)
    {
        $data = $r->validate([
            'tax_enabled'             => ['nullable', 'boolean'],
            'tax_rate'                => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tax_label'               => ['nullable', 'string', 'max:32'],
            'invoice_prefix'          => ['nullable', 'string', 'max:32'],
            'company_name'            => ['nullable', 'string', 'max:160'],
            'company_address'         => ['nullable', 'string', 'max:1000'],
            'company_tax_id'          => ['nullable', 'string', 'max:64'],
            'refund_window_days'      => ['nullable', 'integer', 'min:0', 'max:3650'],
        ]);

        Setting::set('tax_rate', $data['tax_rate'] ?? '');
        Setting::set('tax_label', $data['tax_label'] ?? '');
        Setting::set('invoice_prefix', $data['invoice_prefix'] ?? '');
        Setting::set('company_name', $data['company_name'] ?? '');
        Setting::set('company_address', $data['company_address'] ?? '');
        Setting::set('company_tax_id', $data['company_tax_id'] ?? '');
        Setting::set('refund_window_days', (int) ($data['refund_window_days'] ?? 14), 'int');
        Setting::set('tax_enabled', (bool) ($data['tax_enabled'] ?? false), 'bool');

        return back()->with('success', __('Billing settings saved.'));
    }

    // ───────────────────────── Wallet & affiliate ─────────────────────────

    public function wallet()
    {
        $defaults = [
            'wallet_currency' => Setting::get('default_currency', 'USD') ?: 'USD',
        ];
        $keys = [
            'wallet_enabled', 'affiliate_enabled', 'referral_signup_credit',
            'affiliate_commission_percent', 'min_payout_amount', 'wallet_currency',
        ];

        $values = [];
        foreach ($keys as $k) {
            $values[$k] = Setting::get($k, $defaults[$k] ?? '');
        }
        foreach (['wallet_enabled', 'affiliate_enabled'] as $b) {
            $values[$b] = (bool) $values[$b];
        }

        return view('admin.wallet', compact('values'));
    }

    public function walletSave(Request $r)
    {
        $data = $r->validate([
            'wallet_enabled'               => ['nullable', 'boolean'],
            'affiliate_enabled'            => ['nullable', 'boolean'],
            'referral_signup_credit'       => ['nullable', 'numeric', 'min:0'],
            'affiliate_commission_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'min_payout_amount'            => ['nullable', 'numeric', 'min:0'],
            'wallet_currency'              => ['nullable', 'string', 'max:8'],
        ]);

        Setting::set('referral_signup_credit', $data['referral_signup_credit'] ?? '');
        Setting::set('min_payout_amount', $data['min_payout_amount'] ?? '');
        Setting::set('wallet_currency', $data['wallet_currency'] ?? '');
        Setting::set('affiliate_commission_percent', (int) ($data['affiliate_commission_percent'] ?? 0), 'int');
        Setting::set('wallet_enabled', (bool) ($data['wallet_enabled'] ?? false), 'bool');
        Setting::set('affiliate_enabled', (bool) ($data['affiliate_enabled'] ?? false), 'bool');

        return back()->with('success', __('Wallet & affiliate settings saved.'));
    }
}
