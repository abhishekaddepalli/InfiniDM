<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\MailConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

/**
 * Mail settings — SMTP credentials used by every mailable. Saved values are
 * applied at boot via MailConfig::apply(). Mirrors WaDesk's mail settings page.
 * The password is never re-emitted; a blank submit keeps the stored value.
 */
class MailController extends Controller
{
    public function show()
    {
        $mail = [
            'from_name'    => (string) (Setting::get('mail_from_name', '') ?: site_name()),
            'from_address' => (string) Setting::get('mail_from_address', ''),
            'mailer'       => (string) Setting::get('mail_mailer', 'smtp'),
            'encryption'   => (string) Setting::get('mail_encryption', 'tls'),
            'host'         => (string) Setting::get('mail_host', ''),
            'port'         => (string) Setting::get('mail_port', ''),
            'username'     => (string) Setting::get('mail_username', ''),
            'password_set' => (bool) Setting::get('mail_password', ''),
        ];
        return view('admin.settings.mail', compact('mail'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'mail_from_name'    => ['required', 'string', 'max:120'],
            'mail_from_address' => ['required', 'email', 'max:200'],
            'mail_mailer'       => ['required', 'string', 'in:smtp,sendmail,log,mailgun,ses,postmark,resend'],
            'mail_encryption'   => ['nullable', 'in:tls,ssl,starttls,'],
            'mail_host'         => ['required', 'string', 'max:200'],
            'mail_port'         => ['required', 'integer', 'min:1', 'max:65535'],
            'mail_username'     => ['nullable', 'string', 'max:200'],
            'mail_password'     => ['nullable', 'string', 'max:500'],
        ]);

        foreach (['mail_from_name', 'mail_from_address', 'mail_mailer', 'mail_encryption', 'mail_host', 'mail_port', 'mail_username'] as $k) {
            Setting::set($k, $data[$k] ?? '');
        }
        if (! empty($data['mail_password'])) {
            Setting::set('mail_password', $data['mail_password']);
        }

        return back()->with('success', __('Mail settings saved.'));
    }

    public function test(Request $request)
    {
        $data = $request->validate(['to' => ['required', 'email']]);

        MailConfig::apply();

        try {
            Mail::raw(
                __('This is a test email from :app. If you received it, your SMTP settings are working.', ['app' => site_name()]),
                function ($m) use ($data) {
                    $m->to($data['to'])->subject(site_name() . ' — ' . __('SMTP test'));
                }
            );
            return back()->with('success', __('Test email sent to :to.', ['to' => $data['to']]));
        } catch (\Throwable $e) {
            return back()->with('error', __('Send failed: :m', ['m' => $e->getMessage()]));
        }
    }
}
