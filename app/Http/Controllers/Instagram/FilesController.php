<?php

namespace App\Http\Controllers\Instagram;

use App\Http\Controllers\Controller;
use App\Models\UserFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Files — the personal media library for IgDesk.
 *
 * Lists everything the signed-in operator has uploaded here or had recorded by
 * the composer / AI image tool, with a workspace-card overview (counts, storage
 * used, average size, a storage-type breakdown) and a searchable/paginated grid.
 *
 * Tenancy: IgDesk is workspace-less, so EVERY query and mutation is scoped to
 * auth()->id(). A row's path is never trusted on its own — ownership is checked
 * against user_id before any delete/rename/download.
 */
class FilesController extends Controller
{
    /** The active media disk — the same one the composer writes to. */
    private function disk(): string
    {
        return function_exists('media_disk') ? media_disk() : 'public';
    }

    /** List + overview stats + search + optional folder filter, paginated. */
    public function index(Request $request)
    {
        $uid = (int) auth()->id();
        $q   = trim((string) $request->query('q', ''));
        $folder = trim((string) $request->query('folder', ''));

        $base = UserFile::forUser($uid)
            ->when($q !== '', function ($query) use ($q) {
                $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';
                $query->where(function ($w) use ($like) {
                    $w->where('original_name', 'like', $like)
                      ->orWhere('mime', 'like', $like)
                      ->orWhere('folder', 'like', $like);
                });
            })
            ->when($folder !== '', fn ($query) => $query->where('folder', $folder));

        $files = (clone $base)->orderByDesc('created_at')->paginate(24)->withQueryString();

        // --- Overview stats (whole library, unaffected by the search filter) ---
        $all = UserFile::forUser($uid)->get(['mime', 'size', 'folder']);
        $totalFiles = $all->count();
        $totalBytes = (int) $all->sum('size');
        $folders    = $all->pluck('folder')->filter(fn ($f) => $f !== null && $f !== '')->unique()->count();
        $avgBytes   = $totalFiles > 0 ? (int) round($totalBytes / $totalFiles) : 0;

        $images = $all->filter(fn ($f) => str_starts_with((string) $f->mime, 'image'))->count();
        $videos = $all->filter(fn ($f) => str_starts_with((string) $f->mime, 'video'))->count();
        $other  = $totalFiles - $images - $videos;

        // File ratio = share of the library that is images (a rough "how visual").
        $fileRatio = $totalFiles > 0 ? (int) round($images / $totalFiles * 100) : 0;

        // Storage types breakdown by bytes, biggest first.
        $byType = $all->groupBy(fn ($f) => $this->typeGroup((string) $f->mime))
            ->map(fn ($g) => ['count' => $g->count(), 'bytes' => (int) $g->sum('size')])
            ->sortByDesc('bytes')
            ->all();

        // Folder list for the filter chips.
        $folderList = UserFile::forUser($uid)
            ->whereNotNull('folder')->where('folder', '!=', '')
            ->distinct()->orderBy('folder')->pluck('folder')->all();

        return view('instagram.files.index', [
            'files'      => $files,
            'q'          => $q,
            'folder'     => $folder,
            'folderList' => $folderList,
            'stats'      => [
                'files'      => $totalFiles,
                'folders'    => $folders,
                'ratio'      => $fileRatio,
                'usedBytes'  => $totalBytes,
                'usedHuman'  => $this->human($totalBytes),
                'avgHuman'   => $this->human($avgBytes),
                'images'     => $images,
                'videos'     => $videos,
                'other'      => $other,
            ],
            'byType'     => $byType,
        ]);
    }

    /** Upload one or more images/videos into the library. */
    public function upload(Request $request)
    {
        $request->validate([
            'files'    => 'required|array|max:20',
            'files.*'  => 'file|mimes:jpg,jpeg,png,webp,gif,mp4,mov,m4v,webm|max:102400',
            'folder'   => 'nullable|string|max:120',
        ]);

        $uid    = (int) auth()->id();
        $disk   = $this->disk();
        $folder = trim((string) $request->input('folder', '')) ?: null;
        $saved  = 0;

        foreach ((array) $request->file('files', []) as $f) {
            if (!$f || !$f->isValid()) continue;
            try {
                $path = $f->store('instagram-media', $disk);
            } catch (\Throwable $e) {
                continue; // skip the bad one, keep going
            }
            UserFile::record([
                'user_id'       => $uid,
                'disk'          => $disk,
                'path'          => $path,
                'original_name' => $f->getClientOriginalName(),
                'mime'          => $f->getMimeType(),
                'size'          => $f->getSize(),
                'folder'        => $folder,
            ]);
            $saved++;
        }

        $msg = $saved > 0 ? "{$saved} file(s) uploaded." : 'No files were uploaded.';

        if ($request->expectsJson()) {
            return response()->json(['ok' => $saved > 0, 'saved' => $saved, 'message' => $msg]);
        }
        return back()->with($saved > 0 ? 'status' : 'error', $msg);
    }

