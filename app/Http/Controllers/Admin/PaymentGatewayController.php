<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\PaymentGateway;
use App\Services\Payment\PaymentGatewayManager;
use Illuminate\Http\Request;

/**
 * Admin payment-gateway settings. Every gateway in DRIVER_MAP is
 * pre-seeded by PaymentGatewaySeeder, so there's no "install" step —
 * the admin just enters credentials, picks a mode, and saves.
 *
 *   GET   /admin/payment-gateways              → list all gateways
 *   PATCH /admin/payment-gateways/{id}         → save credentials + mode
 *   POST  /admin/payment-gateways/{id}/toggle  → activate / deactivate
 *
 * Credentials are ENCRYPTED at rest (PaymentGateway::setEncryptedCredentials()).
 * Secret fields are never re-emitted to the form — a blank submission keeps
 * the stored value (mirrors the SMTP-password handling in MailController).
 */
class PaymentGatewayController extends Controller
{
    public function __construct(private readonly PaymentGatewayManager $manager) {}

    public function index()
    {
        // Self-heal: backfill a row for any catalog gateway added after the
        // initial seed so it always shows up here automatically.
        $this->manager->ensureCatalogRows();

        $gateways = PaymentGateway::orderBy('sort_order')->orderBy('name')->get();

        // Attach the driver's credentialFields() schema + decrypted
        // credentials so each per-gateway form can render dynamically.
        $gateways = $gateways->map(function ($g) {
            $g->credential_fields     = $this->manager->credentialFieldsFor($g->slug);
            $g->credentials_decrypted = $g->getDecryptedCredentials();
            return $g;
        });

        return view('admin.payment-gateways.index', [
            'gateways'   => $gateways,
            'currencies' => Currency::where('is_active', true)->orderBy('code')->get(['code', 'name']),
        ]);
    }

    public function update(Request $request, int $id)
    {
        $gateway = PaymentGateway::findOrFail($id);
        $fields  = $this->manager->credentialFieldsFor($gateway->slug);

        $data = $request->validate([
            'mode'                   => ['required', 'in:sandbox,live'],
            'sort_order'             => ['nullable', 'integer', 'min:0'],
            'supported_currencies'   => ['nullable', 'array'],
            'supported_currencies.*' => ['string', 'max:10'],
            'credentials'            => ['nullable', 'array'],
        ]);

        // Merge: only overwrite a key when the form submitted a non-empty
        // value. Lets the password placeholder ("leave blank to keep")
        // actually keep the existing credential.
        $existing = $gateway->getDecryptedCredentials();
        $incoming = $data['credentials'] ?? [];
        $merged   = $existing;
        foreach ($fields as $key => $spec) {
            if (array_key_exists($key, $incoming) && $incoming[$key] !== '') {
                $merged[$key] = $incoming[$key];
            }
        }
        $gateway->setEncryptedCredentials($merged);
        $gateway->fill([
            'mode'                 => $data['mode'],
            'sort_order'           => $data['sort_order'] ?? $gateway->sort_order,
            'supported_currencies' => $data['supported_currencies'] ?? [],
        ])->save();

        return back()->with('success', $gateway->name . ' settings saved.');
    }

    public function toggle(int $id)
    {
        $gateway = PaymentGateway::findOrFail($id);

        // Refuse to activate without the required credentials filled in —
        // otherwise the gateway would appear at checkout but fail on the
        // first request.
        if (! $gateway->is_active) {
            $required = collect($this->manager->credentialFieldsFor($gateway->slug))
                ->filter(fn ($spec) => ! empty($spec['required']))->keys();
            $creds   = $gateway->getDecryptedCredentials();
            $missing = $required->filter(fn ($k) => empty($creds[$k]))->all();
            if (! empty($missing)) {
                return back()->with('error', 'Configure required fields first: ' . implode(', ', $missing));
            }
        }

        $gateway->update(['is_active' => ! $gateway->is_active]);

        return back()->with('success', $gateway->is_active ? 'Activated.' : 'Deactivated.');
    }
}
