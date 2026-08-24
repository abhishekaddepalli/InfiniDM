<?php

namespace App\Services\Instagram;

use Illuminate\Support\Facades\Storage;

/**
 * The IgDesk ⇄ WaDesk connection settings.
 *
 * A self-hosted IgDesk talks to a WaDesk install over HTTP, proving a shared
 * secret on every call (X-Instaflow-Secret). Three values drive it — the WaDesk
 * base URL (where inbound IG messages are pushed), the shared secret, and which
 * WaDesk workspace the IG threads land in — all set on the "Connect WaDesk" page.
 *
 * Persistence is a small JSON file under storage/app, NOT SystemSetting:
 * standalone IgDesk has no SystemSetting table (InstagramGate::putSetting is a
 * deliberate no-op there), and the secret is GENERATED at runtime — you would
 * not pre-bake it in .env. The file is writable in BOTH the addon and standalone
 * builds. Env (config/instagram.php settings) still acts as the fallback default
 * so a pre-seeded .env keeps working when the file is absent.
 */
class WadeskLink
{
    private const FILE = 'instaflow-connection.json';

    /**
     * @return array{wadesk_url:string,secret:string,workspace_id:int,push_enabled:bool}
     */
    public static function all(): array
    {
        $stored = [];
        try {
            if (Storage::disk('local')->exists(self::FILE)) {
                $stored = json_decode((string) Storage::disk('local')->get(self::FILE), true) ?: [];
            }
        } catch (\Throwable $e) {
            $stored = [];
        }

        return [
            'wadesk_url'   => trim((string) ($stored['wadesk_url']   ?? InstagramGate::setting('instagram_wadesk_url', ''))),
            'secret'       => (string)      ($stored['secret']       ?? InstagramGate::setting('instagram_wadesk_secret', '')),
            'workspace_id' => (int)         ($stored['workspace_id'] ?? InstagramGate::setting('instagram_wadesk_workspace_id', 1)),
            // A stored false must win over the env default (true) — hence the
            // array_key_exists check rather than a plain ?? (false ?? x === false,
            // but a MISSING key must fall through to the env default).
            'push_enabled' => array_key_exists('push_enabled', $stored)
                ? (bool) $stored['push_enabled']
                : (bool) InstagramGate::setting('instagram_wadesk_push_enabled', true),
        ];
    }

    public static function wadeskUrl(): string { return rtrim(self::all()['wadesk_url'], '/'); }
    public static function secret(): string    { return self::all()['secret']; }
    public static function workspaceId(): int  { return max(1, self::all()['workspace_id']); }
    public static function pushEnabled(): bool { return self::all()['push_enabled']; }

    /** URL + secret both present — the minimum to attempt any call in either direction. */
    public static function isConfigured(): bool
    {
        $a = self::all();
        return $a['wadesk_url'] !== '' && $a['secret'] !== '';
    }

    /**
     * Persist the connection. Only the provided keys change; the rest are kept.
     * Returns false if storage is not writable.
     */
    public static function save(array $data): bool
    {
        $current = self::all();
        $merged  = [
            'wadesk_url'   => array_key_exists('wadesk_url', $data)   ? trim((string) $data['wadesk_url']) : $current['wadesk_url'],
            'secret'       => array_key_exists('secret', $data)       ? (string) $data['secret']           : $current['secret'],
            'workspace_id' => array_key_exists('workspace_id', $data) ? (int) $data['workspace_id']        : $current['workspace_id'],
            'push_enabled' => array_key_exists('push_enabled', $data) ? (bool) $data['push_enabled']       : $current['push_enabled'],
        ];
        try {
            Storage::disk('local')->put(self::FILE, json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** A fresh random shared secret (NOT persisted until save()). */
    public static function generateSecret(): string
    {
        return 'ifs_' . bin2hex(random_bytes(24));
    }
}
