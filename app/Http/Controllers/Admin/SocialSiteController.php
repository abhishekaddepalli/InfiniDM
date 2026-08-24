<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

/**
 * Social login (OAuth + reCAPTCHA) and public Site settings — both persist into
 * the admin-editable Setting table. OAuth/captcha secrets are never re-emitted
 * to the form: they ship as blank inputs and an empty submit keeps the stored
 * value, so only `<key>_set` booleans ever reach the view.
 */
class SocialSiteController extends Controller
{
    /** Non-secret social-login / captcha keys. */
    private const SOCIAL_KEYS = [
        'google_login_enabled',
        'google_client_id',
        'facebook_login_enabled',
        'facebook_client_id',
        'recaptcha_enabled',
        'recaptcha_site_key',
        'recaptcha_version',
    ];

    /** Secrets — masked out of the view, kept on blank submit. */
    private const SOCIAL_SECRETS = [
        'google_client_secret',
        'facebook_client_secret',
        'recaptcha_secret_key',
    ];

    /** Site-settings keys (brand / contact / social). */
    private const SITE_KEYS = [
        'site_name',
        'site_tagline',
        'contact_email',
        'contact_phone',
        'contact_address',
        'social_facebook',
        'social_instagram',
        'social_twitter',
        'social_youtube',
        'social_linkedin',
        'footer_text',
    ];

    // ───────────────────────── Social login ─────────────────────────

    public function socialLogin()
    {
        $values = [];
        foreach (self::SOCIAL_KEYS as $k) {
            $values[$k] = Setting::get($k, '');
        }
        foreach (self::SOCIAL_SECRETS as $k) {
            $values[$k . '_set'] = (bool) Setting::get($k, '');
            $values[$k] = '';
        }
        if ($values['recaptcha_version'] === '') {
            $values['recaptcha_version'] = 'v2';
        }

        return view('admin.settings.social-login', compact('values'));
    }

    public function socialLoginSave(Request $r)
    {
        $data = $r->validate([
            'google_login_enabled'   => ['nullable', 'boolean'],
            'google_client_id'       => ['nullable', 'string', 'max:300'],
            'google_client_secret'   => ['nullable', 'string', 'max:300'],
            'facebook_login_enabled' => ['nullable', 'boolean'],
            'facebook_client_id'     => ['nullable', 'string', 'max:300'],
            'facebook_client_secret' => ['nullable', 'string', 'max:300'],
            'recaptcha_enabled'      => ['nullable', 'boolean'],
            'recaptcha_site_key'     => ['nullable', 'string', 'max:300'],
            'recaptcha_secret_key'   => ['nullable', 'string', 'max:300'],
            'recaptcha_version'      => ['nullable', 'string', 'in:v2,v3'],
        ]);

        Setting::set('google_login_enabled', $r->boolean('google_login_enabled'), 'bool');
        Setting::set('facebook_login_enabled', $r->boolean('facebook_login_enabled'), 'bool');
        Setting::set('recaptcha_enabled', $r->boolean('recaptcha_enabled'), 'bool');

        Setting::set('google_client_id', $data['google_client_id'] ?? '');
        Setting::set('facebook_client_id', $data['facebook_client_id'] ?? '');
        Setting::set('recaptcha_site_key', $data['recaptcha_site_key'] ?? '');
        Setting::set('recaptcha_version', $data['recaptcha_version'] ?? 'v2');

        // Secrets: only overwrite when a fresh value is supplied; blank keeps the stored one.
        foreach (self::SOCIAL_SECRETS as $k) {
            if (! empty($r->input($k))) {
                Setting::set($k, $r->input($k));
            }
        }

        return back()->with('success', __('Social login settings saved.'));
    }

    // ───────────────────────── Site settings ─────────────────────────

    public function siteSettings()
    {
        $values = [];
        foreach (self::SITE_KEYS as $k) {
            $values[$k] = Setting::get($k, '');
        }

        return view('admin.site-settings', compact('values'));
    }

    public function siteSettingsSave(Request $r)
    {
        $data = $r->validate([
            'site_name'         => ['nullable', 'string', 'max:200'],
            'site_tagline'      => ['nullable', 'string', 'max:200'],
            'contact_email'     => ['nullable', 'string', 'max:200'],
            'contact_phone'     => ['nullable', 'string', 'max:200'],
            'contact_address'   => ['nullable', 'string', 'max:200'],
            'social_facebook'   => ['nullable', 'string', 'max:300'],
            'social_instagram'  => ['nullable', 'string', 'max:300'],
            'social_twitter'    => ['nullable', 'string', 'max:300'],
            'social_youtube'    => ['nullable', 'string', 'max:300'],
            'social_linkedin'   => ['nullable', 'string', 'max:300'],
            'footer_text'       => ['nullable', 'string', 'max:1000'],
        ]);

        foreach (self::SITE_KEYS as $k) {
            Setting::set($k, $data[$k] ?? '');
        }

        return back()->with('success', __('Site settings saved.'));
    }
}
