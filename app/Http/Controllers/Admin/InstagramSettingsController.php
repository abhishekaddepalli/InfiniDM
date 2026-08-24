<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\Instagram\InstagramGate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

/**
 * Admin → Settings → Instagram: platform-level Meta app credentials.
 *
 * Lifted out of AdminPagesController so the whole page leaves with the
 * extension. Core keeping a settings screen for a channel it no longer ships
 * would be a 500 waiting to happen — the view, the SystemSetting keys and the
 * instagram_accounts table all belong to this package.
 *
 * Workspaces connect their own IG accounts via OAuth once App ID + Secret are
 * filled in here. Mirrors the WABA embedded-signup settings.
 */
class InstagramSettingsController extends Controller
{
    public function index(): View
    {
        $settings = [
            'instagram_enabled'              => (bool)   InstagramGate::setting('instagram_enabled', false),
            'instagram_app_id'               => (string) InstagramGate::setting('instagram_app_id', ''),
            'instagram_app_secret_set'       => InstagramGate::settingExists('instagram_app_secret'),
            'instagram_config_id'            => (string) InstagramGate::setting('instagram_config_id', ''),
            'instagram_login_type'           => (string) InstagramGate::setting('instagram_login_type', 'facebook'),
            'instagram_webhook_verify_token' => (string) InstagramGate::setting('instagram_webhook_verify_token', ''),
            'instagram_graph_version'        => (string) InstagramGate::setting('instagram_graph_version', 'v21.0'),
            'instagram_giphy_key_set'        => InstagramGate::settingExists('instagram_giphy_key')
                                                    || (string) config('services.giphy.key', env('GIPHY_API_KEY', '')) !== '',

            // Guarded: this page can be reached in the window between the files
            // landing and the migrations finishing, and an unguarded count on a
            // table that does not exist yet 500s the settings screen.
            'instagram_connected_count'      => Schema::hasTable('instagram_accounts')
                ? (int) DB::table('instagram_accounts')->where('status', 'connected')->count()
                : 0,

            // Node bridge + plan enforcement — moved here from General settings.
            'node_url'        => (string) Setting::get('node_url', ''),
            'node_token_set'  => (bool) Setting::get('node_token', ''),
            'enforce_plans'   => (bool) Setting::get('enforce_plans', false),
        ];

        return view('admin.settings.instagram', compact('settings'));
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'instagram_enabled'              => 'sometimes|boolean',
            'instagram_app_id'               => 'nullable|string|max:64',
            'instagram_app_secret'           => 'nullable|string|max:128',
            'instagram_config_id'            => 'nullable|string|max:64',
            'instagram_login_type'           => 'nullable|in:facebook,instagram',
            'instagram_webhook_verify_token' => 'nullable|string|max:96',
            'instagram_graph_version'        => 'nullable|regex:/^v\d{1,2}\.\d{1,2}$/',
            'instagram_giphy_key'            => 'nullable|string|max:128',
            'node_url'                       => 'nullable|string|max:200',
            'node_token'                     => 'nullable|string|max:200',
            'enforce_plans'                  => 'nullable|boolean',
        ]);

        InstagramGate::putSetting('instagram_enabled',              $request->boolean('instagram_enabled'), 'bool', 'Enable the Instagram automation channel platform-wide.');
        InstagramGate::putSetting('instagram_app_id',              (string) ($data['instagram_app_id'] ?? ''), 'string', 'Meta App ID used for Instagram OAuth + Graph API.');
        InstagramGate::putSetting('instagram_config_id',           (string) ($data['instagram_config_id'] ?? ''), 'string', 'Instagram Embedded-Signup / Login configuration ID.');
        InstagramGate::putSetting('instagram_login_type',          (string) ($data['instagram_login_type'] ?? 'facebook'), 'string', 'OAuth path: facebook (FB-Login-for-Business) or instagram (IG-Login).');
        InstagramGate::putSetting('instagram_webhook_verify_token',(string) ($data['instagram_webhook_verify_token'] ?? ''), 'string', 'Verify token Meta echoes during webhook subscription.');
        InstagramGate::putSetting('instagram_graph_version',       (string) ($data['instagram_graph_version'] ?? 'v21.0'), 'string', 'Graph API version for Instagram calls.');

        // Secrets are only overwritten when the form actually carried a value —
        // the inputs render blank once set, so saving the page must not wipe them.
        if (!empty($data['instagram_app_secret'])) {
            InstagramGate::putSetting('instagram_app_secret', $data['instagram_app_secret'], 'string', 'Meta App Secret for Instagram (encrypted at rest).');
        }
        if (!empty($data['instagram_giphy_key'])) {
            InstagramGate::putSetting('instagram_giphy_key', $data['instagram_giphy_key'], 'string', 'GIPHY API key for the Instagram inbox GIF picker (encrypted at rest).');
        }

        // Node bridge + plan enforcement (same keys the runtime already reads).
        Setting::set('node_url', (string) ($data['node_url'] ?? ''));
        Setting::set('enforce_plans', (bool) ($request->input('enforce_plans')), 'bool');
        if (!empty($request->input('node_token'))) {
            Setting::set('node_token', (string) $request->input('node_token'));
        }

        // Mirror the Node URL + token into BOTH .env files, exactly like WaDesk:
        //   • Laravel's own .env  → SERVER_URL + NODE_WEBHOOK_TOKEN (keys config/instagram.php reads)
        //   • node/.env           → NODE_WEBHOOK_TOKEN (the secret the Node process validates)
        // so whatever the admin typed here is what a fresh boot of either side uses.
        $nodeUrl   = (string) ($data['node_url'] ?? '');
        $nodeToken = (string) ($request->input('node_token') ?? '');

        $laravelEnv = [];
        if ($nodeUrl !== '')   { $laravelEnv['SERVER_URL'] = $nodeUrl; }
        if ($nodeToken !== '') { $laravelEnv['NODE_WEBHOOK_TOKEN'] = $nodeToken; }
        if ($laravelEnv) { $this->writeEnv(base_path('.env'), $laravelEnv); }

        if ($nodeToken !== '' && is_file(base_path('node/.env'))) {
            $this->writeEnv(base_path('node/.env'), ['NODE_WEBHOOK_TOKEN' => $nodeToken]);
        }

        return redirect()->route('admin.settings.instagram')->with('status', 'Instagram settings saved.');
    }

    /**
     * Upsert KEY="value" pairs in a .env file, preserving everything else.
     * Existing keys are replaced in place; missing ones are appended. Values are
     * quoted so spaces/specials survive. Never throws — a save must not fail
     * because a file happened to be read-only.
     */
    private function writeEnv(string $path, array $pairs): void
    {
        try {
            if (! is_file($path) || ! is_writable($path)) {
                return;
            }
            $env = file_get_contents($path);
            foreach ($pairs as $key => $val) {
                $line = $key . '="' . str_replace('"', '\\"', (string) $val) . '"';
                $pat  = '/^' . preg_quote($key, '/') . '=.*$/m';
                $env  = preg_match($pat, $env)
                    ? preg_replace($pat, $line, $env)
                    : rtrim($env, "\n") . "\n" . $line . "\n";
            }
            file_put_contents($path, $env);
        } catch (\Throwable $e) {
            // Ignore — DB settings are already saved and are the primary source.
        }
    }
}
