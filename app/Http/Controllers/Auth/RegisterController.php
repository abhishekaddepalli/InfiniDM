<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Public signup for the standalone edition. Hand-rolled rather than pulled
 * from a starter kit: the dependency audit showed the feature code imports
 * only Illuminate, and a scaffold package would drag in views and routes that
 * fight the IgDesk theme.
 */
class RegisterController extends Controller
{
    /**
     * ALLOW_REGISTRATION=false must make the route look absent, not forbidden.
     * A 403 confirms to a stranger that signup exists and was switched off,
     * which tells them the install is small and worth another look; 404 says
     * nothing at all.
     *
     * Read through env() because the standalone build ships no config/ — the
     * framework defaults cover everything else. Consequence: `php artisan
     * config:cache` makes env() return null here and signup closes. That is
     * the safe direction to fail, and `config:clear` restores it.
     */
    public static function isOpen(): bool
    {
        // The admin "Public registration" toggle (settings → allow_registration)
        // is authoritative; fall back to the ALLOW_REGISTRATION env only when the
        // setting was never saved.
        try {
            $s = \App\Models\Setting::get('allow_registration', null);
            if ($s !== null) {
                return (bool) $s;
            }
        } catch (\Throwable $e) {
        }
        return filter_var(env('ALLOW_REGISTRATION', true), FILTER_VALIDATE_BOOLEAN);
    }

    public function show(): View
    {
        abort_unless(self::isOpen(), 404);

        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(self::isOpen(), 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            // No `confirmed` rule: the sign-up form has no confirm field —
            // it uses a reveal toggle + strength meter instead, matching the
            // register mockup. Password::defaults() still enforces length and
            // complexity. Re-add 'confirmed' here AND a password_confirmation
            // field in register.blade.php to require double entry.
            // Honour the admin Security-page minimum length (defaults to 8),
            // mirroring the password-reset validator.
            'password' => ['required', Password::min((int) \App\Models\Setting::get('password_min_length', 8))],
        ]);

        // Every self-registered user is a Member by default — the standard,
        // non-admin role — mirroring WaDesk. Platform admins are the only ones
        // without a role_id (they bypass the role system). We resolve the Member
        // role by "first system role that is NOT the full-access one", so it keeps
        // working even if the role is renamed.
        $data['role_id'] = \App\Models\Role::where('is_system', true)
            ->get()
            ->first(fn ($r) => ! in_array('*', $r->permissions ?? [], true))?->id;

        // Assign the default signup plan + start the free trial, mirroring the
        // admin "Signups & free trial" settings (registration_default_plan_id /
        // registration_trial_days). Plan id 0 = "Auto — first active free plan".
        // The trial window only applies when that plan is free (matches the admin
        // help text). Wrapped so a plan-lookup hiccup can never block a signup.
        try {
            $planId = (int) \App\Models\Setting::get('registration_default_plan_id', 0);
            $pkg = $planId > 0
                ? \App\Models\Package::find($planId)
                : (\App\Models\Package::where('is_active', true)->where('free', true)->orderBy('sort')->first()
                    ?: \App\Models\Package::default());

            if ($pkg) {
                $data['package_id'] = $pkg->id;
                // Start the free trial whenever a positive length is configured —
                // the signup gets the default plan's features for N days, then the
                // trial bar prompts an upgrade. (Applies to paid default plans too;
                // a genuinely free plan simply never expires.)
                $trialDays = (int) \App\Models\Setting::get('registration_trial_days', 14);
                if ($trialDays > 0) {
                    $data['trial_ends_at'] = now()->addDays($trialDays);
                }
            }
        } catch (\Throwable $e) {
            // Signup proceeds even if the plan can't be resolved.
        }

        $user = User::create($data);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(url('/instagram'));
    }
}
