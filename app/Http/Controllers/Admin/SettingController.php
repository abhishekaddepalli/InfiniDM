<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\Setting;
use Illuminate\Http\Request;

/**
 * General settings — a faithful port of WaDesk's admin/settings/general page:
 * Application identity, Brand assets, Platform toggles, and Signups & free
 * trial. The Meta app + Node bridge + plan-enforcement cards are kept below
 * because this product cannot connect an account or run flows without them.
 *
 * Everything persists into the Setting table. Meta App Secret / Node token are
 * secrets: never re-emitted; ship blank, and an empty submit keeps the value.
 */
class SettingController extends Controller
{
    private const SECRETS = ['meta_app_secret', 'node_token'];

    /** Active currencies from the managed table (WaDesk-style), with a fallback. */
    private function currencies()
    {
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('currencies')) {
                $rows = \App\Models\Currency::where('is_active', true)->orderBy('code')->get(['code', 'name']);
                if ($rows->isNotEmpty()) return $rows;
            }
        } catch (\Throwable $e) {
        }
        $map = ['USD' => 'US Dollar', 'EUR' => 'Euro', 'GBP' => 'British Pound', 'INR' => 'Indian Rupee'];
        return collect($map)->map(fn ($name, $code) => (object) ['code' => $code, 'name' => $name])->values();
    }

    /** Active languages from the managed table (WaDesk-style), with a fallback. */
    private function languages()
    {
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('languages')) {
                $rows = \App\Models\Language::where('is_active', true)->orderBy('name')->get(['code', 'name']);
                if ($rows->isNotEmpty()) return $rows;
            }
        } catch (\Throwable $e) {
        }
        $map = ['en' => 'English', 'es' => 'Spanish', 'fr' => 'French', 'ar' => 'Arabic'];
        return collect($map)->map(fn ($name, $code) => (object) ['code' => $code, 'name' => $name])->values();
    }

    /** Font catalog (key => [label, stack]) — from InstaflowAppearance when present. */
    private function fonts(): array
    {
        if (class_exists(\App\Support\InstaflowAppearance::class) && defined(\App\Support\InstaflowAppearance::class . '::FONTS')) {
            $f = \App\Support\InstaflowAppearance::FONTS;
            if (is_array($f) && $f) {
                return collect($f)->map(function ($v, $k) {
                    if (is_array($v)) return ['label' => $v['label'] ?? $k, 'stack' => $v['stack'] ?? $v['label'] ?? $k];
                    return ['label' => (string) $v, 'stack' => (string) $v];
                })->all();
            }
        }
        return [
            'inter'   => ['label' => 'Inter', 'stack' => 'Inter, sans-serif'],
            'roboto'  => ['label' => 'Roboto', 'stack' => 'Roboto, sans-serif'],
            'poppins' => ['label' => 'Poppins', 'stack' => 'Poppins, sans-serif'],
            'lato'    => ['label' => 'Lato', 'stack' => 'Lato, sans-serif'],
            'nunito'  => ['label' => 'Nunito', 'stack' => 'Nunito, sans-serif'],
        ];
    }

    public function show()
    {
        $keys = [
            'site_name', 'site_tagline', 'app_url', 'support_email', 'contact_number', 'from_email',
            'default_timezone', 'default_language', 'default_currency', 'default_country_code', 'default_country_iso',
            'font_family', 'address', 'map_iframe_url', 'platform_footer',
            'preloader', 'maintenance_mode', 'allow_registration', 'auto_verify_email', 'auth_show_logo',
            'registration_default_plan_id', 'registration_trial_days',
            'site_logo', 'brand_favicon', 'logo_dark',
        ];
        $settings = [];
        foreach ($keys as $k) {
            $settings[$k] = Setting::get($k, '');
        }

        // Sensible defaults.
        $settings['default_timezone']        = $settings['default_timezone'] ?: config('app.timezone', 'UTC');
        $settings['default_language']        = $settings['default_language'] ?: 'en';
        $settings['default_currency']        = $settings['default_currency'] ?: 'USD';
        $settings['default_country_code']    = $settings['default_country_code'] ?: '+1';
        $settings['default_country_iso']     = $settings['default_country_iso'] ?: 'us';
        $settings['registration_trial_days'] = ($settings['registration_trial_days'] === '' || $settings['registration_trial_days'] === null) ? 14 : $settings['registration_trial_days'];

        $currencies  = $this->currencies();
        $languages   = $this->languages();
        $timezones   = timezone_identifiers_list();
        $fonts       = $this->fonts();
        $brandThemes = [
            ['id' => 'paper', 'label' => __('Light'), 'note' => __('Default — shown everywhere unless a dark logo is set.')],
            ['id' => 'dark',  'label' => __('Dark'),  'note' => __('Used when the viewer is in dark mode.')],
        ];
        $signupPlans = Package::where('is_active', true)->orderBy('sort')->get();

        return view('admin.settings', compact('settings', 'currencies', 'languages', 'timezones', 'fonts', 'brandThemes', 'signupPlans'));
    }

    public function save(Request $request)
    {
        $data = $request->validate([
            'site_name'                    => ['nullable', 'string', 'max:120'],
            'site_tagline'                 => ['nullable', 'string', 'max:200'],
            'app_url'                      => ['nullable', 'string', 'max:200'],
            'support_email'                => ['nullable', 'email', 'max:160'],
            'contact_number'               => ['nullable', 'string', 'max:40'],
            'from_email'                   => ['nullable', 'email', 'max:160'],
            'default_timezone'             => ['nullable', 'string', 'max:64'],
            'default_language'             => ['nullable', 'string', 'max:12'],
            'default_currency'             => ['nullable', 'string', 'max:8'],
            'default_country_code'         => ['nullable', 'string', 'max:8'],
            'default_country_iso'          => ['nullable', 'string', 'max:4'],
            'font_family'                  => ['nullable', 'string', 'max:64'],
            'address'                      => ['nullable', 'string', 'max:1000'],
            'map_iframe_url'               => ['nullable', 'string', 'max:1000'],
            'platform_footer'              => ['nullable', 'string', 'max:80'],
            'preloader'                    => ['nullable', 'boolean'],
            'maintenance_mode'             => ['nullable', 'boolean'],
            'allow_registration'           => ['nullable', 'boolean'],
            'auto_verify_email'            => ['nullable', 'boolean'],
            'auth_show_logo'               => ['nullable', 'boolean'],
            'registration_default_plan_id' => ['nullable', 'integer'],
            'registration_trial_days'      => ['nullable', 'integer', 'min:0', 'max:365'],
            'logo'                         => ['nullable', 'image', 'max:2048'],
            'logo_dark'                    => ['nullable', 'image', 'max:2048'],
            'favicon'                      => ['nullable', 'image', 'max:1024'],
        ]);

        // Strings.
        foreach (['site_name', 'site_tagline', 'app_url', 'support_email', 'contact_number', 'from_email',
                  'default_timezone', 'default_language', 'default_currency', 'default_country_code', 'default_country_iso',
                  'font_family', 'address', 'map_iframe_url', 'platform_footer'] as $k) {
            Setting::set($k, $data[$k] ?? '');
        }

        // Booleans.
        foreach (['preloader', 'maintenance_mode', 'allow_registration', 'auto_verify_email', 'auth_show_logo'] as $k) {
            Setting::set($k, (bool) ($data[$k] ?? false), 'bool');
        }

        // Signups / trial.
        Setting::set('registration_default_plan_id', (int) ($data['registration_default_plan_id'] ?? 0), 'int');
        Setting::set('registration_trial_days', (int) ($data['registration_trial_days'] ?? 14), 'int');

        // Uploads. Stored DIRECTLY under public/uploads/branding — not on the
        // storage-disk symlink, which 403s on sub-folder installs (…/instaflow/
        // public). brand_asset() serves these back with a plain asset() URL.
        if ($request->hasFile('logo')) {
            Setting::set('site_logo', $this->storeBrandAsset($request->file('logo')));
        }
        if ($request->hasFile('logo_dark')) {
            Setting::set('logo_dark', $this->storeBrandAsset($request->file('logo_dark')));
        }
        if ($request->hasFile('favicon')) {
            Setting::set('brand_favicon', $this->storeBrandAsset($request->file('favicon')));
        }

        return back()->with('success', __('Settings saved.'));
    }

    /**
     * Move an uploaded brand file into public/uploads/branding and return its
     * web-relative path (uploads/branding/…). Kept off the storage symlink so
     * it serves without a 403 on sub-folder hosting.
     */
    private function storeBrandAsset(\Illuminate\Http\UploadedFile $file): string
    {
        $dir = public_path('uploads/branding');
        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $ext  = strtolower($file->getClientOriginalExtension() ?: 'png');
        $name = 'brand-' . bin2hex(random_bytes(8)) . '.' . $ext;
        $file->move($dir, $name);
        return 'uploads/branding/' . $name;
    }
}
