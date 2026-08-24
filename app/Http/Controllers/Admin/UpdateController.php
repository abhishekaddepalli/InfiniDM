<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\UpdaterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Admin → Updater. Verify CodeCanyon purchase → backup → upload package →
 * apply code → migrate → finalize, with one-click rollback. Access is gated by
 * the admin route group (every /admin/* route requires a platform admin).
 */
class UpdateController extends Controller
{
    public function __construct(private UpdaterService $updater)
    {
    }

    public function show()
    {
        return view('admin.update', [
            'current'        => $this->updater->currentVersion(),
            'currentVersion' => $this->updater->currentVersion(),
            'currentBuild'   => $this->updater->currentBuild(),
            'phpVersion'     => PHP_VERSION,
            'laravel'        => app()->version(),
            'backups'        => $this->updater->listBackups(),
            'verified'       => (bool) Setting::get('envato_purchase_code', ''),
        ]);
    }

    /** Step 0: verify CodeCanyon purchase code against Envato. */
    public function verify(Request $request): JsonResponse
    {
        $request->validate(['purchase_code' => ['required', 'string', 'max:120']]);

        $result = $this->updater->verifyPurchase((string) $request->input('purchase_code'));

        return response()->json([
            'success' => $result['ok'],
            'message' => $result['message'],
            'buyer'   => $result['buyer'] ?? null,
        ], $result['ok'] ? 200 : 422);
    }

    /** Step 1: backup files + database. */
    public function backup(): JsonResponse
    {
        try {
            $result = $this->updater->createBackup();

            return response()->json(['success' => true, 'message' => 'Backup created successfully.', 'backup' => $result]);
        } catch (\Throwable $e) {
            Log::error('[UPDATER-BACKUP] failed', [
                'error'       => $e->getMessage(),
                'file'        => $e->getFile() . ':' . $e->getLine(),
                'peak_mem_mb' => round(memory_get_peak_usage(true) / 1048576, 1),
            ]);

            return response()->json(['success' => false, 'message' => 'Backup failed: ' . $e->getMessage()], 500);
        }
    }

    /** Step 2: upload + validate the update ZIP. */
    public function upload(Request $request): JsonResponse
    {
        $file          = $request->file('file');
        $contentLength = (int) $request->server('CONTENT_LENGTH', 0);
        Log::info('[UPDATER-UPLOAD] received', [
            'ip'                => $request->ip(),
            'user_id'           => optional($request->user())->id,
            'content_length_mb' => round($contentLength / 1048576, 2),
            'has_file'          => $file !== null,
            'client_name'       => $file?->getClientOriginalName(),
            'client_size_mb'    => $file ? round($file->getSize() / 1048576, 2) : null,
            'is_valid'          => $file?->isValid(),
            'php_post_max_size' => ini_get('post_max_size'),
            'php_upload_max'    => ini_get('upload_max_filesize'),
        ]);

        // When the ZIP is bigger than PHP's post_max_size, PHP silently drops the
        // whole body: $_POST + $_FILES arrive EMPTY even though the browser sent
        // bytes. Detect it and return the REAL reason + the exact limits to raise.
        if ($contentLength > 0 && empty($_FILES) && $file === null) {
            $postMax = ini_get('post_max_size') ?: '?';
            $upMax   = ini_get('upload_max_filesize') ?: '?';
            return response()->json([
                'success' => false,
                'message' => "Upload exceeded the server limit (sent ~" . round($contentLength / 1048576, 1) . " MB; PHP post_max_size={$postMax}, upload_max_filesize={$upMax}). Raise post_max_size + upload_max_filesize to 64M in php.ini (and nginx client_max_body_size 64M), restart php-fpm + reload nginx, then retry.",
            ], 413);
        }

        if ($file !== null && ! $file->isValid()) {
            return response()->json([
                'success' => false,
                'message' => 'Upload did not complete: ' . $file->getErrorMessage() . ' Check PHP upload_max_filesize / post_max_size (raise to 64M) and retry.',
            ], 422);
        }

        $request->validate(['file' => ['required', 'file', 'mimes:zip', 'max:512000']]);

        try {
            $path = $this->updater->saveUploadedZip($request->file('file'));
            $zipVersion = $this->updater->getZipVersion($path);

            if (! $zipVersion) {
                $this->updater->cleanup();
                return response()->json(['success' => false, 'message' => 'Invalid update package — no version info found (config/version.php missing in ZIP).'], 422);
            }

            $current = $this->updater->currentVersion();
            if (version_compare($zipVersion, $current, '<=')) {
                $this->updater->cleanup();
                return response()->json(['success' => false, 'message' => "ZIP contains v{$zipVersion} but you already have v{$current}. Upload a newer version."], 422);
            }

            return response()->json(['success' => true, 'message' => "Update package v{$zipVersion} ready to install.", 'zip_version' => $zipVersion]);
        } catch (\Throwable $e) {
            Log::error('[UPDATER-UPLOAD] exception while saving/parsing ZIP', [
                'error' => $e->getMessage(),
                'file'  => $e->getFile() . ':' . $e->getLine(),
            ]);
            return response()->json(['success' => false, 'message' => 'Upload failed: ' . $e->getMessage()], 500);
        }
    }

    /** Step 3: extract + apply code files. */
    public function apply(Request $request): JsonResponse
    {
        $zipPath = storage_path('app/temp/updater/update.zip');
        if (! file_exists($zipPath)) {
            return response()->json(['success' => false, 'message' => 'No update ZIP found. Please upload first.'], 400);
        }

        try {
            $updated = $this->updater->applyUpdate($zipPath);

            return response()->json(['success' => true, 'message' => 'Files updated successfully.', 'updated' => $updated]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Apply failed: ' . $e->getMessage()], 500);
        }
    }

    /** Step 4: run new migrations. */
    public function migrate(): JsonResponse
    {
        try {
            $output = $this->updater->runMigrations();

            return response()->json(['success' => true, 'message' => 'Migrations completed.', 'output' => $output]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Migration failed: ' . $e->getMessage()], 500);
        }
    }

    /** Step 5: clear caches + health check + cleanup. */
    public function finalize(): JsonResponse
    {
        try {
            $this->updater->clearCaches();
            $health = $this->updater->healthCheck();
            $this->updater->cleanup();

            $allGood = ! in_array(false, $health, true);

            return response()->json([
                'success'     => $allGood,
                'message'     => $allGood ? 'Update completed successfully.' : 'Update done, but the health check raised warnings.',
                'health'      => $health,
                'new_version' => config('version.version'),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Finalize failed: ' . $e->getMessage()], 500);
        }
    }

    /** Restore a previous backup. */
    public function rollback(Request $request): JsonResponse
    {
        $request->validate(['backup_dir' => ['required', 'string']]);

        $backupBase = realpath(storage_path('app/backups'));
        $targetDir  = realpath($request->input('backup_dir'));
        if (! $targetDir || ! $backupBase || ! str_starts_with($targetDir, $backupBase)) {
            return response()->json(['success' => false, 'message' => 'Invalid backup path.'], 403);
        }

        try {
            $this->updater->rollback($targetDir);

            return response()->json(['success' => true, 'message' => 'Rollback completed. Version restored.']);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => 'Rollback failed: ' . $e->getMessage()], 500);
        }
    }
}
