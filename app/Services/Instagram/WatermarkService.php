<?php

namespace App\Services\Instagram;

use App\Models\InstagramAccount;
use App\Models\InstagramWatermark;
use App\Models\UserFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Overlays an operator's watermark onto an image just before it is published to
 * Instagram, for content protection.
 *
 * The overlay is done with GD (which ships enabled on virtually every PHP host)
 * and is entirely FAIL-SAFE: if GD is missing, no active config exists, the
 * source can't be read, or anything at all throws, apply() returns null and the
 * caller publishes the ORIGINAL image unchanged. Watermarking must never break a
 * publish.
 *
 * A watermarked COPY is written to the media disk (the same instagram-media/ dir
 * the composer uses); the operator's original file is never mutated.
 */
class WatermarkService
{
    /** The active media disk — the same one the composer/Files write to. */
    private static function disk(): string
    {
        return function_exists('media_disk') ? media_disk() : 'public';
    }

    /** Public URL for a stored path (cloud-aware where available). */
    private static function url(string $path): string
    {
        return function_exists('media_url') ? media_url($path) : asset('storage/' . $path);
    }

    /**
     * Watermark $source (a public URL or a media-disk-relative path) for
     * $account and return the PUBLIC URL of the watermarked copy — or null when
     * no watermark should/could be applied (caller then uses the original).
     */
    public static function apply(string $source, InstagramAccount $account): ?string
    {
        try {
            if ($source === '') {
                return null;
            }

            // GD guard — no GD, no watermark (graceful skip, never fatal).
            if (!function_exists('imagecreatetruecolor') || !function_exists('imagecopymerge')) {
                return null;
            }

            $config = InstagramWatermark::forAccount($account);
            if (!$config || !$config->is_active) {
                return null;
            }

            $bytes = self::readBytes($source);
            if ($bytes === null || $bytes === '') {
                return null;
            }

            $base = @imagecreatefromstring($bytes);
            if (!$base) {
                return null;
            }
            imagealphablending($base, true);

            $baseW = imagesx($base);
            $baseH = imagesy($base);
            if ($baseW < 8 || $baseH < 8) {
                imagedestroy($base);
                return null;
            }

            $ok = $config->type === 'text'
                ? self::stampText($base, $baseW, $baseH, $config)
                : self::stampImage($base, $baseW, $baseH, $config);

            if (!$ok) {
                imagedestroy($base);
                return null;
            }

            // Encode the result as JPEG (Instagram feed images are JPEG anyway).
            ob_start();
            imagejpeg($base, null, 90);
            $out = (string) ob_get_clean();
            imagedestroy($base);

            if ($out === '') {
                return null;
            }

            $path = 'instagram-media/wm_' . Str::random(28) . '.jpg';
            Storage::disk(self::disk())->put($path, $out);

            // Index the copy in the Files library (best-effort — never blocks).
            try {
                UserFile::record([
                    'user_id'       => (int) $account->user_id,
                    'disk'          => self::disk(),
                    'path'          => $path,
                    'original_name' => 'watermarked.jpg',
                    'mime'          => 'image/jpeg',
                    'size'          => strlen($out),
                    'folder'        => 'Watermarked',
                ]);
            } catch (\Throwable $e) {
                // ignore — the index row is a nicety, not a requirement
            }

            return self::url($path);
        } catch (\Throwable $e) {
            Log::warning('[IG-WATERMARK] apply failed, publishing original: ' . $e->getMessage());
            return null;
        }
    }

    /** Read the source bytes from a URL or a media-disk-relative path. */
    private static function readBytes(string $source): ?string
    {
        try {
            if (Str::startsWith($source, ['http://', 'https://'])) {
                $res = Http::timeout(20)->get($source);
                return $res->successful() ? (string) $res->body() : null;
            }
            $disk = self::disk();
            if (Storage::disk($disk)->exists($source)) {
                return (string) Storage::disk($disk)->get($source);
            }
            // Last resort: a local absolute/public path.
            if (is_file($source)) {
                return (string) @file_get_contents($source);
            }
        } catch (\Throwable $e) {
            return null;
        }
        return null;
    }

    /** Overlay the watermark IMAGE onto $base. Returns false on any failure. */
    private static function stampImage($base, int $baseW, int $baseH, InstagramWatermark $config): bool
    {
        $path = (string) $config->image_path;
        if ($path === '') {
            return false;
        }

        $bytes = self::readBytes($path);
        if ($bytes === null || $bytes === '') {
            // image_path might be a full URL rather than a stored path.
            $bytes = self::readBytes(self::url($path));
        }
        if ($bytes === null || $bytes === '') {
            return false;
        }

        $wm = @imagecreatefromstring($bytes);
        if (!$wm) {
            return false;
        }

        $wmW = imagesx($wm);
        $wmH = imagesy($wm);
        if ($wmW < 1 || $wmH < 1) {
            imagedestroy($wm);
            return false;
        }

        // Scale the watermark to size% of the base WIDTH, keeping aspect ratio.
        $targetW = max(1, (int) round($baseW * self::sizeFraction($config)));
        $targetH = max(1, (int) round($targetW * ($wmH / $wmW)));

        $scaled = imagecreatetruecolor($targetW, $targetH);
        imagealphablending($scaled, false);
        imagesavealpha($scaled, true);
        $transparent = imagecolorallocatealpha($scaled, 0, 0, 0, 127);
        imagefill($scaled, 0, 0, $transparent);
        imagecopyresampled($scaled, $wm, 0, 0, 0, 0, $targetW, $targetH, $wmW, $wmH);
        imagedestroy($wm);

        [$x, $y] = self::anchor($config->position, $baseW, $baseH, $targetW, $targetH);
        self::copyMergeAlpha($base, $scaled, $x, $y, $targetW, $targetH, self::opacityPct($config));
        imagedestroy($scaled);

        return true;
    }

