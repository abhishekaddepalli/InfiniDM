<?php

namespace App\Http\Controllers;

use App\Models\InstagramAccount;
use App\Models\InstagramRepostItem;
use App\Models\InstagramReposterSetting;
use App\Services\Instagram\InstagramGate;
use App\Services\Instagram\InstagramService;
use Illuminate\Http\Request;

/**
 * IgDesk "Reels Autopilot" — user-facing config for the scrape→queue→post
 * reposter. Node owns the scheduling/scraping (yt-dlp) + posts via the
 * official Graph API; this page just lets the operator set sources, cadence,
 * hashtags + watch the queue. Plan-gated by access_instagram_reposter.
 */
class InstagramReposterController extends Controller
{
    private function wsId(): int
    {
        return (int) (auth()->user()->current_workspace_id ?? 0);
    }

    /**
     * Entitlement for this page.
     *
     * Asks InstagramGate rather than PlanLimitGuard directly: the gate is what
     * knows whether this install even sells by plan, and it owns the mapping to
     * the `access_instagram_reposter` column so the name lives in exactly one
     * file. The workspace is still resolved here and passed in, because
     * `current_workspace` is the accessor that falls back to the user's first
     * workspace while the gate's own default is the plain relation — handing it
     * over keeps this page's answer exactly what it has always been.
     */
    private function allowed(): bool
    {
        return InstagramGate::allows('reposter', auth()->user()?->current_workspace);
    }

    public function index(Request $r)
    {
        $wsId = $this->wsId();
        $hasFeature = $this->allowed();

        $accounts  = InstagramAccount::forWorkspace($wsId)->where('status', 'connected')->orderBy('id')->get();
        $accountId = (int) $r->query('account', (int) ($accounts->first()->id ?? 0));
        // Only allow an account that belongs to this workspace.
        if ($accountId && !$accounts->contains('id', $accountId)) $accountId = (int) ($accounts->first()->id ?? 0);

        $setting = $accountId
            ? InstagramReposterSetting::firstOrNew(['instagram_account_id' => $accountId])
            : new InstagramReposterSetting();

        $items = $accountId
            ? InstagramRepostItem::where('instagram_account_id', $accountId)->orderByDesc('id')->limit(60)->get()
            : collect();

        $base  = $accountId ? InstagramRepostItem::where('instagram_account_id', $accountId) : null;
        $stats = [
            'queued' => $base ? (clone $base)->where('status', 'queued')->count() : 0,
            'posted' => $base ? (clone $base)->where('status', 'posted')->count() : 0,
            'failed' => $base ? (clone $base)->where('status', 'failed')->count() : 0,
        ];

        return view('instagram.reposter', compact('accounts', 'accountId', 'setting', 'items', 'stats', 'hasFeature'));
    }

    public function save(Request $r)
    {
        abort_unless($this->allowed(), 403, 'Reels Autopilot is not in your plan.');
        $wsId = $this->wsId();

        $data = $r->validate([
            'instagram_account_id' => 'required|integer',
            'source_ig_accounts'   => 'nullable|string',
            'source_yt_channels'   => 'nullable|string',
            'youtube_api_key'      => 'nullable|string|max:191',
            'fetch_limit'          => 'nullable|integer|min:1|max:50',
            'scraper_interval_min' => 'nullable|integer|min:10|max:1440',
            'posting_interval_min' => 'nullable|integer|min:1|max:1440',
            'daily_cap'            => 'nullable|integer|min:1|max:50',
            'remove_after_min'     => 'nullable|integer|min:5|max:1440',
            'hashtags'             => 'nullable|string|max:2000',
        ]);

        $accountId = (int) $data['instagram_account_id'];
        abort_unless(
            InstagramAccount::forWorkspace($wsId)->whereKey($accountId)->exists(),
            403, 'That Instagram account is not in this workspace.'
        );

        $split = fn (?string $s) => array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', (string) $s))));

        $update = [
            'workspace_id'         => $wsId,
            'enabled'              => $r->boolean('enabled'),
            'source_ig_accounts'   => $split($data['source_ig_accounts'] ?? ''),
            'youtube_enabled'      => $r->boolean('youtube_enabled'),
            'source_yt_channels'   => $split($data['source_yt_channels'] ?? ''),
            'fetch_limit'          => (int) ($data['fetch_limit'] ?? 10),
            'scraper_interval_min' => (int) ($data['scraper_interval_min'] ?? 120),
            'posting_interval_min' => (int) ($data['posting_interval_min'] ?? 30),
            'daily_cap'            => (int) ($data['daily_cap'] ?? 10),
            'remove_after_min'     => (int) ($data['remove_after_min'] ?? 120),
            'post_to_story'        => $r->boolean('post_to_story'),
            'hashtags'             => $data['hashtags'] ?? null,
        ];
        // Only overwrite the encrypted YouTube key when a new value is typed
        // (the field renders blank, so an empty submit must NOT wipe it).
        if (!empty($data['youtube_api_key'])) $update['youtube_api_key'] = $data['youtube_api_key'];

        InstagramReposterSetting::updateOrCreate(['instagram_account_id' => $accountId], $update);

        return back()->with('status', __('Reels Autopilot settings saved.'));
    }

