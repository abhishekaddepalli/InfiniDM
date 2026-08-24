<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

/**
 * Forgot / reset password. IgDesk shipped without this on purpose (no mailer),
 * but the admin now configures SMTP at /admin/settings/mail (applied at boot),
 * so the flow works. Uses Laravel's password broker + the framework
 * ResetPassword notification, which mails via the configured mailer.
 *
 * The four route names are framework-fixed: password.request, password.email,
 * password.reset (keeps its {token}), password.update.
 */
class ForgotPasswordController extends Controller
{
    /** Step 1 — "email me a reset link" form. */
    public function showLinkRequest(): View
    {
        return view('auth.forgot-password');
    }

    /** Step 2 — send the link. Response is identical whether or not the address
     *  exists, so the form can't be used to enumerate accounts. */
    public function sendLink(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $status = Password::sendResetLink($request->only('email'));

        AuditLog::record('password.reset_requested', $request->input('email'));

        // Always show the neutral "if that address exists…" confirmation.
        return back()->with('status', __('If that email is registered, a password reset link is on its way.'));
    }

    /** Step 3 — the reset form the emailed link opens. */
    public function showReset(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    /** Step 4 — set the new password. */
    public function reset(Request $request): RedirectResponse
    {
        $request->validate([
            'token'    => ['required'],
            'email'    => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min((int) (\App\Models\Setting::get('password_min_length', 8) ?: 8))],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password'       => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            AuditLog::record('password.reset_completed', $request->input('email'));
            return redirect()->route('login')->with('status', __('Your password has been reset. You can sign in now.'));
        }

        return back()->withInput($request->only('email'))
            ->withErrors(['email' => __($status)]);
    }
}
