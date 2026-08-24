<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

/**
 * Developer file-sync receiver — a private "push code to the test server"
 * endpoint. My local machine POSTs a batch of changed files here; this writes
 * them to disk, runs pending migrations and clears the caches, so a code change
 * lands live without a manual cPanel upload.
 *
 * THIS IS A REMOTE FILE-WRITE ENDPOINT. It is deliberately dangerous, so it is:
 *   - OFF unless WD_SYNC_KEY is a non-empty string in .env (no key ⇒ 404, invisible).
 *   - authenticated by that key via hash_equals (constant-time) in the
 *     X-Sync-Key header — never a query string, so it stays out of access logs.
 *   - jailed to base_path(): every target path is normalised and must resolve
 *     INSIDE the app root, and a blocklist protects the credential/secret files.
 *   - logged on every call (ip + file count + result).
 *
 * If the key leaks, an attacker can overwrite the site — so treat it as a
 * secret, rotate it by changing the .env value, and remove the line to kill
 * the endpoint entirely.
 */
class WdSyncController extends Controller
{
    /** Paths that may NEVER be written, however the request spells them. */
    private const BLOCKED = [
        '.env',
        '.env.',            // .env.production, .env.backup, …
        '.git/',
        'storage/framework/down',
    ];

    public function push(Request $request): JsonResponse
    {
        $key = (string) config('wdsync.key', env('WD_SYNC_KEY', ''));

        // No key configured ⇒ the feature does not exist. 404, not 403, so a
        // scanner can't even tell the route is there.
        if ($key === '') {
            abort(404);
        }

        $given = (string) $request->header('X-Sync-Key', '');
        if (! hash_equals($key, $given)) {
            Log::warning('[WD-SYNC] rejected — bad key', ['ip' => $request->ip()]);
            abort(404);
        }

        $files = $request->input('files', []);
        if (! is_array($files) || $files === []) {
            return response()->json(['ok' => false, 'error' => 'no files in payload'], 422);
        }

        $written = [];
        $skipped = [];
        $base    = rtrim(str_replace('\\', '/', base_path()), '/');

        foreach ($files as $f) {
            $rel = isset($f['path']) ? (string) $f['path'] : '';
            $b64 = isset($f['contents_b64']) ? (string) $f['contents_b64'] : '';

            $reason = $this->rejectReason($rel);
            if ($reason !== null) {
                $skipped[] = ['path' => $rel, 'reason' => $reason];
                continue;
            }

            // Normalise and confirm the final absolute path stays inside the app
            // root. We can't realpath() a not-yet-existing file, so we normalise
            // the string ourselves and prefix-check against base_path().
            $abs = $this->safeAbsolute($base, $rel);
            if ($abs === null) {
                $skipped[] = ['path' => $rel, 'reason' => 'escapes app root'];
                continue;
            }

            $data = base64_decode($b64, true);
            if ($data === false) {
                $skipped[] = ['path' => $rel, 'reason' => 'bad base64'];
                continue;
            }

            $dir = dirname($abs);
            if (! is_dir($dir) && ! @mkdir($dir, 0755, true) && ! is_dir($dir)) {
                $skipped[] = ['path' => $rel, 'reason' => 'mkdir failed'];
                continue;
            }

            // Write to a temp file in the SAME directory, then rename — an
            // atomic swap, so a half-written PHP file is never executed.
            $tmp = $abs . '.wdsync.' . substr(md5($rel . strlen($data)), 0, 8) . '.tmp';
            if (@file_put_contents($tmp, $data) === false || ! @rename($tmp, $abs)) {
                @unlink($tmp);
                $skipped[] = ['path' => $rel, 'reason' => 'write failed'];
                continue;
            }

            $written[] = $rel;
        }

        // Run migrations + clear caches only when files actually landed and the
        // caller asked for it (defaults on). Captured, never fatal.
        $migrateOut = null;
        $cacheOut   = null;

        if ($written !== [] && $request->boolean('migrate', true)) {
            try {
                Artisan::call('migrate', ['--force' => true]);
                $migrateOut = trim(Artisan::output());
            } catch (\Throwable $e) {
                $migrateOut = 'ERROR: ' . $e->getMessage();
            }
        }

        if ($written !== [] && $request->boolean('clear', true)) {
            try {
                // Clears compiled views, config, routes, events + app cache in
                // one shot — so a changed blade/route/config is picked up.
                Artisan::call('optimize:clear');
                $cacheOut = trim(Artisan::output());
            } catch (\Throwable $e) {
                $cacheOut = 'ERROR: ' . $e->getMessage();
            }
        }

        // Ensure the public storage symlink exists — a fresh subfolder deploy has
        // no `public/storage`, so any user-uploaded media (IG DM attachments,
        // avatars) 404s and Instagram can't fetch it. --force replaces a stale
        // link. Best-effort: some shared hosts forbid symlinks, hence the guard.
        $linkOut = null;
        if ($request->boolean('link', true)) {
            try {
                Artisan::call('storage:link', ['--force' => true]);
                $linkOut = trim(Artisan::output());
            } catch (\Throwable $e) {
                $linkOut = 'ERROR: ' . $e->getMessage();
            }
        }

        Log::info('[WD-SYNC] push applied', [
            'ip'      => $request->ip(),
            'written' => count($written),
            'skipped' => count($skipped),
        ]);

        return response()->json([
            'ok'      => true,
            'written' => $written,
            'skipped' => $skipped,
            'migrate' => $migrateOut,
            'cache'   => $cacheOut,
            'link'    => $linkOut,
            'count'   => count($written),
        ]);
    }

    /** Null = allowed; otherwise a human reason the path is refused. */
    private function rejectReason(string $rel): ?string
    {
        if ($rel === '')                       return 'empty path';
        if (str_contains($rel, "\0"))          return 'null byte';
        // Reject absolute paths (unix / and windows C:\) and any parent-dir hop.
        if (str_starts_with($rel, '/'))        return 'absolute path';
        if (preg_match('#^[A-Za-z]:#', $rel))  return 'absolute path';
        if (str_contains($rel, '..'))          return 'parent traversal';

        $norm = ltrim(str_replace('\\', '/', $rel), '/');
        foreach (self::BLOCKED as $b) {
            if ($norm === rtrim($b, '/') || str_starts_with($norm, $b)) {
                return 'protected path';
            }
        }
        return null;
    }

    /** Resolve $rel under $base, or null if it would escape the app root. */
    private function safeAbsolute(string $base, string $rel): ?string
    {
        $norm = ltrim(str_replace('\\', '/', $rel), '/');
        $abs  = $base . '/' . $norm;

        // Collapse any './' and confirm no surviving '../' — belt and braces on
        // top of rejectReason(), since a write here is unforgiving.
        $parts = [];
        foreach (explode('/', $abs) as $seg) {
            if ($seg === '' || $seg === '.') continue;
            if ($seg === '..') return null;
            $parts[] = $seg;
        }
        $rebuilt = '/' . implode('/', $parts);

        // On Windows base_path() has a drive letter; normalise the leading slash
        // away for the prefix comparison.
        $baseCmp = ltrim($base, '/');
        $absCmp  = ltrim($rebuilt, '/');
        if (! str_starts_with($absCmp . '/', $baseCmp . '/')) {
            return null;
        }
        return $rebuilt;
    }
}