    /**
     * Repost a reel NOW from a direct public HTTPS video URL — publishes to the
     * connected account immediately through the official Graph API (the same
     * path the composer uses). This is the manual, no-worker-needed path; the
     * scheduled auto-scrape still runs on the Node worker.
     */
    public function repostNow(Request $r)
    {
        abort_unless($this->allowed(), 403, 'Reels Autopilot is not in your plan.');
        $wsId = $this->wsId();

        $data = $r->validate([
            'instagram_account_id' => 'required|integer',
            'video_url'            => 'required|url|max:1024',
            'caption'              => 'nullable|string|max:2200',
        ]);

        $account = InstagramAccount::forWorkspace($wsId)->whereKey((int) $data['instagram_account_id'])->first();
        if (!$account) return back()->withErrors(['reposter' => 'That Instagram account is not in your workspace.']);
        if (!str_starts_with($data['video_url'], 'https://')) {
            return back()->withErrors(['reposter' => 'The video URL must be a public HTTPS link to an .mp4 / .mov file (Meta publishes from a public HTTPS URL).'])->withInput();
        }

        $caption = trim((string) ($data['caption'] ?? ''));
        $res = (new InstagramService($account))->publishReel($data['video_url'], $caption, []);

        InstagramRepostItem::create([
            'workspace_id'         => $wsId,
            'instagram_account_id' => $account->id,
            'source'               => 'manual',
            'source_id'            => \Illuminate\Support\Str::limit($data['video_url'], 180, ''),
            'source_handle'        => 'manual URL',
            'caption'              => $caption,
            'public_url'           => $data['video_url'],
            'status'               => !empty($res['ok']) ? 'posted' : 'failed',
            'media_id'             => $res['media_id'] ?? null,
            'last_error'           => empty($res['ok']) ? mb_substr((string) ($res['error'] ?? 'publish failed'), 0, 500) : null,
            'posted_at'            => !empty($res['ok']) ? now() : null,
        ]);

        if (empty($res['ok'])) {
            return back()->withErrors(['reposter' => 'Publish failed: ' . ($res['error'] ?? 'unknown') . ' — the URL must be a public HTTPS MP4/MOV reel.'])->withInput();
        }
        // Stamp the post cursor so the "Last post" pill reflects this manual post too.
        InstagramReposterSetting::where('instagram_account_id', $account->id)->update(['last_post_at' => now()]);
        return back()->with('status', 'Reel published to Instagram now. Media ID ' . ($res['media_id'] ?? '') . '.');
    }

    /**
     * Full detail for one queued/posted/failed clip — the page an operator
     * lands on from the queue row. Shows every field (source, caption, the
     * public video URL, the resulting Media ID + a live link to it, the exact
     * failure reason, and every timestamp) plus an inline caption editor and
     * the same retry / delete / repost-now actions the row carries.
     */
    public function show(Request $r, int $id)
    {
        $wsId = $this->wsId();
        $item = InstagramRepostItem::where('workspace_id', $wsId)->whereKey($id)->firstOrFail();
        $account = InstagramAccount::forWorkspace($wsId)->whereKey($item->instagram_account_id)->first();

        return view('instagram.reposter-item', compact('item', 'account'));
    }

    /**
     * Edit a clip's caption (and, while it's still queued, its source video
     * URL) before it posts. Posted clips are already live on Instagram, so the
     * caption is read-only there — we still let the operator fix a queued or
     * failed clip and re-queue it in one go.
     */
    public function update(Request $r, int $id)
    {
        abort_unless($this->allowed(), 403, 'Reels Autopilot is not in your plan.');
        $wsId = $this->wsId();
        $item = InstagramRepostItem::where('workspace_id', $wsId)->whereKey($id)->firstOrFail();

        $data = $r->validate([
            'caption'    => 'nullable|string|max:2200',
            'public_url' => 'nullable|url|max:1024',
            'requeue'    => 'nullable|boolean',
        ]);

        $update = ['caption' => trim((string) ($data['caption'] ?? ''))];

        // The source URL is only meaningful before it posts — a posted clip is
        // already published, changing the URL would just confuse the record.
        if ($item->status !== 'posted' && !empty($data['public_url'])) {
            if (!str_starts_with($data['public_url'], 'https://')) {
                return back()->withErrors(['reposter' => 'The video URL must be a public HTTPS link.'])->withInput();
            }
            $update['public_url'] = $data['public_url'];
        }

        // Optional: put a failed/queued clip back in line for the next post tick.
        if ($r->boolean('requeue') && $item->status !== 'posted') {
            $update['status']     = 'queued';
            $update['claimed_at'] = null;
            $update['last_error'] = null;
        }

        $item->update($update);

        return redirect()->route('instagram.reposter.show', $item->id)
            ->with('status', __('Clip updated.'));
    }

    /** Re-queue a failed clip (only if its hosted file still exists). */
    public function retry(Request $r, int $id)
    {
        $wsId = $this->wsId();
        $item = InstagramRepostItem::where('workspace_id', $wsId)->whereKey($id)->firstOrFail();
        if (!$item->video_path) return back()->with('error', __('That clip was already cleaned up — re-scrape it instead.'));
        $item->update(['status' => 'queued', 'claimed_at' => null, 'last_error' => null]);
        return back()->with('status', __('Clip re-queued.'));
    }

    public function destroy(Request $r, int $id)
    {
        $wsId = $this->wsId();
        $item = InstagramRepostItem::where('workspace_id', $wsId)->whereKey($id)->firstOrFail();
        // media_storage() is core-only. In WaDesk it honours the workspace's
        // cloud disk; standalone has no such helper, so fall back to the same
        // disk media_disk() resolves for uploads — otherwise the try/catch
        // swallows the undefined-function Error and the file silently orphans
        // on disk while its row is deleted. Guarded like every sibling site.
        if ($item->video_path) {
            try {
                if (function_exists('media_storage')) {
                    media_storage()->delete($item->video_path);
                } else {
                    \Illuminate\Support\Facades\Storage::disk(function_exists('media_disk') ? media_disk() : 'public')
                        ->delete($item->video_path);
                }
            } catch (\Throwable $e) {}
        }
        $item->delete();
        return back()->with('status', __('Clip removed from the queue.'));
    }
}