    /** Delete one file (bytes + row). Owner-only. */
    public function destroy(Request $request, int $id)
    {
        $file = UserFile::forUser((int) auth()->id())->where('id', $id)->first();
        if (!$file) {
            return $request->expectsJson()
                ? response()->json(['ok' => false, 'message' => 'Not found.'], 404)
                : back()->with('error', 'File not found.');
        }

        $this->deleteBytes($file);
        $file->delete();

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'message' => 'File deleted.']);
        }
        return back()->with('status', 'File deleted.');
    }

    /** Delete a set of selected files. Owner-only; silently ignores foreign ids. */
    public function bulkDestroy(Request $request)
    {
        $data = $request->validate([
            'ids'   => 'required|array|max:500',
            'ids.*' => 'integer',
        ]);

        $files = UserFile::forUser((int) auth()->id())
            ->whereIn('id', $data['ids'])->get();

        $count = 0;
        foreach ($files as $file) {
            $this->deleteBytes($file);
            $file->delete();
            $count++;
        }

        $msg = "{$count} file(s) deleted.";
        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'deleted' => $count, 'message' => $msg]);
        }
        return back()->with('status', $msg);
    }

    /** Rename a file's display name (the honest "Edit" action — no image editor). */
    public function rename(Request $request, int $id)
    {
        $data = $request->validate([
            'original_name' => 'required|string|max:255',
        ]);

        $file = UserFile::forUser((int) auth()->id())->where('id', $id)->first();
        if (!$file) {
            return $request->expectsJson()
                ? response()->json(['ok' => false, 'message' => 'Not found.'], 404)
                : back()->with('error', 'File not found.');
        }

        // Rename the label only — the stored path/extension stays put so existing
        // published posts that reference the URL keep working.
        $name = trim($data['original_name']);
        $ext  = pathinfo((string) $file->path, PATHINFO_EXTENSION);
        if ($ext !== '' && !str_ends_with(strtolower($name), '.' . strtolower($ext))) {
            $name = preg_replace('/\.[A-Za-z0-9]{1,8}$/', '', $name) . '.' . $ext;
        }
        $file->original_name = mb_substr($name, 0, 255);
        $file->save();

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'name' => $file->original_name, 'message' => 'Renamed.']);
        }
        return back()->with('status', 'File renamed.');
    }

    /** Move a file into a folder (blank clears it back to "All"). Owner-only. */
    public function move(Request $request, int $id)
    {
        $request->validate(['folder' => 'nullable|string|max:120']);

        $file = UserFile::forUser((int) auth()->id())->where('id', $id)->first();
        if (!$file) {
            return $request->expectsJson()
                ? response()->json(['ok' => false, 'message' => 'Not found.'], 404)
                : back()->with('error', 'File not found.');
        }

        $folder = trim((string) $request->input('folder', '')) ?: null;
        $file->folder = $folder ? mb_substr($folder, 0, 120) : null;
        $file->save();

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'folder' => $file->folder, 'message' => 'Moved.']);
        }
        return back()->with('status', $folder ? 'Moved to “' . $file->folder . '”.' : 'Removed from folder.');
    }

    /** Stream/redirect to the file for download. Owner-only. */
    public function download(int $id)
    {
        $file = UserFile::forUser((int) auth()->id())->where('id', $id)->firstOrFail();
        $disk = $file->disk ?: 'public';
        $name = $file->original_name ?: basename($file->path);

        try {
            if (Storage::disk($disk)->exists($file->path)) {
                return Storage::disk($disk)->download($file->path, $name);
            }
        } catch (\Throwable $e) {
            // Fall through to a redirect for cloud disks that can't stream locally.
        }

        return redirect()->away($file->url);
    }

    // ---- helpers ---------------------------------------------------------

    /** Best-effort byte removal — a missing file must not block the row delete. */
    private function deleteBytes(UserFile $file): void
    {
        try {
            Storage::disk($file->disk ?: 'public')->delete($file->path);
        } catch (\Throwable $e) {
            // ignore — the index row is what the user sees; drop it regardless
        }
    }

    /** Coarse type bucket used in the "Storage types" breakdown. */
    private function typeGroup(string $mime): string
    {
        if (str_starts_with($mime, 'image')) return 'Images';
        if (str_starts_with($mime, 'video')) return 'Videos';
        if (str_starts_with($mime, 'audio')) return 'Audio';
        if (str_contains($mime, 'pdf'))      return 'Documents';
        return 'Other';
    }

    /** Human-friendly byte size, e.g. "1.4 MB". */
    private function human(int $bytes): string
    {
        if ($bytes <= 0) return '0 B';
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = (int) floor(log($bytes, 1024));
        $i = max(0, min($i, count($units) - 1));
        $val = $bytes / (1024 ** $i);
        return ($i === 0 ? (string) $bytes : number_format($val, $val >= 100 ? 0 : 1)) . ' ' . $units[$i];
    }
}
