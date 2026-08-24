<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Admin\AdminAiKeyController;
use App\Models\Order;
use App\Models\Package;
use App\Models\UserAiKey;
use App\Support\FormatSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * /account — the signed-in customer's own profile page.
 *
 * A tabbed surface ported from WaDesk's user account page, trimmed to what
 * IgDesk actually has: Profile, Plan & billing, and Invoices/Orders. There
 * is NO Workspace model here — the plan (users.package_id + users.plan_ends_at)
 * and the plan orders (Order.user_id) hang directly off the User. Billing is
 * one-time only, so there is no subscription-cancel / auto-renew UI.
 */
class AccountController extends Controller
{
    public function index(Request $request): View
    {
        $user = Auth::user()->fresh();

        // Current plan — resolved from users.package_id. Null = free / no plan.
        $package = $user->package_id ? Package::find($user->package_id) : null;

        // This user's plan orders, newest first. Scoped strictly to their own
        // user_id — each customer sees only their purchase history.
        $orders = Order::query()
            ->where('user_id', $user->id)
            ->with('package')
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        // Lifetime spend across every PAID order, converted into the display
        // currency so the headline is meaningful even when orders span
        // currencies. Same shape as the per-row display uses.
        $targetCode = strtoupper((string) setting('default_currency', 'USD'));
        $ordersLifetimeAmount = (float) $orders
            ->where('status', 'paid')
            ->sum(fn (Order $o) => FormatSettings::convert(
                (float) ($o->total_amount ?: $o->amount),
                (string) ($o->currency ?: $targetCode),
                $targetCode,
            ));

        // BYOK — the AI providers the user can set their own key for, with
        // whether a key is already saved (the secret itself is never exposed to
        // the view) and their chosen default model. Providers mirror the admin
        // AI-keys page so the two stay in lockstep.
        $saved = UserAiKey::where('user_id', $user->id)->get()->keyBy('provider');
        $aiProviders = collect([
            'openai'    => __('OpenAI'),
            'anthropic' => __('Anthropic Claude'),
            'gemini'    => __('Google Gemini'),
            'mistral'   => __('Mistral'),
        ])->map(function ($label, $provider) use ($saved) {
            $row = $saved->get($provider);
            return [
                'provider'      => $provider,
                'label'         => $label,
                'has_key'       => (bool) ($row && ! empty($row->api_key)),
                'default_model' => $row->default_model ?? '',
                'models'        => AdminAiKeyController::MODELS[$provider] ?? [],
            ];
        })->values();

        return view('account.index', [
            'authUser'             => $user,
            'package'              => $package,
            'orders'               => $orders,
            'ordersLifetimeAmount' => $ordersLifetimeAmount,
            'aiProviders'          => $aiProviders,
        ]);
    }

    /**
     * Save the user's BYOK AI keys. Upserts one UserAiKey row per provider,
     * scoped to the signed-in user's id (IgDesk has no workspaces — a key is
     * owned by the user). A blank key field leaves any saved key untouched; a
     * key of "-" clears it. The default-model select is stored alongside.
     */
    public function updateAiKeys(Request $request): RedirectResponse
    {
        $user      = Auth::user();
        $providers = ['openai', 'anthropic', 'gemini', 'mistral'];

        $data = $request->validate([
            'keys'          => ['nullable', 'array'],
            'keys.*'        => ['nullable', 'string', 'max:1024'],
            'models'        => ['nullable', 'array'],
            'models.*'      => ['nullable', 'string', 'max:80'],
        ]);

        foreach ($providers as $provider) {
            $key   = trim((string) ($data['keys'][$provider] ?? ''));
            $model = trim((string) ($data['models'][$provider] ?? ''));

            $row = UserAiKey::firstOrNew([
                'user_id'  => $user->id,
                'provider' => $provider,
            ]);

            // Blank = leave the saved key as-is. "-" = explicit clear.
            if ($key === '-') {
                $row->api_key = null;
            } elseif ($key !== '') {
                $row->api_key = $key;
            }

            $row->default_model = $model !== '' ? $model : null;

            // Only persist when there's something to store — avoids empty rows.
            if ($row->exists || ! empty($row->api_key) || ! empty($row->default_model)) {
                $row->save();
            }
        }

        return redirect()->route('account.index', ['tab' => 'ai-keys'])
            ->with('ai_keys_status', __('AI keys saved.'));
    }

    /**
     * Save the personal-details form. Mirrors the admin user-edit validation
     * (mobile / gender / address block) plus name + email + optional avatar.
     * Avatar is stored on the public disk, exactly like the admin form.
     */
    public function updateProfile(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $data = $request->validate([
            'name'    => ['required', 'string', 'max:120'],
            'email'   => ['required', 'email', 'max:160', Rule::unique('users', 'email')->ignore($user->id)],
            'mobile'  => ['nullable', 'string', 'max:40'],
            'gender'  => ['nullable', 'in:m,f,o'],
            'address' => ['nullable', 'string', 'max:1000'],
            'country' => ['nullable', 'string', 'max:80'],
            'state'   => ['nullable', 'string', 'max:80'],
            'city'    => ['nullable', 'string', 'max:80'],
            'zip'     => ['nullable', 'string', 'max:20'],
            'notes'   => ['nullable', 'string', 'max:2000'],
            'avatar'  => ['nullable', 'image', 'max:2048'],
        ]);

        // New avatar → store on the public disk and best-effort delete the old
        // stored file (never a social http URL — IgDesk stores none for
        // users, but guard anyway).
        if ($request->hasFile('avatar')) {
            $old = $user->avatar;
            $user->avatar = $request->file('avatar')->store('avatars', 'public');
            if ($old && ! Str::startsWith($old, ['http://', 'https://'])) {
                try {
                    if (Storage::disk('public')->exists($old)) {
                        Storage::disk('public')->delete($old);
                    }
                } catch (\Throwable $e) { /* best-effort cleanup */ }
            }
        }

        $user->fill([
            'name'    => $data['name'],
            'email'   => $data['email'],
            'mobile'  => $data['mobile']  ?? null,
            'gender'  => $data['gender']  ?? null,
            'address' => $data['address'] ?? null,
            'country' => $data['country'] ?? null,
            'state'   => $data['state']   ?? null,
            'city'    => $data['city']    ?? null,
            'zip'     => $data['zip']      ?? null,
            'notes'   => $data['notes']    ?? null,
        ])->save();

        return redirect()->route('account.index', ['tab' => 'profile'])
            ->with('status', __('Profile saved.'));
    }

    /**
     * Change password — current-password check + a fresh confirmed password.
     * Rotates the remember token so any previously issued remember-me cookie
     * stops validating after the change.
     */
    public function updatePassword(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password'         => ['required', 'confirmed', 'min:8'],
        ]);

        $user = Auth::user();
        $user->forceFill([
            'password'       => Hash::make($request->input('password')),
            'remember_token' => Str::random(60),
        ])->save();

        return redirect()->route('account.index', ['tab' => 'security'])
            ->with('password_status', __('Password updated.'));
    }
}
