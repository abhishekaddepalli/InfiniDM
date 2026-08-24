<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * One row in the personal media library. The bytes live on `disk` at `path`;
 * this record is the owner-scoped index that makes them listable/searchable.
 *
 * Owner scoping: IgDesk uses user_id (workspace_id stays 0). Never query
 * without forUser()/the owner constraint — path alone is not a tenancy boundary.
 */
class UserFile extends Model
{
    protected $fillable = [
        'user_id', 'workspace_id', 'folder', 'disk',
        'path', 'original_name', 'mime', 'size',
    ];

    protected $casts = [
        'user_id'      => 'integer',
        'workspace_id' => 'integer',
        'size'         => 'integer',
    ];

    /** Owner-scoped query — the tenancy boundary for the library. */
    public function scopeForUser($q, ?int $userId)
    {
        return $q->where('user_id', (int) $userId);
    }

    /** True when the stored mime is an image. */
    public function getIsImageAttribute(): bool
    {
        return str_starts_with((string) $this->mime, 'image');
    }

    /** True when the stored mime is a video. */
    public function getIsVideoAttribute(): bool
    {
        return str_starts_with((string) $this->mime, 'video');
    }

    /** Human-friendly size, e.g. "1.4 MB". */
    public function getHumanSizeAttribute(): string
    {
        $bytes = (int) $this->size;
        if ($bytes <= 0) return '0 B';
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = (int) floor(log($bytes, 1024));
        $i = max(0, min($i, count($units) - 1));
        $val = $bytes / (1024 ** $i);
        return ($i === 0 ? (string) $bytes : number_format($val, $val >= 100 ? 0 : 1)) . ' ' . $units[$i];
    }

    /** Short label for the file's type, e.g. "PNG", "MP4". */
    public function getKindLabelAttribute(): string
    {
        $ext = strtoupper(pathinfo((string) ($this->original_name ?: $this->path), PATHINFO_EXTENSION));
        if ($ext !== '') return $ext;
        $mime = (string) $this->mime;
        return $mime !== '' ? strtoupper((string) (explode('/', $mime)[1] ?? $mime)) : 'FILE';
    }

    /** Public URL for the stored file (cloud disks return their own https URL). */
    public function getUrlAttribute(): string
    {
        if (function_exists('media_url')) {
            return media_url($this->path);
        }
        try {
            return Storage::disk($this->disk ?: 'public')->url($this->path);
        } catch (\Throwable $e) {
            return asset('storage/' . ltrim((string) $this->path, '/'));
        }
    }

    /**
     * Record a stored file into the library. Safe to call from anywhere media is
     * saved (composer upload, AI image tool) — it never throws: a failed index
     * write must not break the actual publish/generate flow.
     *
     * @return self|null  the row, or null on any failure (logged by caller if it cares)
     */
    public static function record(array $attrs): ?self
    {
        try {
            $path = trim((string) ($attrs['path'] ?? ''));
            if ($path === '') return null;

            $userId = (int) ($attrs['user_id'] ?? (auth()->id() ?? 0));
            $disk   = (string) ($attrs['disk'] ?? (function_exists('media_disk') ? media_disk() : 'public'));

            // Don't index the same physical file twice for the same owner.
            $existing = static::where('user_id', $userId)->where('path', $path)->first();
            if ($existing) return $existing;

            $size = (int) ($attrs['size'] ?? 0);
            if ($size <= 0) {
                try { $size = (int) Storage::disk($disk)->size($path); } catch (\Throwable $e) { $size = 0; }
            }
            $mime = (string) ($attrs['mime'] ?? '');
            if ($mime === '') {
                try { $mime = (string) Storage::disk($disk)->mimeType($path); } catch (\Throwable $e) { $mime = ''; }
            }

            return static::create([
                'user_id'       => $userId,
                'workspace_id'  => (int) ($attrs['workspace_id'] ?? 0),
                'folder'        => $attrs['folder'] ?? null,
                'disk'          => $disk,
                'path'          => $path,
                'original_name' => $attrs['original_name'] ?? basename($path),
                'mime'          => $mime ?: null,
                'size'          => $size,
            ]);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
