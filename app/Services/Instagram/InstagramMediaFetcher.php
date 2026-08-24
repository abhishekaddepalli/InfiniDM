<?php

namespace App\Services\Instagram;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Inbound Instagram DM media arrives as short-lived lookaside CDN URLs
 * (lookaside.fbsbx.com / scontent) that expire — so a stored URL renders fine
 * for a minute then becomes a broken image / black video player. We copy the
 * bytes to our own media disk at ingest and serve a stable URL instead.
 *
 * Best-effort: any failure returns the original URL so the bubble still has a
 * source, and only real media (image/video/audio/file) is downloaded — never a
 * shared-reel permalink or a story link (those are pages, not files).
 */
class InstagramMediaFetcher
{
    /** Types that are actual downloadable files (NOT permalinks/pages). */
    private const FILE_TYPES = ['image', 'video', 'audio', 'file', 'story_mention'];

    public static function localize(?string $url, ?string $type = 'image'): ?string
    {
        $url  = trim((string) $url);
        $type = (string) $type;
        if ($url === '' || !preg_match('#^https?://#i', $url)) return $url ?: null;
        // Only pull real media — shares/reels carry a permalink we must keep as-is.
        if (!in_array($type, self::FILE_TYPES, true)) return $url;
        // Already hosted by us? leave it.
        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        if ($appHost && str_contains($url, (string) $appHost)) return $url;

        try {
            $disk = function_exists('media_disk') ? media_disk() : 'public';
            $r = Http::connectTimeout(6)->timeout(20)->retry(2, 500, null, false)->get($url);
            if (!$r->successful()) return $url;
            $body = $r->body();
            if ($body === '' || strlen($body) > 30 * 1024 * 1024) return $url;   // skip empty / >30MB
            $ext  = self::extFor($type, (string) $r->header('Content-Type'), $url);
            $path = 'instagram-inbound/' . Str::random(40) . '.' . $ext;
            Storage::disk($disk)->put($path, $body, 'public');
            return function_exists('media_url') ? media_url($path) : Storage::disk($disk)->url($path);
        } catch (\Throwable $e) {
            Log::warning('[IG-MEDIA-FETCH] ' . $e->getMessage());
            return $url;
        }
    }

    private static function extFor(string $type, string $contentType, string $url): string
    {
        $ct = strtolower($contentType);
        if (str_contains($ct, 'mp4') || str_contains($ct, 'video')) return 'mp4';
        if (str_contains($ct, 'png'))  return 'png';
        if (str_contains($ct, 'webp')) return 'webp';
        if (str_contains($ct, 'gif'))  return 'gif';
        if (str_contains($ct, 'jpeg') || str_contains($ct, 'jpg')) return 'jpg';
        if (str_contains($ct, 'mpeg') || str_contains($ct, 'mp3')) return 'mp3';
        if (str_contains($ct, 'ogg'))  return 'ogg';
        if (str_contains($ct, 'pdf'))  return 'pdf';
        if (in_array($type, ['video', 'ig_reel', 'reel'], true)) return 'mp4';
        if ($type === 'audio') return 'mp3';
        $u = strtolower((string) parse_url($url, PHP_URL_PATH));
        foreach (['mp4', 'png', 'webp', 'gif', 'jpg', 'jpeg', 'mp3', 'm4a', 'ogg', 'pdf'] as $e) {
            if (str_ends_with($u, '.' . $e)) return $e === 'jpeg' ? 'jpg' : $e;
        }
        return $type === 'image' ? 'jpg' : 'bin';
    }
}
