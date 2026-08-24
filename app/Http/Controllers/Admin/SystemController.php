<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

/**
 * System settings — Security and Storage. Both persist into the Setting table
 * (the same admin-editable store the general Settings page uses). Secrets are
 * never re-emitted to the form: they ship blank and an empty submit keeps the
 * stored value.
 */
class SystemController extends Controller
{
    private const STORAGE_SECRETS = ['s3_secret'];

    // ───────────────────────── Security ─────────────────────────

    public function security()
    {
        $values = [
            'force_https'                => (bool) Setting::get('force_https', false),
            'require_email_verification' => (bool) Setting::get('require_email_verification', true),
            'login_max_attempts'         => (int) Setting::get('login_max_attempts', 5),
            'login_decay_minutes'        => (int) Setting::get('login_decay_minutes', 15),
            'session_lifetime_minutes'   => (int) Setting::get('session_lifetime_minutes', 120),
            'password_min_length'        => (int) Setting::get('password_min_length', 8),
            'allowed_admin_ips'          => (string) Setting::get('allowed_admin_ips', ''),
        ];
        return view('admin.system.security', compact('values'));
    }

    public function securitySave(Request $request)
    {
        $data = $request->validate([
            'force_https'                => ['nullable', 'boolean'],
            'require_email_verification' => ['nullable', 'boolean'],
            'login_max_attempts'         => ['nullable', 'integer', 'min:1', 'max:100'],
            'login_decay_minutes'        => ['nullable', 'integer', 'min:1', 'max:1440'],
            'session_lifetime_minutes'   => ['nullable', 'integer', 'min:5', 'max:100000'],
            'password_min_length'        => ['nullable', 'integer', 'min:6', 'max:64'],
            'allowed_admin_ips'          => ['nullable', 'string', 'max:4000'],
        ]);

        Setting::set('force_https', (bool) ($data['force_https'] ?? false), 'bool');
        Setting::set('require_email_verification', (bool) ($data['require_email_verification'] ?? false), 'bool');
        Setting::set('login_max_attempts', (int) ($data['login_max_attempts'] ?? 5), 'int');
        Setting::set('login_decay_minutes', (int) ($data['login_decay_minutes'] ?? 15), 'int');
        Setting::set('session_lifetime_minutes', (int) ($data['session_lifetime_minutes'] ?? 120), 'int');
        Setting::set('password_min_length', (int) ($data['password_min_length'] ?? 8), 'int');
        Setting::set('allowed_admin_ips', $data['allowed_admin_ips'] ?? '');

        return back()->with('success', __('Security settings saved.'));
    }

    // ───────────────────────── Storage ─────────────────────────

    /** S3-compatible providers the storage page offers. */
    private function storageProviders(): array
    {
        return [
            's3'     => 'Amazon S3',
            'wasabi' => 'Wasabi',
            'bunny'  => 'Bunny.net',
            'spaces' => 'DigitalOcean Spaces',
            'r2'     => 'Cloudflare R2',
            'minio'  => 'MinIO / S3-compatible',
        ];
    }

    /** The stored config blob, decoded. */
    private function storageConfig(): array
    {
        $raw = Setting::get('storage_config', []);
        if (is_string($raw)) $raw = json_decode($raw, true) ?: [];
        return is_array($raw) ? $raw : [];
    }

    public function storage()
    {
        $stored    = $this->storageConfig();
        $providers = $this->storageProviders();
        $provider  = $stored['provider'] ?? 's3';

        // Build the per-provider view model, masking the secrets.
        $providersOut = [];
        foreach ($providers as $key => $label) {
            $p = $stored['providers'][$key] ?? [];
            $p['__has_secret']     = ! empty($p['secret']);
            $p['__has_access_key'] = ! empty($p['access_key']);
            unset($p['secret'], $p['access_key']);
            $providersOut[$key] = $p;
        }

        $cfg = [
            'enabled'    => (bool) ($stored['enabled'] ?? false),
            'provider'   => $provider,
            'visibility' => $stored['visibility'] ?? 'private',
            'base_path'  => $stored['base_path'] ?? '',
            'providers'  => $providersOut,
        ];

        $enabled     = $cfg['enabled'];
        $activeLabel = $providers[$provider] ?? $provider;
        $hasConfig   = ! empty($stored['providers'][$provider]['bucket'] ?? '') || ! empty($stored['providers'][$provider]['storage_zone'] ?? '');

        return view('admin.system.storage', compact('cfg', 'enabled', 'hasConfig', 'activeLabel', 'providers'));
    }

    /** Merge the submitted provider fields into the stored blob, keeping secrets. */
    private function persistStorage(Request $request): array
    {
        $provider = (string) $request->input('provider', 's3');
        if (! array_key_exists($provider, $this->storageProviders())) $provider = 's3';

        $stored = $this->storageConfig();
        $cfgIn  = (array) $request->input('cfg', []);
        $prev   = $stored['providers'][$provider] ?? [];

        $keep = fn ($field) => ! empty($cfgIn[$field]) ? $cfgIn[$field] : ($prev[$field] ?? '');
        $merged = [
            'key'                     => $cfgIn['key'] ?? ($prev['key'] ?? ''),
            'secret'                  => $keep('secret'),
            'region'                  => $cfgIn['region'] ?? ($prev['region'] ?? ''),
            'bucket'                  => $cfgIn['bucket'] ?? ($prev['bucket'] ?? ''),
            'endpoint'                => $cfgIn['endpoint'] ?? ($prev['endpoint'] ?? ''),
            'cdn_url'                 => $cfgIn['cdn_url'] ?? ($prev['cdn_url'] ?? ''),
            'use_path_style_endpoint' => ! empty($cfgIn['use_path_style_endpoint']),
            'storage_zone'            => $cfgIn['storage_zone'] ?? ($prev['storage_zone'] ?? ''),
            'access_key'              => $keep('access_key'),
        ];

        $stored['provider']   = $provider;
        $stored['visibility'] = in_array($request->input('visibility'), ['private', 'public'], true) ? $request->input('visibility') : 'private';
        $stored['base_path']  = (string) $request->input('base_path', '');
        $stored['enabled']    = (bool) $request->boolean('enabled');
        $stored['providers'][$provider] = $merged;

        Setting::set('storage_config', $stored, 'json');
        return $stored;
    }

    public function storageSave(Request $request)
    {
        $this->persistStorage($request);
        return back()->with('success', __('Storage settings saved.'));
    }

    public function storageTest(Request $request)
    {
        $stored = $this->persistStorage($request);
        $p = $stored['providers'][$stored['provider']] ?? [];
        if (empty($p['bucket']) && empty($p['storage_zone'])) {
            return back()->with('error', __('Add a bucket (or Bunny storage zone) before testing.'));
        }
        // A real write/delete probe needs the S3 driver; keep the save honest.
        return back()->with('success', __('Saved. Enable the toggle and upload a file to verify the connection end-to-end.'));
    }
}