    /**
     * Overlay the watermark TEXT onto $base. Drawn with GD's built-in bitmap
     * font onto a small transparent canvas, then scaled up to size% of the base
     * width — so no TTF font file is required (IgDesk ships none) and the
     * Size control still means something.
     */
    private static function stampText($base, int $baseW, int $baseH, InstagramWatermark $config): bool
    {
        $text = trim((string) $config->text);
        if ($text === '') {
            return false;
        }
        $text = mb_substr($text, 0, 60);

        $font   = 5;                              // largest built-in GD font
        $charW  = imagefontwidth($font);
        $charH  = imagefontheight($font);
        $padded = 6;                              // px padding around the label
        $rawW   = $charW * strlen($text) + $padded * 2;
        $rawH   = $charH + $padded * 2;

        // Draw the label at 1x on a transparent canvas.
        $label = imagecreatetruecolor($rawW, $rawH);
        imagealphablending($label, false);
        imagesavealpha($label, true);
        imagefill($label, 0, 0, imagecolorallocatealpha($label, 0, 0, 0, 127));
        imagealphablending($label, true);

        [$r, $g, $b] = self::hexToRgb((string) ($config->text_color ?: '#ffffff'));
        // A soft shadow so light text stays readable on light images.
        $shadow = imagecolorallocatealpha($label, 0, 0, 0, 70);
        $ink    = imagecolorallocate($label, $r, $g, $b);
        imagestring($label, $font, $padded + 1, $padded + 1, $text, $shadow);
        imagestring($label, $font, $padded, $padded, $text, $ink);

        // Scale the label up to size% of the base width.
        $targetW = max(1, (int) round($baseW * self::sizeFraction($config)));
        $targetH = max(1, (int) round($targetW * ($rawH / $rawW)));

        $scaled = imagecreatetruecolor($targetW, $targetH);
        imagealphablending($scaled, false);
        imagesavealpha($scaled, true);
        imagefill($scaled, 0, 0, imagecolorallocatealpha($scaled, 0, 0, 0, 127));
        imagecopyresampled($scaled, $label, 0, 0, 0, 0, $targetW, $targetH, $rawW, $rawH);
        imagedestroy($label);

        [$x, $y] = self::anchor($config->position, $baseW, $baseH, $targetW, $targetH);
        self::copyMergeAlpha($base, $scaled, $x, $y, $targetW, $targetH, self::opacityPct($config));
        imagedestroy($scaled);

        return true;
    }

    /** size% clamped to a sane 5–90% of the base width, as a 0–1 fraction. */
    private static function sizeFraction(InstagramWatermark $config): float
    {
        $pct = (int) $config->size;
        $pct = max(5, min(90, $pct));
        return $pct / 100;
    }

    /** opacity clamped to 5–100 (0 would be invisible → treat as the default). */
    private static function opacityPct(InstagramWatermark $config): int
    {
        $o = (int) $config->opacity;
        return max(5, min(100, $o ?: 70));
    }

    /** Top-left (x,y) for a watermark of $wW×$wH at $pos, with an 4% margin. */
    private static function anchor(string $pos, int $baseW, int $baseH, int $wW, int $wH): array
    {
        $mx = (int) round($baseW * 0.04);
        $my = (int) round($baseH * 0.04);

        $left   = $mx;
        $center = (int) round(($baseW - $wW) / 2);
        $right  = $baseW - $wW - $mx;

        $top    = $my;
        $middle = (int) round(($baseH - $wH) / 2);
        $bottom = $baseH - $wH - $my;

        $x = match (substr($pos, 1, 1)) {
            'l'     => $left,
            'r'     => $right,
            default => $center,
        };
        $y = match (substr($pos, 0, 1)) {
            't'     => $top,
            'b'     => $bottom,
            default => $middle,
        };

        return [max(0, $x), max(0, $y)];
    }

    /**
     * Alpha-preserving copymerge: blends $src over the matching patch of $dst,
     * then merges it back at $pct opacity. This is the well-known workaround for
     * imagecopymerge() ignoring per-pixel alpha, so PNG transparency survives.
     */
    private static function copyMergeAlpha($dst, $src, int $x, int $y, int $w, int $h, int $pct): void
    {
        $cut = imagecreatetruecolor($w, $h);
        imagecopy($cut, $dst, 0, 0, $x, $y, $w, $h);   // grab the destination patch
        imagecopy($cut, $src, 0, 0, 0, 0, $w, $h);     // lay the (alpha) source over it
        imagecopymerge($dst, $cut, $x, $y, 0, 0, $w, $h, $pct);
        imagedestroy($cut);
    }

    /** "#rrggbb" (or "rgb") → [r,g,b], defaulting to white on garbage. */
    private static function hexToRgb(string $hex): array
    {
        $hex = ltrim(trim($hex), '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (strlen($hex) !== 6 || !ctype_xdigit($hex)) {
            return [255, 255, 255];
        }
        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }
}
