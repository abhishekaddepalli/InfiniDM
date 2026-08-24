<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    private const MAX_ATTEMPTS = 5;
    private const DECAY_SECONDS = 60;

    public function show(): View
    {
        return view('auth.login', [
            'allowRegistration' => RegisterController::isOpen(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $key = $this->throttleKey($request);

        // Honour the admin's Security settings (login_max_attempts /
        // login_decay_minutes); fall back to the constants when unset so a
        // fresh install still throttles.
        $maxAttempts  = (int) (\App\Models\Setting::get('login_max_attempts', self::MAX_ATTEMPTS) ?: self::MAX_ATTEMPTS);
        $decaySeconds = ((int) \App\Models\Setting::get('login_decay_minutes', 0)) * 60 ?: self::DECAY_SECONDS;

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            \App\Models\AuditLog::record('user.login_throttled', (string) $request->input('email'));
            throw ValidationException::withMessages([
                'email' => __('Too many login attempts. Try again in :seconds seconds.', [
                    'seconds' => RateLimiter::availableIn($key),
                ]),
            ]);
        }

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($key, $decaySeconds);
            \App\Models\AuditLog::record('user.login_failed', (string) $request->input('email'));

            // One message for both a wrong password and an unknown address —
            // distinguishing them turns the form into an account-enumeration
            // oracle.
            throw ValidationException::withMessages([
                'email' => __('These credentials do not match our records.'),
            ]);
        }

        RateLimiter::clear($key);

        // Fresh session id on privilege change, or a session fixated before
        // login stays valid afterwards.
        $request->session()->regenerate();

        \App\Models\AuditLog::record('user.login', (string) $request->input('email'));

        // Admins land on the admin dashboard after sign-in (this is where a
        // fresh install drops the installer account); everyone else on the user
        // app. An explicit deep-link the user was headed to still wins.
        // Admins land on the insights dashboard, NOT the bare /admin index —
        // some hosts' firewalls (Hostinger ModSecurity) block the literal
        // "/admin" path, so we deep-link to a real sub-page instead.
        $default = auth()->user()->isAdmin() ? url('/admin/insights') : url('/instagram');

        return redirect()->intended($default);
    }

    public function destroy(Request $request): RedirectResponse
    {
        \App\Models\AuditLog::record('user.logout', (string) (auth()->user()->email ?? ''));

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /**
     * Email *and* IP: keyed on email alone one attacker could lock a known
     * user out of their own account, and on IP alone a botnet spreads the
     * guesses across addresses for free.
     */
    private function throttleKey(Request $request): string
    {
        return 'login|' . Str::transliterate(Str::lower((string) $request->input('email'))) . '|' . $request->ip();
    }
}
