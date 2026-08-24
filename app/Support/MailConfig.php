<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Schema;

/**
 * Applies the admin-saved SMTP settings onto the live mail config at boot, so
 * every Mailable / Mail::raw() in the system sends through them — no .env edit
 * or restart. Mirrors WaDesk's MailConfig::apply().
 */
class MailConfig
{
    public static function apply(): void
    {
        try {
            if (! Schema::hasTable('settings')) {
                return;
            }
            $host = (string) Setting::get('mail_host', '');
            if ($host === '') {
                return; // not configured — leave the framework default (.env) in place
            }

            $mailer     = (string) Setting::get('mail_mailer', 'smtp') ?: 'smtp';
            $encryption = (string) Setting::get('mail_encryption', 'tls');

            config([
                'mail.default'                 => $mailer,
                'mail.mailers.smtp.host'       => $host,
                'mail.mailers.smtp.port'       => (int) (Setting::get('mail_port', 587) ?: 587),
                'mail.mailers.smtp.username'   => Setting::get('mail_username', '') ?: null,
                'mail.mailers.smtp.password'   => Setting::get('mail_password', '') ?: null,
                'mail.mailers.smtp.encryption' => $encryption !== '' ? $encryption : null,
                'mail.from.address'            => Setting::get('mail_from_address', '') ?: config('mail.from.address'),
                'mail.from.name'               => Setting::get('mail_from_name', '') ?: config('mail.from.name'),
            ]);
        } catch (\Throwable $e) {
            // Never let a mail-config read break the whole app boot.
        }
    }
}
