<?php

namespace App\Http\Controllers;

use App\Models\InstagramAccount;
use App\Services\Instagram\InstagramGate;
use App\Services\Instagram\InstagramService;
use App\Models\InstagramAutomation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * User-facing Instagram automation pages — the "IgDesk" suite.
 *   /instagram             → dashboard (accounts + automations + KPIs)
 *   /instagram/automations → build keyword→DM + comment→DM rules
 */
class InstagramController extends Controller
{
    /** Highest inbound message id the operator has seen (drives the rail dot). */
    private const INBOX_SEEN_KEY = 'instaflow.inbox_seen_id';

    private function wsId(): int
    {
        return (int) (Auth::user()?->current_workspace_id ?? 0);
    }

    public function dashboard()
    {
        $wsId = $this->wsId();
        $accounts    = InstagramAccount::forWorkspace($wsId)->orderBy('id')->get();
        // Every stat below is scoped to the accounts that are STILL connected — so
        // a disconnected/removed account's messages, contacts and automations stop
        // counting immediately, and with zero accounts the dashboard reads zero
        // (instead of leftover data from deleted accounts).
        $accIds      = $accounts->pluck('id');
        $automations = InstagramAutomation::where('workspace_id', $wsId)
            ->whereIn('instagram_account_id', $accIds)
            ->orderByDesc('id')->get();

        $stats = [
            'accounts'    => $accounts->count(),
            'live'        => $accounts->where('status', 'connected')->count(),
            'automations' => $automations->where('is_active', true)->count(),
            'fired'       => (int) $automations->sum('fired_count'),
        ];

        // ── 14-day DM volume, grouped in the DB (not in PHP): a workspace with
        // months of history would otherwise hydrate every row just to count them.
        $since = now()->subDays(13)->startOfDay();
        $rows  = \App\Models\InstagramMessage::whereIn('instagram_account_id', $accIds)
            ->where('created_at', '>=', $since)
            ->selectRaw('DATE(created_at) as d, direction, COUNT(*) as c')
            ->groupBy('d', 'direction')
            ->get();

        $days = [];
        for ($i = 13; $i >= 0; $i--) $days[now()->subDays($i)->toDateString()] = ['in' => 0, 'out' => 0];
        foreach ($rows as $r) {
            $d = (string) $r->d;
            if (!isset($days[$d])) continue;
            $days[$d][$r->direction === 'out' ? 'out' : 'in'] = (int) $r->c;
        }

        $series = [
            'labels' => array_map(fn ($d) => \Carbon\Carbon::parse($d)->format('M j'), array_keys($days)),
            'in'     => array_values(array_map(fn ($v) => $v['in'], $days)),
            'out'    => array_values(array_map(fn ($v) => $v['out'], $days)),
        ];

        $stats['dms_14d']   = array_sum($series['in']) + array_sum($series['out']);
        $stats['received']  = array_sum($series['in']);
        $stats['auto_sent'] = array_sum($series['out']);
        $stats['contacts']  = \App\Models\InstagramContact::whereIn('instagram_account_id', $accIds)->count();
        $stats['followers'] = (int) $accounts->sum('followers_count');

        // Which automations actually earn their keep — fired_count is a lifetime
        // counter on the row, so this needs no extra query.
        $topAutomations = $automations->sortByDesc('fired_count')->take(5)->values();

        return view('instagram.dashboard', compact('accounts', 'automations', 'stats', 'series', 'topAutomations'));
    }

    /** Content calendar — scheduled IG posts / reels / stories (month grid). */
    public function calendar(Request $request)
    {
        $wsId = $this->wsId();
        $accounts = InstagramAccount::forWorkspace($wsId)->orderBy('id')->get();

        // View mode: month | week | day (a proper scheduler).
        $view = in_array($request->query('view'), ['month', 'week', 'day'], true) ? $request->query('view') : 'month';

        // Anchor date: explicit ?date=Y-m-d, legacy ?month=Y-m, else today.
        try {
            if ($request->query('date')) {
                $anchor = \Illuminate\Support\Carbon::parse($request->query('date'))->startOfDay();
            } elseif ($request->query('month')) {
                $anchor = \Illuminate\Support\Carbon::parse($request->query('month') . '-01')->startOfDay();
            } else {
                $anchor = now()->startOfDay();
            }
        } catch (\Throwable $e) {
            $anchor = now()->startOfDay();
        }

        // Load range depends on the active view.
        if ($view === 'week') {
            $gridStart = $anchor->copy()->startOfWeek(\Carbon\CarbonInterface::SUNDAY);
            $gridEnd   = $anchor->copy()->endOfWeek(\Carbon\CarbonInterface::SATURDAY);
        } elseif ($view === 'day') {
            $gridStart = $anchor->copy()->startOfDay();
            $gridEnd   = $anchor->copy()->endOfDay();
        } else {
            $month     = $anchor->copy()->startOfMonth();
            $gridStart = $month->copy()->startOfWeek(\Carbon\CarbonInterface::SUNDAY);
            $gridEnd   = $month->copy()->endOfMonth()->endOfWeek(\Carbon\CarbonInterface::SATURDAY);
        }

        // Optional per-account filter (?account=<id>). 0 / absent = all accounts.
        $accountId = (int) $request->query('account', 0);
        $currentAccount = $accountId ? $accounts->firstWhere('id', $accountId) : null;

        $posts = \App\Models\InstagramScheduledPost::where('workspace_id', $wsId)
            ->when($currentAccount, fn ($q) => $q->where('instagram_account_id', $currentAccount->id))
            ->whereBetween('scheduled_at', [$gridStart->copy()->startOfDay(), $gridEnd->copy()->endOfDay()])
            ->orderBy('scheduled_at')->get();

        return view('instagram.calendar', compact('accounts', 'posts', 'view', 'anchor', 'gridStart', 'gridEnd', 'currentAccount'));
    }

    /** Comment automations — comment-keyword → DM rules. */
    public function autoComments()
    {
        $wsId = $this->wsId();
        $accounts = InstagramAccount::forWorkspace($wsId)->orderBy('id')->get();
        $rules = InstagramAutomation::where('workspace_id', $wsId)
            ->whereIn('instagram_account_id', $accounts->pluck('id'))
            ->where('type', 'comment_to_dm')->orderByDesc('id')->get();
        return view('instagram.auto-comments', compact('accounts', 'rules'));
    }

    /** Instagram ads — promote posts / story ads via the Meta Marketing API. */
    public function ads()
    {
        $wsId = $this->wsId();
        $accounts = InstagramAccount::forWorkspace($wsId)->orderBy('id')->get();
        // Recent posts for the "boost an existing post" picker.
        $boostMedia = [];
        if ($acc = $accounts->where('status', 'connected')->first()) {
            $boostMedia = \Illuminate\Support\Facades\Cache::remember("ig_media_{$acc->id}", 600, fn () => (new InstagramService($acc))->getMedia(12));
        }
        return view('instagram.ads', compact('accounts', 'boostMedia'));
    }

    /** Boost an existing IG post — builds a paused engagement ad via the Marketing API. */
    public function boostPost(Request $request)
    {
        $d = $request->validate([
            'instagram_account_id' => 'required|integer',
            'media_id'             => 'required|string|max:64',
            'daily_budget'         => 'required|numeric|min:1',
            'days'                 => 'required|integer|min:1|max:30',
        ]);
        if (!$acc = $this->igAccount($request)) return back()->withErrors(['instagram' => 'Account not found.']);

        // Boosting is the one feature that cannot be shimmed — it drives the
        // host's Meta Ads module (ad account, funding source, Marketing API
        // client), which the standalone product does not ship. Config already
        // turns the `ads` gate off there so the button never renders; this is
        // the server-side half, for a request that arrives anyway.
        if (! InstagramGate::hasCore('meta_ads')) {
            return back()->withErrors(['instagram' => 'Boosting posts needs the Meta Ads module, which this install does not have.']);
        }

        $cfg = \App\Models\WaProviderConfig::query()->where('workspace_id', $this->wsId())
            ->whereIn('provider', ['meta_ads', 'waba'])
            ->orderByRaw("CASE provider WHEN 'meta_ads' THEN 0 ELSE 1 END")
            ->orderByDesc('is_primary')->orderByDesc('id')->first();
        $graph = new \App\Services\MetaGraphClient($cfg);
        if (!$graph->isConfigured()) {
            return back()->withErrors(['instagram' => 'Connect a Meta ad account first at /meta-ads.']);
        }
        if ($acc->ig_user_id) $graph->withInstagramUserId((string) $acc->ig_user_id);

        $res = $graph->boostInstagramMedia($d['media_id'], (float) $d['daily_budget'], (int) $d['days']);
        if (!empty($res['ok'])) {
            return back()->with('status', 'Boost created (paused) — review + activate it in Ads Manager. Ad ID ' . ($res['ad_id'] ?? '') . '.');
        }
        return back()->withErrors(['instagram' => 'Boost failed: ' . ($res['error'] ?? 'unknown')]);
    }

    /** Resolve a workspace-owned connected account from a request field. */
    private function igAccount(Request $request, string $field = 'instagram_account_id'): ?InstagramAccount
    {
        return InstagramAccount::forWorkspace($this->wsId())->where('id', (int) $request->input($field))->first();
    }

    // ───────────── My posts (grid + post modal + comments) ─────────────

    /**
     * The account's published grid, Instagram-style. Media comes live from the
     * Graph API (we never mirror someone's feed into our DB — the CDN URLs
     * expire and the counts go stale within minutes).
     */
    public function posts(Request $request)
    {
        $wsId     = $this->wsId();
        $accounts = InstagramAccount::forWorkspace($wsId)->where('status', 'connected')->orderBy('id')->get();

        $wanted   = (int) $request->query('account');
        $account  = ($wanted ? $accounts->firstWhere('id', $wanted) : null) ?: $accounts->first();
        $tab      = in_array($request->query('tab'), ['reels', 'posts', 'stories'], true) ? $request->query('tab') : 'all';

        $media        = [];
        $stories      = [];
        $storiesError = null;

        if ($tab === 'stories') {
            // Your OWN active stories (last 24h) — the dedicated /stories edge,
            // NOT /media. Not cached: stories are 24h-ephemeral and we want
            // Meta's live error surfaced if the edge/permission is unavailable.
            $raw = [];
            if ($account) {
                $svc = new \App\Services\Instagram\InstagramService($account);
                $raw = $svc->getStories();
                $storiesError = $svc->lastStoriesError;
            }
            foreach ((array) $raw as $s) {
                if (empty($s['id'])) continue;
                $isVideo = strtoupper((string) ($s['media_type'] ?? '')) === 'VIDEO';
                $stories[] = [
                    'id'        => (string) $s['id'],
                    'type'      => $isVideo ? 'video' : 'image',
                    'media'     => (string) ($s['media_url'] ?? ''),
                    'thumb'     => (string) ($s['thumbnail_url'] ?? (!$isVideo ? ($s['media_url'] ?? '') : '')),
                    'permalink' => (string) ($s['permalink'] ?? ''),
                    'ts'        => (string) ($s['timestamp'] ?? ''),
                ];
            }
        } else {
            $media = $account ? (new \App\Services\Instagram\InstagramService($account))->getMedia(50) : [];
            // REELS and VIDEO are separate media_types; the grid's Reels tab means
            // "things that play", so both belong there.
            $media = array_values(array_filter($media, function ($m) use ($tab) {
                $t = strtoupper((string) ($m['media_type'] ?? ''));
                if ($tab === 'reels') return in_array($t, ['VIDEO', 'REELS'], true);
                if ($tab === 'posts') return in_array($t, ['IMAGE', 'CAROUSEL_ALBUM'], true);
                return true;
            }));
        }

        return view('instagram.posts', [
            'accounts'          => $accounts,
            'account'           => $account,
            'selectedAccountId' => (int) ($account->id ?? 0),
            'tab'               => $tab,
            'media'             => $media,
            'stories'           => $stories,
            'storiesError'      => $storiesError,
        ]);
    }

    /** GET — comments on one of our posts, for the post modal. */
    public function apiPostComments(Request $request): JsonResponse
    {
        $account = $this->igAccount($request, 'account_id');
        $mediaId = trim((string) $request->query('media_id'));
        if (!$account || $mediaId === '') return response()->json(['ok' => false, 'error' => 'bad_request'], 400);

        try {
            return response()->json([
                'ok'       => true,
                'comments' => (new \App\Services\Instagram\InstagramService($account))->getComments($mediaId, 50),
            ]);
        } catch (\Throwable $e) {
            // Hand Meta's own reason to the modal — an empty list would read as
            // "no comments" and hide a token/permission problem.
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 502);
        }
    }

    /**
     * Post a comment on our own media, or reply UNDER an existing comment.
     *
     * These are two different Graph edges and picking the wrong one is a hard
     * rejection, so the caller says which by field:
     *   comment_id → POST /{comment-id}/replies  (threaded, like tapping Reply)
     *   media_id   → POST /{media-id}/comments   (top-level comment)
     * There is no /{media-id}/replies edge — a media id sent as `comment_id`
     * is always refused by Meta.
     */
    public function apiPostReply(Request $request): JsonResponse
    {
        $data = $request->validate([
            'account_id' => 'required|integer',
            'comment_id' => 'required_without:media_id|nullable|string|max:120',
            'media_id'   => 'required_without:comment_id|nullable|string|max:120',
            'text'       => 'required|string|max:2200',
        ]);
        $account = $this->igAccount($request, 'account_id');
        if (!$account) return response()->json(['ok' => false, 'error' => 'no_account'], 400);

        $svc = new \App\Services\Instagram\InstagramService($account);
        $r   = !empty($data['comment_id'])
            ? $svc->replyComment($data['comment_id'], $data['text'])
            : $svc->commentOnMedia($data['media_id'], $data['text']);

        // An id means Meta accepted it. On refusal show Meta's OWN reason —
        // the service returns `error` as a plain string.
        return response()->json(empty($r['id'])
            ? ['ok' => false, 'error' => (string) ($r['error'] ?? __('Instagram rejected the comment.'))]
            : ['ok' => true, 'id' => $r['id']]);
    }

    /** Hide / unhide a comment (Instagram has no "like a comment" API — see the view). */
    public function apiPostHideComment(Request $request): JsonResponse
    {
        $data = $request->validate([
            'account_id' => 'required|integer',
            'comment_id' => 'required|string|max:120',
            'hide'       => 'required|boolean',
        ]);
        $account = $this->igAccount($request, 'account_id');
        if (!$account) return response()->json(['ok' => false, 'error' => 'no_account'], 400);

        (new \App\Services\Instagram\InstagramService($account))->hideComment($data['comment_id'], (bool) $data['hide']);
        return response()->json(['ok' => true]);
    }

    // ───────────── Attributes (Instagram-scoped view of the shared table) ─────────────

    /**
     * Same attributes the WhatsApp side uses — one table, one source of truth.
     * Merge tags typed here resolve identically in IG templates/automations, so
     * a second store would only create two half-truths.
     */
    /**
     * The tools the left rail no longer carries.
     *
     * Purely a view — the card list lives in the Blade, since nothing here needs
     * a query and threading a nav array through the controller would only add a
     * second place for the two to disagree.
     */
    /**
     * Unread summary for the rail dot and the floating new-message pill.
     *
     * There is no read/unread column on instagram_messages — the schema simply
     * never had one — so "unread" is derived: the id of the newest INBOUND
     * message the operator has actually looked at is remembered per session, and
     * anything newer than that counts. Opening the inbox advances the marker,
     * which is what makes the dot clear when you look at it.
     *
     * Inbound only (direction = 'in'): our own replies must never light the dot.
     */
    public function inboxUnreadSummary(Request $request): JsonResponse
    {
        $wsId   = $this->wsId();
        $accIds = InstagramAccount::forWorkspace($wsId)->pluck('id');

        if ($accIds->isEmpty()) {
            return response()->json(['total' => 0, 'max_id' => 0, 'items' => []]);
        }

        $seen  = (int) $request->session()->get(self::INBOX_SEEN_KEY, 0);
        $maxId = (int) \App\Models\InstagramMessage::whereIn('instagram_account_id', $accIds)->max('id');

        $fresh = \App\Models\InstagramMessage::query()
            ->whereIn('instagram_account_id', $accIds)
            ->where('direction', 'in')
            ->where('id', '>', $seen)
            ->orderByDesc('id')
            ->limit(5)
            ->get(['id', 'igsid', 'body', 'created_at']);

        $total = \App\Models\InstagramMessage::query()
            ->whereIn('instagram_account_id', $accIds)
            ->where('direction', 'in')
            ->where('id', '>', $seen)
            ->count();

        // One lookup for the whole batch — a find() inside the map would be five
        // queries per poll, on a timer, on every page.
        $names = \App\Models\InstagramContact::whereIn('igsid', $fresh->pluck('igsid'))
            ->pluck('username', 'igsid');

        return response()->json([
            'total'  => $total,
            'max_id' => $maxId,
            'items'  => $fresh->map(fn ($m) => [
                'id'      => $m->id,
                'name'    => $names[$m->igsid] ?? __('Someone'),
                'preview' => \Illuminate\Support\Str::limit((string) $m->body, 60),
                'at'      => optional($m->created_at)->diffForHumans(),
            ])->values(),
        ]);
    }

    /**
     * Mark everything currently in the inbox as seen.
     *
     * Called by inbox() on render, so simply opening the page clears the dot —
     * the behaviour an operator expects from "I have looked at this".
     */
    private function markInboxSeen(Request $request): void
    {
        $accIds = InstagramAccount::forWorkspace($this->wsId())->pluck('id');
        if ($accIds->isEmpty()) return;

        $maxId = (int) \App\Models\InstagramMessage::whereIn('instagram_account_id', $accIds)->max('id');
        $request->session()->put(self::INBOX_SEEN_KEY, $maxId);
    }

    /** Appearance picker. */
    public function theme()
    {
        return view('instagram.theme');
    }

    /**
     * Language picker as a full PAGE, not a rail flyout. A flyout from the slim
     * rail kept landing off-screen / clipped; a page is the same choice the
     * Theme control already makes, and it just works on every viewport. Tapping
     * a card POSTs to locale.update.
     */
    public function language()
    {
        return view('instagram.language', [
            'langs'   => \App\Support\LocaleSettings::active(),
            'current' => app()->getLocale(),
        ]);
    }

    /**
     * Persist appearance.
     *
     * Written through Laravel's cookie jar, NOT document.cookie: a JS-written
     * cookie is plaintext, EncryptCookies fails to decrypt it on the next
     * request, and the choice silently reverts on every navigation. That is the
     * whole reason this is a POST rather than an in-place toggle.
     *
     * A cookie rather than user columns because the standalone schema has no
     * settings table, and appearance is genuinely per-device.
     */
    public function saveTheme(Request $request)
    {
        if ($request->boolean('reset')) {
            return redirect()->route('instagram.theme')
                ->with('success', __('Appearance reset to defaults.'))
                ->withCookie(\App\Support\InstaflowAppearance::cookie(
                    \App\Support\InstaflowAppearance::DEFAULTS
                ));
        }

        // sanitise() drops anything unknown, so a hand-posted value can never
        // reach the emitted CSS.
        $appearance = \App\Support\InstaflowAppearance::sanitise([
            'theme'  => $request->input('theme'),
            'accent' => $request->input('accent'),
            'font'   => $request->input('font'),
            'size'   => $request->input('size'),
        ]);

        return redirect()->route('instagram.theme')
            ->with('success', __('Appearance saved.'))
            ->withCookie(\App\Support\InstaflowAppearance::cookie($appearance));
    }

    public function more()
    {
        return view('instagram.more');
    }

    public function attributes()
    {
        $wsId = $this->wsId();
        $rows = InstagramGate::contactAttributes($wsId);

        return view('instagram.attributes', [
            'system' => $rows->where('type', 'system')->values(),
            'custom' => $rows->where('type', '!=', 'system')->values(),
        ]);
    }

    // ───────────── Comment moderation ─────────────

    /** Live comment moderation — pick a post, see + act on its comments. */
    public function comments(Request $request)
    {
        $wsId = $this->wsId();
        $accounts = InstagramAccount::forWorkspace($wsId)->where('status', 'connected')->orderBy('id')->get();
        $account  = $accounts->firstWhere('id', (int) $request->query('account')) ?: $accounts->first();
        $media = $account ? (new InstagramService($account))->getMedia(24) : [];
        $selectedMediaId = (string) $request->query('media', '');
        // getComments() throws on a Graph refusal; surface it as a page error
        // rather than a 500 (or a silently empty list).
        $comments = [];
        $commentsError = null;
        if ($account && $selectedMediaId !== '') {
            try {
                $comments = (new InstagramService($account))->getComments($selectedMediaId, 50);
            } catch (\Throwable $e) {
                $commentsError = $e->getMessage();
            }
        }
        return view('instagram.comments', compact('accounts', 'account', 'media', 'selectedMediaId', 'comments', 'commentsError'));
    }

    public function commentReply(Request $request)
    {
        $d = $request->validate(['instagram_account_id' => 'required|integer', 'comment_id' => 'required|string|max:64', 'message' => 'required|string|max:2000']);
        if (!$acc = $this->igAccount($request)) return back()->withErrors(['instagram' => 'Account not found.']);
        $r = (new InstagramService($acc))->replyComment($d['comment_id'], $d['message']);
        return $this->igResult($r, 'Reply posted.');
    }

    public function commentPrivateReply(Request $request)
    {
        $d = $request->validate(['instagram_account_id' => 'required|integer', 'comment_id' => 'required|string|max:64', 'message' => 'required|string|max:1000']);
        if (!$acc = $this->igAccount($request)) return back()->withErrors(['instagram' => 'Account not found.']);
        $r = (new InstagramService($acc))->privateReply($d['comment_id'], $d['message']);
        return $this->igResult($r, 'Private DM sent.');
    }

    public function commentCreate(Request $request)
    {
        $d = $request->validate(['instagram_account_id' => 'required|integer', 'media_id' => 'required|string|max:64', 'message' => 'required|string|max:2000']);
        if (!$acc = $this->igAccount($request)) return back()->withErrors(['instagram' => 'Account not found.']);
        $r = (new InstagramService($acc))->commentOnMedia($d['media_id'], $d['message']);
        return $this->igResult($r, 'Comment posted.');
    }

    public function commentHide(Request $request)
    {
        $d = $request->validate(['instagram_account_id' => 'required|integer', 'comment_id' => 'required|string|max:64', 'hide' => 'nullable|boolean']);
        if (!$acc = $this->igAccount($request)) return back()->withErrors(['instagram' => 'Account not found.']);
        $r = (new InstagramService($acc))->hideComment($d['comment_id'], (bool) ($d['hide'] ?? true));
        return $this->igResult($r, ($d['hide'] ?? true) ? 'Comment hidden.' : 'Comment shown.');
    }

    public function commentDelete(Request $request)
    {
        $d = $request->validate(['instagram_account_id' => 'required|integer', 'comment_id' => 'required|string|max:64']);
        if (!$acc = $this->igAccount($request)) return back()->withErrors(['instagram' => 'Account not found.']);
        $r = (new InstagramService($acc))->deleteComment($d['comment_id']);
        return $this->igResult($r, 'Comment deleted.');
    }

    // ───────────── Discovery (competitor + hashtag) ─────────────

    public function discovery(Request $request)
    {
        $wsId = $this->wsId();
        $accounts = InstagramAccount::forWorkspace($wsId)->where('status', 'connected')->orderBy('id')->get();
        return view('instagram.discovery', compact('accounts'));
    }

    public function discoverySearch(Request $request)
    {
        $d = $request->validate(['instagram_account_id' => 'required|integer', 'username' => 'required|string|max:60']);
        $wsId = $this->wsId();
        $accounts = InstagramAccount::forWorkspace($wsId)->where('status', 'connected')->orderBy('id')->get();
        if (!$acc = $this->igAccount($request)) return back()->withErrors(['instagram' => 'Account not found.']);
        // Entry-point trace — confirms the request reached the controller and
        // shows the account's login_type (the key discovery blocker). If you see
        // NO '[IG-DISCOVERY] request received' line after clicking Look up, the
        // updated code isn't running yet (clear OPcache / wrong server / wrong log).
        \Log::info('[IG-DISCOVERY] request received', [
            'workspace_id' => $wsId,
            'account_id'   => $acc->id,
            'login_type'   => $acc->login_type,
            'graph_host'   => $acc->login_type === 'instagram' ? 'graph.instagram.com' : 'graph.facebook.com',
            'username'     => $d['username'],
        ]);
        // Discovery + hashtag tools are a Facebook Graph feature — an account
        // connected via Instagram Login cannot use them at all. Tell the user
        // exactly that instead of the misleading "no account found".
        if ($acc->login_type === 'instagram') {
            return back()->withErrors([
                'instagram' => 'Discovery needs an Instagram account connected via Facebook Login (a Business/Creator account linked to a Facebook Page). The selected account was connected via Instagram Login, so Meta does not allow Business Discovery on it. Reconnect it through Facebook to enable this.',
            ])->withInput();
        }
        $profile = (new InstagramService($acc))->businessDiscovery($d['username']);
        if (empty($profile)) {
            return back()->withErrors(['instagram' => 'No public Business/Creator account found for @' . ltrim($d['username'], '@') . ' (needs a Facebook-login account with insights access).'])->withInput();
        }
        return view('instagram.discovery', compact('accounts', 'profile'))->with('queried', $d['username']);
    }

    public function hashtagSearch(Request $request)
    {
        $d = $request->validate(['instagram_account_id' => 'required|integer', 'hashtag' => 'required|string|max:100', 'kind' => 'nullable|in:top_media,recent_media']);
        $wsId = $this->wsId();
        $accounts = InstagramAccount::forWorkspace($wsId)->where('status', 'connected')->orderBy('id')->get();
        if (!$acc = $this->igAccount($request)) return back()->withErrors(['instagram' => 'Account not found.']);
        \Log::info('[IG-HASHTAG] request received', [
            'workspace_id' => $wsId,
            'account_id'   => $acc->id,
            'login_type'   => $acc->login_type,
            'hashtag'      => $d['hashtag'],
        ]);
        if ($acc->login_type === 'instagram') {
            return back()->withErrors([
                'instagram' => 'Hashtag search needs an Instagram account connected via Facebook Login (a Business/Creator account linked to a Facebook Page). The selected account was connected via Instagram Login. Reconnect it through Facebook to enable this.',
            ])->withInput();
        }
        $svc = new InstagramService($acc);
        $id = $svc->hashtagId($d['hashtag']);
        if ($id === '') {
            return back()->withErrors(['instagram' => 'Hashtag not found, or the weekly 30-hashtag limit is reached.'])->withInput();
        }
        $hashtagMedia = $svc->hashtagMedia($id, $d['kind'] ?? 'top_media', 24);
        return view('instagram.discovery', compact('accounts', 'hashtagMedia'))
            ->with('queriedTag', ltrim($d['hashtag'], '#'))->with('hashtagKind', $d['kind'] ?? 'top_media');
    }

    // ───────────── Per-post analytics ─────────────

    /** JSON insights for one media (analytics drill-down). */
    public function postInsights(Request $request, string $mediaId)
    {
        if (!$acc = $this->igAccount($request, 'account')) return response()->json(['error' => 'account not found'], 404);
        $data = (new InstagramService($acc))->mediaInsights($mediaId, (string) $request->query('type', ''));
        return response()->json(['ok' => true, 'metrics' => $data]);
    }

    // ───────────── Scheduled-post edit / delete ─────────────

    public function scheduledUpdate(Request $request, int $id)
    {
        $d = $request->validate(['schedule_at' => 'required|date', 'caption' => 'nullable|string|max:2200', 'timezone' => 'nullable|timezone']);
        $post = \App\Models\InstagramScheduledPost::where('workspace_id', $this->wsId())->where('id', $id)->where('status', 'pending')->first();
        if (!$post) return back()->withErrors(['instagram' => 'Only a pending scheduled post can be edited.']);
        // The datetime-local is a wall-clock time in the user's zone — interpret it
        // there, then store in the app zone so the no-cron sweep fires on schedule.
        // (Was stored raw, so a 2 PM IST edit fired at 2 PM UTC = 7:30 PM IST.)
        $tz = $d['timezone'] ?? (setting('default_timezone') ?: config('app.timezone'));
        $when = \Illuminate\Support\Carbon::parse($d['schedule_at'], $tz)->setTimezone(config('app.timezone'));
        if ($when->isPast()) {
            return back()->withErrors(['instagram' => 'Pick a time in the future.']);
        }
        $post->scheduled_at = $when;
        if (array_key_exists('caption', $d)) $post->caption = $d['caption'];
        $post->save();
        return back()->with('status', 'Scheduled post updated.');
    }

    public function scheduledDestroy(int $id)
    {
        $post = \App\Models\InstagramScheduledPost::where('workspace_id', $this->wsId())->where('id', $id)->where('status', 'pending')->first();
        if (!$post) return back()->withErrors(['instagram' => 'Only a pending scheduled post can be removed.']);
        $post->delete();
        return back()->with('status', 'Scheduled post removed.');
    }

    /** Publish a pending scheduled post RIGHT NOW (same path immediate-publish uses). */
    public function scheduledPublishNow(int $id)
    {
        $wsId = $this->wsId();
        $post = \App\Models\InstagramScheduledPost::where('workspace_id', $wsId)->where('id', $id)->where('status', 'pending')->first();
        if (!$post) return back()->withErrors(['instagram' => 'Only a pending scheduled post can be published.']);

        $account = InstagramAccount::forWorkspace($wsId)->where('id', $post->instagram_account_id)->first();
        if (!$account) return back()->withErrors(['instagram' => 'That account is no longer connected.']);

        $data = [
            'caption'    => (string) ($post->caption ?? ''),
            'image_url'  => $post->image_url,
            'video_url'  => $post->video_url,
            'media_urls' => is_array($post->media_urls) ? $post->media_urls : null,
        ];
        // Content protection — watermark at publish time (fail-safe; see helper).
        $data = self::applyWatermark($account, (string) $post->media_type, $data);
        $res = self::publishByType(new \App\Services\Instagram\InstagramService($account), (string) $post->media_type, $data);
        if (empty($res['ok'])) {
            $post->update(['last_error' => mb_substr((string) ($res['error'] ?? 'publish failed'), 0, 500)]);
            return back()->withErrors(['instagram' => 'Publish failed: ' . ($res['error'] ?? 'unknown')]);
        }
        $post->update(['status' => 'published', 'media_id' => (string) ($res['media_id'] ?? ''), 'last_error' => null]);
        return back()->with('status', ucfirst((string) $post->media_type) . ' published to Instagram now.');
    }

    /** Flash a Graph-call result (['ok'=>bool,'error'?]) to the session. */
    private function igResult(array $r, string $okMsg)
    {
        $ok    = (bool) ($r['ok'] ?? false);
        $error = (string) ($r['error'] ?? 'Action failed.');

        // AJAX composer (instagram-inbox.js) expects JSON so it can append
        // the sent bubble inline + surface the real reason (e.g. "24h window
        // closed") without a full page reload.
        if (request()->expectsJson() || request()->boolean('ajax')) {
            return response()->json([
                'ok'      => $ok,
                'message' => $ok ? $okMsg : $error,
                'mid'     => $r['mid'] ?? null,
            ], $ok ? 200 : 422);
        }

        return $ok
            ? back()->with('status', $okMsg)
            : back()->withErrors(['instagram' => $error]);
    }

    public function automations()
    {
        $wsId = $this->wsId();
        $accounts    = InstagramAccount::forWorkspace($wsId)->where('status', 'connected')->orderBy('id')->get();
        $automations = InstagramAutomation::where('workspace_id', $wsId)
            ->whereIn('instagram_account_id', $accounts->pluck('id'))
            ->orderByDesc('id')->get();
        // AI-Training assistants for the AI-agent knowledge-base picker.
        $assistants  = InstagramGate::aiAssistants($wsId);
        // Visual Instagram flows (flow_type=instagram) for the "Run a flow" picker.
        $igFlows     = InstagramGate::flows()->where('workspace_id', $wsId)->where('flow_type', 'instagram')->orderByDesc('id')->get();
        return view('instagram.automations', compact('accounts', 'automations', 'assistants', 'igFlows'));
    }

    /**
     * Save Instagram ice breakers (starter questions shown when someone opens a
     * NEW DM). Pushes to Meta via /messenger_profile and caches what we pushed on
     * the account so the form prefills without an API round-trip. A tapped ice
     * breaker arrives as a postback whose payload routes to keyword/flow rules.
     */
    public function iceBreakersSave(Request $request)
    {
        $wsId = $this->wsId();
        $d = $request->validate([
            'instagram_account_id'  => 'required|integer',
            'questions'             => 'nullable|array|max:4',
            'questions.*.question'  => 'nullable|string|max:80',
            'questions.*.payload'   => 'nullable|string|max:200',
        ]);
        $acc = InstagramAccount::forWorkspace($wsId)->where('id', $d['instagram_account_id'])->first();
        if (!$acc) return back()->withErrors(['instagram' => 'That Instagram account is not in your workspace.']);

        // Drop blank rows; keep order.
        $questions = array_values(array_filter($d['questions'] ?? [], fn ($q) => trim((string) ($q['question'] ?? '')) !== ''));
        $r = (new InstagramService($acc))->setIceBreakers($questions);
        if (empty($r['ok'])) return back()->withErrors(['instagram' => $r['error'] ?? 'Could not save ice breakers.']);

        $meta = (array) $acc->meta_json;
        $meta['ice_breakers'] = $questions;
        $acc->forceFill(['meta_json' => $meta])->save();
        return back()->with('status', $questions ? __('Ice breakers saved.') : __('Ice breakers cleared.'));
    }

    /**
     * Save the persistent menu — the always-open menu in the DM composer. Each
     * item is a postback (payload routes to keyword/flow automations, e.g.
     * "SHOP" launches the product flow) or a web_url. Pushed to Meta via
     * /messenger_profile and cached on the account for the form prefill.
     */
    public function persistentMenuSave(Request $request)
    {
        $wsId = $this->wsId();
        $d = $request->validate([
            'instagram_account_id' => 'required|integer',
            'items'                => 'nullable|array|max:20',
            'items.*.title'        => 'nullable|string|max:30',
            'items.*.type'         => 'nullable|in:postback,web_url',
            'items.*.payload'      => 'nullable|string|max:200',
            'items.*.url'          => 'nullable|string|max:2000',
        ]);
        $acc = InstagramAccount::forWorkspace($wsId)->where('id', $d['instagram_account_id'])->first();
        if (!$acc) return back()->withErrors(['instagram' => 'That Instagram account is not in your workspace.']);

        $items = array_values(array_filter($d['items'] ?? [], fn ($i) => trim((string) ($i['title'] ?? '')) !== ''));
        $r = (new InstagramService($acc))->setPersistentMenu($items);
        if (empty($r['ok'])) return back()->withErrors(['instagram' => $r['error'] ?? 'Could not save the menu.']);

        $meta = (array) $acc->meta_json;
        $meta['persistent_menu'] = $items;
        $acc->forceFill(['meta_json' => $meta])->save();
        return back()->with('status', $items ? __('Persistent menu saved.') : __('Persistent menu cleared.'));
    }

    /**
     * FAQ builder (ManyChat parity) — one screen of question/answer pairs.
     * Each pair becomes BOTH an ice-breaker (the tappable question) AND a
     * dm_keyword automation that replies with the answer, so tapping the
     * suggested question (or typing it) auto-answers. Re-saving replaces the
     * previously-generated FAQ set for the account.
     */
    public function faqSave(Request $request)
    {
        $wsId = $this->wsId();
        $d = $request->validate([
            'instagram_account_id' => 'required|integer',
            'faq'                  => 'nullable|array|max:4',
            'faq.*.q'              => 'nullable|string|max:80',
            'faq.*.a'              => 'nullable|string|max:1000',
        ]);
        $acc = InstagramAccount::forWorkspace($wsId)->where('id', $d['instagram_account_id'])->first();
        if (!$acc) return back()->withErrors(['instagram' => 'That Instagram account is not in your workspace.']);

        // Wipe the FAQ-generated rules for this account (identified by meta flag).
        foreach (InstagramAutomation::where('instagram_account_id', $acc->id)->get() as $r) {
            if (data_get($r->meta_json, 'faq') === true) $r->delete();
        }

        $pairs = array_values(array_filter($d['faq'] ?? [], fn ($p) =>
            trim((string) ($p['q'] ?? '')) !== '' && trim((string) ($p['a'] ?? '')) !== ''));

        $iceBreakers = [];
        foreach ($pairs as $i => $p) {
            $q = trim((string) $p['q']);
            InstagramAutomation::create([
                'workspace_id'         => $wsId,
                'instagram_account_id' => $acc->id,
                'type'                 => 'dm_keyword',
                'name'                 => 'FAQ: ' . \Illuminate\Support\Str::limit($q, 40),
                'trigger_keyword'      => $q,
                'match_mode'           => 'contains',
                'dm_message'           => trim((string) $p['a']),
                'is_active'            => true,
                'meta_json'            => ['faq' => true],
            ]);
            $iceBreakers[] = ['question' => $q, 'payload' => 'FAQ_' . ($i + 1)];
        }

        // Surface the questions as tappable ice breakers (Meta caps at 4).
        (new InstagramService($acc))->setIceBreakers($iceBreakers);
        $meta = (array) $acc->meta_json;
        $meta['ice_breakers'] = $iceBreakers;
        $acc->forceFill(['meta_json' => $meta])->save();

        return back()->with('status', $pairs ? __('FAQ saved — questions are now suggested in the DM and answer automatically.') : __('FAQ cleared.'));
    }

    /**
     * Capture the person in an inbox conversation as a lead + pipeline deal —
     * the WaDesk-style "complete the deal while you chat" panel. Reuses the same
     * InstagramLeadService the DM lead-flow uses, so a manual capture and an
     * automated one land in exactly the same place (one deal per conversation).
     */
    public function captureLead(Request $request)
    {
        $wsId = $this->wsId();
        $d = $request->validate([
            'instagram_account_id' => 'required|integer',
            'igsid'                => 'required|string|max:64',
            'full_name'            => 'nullable|string|max:191',
            'email'                => 'nullable|string|max:191',
            'phone'                => 'nullable|string|max:64',
            'notes'                => 'nullable|string|max:2000',
            'create_deal'          => 'nullable|boolean',
        ]);
        $acc = InstagramAccount::where('user_id', (int) auth()->id())->where('id', $d['instagram_account_id'])->first();
        if (!$acc) return back()->withErrors(['instagram' => 'That Instagram account is not in your workspace.']);

        if (trim(($d['full_name'] ?? '') . ($d['email'] ?? '') . ($d['phone'] ?? '')) === '') {
            return back()->withErrors(['instagram' => 'Add at least a name, email, or phone to save the lead.']);
        }

        $lead = app(\App\Services\Instagram\InstagramLeadService::class)->capture($acc, $d['igsid'], [
            'full_name' => $d['full_name'] ?? '',
            'email'     => $d['email'] ?? '',
            'phone'     => $d['phone'] ?? '',
            'notes'     => $d['notes'] ?? '',
        ], (bool) ($d['create_deal'] ?? true));

        if (!$lead) return back()->withErrors(['instagram' => 'Could not save the lead.']);
        return back()->with('status', __('Lead saved to your pipeline.'));
    }

    public function automationStore(Request $request)
    {
        $wsId = $this->wsId();
        $data = $request->validate([
            'instagram_account_id' => 'required|integer',
            'type'                 => 'required|in:dm_keyword,comment_to_dm,ai_agent,flow,story_reply,story_mention,mention',
            'name'                 => 'nullable|string|max:191',
            'trigger_keyword'      => 'nullable|string|max:500',
            'match_mode'           => 'nullable|in:contains,exact,any',
            'post_id'              => 'nullable|string|max:64',
            'public_reply'         => 'nullable|string|max:500',
            'dm_message'           => 'required_unless:type,flow,mention|nullable|string|max:2000',
            'ai_assistant_id'      => 'nullable|integer',
            'ai_model'             => 'nullable|string|max:120',
            'flow_id'              => 'nullable|integer',
        ]);

        // Flow automations must point at a flow_type=instagram flow this user
        // owns. Standalone builder flows save with workspace_id = NULL and a
        // user_id, so scope by user_id — a workspace_id filter would never match.
        if ($data['type'] === 'flow') {
            $ok = InstagramGate::flows()->where('user_id', (int) auth()->id())->where('flow_type', 'instagram')->where('id', (int) ($data['flow_id'] ?? 0))->exists();
            if (!$ok) return back()->withErrors(['instagram' => 'Pick an Instagram flow to run.']);
        }

        // Ownership check — the account must belong to this user.
        $owns = InstagramAccount::where('user_id', (int) auth()->id())->where('id', $data['instagram_account_id'])->exists();
        if (!$owns) return back()->withErrors(['instagram' => 'That Instagram account is not in your workspace.']);

        // Plan cap on automation rules (no-op unless enforcement is switched on;
        // the gate returns unlimited otherwise and never caps admins).
        $usedAutomations = (int) InstagramAutomation::whereIn('instagram_account_id', InstagramAccount::forWorkspace($wsId)->pluck('id'))->count();
        if (InstagramGate::exceeded('instagram_automations', $usedAutomations)) {
            return back()->withErrors(['instagram' => 'Your current plan allows ' . InstagramGate::limit('instagram_automations') . ' automation(s). Upgrade your plan to add more.']);
        }

        // AI agent → stash model + knowledge-base assistant in meta_json.
        $meta = [];
        if ($data['type'] === 'ai_agent') {
            $meta = array_filter([
                'assistant_id' => (int) ($data['ai_assistant_id'] ?? 0) ?: null,
                'model'        => $data['ai_model'] ?? null,
            ]);
        }
        // Rotating reply variations (ManyChat parity) — one per line.
        $meta = array_merge($meta, $this->automationVariants($request));

        InstagramAutomation::create([
            'workspace_id'         => $wsId,
            'instagram_account_id' => (int) $data['instagram_account_id'],
            'type'                 => $data['type'],
            'name'                 => $data['name'] ?? null,
            'trigger_keyword'      => $data['trigger_keyword'] ?? null,
            'match_mode'           => $data['type'] === 'ai_agent' ? 'any' : ($data['match_mode'] ?? 'contains'),
            'post_id'              => $data['post_id'] ?? null,
            'public_reply'         => $data['public_reply'] ?? null,
            'dm_message'           => $data['dm_message'] ?? '',
            'flow_id'              => $data['type'] === 'flow' ? (int) ($data['flow_id'] ?? 0) : null,
            'is_active'            => true,
            'meta_json'            => $meta ?: null,
        ]);
        return back()->with('status', 'Automation created.');
    }

    /**
     * Parse the "one per line" rotating-variation textareas into capped arrays
     * for meta_json (ManyChat parity: comment public replies rotate up to 3,
     * story-mention/DM replies up to 6, picked at random per fire).
     */
    private function automationVariants(Request $request): array
    {
        $lines = fn ($v, int $cap) => array_slice(array_values(array_filter(
            array_map('trim', preg_split('/\r\n|\r|\n/', (string) $v)), fn ($s) => $s !== ''
        )), 0, $cap);
        return array_filter([
            'public_reply_variants' => $lines($request->input('public_reply_variants'), 3),
            'dm_variants'           => $lines($request->input('dm_variants'), 6),
        ]);
    }

    /** Update an existing automation (full CRUD edit). Same fields as store. */
    public function automationUpdate(Request $request, int $id)
    {
        $wsId = $this->wsId();
        $a = InstagramAutomation::where('workspace_id', $wsId)->where('id', $id)->firstOrFail();
        $data = $request->validate([
            'instagram_account_id' => 'required|integer',
            'type'                 => 'required|in:dm_keyword,comment_to_dm,ai_agent,flow,story_reply,story_mention,mention',
            'name'                 => 'nullable|string|max:191',
            'trigger_keyword'      => 'nullable|string|max:500',
            'match_mode'           => 'nullable|in:contains,exact,any',
            'post_id'              => 'nullable|string|max:64',
            'public_reply'         => 'nullable|string|max:500',
            'dm_message'           => 'required_unless:type,flow,mention|nullable|string|max:2000',
            'ai_assistant_id'      => 'nullable|integer',
            'ai_model'             => 'nullable|string|max:120',
            'flow_id'              => 'nullable|integer',
        ]);
        if ($data['type'] === 'flow') {
            // Same as automationStore: builder flows carry user_id, not workspace_id.
            $ok = InstagramGate::flows()->where('user_id', (int) auth()->id())->where('flow_type', 'instagram')->where('id', (int) ($data['flow_id'] ?? 0))->exists();
            if (!$ok) return back()->withErrors(['instagram' => 'Pick an Instagram flow to run.']);
        }
        if (!InstagramAccount::where('user_id', (int) auth()->id())->where('id', $data['instagram_account_id'])->exists()) {
            return back()->withErrors(['instagram' => 'That Instagram account is not in your workspace.']);
        }
        $meta = $data['type'] === 'ai_agent'
            ? array_filter(['assistant_id' => (int) ($data['ai_assistant_id'] ?? 0) ?: null, 'model' => $data['ai_model'] ?? null])
            : [];
        $meta = array_merge($meta, $this->automationVariants($request)) ?: null;
        $a->update([
            'instagram_account_id' => (int) $data['instagram_account_id'],
            'type'                 => $data['type'],
            'name'                 => $data['name'] ?? null,
            'trigger_keyword'      => $data['trigger_keyword'] ?? null,
            'match_mode'           => $data['type'] === 'ai_agent' ? 'any' : ($data['match_mode'] ?? 'contains'),
            'post_id'              => $data['post_id'] ?? null,
            'public_reply'         => $data['public_reply'] ?? null,
            'dm_message'           => $data['dm_message'] ?? '',
            'flow_id'              => $data['type'] === 'flow' ? (int) ($data['flow_id'] ?? 0) : null,
            'meta_json'            => $meta,
        ]);
        return back()->with('status', 'Automation updated.');
    }

    /** Pickers shared by the create + edit automation wizards. */
    private function automationFormData(): array
    {
        $wsId = $this->wsId();
        return [
            'accounts'   => InstagramAccount::forWorkspace($wsId)->where('status', 'connected')->orderBy('id')->get(),
            'assistants' => InstagramGate::aiAssistants($wsId),
            'igFlows'    => InstagramGate::flows()->where('workspace_id', $wsId)->where('flow_type', 'instagram')->orderByDesc('id')->get(),
        ];
    }

    /** Multi-step CREATE wizard page (separate page, auto-reply style). */
    public function automationsCreate(Request $request)
    {
        $recipes = self::automationRecipes();
        $prefill = $recipes[$request->query('recipe')]['fields'] ?? [];
        return view('instagram.automations.create', array_merge($this->automationFormData(), [
            'automation' => null,
            'prefill'    => $prefill,
            'recipes'    => $recipes,
        ]));
    }

    /**
     * Pre-built automation "recipes" (ManyChat template-gallery parity). Each
     * one pre-fills the create wizard via ?recipe=<key>. Fields map 1:1 to the
     * store() inputs, so a recipe is just a set of sensible defaults.
     */
    public static function automationRecipes(): array
    {
        return [
            'comment_link'  => ['label' => 'Auto-DM a link from comments', 'desc' => 'Someone comments a keyword → public reply + a DM with your link.',
                'fields' => ['type' => 'comment_to_dm', 'name' => 'Comment → DM link', 'trigger_keyword' => 'link, price', 'match_mode' => 'contains', 'public_reply' => 'Just sent you a DM! 💜', 'dm_message' => "Here's the link you asked for 👉 https://"]],
            'welcome_dm'    => ['label' => 'Welcome every new DM', 'desc' => 'Reply instantly to anyone who messages you first.',
                'fields' => ['type' => 'dm_keyword', 'name' => 'Welcome message', 'match_mode' => 'any', 'dm_message' => "Hey {first_name}! 💜 Thanks for reaching out — how can I help?"]],
            'story_mention' => ['label' => 'Thank story mentions', 'desc' => 'Auto-DM anyone who @-mentions you in their story.',
                'fields' => ['type' => 'story_mention', 'name' => 'Story mention thanks', 'dm_message' => "Thanks so much for the mention! 💜 Really appreciate you."]],
            'price_keyword' => ['label' => 'Answer "price" in DMs', 'desc' => 'Reply with your pricing whenever someone asks.',
                'fields' => ['type' => 'dm_keyword', 'name' => 'Pricing reply', 'trigger_keyword' => 'price, cost, how much', 'match_mode' => 'contains', 'dm_message' => "Our plans start at \$9/mo — full pricing here 👉 https://"]],
            'grow_comments' => ['label' => 'Grow from comments', 'desc' => 'Reply publicly + DM when people comment 🔥 on a post.',
                'fields' => ['type' => 'comment_to_dm', 'name' => 'Grow from comments', 'trigger_keyword' => '🔥', 'match_mode' => 'contains', 'public_reply' => 'Thanks! Check your DMs 💜', 'dm_message' => "Loved your comment! Here's what you're after 👉 https://"]],
            'story_reply'   => ['label' => 'Reply to story replies', 'desc' => 'Auto-answer people who reply to your story.',
                'fields' => ['type' => 'story_reply', 'name' => 'Story reply', 'match_mode' => 'any', 'dm_message' => "Thanks for replying to my story! 💜"]],
        ];
    }

    /** Multi-step EDIT wizard page — same wizard, pre-filled. */
    public function automationEdit(int $id)
    {
        $automation = InstagramAutomation::where('workspace_id', $this->wsId())->where('id', $id)->firstOrFail();
        return view('instagram.automations.edit', array_merge($this->automationFormData(), ['automation' => $automation]));
    }

    /** Automations analytics page — fired volume, per-type + per-rule breakdown. */
    public function automationsAnalytics()
    {
        $wsId = $this->wsId();
        $automations = InstagramAutomation::where('workspace_id', $wsId)
            ->whereIn('instagram_account_id', InstagramAccount::forWorkspace($wsId)->pluck('id'))
            ->get();
        $totalFired  = (int) $automations->sum('fired_count');
        $activeCount = $automations->where('is_active', true)->count();
        $byType = $automations->groupBy('type')->map(fn ($g) => [
            'count' => $g->count(),
            'fired' => (int) $g->sum('fired_count'),
            'active' => $g->where('is_active', true)->count(),
        ]);
        $top = $automations->sortByDesc('fired_count')->take(10)->values();
        return view('instagram.automations.analytics', compact('automations', 'totalFired', 'activeCount', 'byType', 'top'));
    }

    /** DM inbox — thread list + (optionally) one open thread. */
    /**
     * GET /instagram/message-history — a flat, filterable log of every DM (in +
     * out) across the workspace's connected accounts. Complements the threaded
     * inbox with a searchable, paginated audit-style view.
     */
    public function messageHistory(Request $request)
    {
        // Instaflow standalone scopes by the signed-in USER — resolve the user's
        // OWN Instagram accounts, then reach every message/contact through their
        // instagram_account_id. No workspace filter anywhere in this method.
        $userId   = (int) auth()->id();
        $accounts = InstagramAccount::where('user_id', $userId)->orderBy('id')->get();
        $accIds   = $accounts->pluck('id')->all();

        $selectedAccountId = (int) $request->query('account', 0);
        if ($selectedAccountId && !in_array($selectedAccountId, $accIds, true)) $selectedAccountId = 0;
        $scopeIds  = $selectedAccountId ? [$selectedAccountId] : $accIds;
        $direction = in_array($request->query('direction'), ['in', 'out'], true) ? $request->query('direction') : '';
        $q         = trim((string) $request->query('q', ''));

        $messages = collect();
        $total    = 0;
        if ($scopeIds) {
            $base = \App\Models\InstagramMessage::whereIn('instagram_account_id', $scopeIds)
                ->when($direction, fn ($qq) => $qq->where('direction', $direction))
                ->when($q !== '', fn ($qq) => $qq->where('body', 'like', '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%'));
            $total    = (clone $base)->count();
            $messages = $base->orderByDesc(\Illuminate\Support\Facades\DB::raw('COALESCE(sent_at, created_at)'))
                ->paginate(30)->withQueryString();

            // Resolve contact display names for the rows on this page (one query),
            // scoped through the user's own accounts — NOT a workspace column.
            $igsids   = $messages->pluck('igsid')->filter()->unique()->all();
            $contacts = $igsids
                ? \App\Models\InstagramContact::whereIn('instagram_account_id', $scopeIds)->whereIn('igsid', $igsids)->get()->keyBy('igsid')
                : collect();
            $messages->getCollection()->transform(function ($m) use ($contacts, $accounts) {
                $c = $contacts->get($m->igsid);
                $m->contact_name   = $c ? ($c->name ?: $c->username ?: $m->igsid) : $m->igsid;
                $m->contact_handle = $c && $c->username ? '@' . ltrim($c->username, '@') : null;
                $m->account_name   = optional($accounts->firstWhere('id', $m->instagram_account_id))->username;
                return $m;
            });
        }

        return view('instagram.message-history', [
            'accounts'          => $accounts,
            'messages'          => $messages,
            'total'             => $total,
            'selectedAccountId' => $selectedAccountId,
            'direction'         => $direction,
            'q'                 => $q,
        ]);
    }

    public function inbox(Request $request)
    {
        // Looking at the inbox IS reading it — advance the marker so the rail
        // dot and the pill clear the moment this page renders.
        $this->markInboxSeen($request);

        $wsId     = $this->wsId();
        $accounts = InstagramAccount::forWorkspace($wsId)->orderBy('id')->get();
        $accIds   = $accounts->pluck('id')->all();

        // Account switcher — ?account=<id> narrows the thread list to one
        // connected account (0 = All accounts). Guard against ids from another
        // workspace.
        $selectedAccountId = (int) $request->query('account', 0);
        if ($selectedAccountId && !in_array($selectedAccountId, $accIds, true)) $selectedAccountId = 0;
        $scopeIds = $selectedAccountId ? [$selectedAccountId] : $accIds;

        // Latest message per (account, igsid) thread. The rail's Received /
        // Auto-sent links pass ?status=in|out — honour it (was ignored, so the
        // filters did nothing). 'All' (no status) shows every thread.
        $threads = collect();
        if ($scopeIds) {
            $status = (string) $request->query('status', '');
            // Rank on Instagram's clock (sent_at), never on id/created_at: the
            // inbox backfills over the Graph API, so insert order is the API's
            // order — a new DM would not reliably reach the top. Legacy rows
            // predate sent_at, hence the COALESCE fallback.
            $threads = \App\Models\InstagramMessage::whereIn('instagram_account_id', $scopeIds)
                ->when(in_array($status, ['in', 'out'], true), fn ($q) => $q->where('direction', $status))
                ->orderByDesc(\Illuminate\Support\Facades\DB::raw('COALESCE(sent_at, created_at)'))->limit(400)->get()
                ->groupBy(fn ($m) => $m->instagram_account_id . ':' . $m->igsid)
                ->map(fn ($msgs) => $msgs->first())   // first = newest (desc order)
                ->values()->sortByDesc(fn ($m) => $m->sent_at ?? $m->created_at)->values();
        }

        // Phase 6 — funnel stage chip per conversation. Orders outrank leads
        // (further down the funnel); only the visible threads are looked up.
        $stageByIgsid = [];
        $stageFilter  = (string) $request->query('stage', '');
        if ($threads->isNotEmpty()) {
            $igsids = $threads->pluck('igsid')->filter()->unique()->values()->all();
            $orderChip = [
                'placed'     => ['key' => 'ordered',    'label' => __('Ordered'),    'color' => '#0EA5E9'],
                'paid'       => ['key' => 'paid',       'label' => __('Paid'),       'color' => '#F59E0B'],
                'dispatched' => ['key' => 'dispatched', 'label' => __('Dispatched'), 'color' => '#16A34A'],
                'cancelled'  => ['key' => 'cancelled',  'label' => __('Cancelled'),  'color' => '#DC2626'],
            ];
            foreach (\App\Models\InstagramOrder::forWorkspace($wsId)->whereIn('igsid', $igsids)
                ->where('status', '!=', 'draft')->orderByDesc('id')->get(['igsid', 'status']) as $o) {
                if ($o->igsid && !isset($stageByIgsid[$o->igsid]) && isset($orderChip[$o->status])) {
                    $stageByIgsid[$o->igsid] = $orderChip[$o->status];
                }
            }
            foreach (\App\Models\InstagramLead::forWorkspace($wsId)->whereIn('igsid', $igsids)
                ->orderByDesc('id')->get(['igsid']) as $l) {
                if ($l->igsid && !isset($stageByIgsid[$l->igsid])) {
                    $stageByIgsid[$l->igsid] = ['key' => 'lead', 'label' => __('Lead'), 'color' => '#8B5CF6'];
                }
            }
            if ($stageFilter !== '') {
                $threads = $threads->filter(fn ($m) => ($stageByIgsid[$m->igsid]['key'] ?? '') === $stageFilter)->values();
            }
        }

        // Sender identities (username/name/avatar) for the visible threads,
        // keyed "account:igsid" so the view can label each thread by WHO
        // messaged instead of our own account handle.
        $contacts = collect();
        if ($accIds) {
            $contacts = \App\Models\InstagramContact::whereIn('instagram_account_id', $scopeIds ?: $accIds)
                ->get()->keyBy(fn ($c) => $c->instagram_account_id . ':' . $c->igsid);
        }

        // Per-conversation inbox controls (mute / archive) — local flags read off
        // the contacts we just loaded. Archived threads drop out of the main list;
        // ?view=archived surfaces them. Muted threads stay but show a mute icon.
        $view      = (string) $request->query('view', '') === 'archived' ? 'archived' : '';
        $mutedKeys = $contacts->filter(fn ($c) => $c->muted_at)->keys()->flip();
        // Archived count excludes deleted (hidden) threads.
        $archivedCount = $contacts->filter(fn ($c) => $c->archived_at && ! $c->hidden_at)->count();
        $threads = $threads->filter(function ($m) use ($contacts, $view) {
            $c = $contacts->get($m->instagram_account_id . ':' . $m->igsid);
            if ($c && $c->hidden_at) return false;                 // deleted → never show anywhere
            $archived = $c && $c->archived_at;
            return $view === 'archived' ? (bool) $archived : ! $archived;
        })->values();

        // Open thread. Load the LATEST batch chronologically; older history is
        // pulled in on scroll-up via inboxOlder(). ($oldestId + $hasMore drive it.)
        $openKey  = (string) $request->query('thread', '');
        $messages = collect();
        $openIgsid = ''; $openAccountId = 0;
        $oldestId = 0; $hasMore = false;
        $INIT_MESSAGES = 40;
        if ($openKey && str_contains($openKey, ':')) {
            [$openAccountId, $openIgsid] = array_map('strval', explode(':', $openKey, 2));
            $openAccountId = (int) $openAccountId;
            if (in_array($openAccountId, $accIds, true)) {
                $mbase = \App\Models\InstagramMessage::where('instagram_account_id', $openAccountId)->where('igsid', $openIgsid);
                $mtotal = (clone $mbase)->count();
                // Newest N, then flip to chronological for display.
                $messages = (clone $mbase)->orderByDesc('id')->limit($INIT_MESSAGES)->get()->sortBy('id')->values();
                $oldestId = (int) (optional($messages->first())->id ?? 0);
                $hasMore  = $mtotal > $messages->count();

                // Mark this thread READ — advance the read cursor to the newest
                // message id so the unread dot clears on open. Also reflect it on
                // the already-loaded contacts collection so this row renders read.
                $newestId = (int) ((clone $mbase)->max('id') ?? 0);
                if ($newestId > 0) {
                    \App\Models\InstagramContact::updateOrCreate(
                        ['instagram_account_id' => $openAccountId, 'igsid' => $openIgsid],
                        ['last_read_id' => $newestId, 'workspace_id' => $wsId]
                    );
                    if ($oc = $contacts->get($openAccountId . ':' . $openIgsid)) {
                        $oc->last_read_id = $newestId;
                    }
                }

                // Send a read receipt (best-effort, once / minute / thread).
                if ($messages->isNotEmpty() && \Illuminate\Support\Facades\Cache::add('ig_seen_' . $openAccountId . '_' . $openIgsid, 1, 60)) {
                    if ($openAcc = $accounts->firstWhere('id', $openAccountId)) {
                        try { (new InstagramService($openAcc))->markSeen($openIgsid); } catch (\Throwable $e) {}
                    }
                }
            }
        }
        // The lead already captured for the open conversation (prefills the
        // inbox "Lead / Deal" panel so an agent can complete it while chatting).
        $openLead = ($openIgsid && $openAccountId)
            ? \App\Models\InstagramLead::forWorkspace($wsId)
                ->where('instagram_account_id', $openAccountId)->where('igsid', $openIgsid)
                ->latest('id')->first()
            : null;

        return view('instagram.inbox', compact('accounts', 'threads', 'messages', 'openKey', 'openIgsid', 'openAccountId', 'contacts', 'selectedAccountId', 'stageByIgsid', 'stageFilter', 'openLead', 'mutedKeys', 'view', 'archivedCount', 'oldestId', 'hasMore'));
    }

    /**
     * Load OLDER messages for the open thread — the scroll-up "load more" that
     * lets an agent read the full history (the initial page shows only the
     * latest batch). Returns the same bubble markup the inbox renders, so the
     * front-end just prepends the HTML.
     *
     *   GET /instagram/inbox/older?account=5&igsid=...&before_id=1234
     */
    public function inboxOlder(Request $request)
    {
        $wsId  = $this->wsId();
        $accId = (int) $request->query('account', 0);
        $igsid = (string) $request->query('igsid', '');
        $before = (int) $request->query('before_id', 0);

        $accIds = InstagramAccount::forWorkspace($wsId)->pluck('id')->map(fn ($i) => (int) $i)->all();
        if (! in_array($accId, $accIds, true) || $igsid === '' || $before <= 0) {
            return response()->json(['ok' => false, 'html' => '', 'has_more' => false, 'oldest_id' => 0]);
        }

        $BATCH = 30;
        $base  = \App\Models\InstagramMessage::where('instagram_account_id', $accId)
            ->where('igsid', $igsid)->where('id', '<', $before);

        $remaining = (clone $base)->count();
        // The batch just older than what's loaded, flipped to chronological.
        $older = (clone $base)->orderByDesc('id')->limit($BATCH)->get()->sortBy('id')->values();

        if ($older->isEmpty()) {
            return response()->json(['ok' => true, 'html' => '', 'has_more' => false, 'oldest_id' => $before]);
        }

        $html = view('instagram.partials.thread-messages', ['messages' => $older])->render();

        return response()->json([
            'ok'        => true,
            'html'      => $html,
            'has_more'  => $remaining > $older->count(),
            'oldest_id' => (int) $older->first()->id,
        ]);
    }

    /**
     * Re-fetch profile pictures for conversations that are missing one. The app
     * grabs each sender's avatar on their first DM, but Instagram only returns a
     * picture for accounts that permit it (business/creator, or users Meta has
     * granted) — so many personal-account senders stay pictureless. This clears
     * the per-contact fetch throttle and re-pulls, filling in anyone Instagram
     * now allows. Batched so one click never times out; click again for more.
     * Does NOT touch last_message_at, so thread order stays put.
     */
    public function inboxRefreshAvatars(Request $request)
    {
        $wsId     = $this->wsId();
        $accounts = InstagramAccount::forWorkspace($wsId)->where('status', 'connected')->get()->keyBy('id');
        if ($accounts->isEmpty()) {
            return back()->with('status', __('No connected accounts to refresh.'));
        }

        $missing = \App\Models\InstagramContact::whereIn('instagram_account_id', $accounts->keys()->all())
            ->where(fn ($q) => $q->whereNull('avatar_url')->orWhere('avatar_url', ''))
            ->whereNull('hidden_at')
            ->orderByDesc('last_message_at')
            ->limit(40)   // ~40 Graph calls/click keeps the request comfortably under the timeout.
            ->get();

        $filled = 0;
        foreach ($missing as $c) {
            $acc = $accounts->get($c->instagram_account_id);
            if (! $acc) continue;

            // Clear the 6-hour fetch throttle so the re-pull actually runs.
            try { \Illuminate\Support\Facades\Cache::forget('ig-profile:' . $acc->id . ':' . $c->igsid); } catch (\Throwable $e) {}

            try {
                $p = (new InstagramService($acc))->getSenderProfile($c->igsid);
                $changed = false;
                if (! empty($p['profile_pic'])) { $c->avatar_url = (string) $p['profile_pic']; $changed = true; $filled++; }
                if (empty($c->username) && ! empty($p['username'])) { $c->username = (string) $p['username']; $changed = true; }
                if (empty($c->name) && ! empty($p['name']))         { $c->name = (string) $p['name']; $changed = true; }
                if ($changed) $c->save();
            } catch (\Throwable $e) {
            }
        }

        $remaining = \App\Models\InstagramContact::whereIn('instagram_account_id', $accounts->keys()->all())
            ->where(fn ($q) => $q->whereNull('avatar_url')->orWhere('avatar_url', ''))
            ->whereNull('hidden_at')->count();

        return back()->with('status', __(':n photo(s) added.', ['n' => $filled])
            . ($remaining > 0 ? ' ' . __(':r still without a photo (Instagram doesn\'t provide one for every user — click again to retry the rest).', ['r' => $remaining]) : ''));
    }

    /** Backfill the inbox from the Graph API (conversations + messages), deduped by mid. */
    public function inboxSync()
    {
        $wsId = $this->wsId();
        $accounts = InstagramAccount::forWorkspace($wsId)->where('status', 'connected')->get();
        $synced = 0;
        foreach ($accounts as $acc) {
            try {
                $svc = new InstagramService($acc);
                $convos = $svc->getConversations(30);

                // CRITICAL: Instagram returns the ACCOUNT itself as a conversation
                // participant using its MESSAGING igsid — which is NOT the same as
                // $acc->ig_user_id (that's an app/content-scoped id). Matching on
                // ig_user_id therefore never recognised the account, so every thread
                // collapsed onto the account's own igsid. The account is the one
                // participant present in EVERY conversation → find it by frequency.
                $freq = [];
                foreach ($convos as $c) {
                    foreach ((array) ($c['participants']['data'] ?? []) as $p) {
                        $id = (string) ($p['id'] ?? '');
                        if ($id !== '') $freq[$id] = ($freq[$id] ?? 0) + 1;
                    }
                }
                arsort($freq);
                $selfIgsid = (string) (array_key_first($freq) ?? '');
                $selfIds   = array_values(array_filter([$selfIgsid, (string) $acc->ig_user_id]));
                // Persist the messaging igsid so inbound webhooks can match this
                // account (webhook entry.id = self_igsid ≠ ig_user_id). One write,
                // only when it changes.
                if ($selfIgsid !== '' && (string) ($acc->meta_json['self_igsid'] ?? '') !== $selfIgsid) {
                    $meta = (array) $acc->meta_json;
                    $meta['self_igsid'] = $selfIgsid;
                    $acc->forceFill(['meta_json' => $meta])->save();
                }
                \Illuminate\Support\Facades\Log::info('[IG-SYNC] conversations pulled', [
                    'account' => $acc->id, 'self_igsid' => $selfIgsid,
                    'count'   => is_countable($convos) ? count($convos) : 0,
                ]);

                foreach ($convos as $c) {
                    // The customer = the first participant that ISN'T the account.
                    $igsid = ''; $uname = '';
                    foreach ((array) ($c['participants']['data'] ?? []) as $p) {
                        $pid = (string) ($p['id'] ?? '');
                        if ($pid !== '' && !in_array($pid, $selfIds, true)) {
                            $igsid = $pid;
                            $uname = (string) ($p['username'] ?? '');   // Meta often gives the handle here
                            break;
                        }
                    }
                    if ($igsid === '') continue;
                    // Store WHO this is, seeding the handle Meta already gave us on
                    // the participant so touchFromInbound() doesn't spend a call on
                    // it. It still fetches the AVATAR when we don't have one (it's
                    // throttled). The old code took a shortcut here and wrote the
                    // row directly whenever a username was present — those rows were
                    // born with avatar_url = NULL and nothing ever filled it, which
                    // is why every thread showed the grey silhouette.
                    try {
                        \App\Models\InstagramContact::touchFromInbound($acc, $igsid, $uname);
                    } catch (\Throwable $e) {}
                    foreach ($svc->getMessages((string) ($c['id'] ?? ''), 25) as $m) {
                        $mid = (string) ($m['id'] ?? '');
                        if ($mid === '') continue;
                        if (\App\Models\InstagramMessage::where('instagram_account_id', $acc->id)->where('mid', $mid)->exists()) continue;
                        // Outbound = the message's sender is the account (any self id).
                        $fromId = (string) ($m['from']['id'] ?? '');
                        $dir = in_array($fromId, $selfIds, true) ? 'out' : 'in';
                        // Backfill media too — the conversation API returns attachments
                        // as attachments.data[].{image_data|video_data|file_url}. Keep
                        // the first url so history media renders like live media.
                        $attType = null; $attUrl = null;
                        $hadAttachment = !empty($m['attachments']['data']);
                        foreach ((array) ($m['attachments']['data'] ?? []) as $att) {
                            $u = (string) ($att['image_data']['url'] ?? $att['video_data']['url'] ?? $att['file_url'] ?? $att['url'] ?? '');
                            if ($u !== '') {
                                $attType = isset($att['video_data']) ? 'video' : (isset($att['image_data']) ? 'image' : 'file');
                                // Copy off Meta's expiring CDN to our own disk (stable URL).
                                $attUrl  = \App\Services\Instagram\InstagramMediaFetcher::localize($u, $attType);
                                break;
                            }
                        }
                        // Shared reels/posts: the read-back API returns the reel's
                        // VIDEO to no one (Meta limitation) — only a permalink under
                        // `shares`. Capture it (or a bare "shared" marker) so the
                        // bubble shows a "Shared a reel" card instead of an empty
                        // bubble. The full inline video only arrives via the message
                        // webhook (see InstagramWebhookController::onDm payload.url).
                        if ($attUrl === null) {
                            $shareLink = (string) ($m['shares']['data'][0]['link'] ?? '');
                            $storyLink = (string) ($m['story']['link'] ?? '');
                            if ($shareLink !== '')      { $attType = 'share'; $attUrl = $shareLink; }
                            elseif (!empty($m['story'])) { $attType = 'story_mention'; $attUrl = $storyLink !== '' ? $storyLink : null; }
                            elseif ($hadAttachment && trim((string) ($m['message'] ?? '')) === '') { $attType = 'share'; }
                        }
                        // Never create a blank bubble: a message read back with no text
                        // and nothing renderable is an unsent/unsupported-empty message.
                        if (trim((string) ($m['message'] ?? '')) === '' && $attType === null) continue;
                        // The read-back walks conversations/messages in the API's
                        // order, so these rows are inserted nowhere near the order
                        // they were sent — carry Meta's ISO8601 `created_time` so
                        // the thread list can rank on Instagram's clock, not ours.
                        \App\Models\InstagramMessage::log($acc, $igsid, $dir, (string) ($m['message'] ?? ''), 'sync', $mid, $attType, $attUrl, null, $m['created_time'] ?? null);
                        $synced++;
                    }
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('[IG-SYNC] ' . $e->getMessage());
            }
        }
        // Background auto-sync (the inbox JS calls this silently every ~45s) wants
        // JSON, not a redirect. The manual "Sync" button still gets the banner.
        if (request()->ajax() || request()->wantsJson()) {
            return response()->json(['ok' => true, 'synced' => $synced]);
        }
        return back()->with('status', "Synced {$synced} new messages from Instagram.");
    }

    /**
     * Ultra-cheap "is there anything new?" poll for the whole thread LIST.
     * Returns the newest instagram_messages id across this workspace's accounts
     * (optionally scoped to ?account=). The inbox JS polls it every few seconds
     * and, when the id grows, refreshes the conversation list — so a new DM pops
     * in live, exactly like the Team Inbox.
     */
    public function inboxListPoll(Request $request)
    {
        $wsId   = $this->wsId();
        $accIds = InstagramAccount::forWorkspace($wsId)->pluck('id');
        $sel    = (int) $request->query('account', 0);
        if ($sel && $accIds->contains($sel)) $accIds = collect([$sel]);

        $maxId = $accIds->isEmpty() ? 0
            : (int) \App\Models\InstagramMessage::whereIn('instagram_account_id', $accIds)->max('id');

        return response()->json(['max_id' => $maxId]);
    }

    /**
     * Lightweight DB poll for the open thread — returns messages newer than
     * `after` for one account+igsid. Cheap (reads our DB, which the webhook
     * keeps live), so the inbox JS can poll it every few seconds like Team Inbox.
     */
    public function inboxPoll(Request $request)
    {
        // Second host for the flow-Wait sweep (primary is the IG webhook).
        // Covers the case where a Wait is pending but no new inbound arrives to
        // tick the webhook — an operator watching the inbox keeps it moving.
        // Same 20s cache gate, and never allowed to break the poll response.
        try {
            if (\Illuminate\Support\Facades\Cache::add('ig_flow_delay_sweep', 1, 20)) {
                \App\Services\Instagram\IgFlowRunner::sweepDue();
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[IG-FLOW] delay sweep failed: ' . $e->getMessage());
        }

        $wsId  = $this->wsId();
        $accId = (int) $request->query('account_id');
        $igsid = (string) $request->query('igsid');
        $after = (int) $request->query('after', 0);

        $acc = InstagramAccount::forWorkspace($wsId)->whereKey($accId)->first();
        if (!$acc || $igsid === '') {
            return response()->json(['messages' => [], 'last_id' => $after]);
        }
        $rows = \App\Models\InstagramMessage::where('instagram_account_id', $accId)
            ->where('igsid', $igsid)
            ->where('id', '>', $after)
            ->orderBy('id')->limit(50)
            ->get(['id', 'direction', 'body', 'attachment_type', 'attachment_url', 'mid', 'reaction', 'pinned_at', 'meta', 'created_at']);

        return response()->json([
            'messages' => $rows->map(fn ($m) => [
                'id'    => (int) $m->id,
                'dir'   => (string) $m->direction,
                'body'  => (string) $m->body,
                'atype' => $m->attachment_type,
                'aurl'  => $m->attachment_url,
                'mid'   => (string) ($m->mid ?? ''),
                'reaction' => (string) ($m->reaction ?? ''),
                'pinned' => (bool) $m->pinned_at,
                'tpl'   => $m->meta,   // interactive payload (quick replies / buttons / carousel)
                'time'  => optional($m->created_at)->format('H:i'),   // 24h fallback (UTC) — JS re-formats to browser-local
                'ts'    => optional($m->created_at)->toIso8601String(),
            ])->values(),
            'last_id'  => (int) ($rows->last()->id ?? $after),
        ]);
    }

    /**
     * Stream a shared reel / video attachment through our own origin so it can
     * play inside the in-app modal <video>. Meta's signed CDN url can't be
     * decoded in an inline <video> or an <iframe> (it renders a dead frame),
     * but proxying the bytes with a video/mp4 content-type plays fine. Scoped to
     * the caller's workspace + restricted to Meta CDN hosts (no open proxy).
     */
    public function inboxMedia(Request $request, int $message)
    {
        // Scope by the workspace's own account ids — the exact set inbox() renders,
        // so any reel visible in the thread resolves here (some legacy rows have a
        // null workspace_id, which a where('workspace_id') scope would 404).
        $wsId   = $this->wsId();
        $accIds = InstagramAccount::forWorkspace($wsId)->pluck('id')->all();
        $msg    = \App\Models\InstagramMessage::whereIn('instagram_account_id', $accIds)->find($message);
        if (!$msg || !$msg->attachment_url) {
            \Log::warning('inboxMedia: message/url missing', ['id' => $message, 'found' => (bool) $msg, 'ws' => $wsId]);
            abort(404);
        }

        $url  = (string) $msg->attachment_url;
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        // The url is our own stored webhook data (not user input), so a loose
        // Meta-host contains-check is enough defence against an open proxy.
        $okHost = false;
        foreach (['cdninstagram', 'fbcdn', 'fbsbx', 'instagram'] as $needle) {
            if (str_contains($host, $needle)) { $okHost = true; break; }
        }
        if (!$okHost || !str_starts_with($url, 'https://')) {
            \Log::warning('inboxMedia: host rejected', ['id' => $message, 'host' => $host]);
            abort(404);
        }

        // A DM-shared reel's url is a PERMALINK (an HTML page), not a video file.
        // Scrape the real .mp4 url out of the reel page so we can play it inline;
        // a url that's already a CDN video is streamed as-is.
        $videoUrl = $url;
        if (str_contains($host, 'instagram.com')) {
            $videoUrl = $this->resolveReelVideoUrl($url);
            if (!$videoUrl) {
                \Log::warning('inboxMedia: no video url scraped', ['id' => $message, 'permalink' => $url]);
                abort(404);   // JS then falls back to the Instagram embed player
            }
        }

        \Log::info('inboxMedia: fetching', ['id' => $message, 'type' => $msg->attachment_type, 'video' => substr($videoUrl, 0, 160)]);

        // Fetch the mp4 server-side WITH a browser User-Agent (Meta's CDN gives
        // header-less clients an error page). Buffer it (reels are short) and relay
        // with the upstream content-type + a real Content-Length so it plays.
        $ch = curl_init($videoUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36',
            CURLOPT_HTTPHEADER     => ['Accept: video/mp4,video/*;q=0.9,*/*;q=0.8'],
        ]);
        $body  = curl_exec($ch);
        $code  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $ctype = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $err   = curl_error($ch);
        curl_close($ch);

        \Log::info('inboxMedia: upstream', ['id' => $message, 'code' => $code, 'ctype' => $ctype, 'bytes' => strlen((string) $body), 'err' => $err]);

        if ($body === false || $body === '' || $code >= 400) abort(404);
        // If we still got HTML (not a media file), the <video> can't use it.
        if ($ctype && str_starts_with($ctype, 'text/')) abort(404);

        $out = $ctype && str_starts_with($ctype, 'video/') ? $ctype
             : ($ctype && str_starts_with($ctype, 'image/') ? $ctype : 'video/mp4');

        return response($body, 200, [
            'Content-Type'   => $out,
            'Content-Length' => (string) strlen($body),
            'Cache-Control'  => 'no-store, no-cache, must-revalidate',
            'Accept-Ranges'  => 'none',
        ]);
    }

    /**
     * Pull the direct .mp4 url out of a public reel/post permalink by fetching the
     * page HTML and matching the video url Instagram embeds in it (og:video first,
     * then the JSON `video_url`). Returns null if the reel is private / login-walled
     * or Instagram no longer exposes it — the caller then falls back to the embed.
     */
    private function resolveReelVideoUrl(string $permalink): ?string
    {
        $unescape = fn ($v) => str_replace(['\\u0026', '\\/', '&amp;'], ['&', '/', '&'], (string) $v);

        // 1) Instagram's web media-info API — returns video_versions for PUBLIC
        //    media WITHOUT login when the web app-id header is present. This is the
        //    reliable path now that the page HTML no longer carries the video url.
        if (preg_match('~instagram\.com/(?:reel|reels|p|tv)/([A-Za-z0-9_-]+)~i', $permalink, $mm)) {
            $mediaId = $this->shortcodeToMediaId($mm[1]);
            \Log::info('resolveReel: trying api', ['code' => $mm[1], 'mediaId' => $mediaId]);
            if ($mediaId !== '') {
                $json = $this->igGet("https://www.instagram.com/api/v1/media/{$mediaId}/info/", [
                    'X-IG-App-ID: 936619743392459',
                    'Accept: application/json',
                    'Referer: https://www.instagram.com/',
                ]);
                if ($json !== '') {
                    $data = json_decode($json, true);
                    $vv = $data['items'][0]['video_versions'][0]['url'] ?? null;
                    if ($vv && str_starts_with($vv, 'http')) {
                        \Log::info('resolveReel: found via api', ['mediaId' => $mediaId, 'video' => substr($vv, 0, 130)]);
                        return $vv;
                    }
                    \Log::info('resolveReel: api no video', ['mediaId' => $mediaId, 'bytes' => strlen($json), 'snip' => substr($json, 0, 180)]);
                }
            }
        }

        // 2) HTML scrape fallback (og:video / video_url) for reels that still expose it.
        $base = rtrim(explode('?', $permalink)[0], '/');
        foreach ([$base . '/embed/captioned/', $base . '/embed/', $base . '/'] as $page) {
            $html = $this->igGet($page);
            if ($html === '') continue;
            foreach ([
                '/property=["\']og:video(?::secure_url)?["\']\s+content=["\']([^"\']+)["\']/i',
                '/"video_url":"([^"]+)"/i',
                '/"video_versions":\[\{[^}]*?"url":"([^"]+)"/i',
                '/"playback_url":"([^"]+)"/i',
            ] as $re) {
                if (preg_match($re, $html, $m)) {
                    $v = $unescape($m[1]);
                    if (str_starts_with($v, 'http')) {
                        \Log::info('resolveReel: found via html', ['page' => $page, 'video' => substr($v, 0, 130)]);
                        return $v;
                    }
                }
            }
            \Log::info('resolveReel: no match', ['page' => $page, 'bytes' => strlen($html)]);
        }
        return null;
    }

    /** GET with a browser UA (+ optional extra headers), returns the body. */
    private function igGet(string $url, array $extraHeaders = []): string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 25,
            CURLOPT_CONNECTTIMEOUT => 12,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36',
            CURLOPT_HTTPHEADER     => array_merge(['Accept-Language: en-US,en;q=0.9'], $extraHeaders),
        ]);
        $out = (string) curl_exec($ch);
        curl_close($ch);
        return $out;
    }

    /**
     * Decode an Instagram shortcode (url-safe base64 alphabet) → numeric media id.
     * Pure-PHP big-integer math on a decimal string (the server has no bcmath/gmp),
     * computing id = id*64 + pos for each char without ever overflowing a PHP int.
     */
    private function shortcodeToMediaId(string $code): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_';
        $id = '0';
        for ($i = 0, $n = strlen($code); $i < $n; $i++) {
            $pos = strpos($alphabet, $code[$i]);
            if ($pos === false) return '';
            // id = id * 64 + pos, digit by digit (least-significant first).
            $carry = $pos; $out = '';
            for ($j = strlen($id) - 1; $j >= 0; $j--) {
                $p = ((int) $id[$j]) * 64 + $carry;
                $out = ($p % 10) . $out;
                $carry = intdiv($p, 10);
            }
            while ($carry > 0) { $out = ($carry % 10) . $out; $carry = intdiv($carry, 10); }
            $id = ltrim($out, '0');
            if ($id === '') $id = '0';
        }
        return $id;
    }

    /**
     * Toggle per-conversation AI handoff (Team-Inbox style). When off, the
     * ai_agent automation stops auto-replying to this sender until turned back on.
     */
    public function inboxAiToggle(Request $request)
    {
        $wsId  = $this->wsId();
        $accId = (int) $request->input('instagram_account_id');
        $igsid = (string) $request->input('igsid');
        $enabled = $request->boolean('enabled');

        $acc = InstagramAccount::forWorkspace($wsId)->whereKey($accId)->first();
        if (!$acc || $igsid === '') return response()->json(['ok' => false], 422);

        \App\Models\InstagramContact::updateOrCreate(
            ['instagram_account_id' => $acc->id, 'igsid' => $igsid],
            ['workspace_id' => $acc->workspace_id, 'ai_enabled' => $enabled]
        );
        return response()->json(['ok' => true, 'ai_enabled' => $enabled]);
    }

    /**
     * Assign a specific AI agent to THIS conversation (Team-Inbox "AI Agent"
     * picker). agent_id = an active ai_agent automation on the account; 0/empty
     * turns AI OFF for the thread. Any positive agent turns AI back ON + pins it.
     */
    public function inboxAssignAgent(Request $request)
    {
        $wsId    = $this->wsId();
        $accId   = (int) $request->input('instagram_account_id');
        $igsid   = (string) $request->input('igsid');
        $agentId = (int) $request->input('agent_id');

        $acc = InstagramAccount::forWorkspace($wsId)->whereKey($accId)->first();
        if (!$acc || $igsid === '') return response()->json(['ok' => false], 422);

        // Validate the agent belongs to this account + is an active ai_agent.
        $agent = null;
        if ($agentId > 0) {
            $agent = \App\Models\InstagramAutomation::where('instagram_account_id', $acc->id)
                ->where('type', 'ai_agent')->whereKey($agentId)->first();
            if (!$agent) return response()->json(['ok' => false, 'error' => 'agent_not_found'], 422);
        }

        \App\Models\InstagramContact::updateOrCreate(
            ['instagram_account_id' => $acc->id, 'igsid' => $igsid],
            [
                'workspace_id' => $acc->workspace_id,
                // Turning a real agent on re-enables AI + pins it; "off" clears both.
                'ai_enabled'   => $agentId > 0,
                'ai_agent_id'  => $agentId > 0 ? $agentId : null,
            ]
        );

        return response()->json([
            'ok'         => true,
            'agent_id'   => $agentId ?: null,
            'agent_name' => $agent?->name ?: null,
            'ai_enabled' => $agentId > 0,
        ]);
    }

    /**
     * Create an AI agent from INSIDE the inbox (Team-Inbox style — no redirect to
     * /instagram/automations). Makes an ai_agent automation for the account.
     */
    public function inboxCreateAgent(Request $request)
    {
        $wsId = $this->wsId();
        $data = $request->validate([
            'instagram_account_id' => 'required|integer',
            'name'                 => 'required|string|max:120',
            'provider'             => 'nullable|string|max:32',
            'model'                => 'nullable|string|max:120',
            'tone'                 => 'nullable|string|max:32',
            'avatar_color'         => 'nullable|string|max:16',
            'system_prompt'        => 'nullable|string|max:4000',   // stored as dm_message
            'max_tokens'           => 'nullable|integer|min:64|max:4096',
            'temperature'          => 'nullable|integer|min:0|max:10',
            'auto_respond'         => 'nullable|boolean',
            'use_saved_replies'    => 'nullable|boolean',
            'handoff_enabled'      => 'nullable|boolean',
        ]);

        $acc = InstagramAccount::forWorkspace($wsId)->whereKey((int) $data['instagram_account_id'])->first();
        if (!$acc) return response()->json(['ok' => false, 'error' => 'account_not_found'], 422);

        $auto = $request->boolean('auto_respond', true);
        // Everything the create modal collects lives in meta_json (aiReply reads
        // model / max_tokens / temperature / tone from here).
        $meta = array_filter([
            'provider'          => $data['provider'] ?? null,
            'model'             => $data['model'] ?? null,
            'tone'              => $data['tone'] ?? null,
            'avatar_color'      => $data['avatar_color'] ?? null,
            'max_tokens'        => (int) ($data['max_tokens'] ?? 0) ?: null,
            'temperature'       => isset($data['temperature']) ? (int) $data['temperature'] : null,
            'use_saved_replies' => $request->boolean('use_saved_replies'),
            'handoff_enabled'   => $request->boolean('handoff_enabled', true),
        ], fn ($v) => $v !== null);

        $agent = \App\Models\InstagramAutomation::create([
            'workspace_id'         => $wsId,
            'instagram_account_id' => $acc->id,
            'type'                 => 'ai_agent',
            'name'                 => $data['name'],
            'match_mode'           => 'any',
            'dm_message'           => $data['system_prompt'] ?? '',
            'is_active'            => $auto,
            'meta_json'            => $meta ?: null,
        ]);

        return response()->json(['ok' => true, 'id' => $agent->id, 'name' => $agent->name]);
    }

    /** Instagram notifications — recent inbound DMs across the workspace's accounts. */
    public function notifications(Request $request)
    {
        $wsId = $this->wsId();
        $accounts = InstagramAccount::forWorkspace($wsId)->get();
        $acctById = $accounts->keyBy('id');
        $accIds = $accounts->pluck('id')->all();

        // Account filter — ?account=<id>, guarded against ids from another
        // workspace (fall back to All). 0/empty = every connected account.
        $accFilter = (int) $request->query('account', 0);
        if ($accFilter && !in_array($accFilter, $accIds, true)) $accFilter = 0;
        $scopeIds = $accFilter ? [$accFilter] : $accIds;

        // Base inbound query for the active scope. Counts + the paginated feed
        // all derive from it so the KPIs match what the pager walks.
        $base = \App\Models\InstagramMessage::query()
            ->whereIn('instagram_account_id', $scopeIds ?: [0])
            ->where('direction', 'in');

        // KPIs (whole scope, not just the visible page).
        $total           = (clone $base)->count();
        $todayCount      = (clone $base)->whereDate('created_at', now()->toDateString())->count();
        $distinctSenders = (clone $base)->distinct()->count('igsid');
        $latestAt        = (clone $base)->max('created_at');
        $latestAt        = $latestAt ? \Illuminate\Support\Carbon::parse($latestAt) : null;

        // Per-account counts for the segment pills + sidebar (across ALL accounts,
        // independent of the active filter, so the pills always show real totals).
        $perAccount = $accIds
            ? \App\Models\InstagramMessage::whereIn('instagram_account_id', $accIds)
                ->where('direction', 'in')
                ->selectRaw('instagram_account_id, COUNT(*) as c')
                ->groupBy('instagram_account_id')
                ->pluck('c', 'instagram_account_id')
            : collect();
        $grandTotal = (int) $perAccount->sum();

        // The feed itself — real pagination (25/page), query string preserved so
        // the account filter survives page navigation.
        $items = $accIds
            ? (clone $base)->orderByDesc('id')->paginate(25)->withQueryString()
            : new \Illuminate\Pagination\LengthAwarePaginator([], 0, 25);

        // Resolve sender @usernames for the visible page only.
        $nameBy = collect();
        if ($items->isNotEmpty()) {
            $nameBy = \App\Models\InstagramContact::whereIn('instagram_account_id', collect($items->items())->pluck('instagram_account_id')->unique())
                ->whereIn('igsid', collect($items->items())->pluck('igsid')->unique())
                ->get()
                ->keyBy(fn ($c) => $c->instagram_account_id . ':' . $c->igsid)
                ->map(fn ($c) => (string) ($c->username ?: $c->name ?: ''));
        }

        return view('instagram.notifications', compact(
            'accounts', 'items', 'acctById', 'nameBy', 'accFilter',
            'total', 'todayCount', 'distinctSenders', 'latestAt', 'perAccount', 'grandTotal'
        ));
    }

    /** Manual operator reply from the inbox — text, attachment, quick replies or human-agent. */
    /**
     * Uniform reply result: JSON for the AJAX composer (so the inbox sends
     * WITHOUT a full page reload — the live poll then shows the sent message),
     * or a normal redirect-back for a no-JS form post.
     */
    private function igReplyOut(Request $request, bool $ok, string $message)
    {
        if ($request->ajax() || $request->wantsJson() || $request->expectsJson()) {
            return response()->json(['ok' => $ok, 'message' => $message], $ok ? 200 : 422);
        }
        return $ok ? back()->with('status', $message) : back()->withErrors(['instagram' => $message]);
    }

    /**
     * PUBLIC, symlink-free server for outbound DM media. Instagram fetches the
     * attachment from this URL, so it must be reachable WITHOUT auth and without
     * depending on the public/storage symlink (absent or unfollowed on many
     * shared hosts). Locked to the instagram-dm upload dir so it can never serve
     * an arbitrary file.
     */
    public function dmMedia(string $path)
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');
        if ($path === '' || str_contains($path, '..') || !str_starts_with($path, 'instagram-dm/')) {
            abort(404);
        }
        $disk = \Illuminate\Support\Facades\Storage::disk('public');
        if (!$disk->exists($path)) {
            abort(404);
        }
        // Stream with the right content-type + long cache (media is immutable).
        return $disk->response($path, null, ['Cache-Control' => 'public, max-age=31536000']);
    }

    /**
     * Self-healing avatar proxy — a STABLE url per (account, igsid).
     *
     * Instagram's profile_pic URLs are signed and expire (oe=<unix ts>) in ~1–2
     * days. Storing one and rendering it directly means every thread's avatar
     * 403s a day later — the grey-silhouette bug. This endpoint:
     *   1. serves a fresh local cache immediately,
     *   2. else re-downloads from the stored source if it's still live,
     *   3. else re-fetches a fresh profile_pic from Graph (throttled), and
     *   4. caches the bytes so the browser + the WaDesk mirror only ever load
     *      this never-expiring url.
     * Public (no session) so WaDesk can load it cross-domain. igsid 'self'
     * serves the account's own avatar. 404 when we have nothing → the blade's
     * onerror falls back to initials / the IG silhouette.
     */
    public function igAvatar(int $account, string $igsid)
    {
        $disk      = \Illuminate\Support\Facades\Storage::disk('public');
        $cacheFile = 'ig-avatars/' . $account . '_' . preg_replace('/[^A-Za-z0-9]/', '', $igsid) . '.jpg';
        $isFresh   = fn () => $disk->exists($cacheFile)
            && $disk->lastModified($cacheFile) > now()->subHours(12)->timestamp;

        // 1) Fresh cache → serve immediately (no Graph, no CDN hit).
        if ($isFresh()) {
            return $this->streamCachedAvatar($disk, $cacheFile);
        }

        $acc     = InstagramAccount::find($account);
        $isSelf  = $igsid === 'self';
        $contact = $isSelf ? null : \App\Models\InstagramContact::where('instagram_account_id', $account)
            ->where('igsid', $igsid)->first();

        $source = $isSelf ? (string) ($acc->profile_pic_url ?? '') : (string) ($contact->avatar_url ?? '');

        // 2) Stored source still live?
        $bytes = $this->fetchAvatarBytes($source);

        // 3) Dead/expired → re-fetch a fresh URL from Graph (throttled 30m so an
        //    unavailable/consent-blocked user can't trigger a call on every hit).
        if ($bytes === null && $acc) {
            $refreshKey = 'ig-avatar-refresh:' . $account . ':' . $igsid;
            if (\Illuminate\Support\Facades\Cache::add($refreshKey, 1, now()->addMinutes(30))) {
                try {
                    $svc = new InstagramService($acc);
                    if ($isSelf) {
                        $p     = $svc->getProfile();
                        $fresh = (string) ($p['profile_picture_url'] ?? '');
                        if ($fresh !== '' && $fresh !== $source) {
                            $acc->forceFill(['profile_pic_url' => $fresh])->save();
                        }
                    } else {
                        $p     = $svc->getSenderProfile($igsid);
                        $fresh = (string) ($p['profile_pic'] ?? '');
                        if ($fresh !== '' && $fresh !== $source && $contact) {
                            $contact->forceFill(['avatar_url' => $fresh])->save();
                        }
                    }
                    if (!empty($fresh) && $fresh !== $source) {
                        $bytes = $this->fetchAvatarBytes($fresh);
                    }
                } catch (\Throwable $e) {
                    // best-effort — fall through to the 404/initials path
                }
            }
        }

        // 4) Got bytes → cache + serve.
        if ($bytes !== null) {
            try { $disk->put($cacheFile, $bytes); } catch (\Throwable $e) {}
            return response($bytes, 200)
                ->header('Content-Type', 'image/jpeg')
                ->header('Cache-Control', 'public, max-age=3600');
        }

        // 5) Nothing fresh — serve a stale cache if we have one, else 404.
        if ($disk->exists($cacheFile)) {
            return $this->streamCachedAvatar($disk, $cacheFile);
        }
        abort(404);
    }

    /** Download an image URL server-side; bytes on a 2xx image response, else null. */
    private function fetchAvatarBytes(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '' || ! str_starts_with($url, 'http')) return null;
        if (str_contains($url, '/ig-avatar/')) return null;   // never proxy ourselves (loop guard)
        try {
            $r = \Illuminate\Support\Facades\Http::timeout(10)->get($url);
            if ($r->successful() && str_starts_with((string) $r->header('Content-Type'), 'image/')) {
                return $r->body();
            }
        } catch (\Throwable $e) {}
        return null;
    }

    /** Stream a cached avatar file with an image content-type + short public cache. */
    private function streamCachedAvatar($disk, string $cacheFile)
    {
        return response($disk->get($cacheFile), 200)
            ->header('Content-Type', 'image/jpeg')
            ->header('Cache-Control', 'public, max-age=3600');
    }

    /** Toggle MUTE on a conversation (local flag — Instagram has no mute API). */
    public function inboxMute(Request $request)
    {
        return $this->toggleThreadFlag($request, 'muted_at');
    }

    /** Toggle ARCHIVE — hides the thread from the main list (?view=archived shows it). */
    public function inboxArchive(Request $request)
    {
        return $this->toggleThreadFlag($request, 'archived_at');
    }

    /** DELETE a conversation locally: drop our stored messages + archive it so a
     *  background re-sync doesn't resurrect it in the main list. */
    public function inboxDeleteThread(Request $request)
    {
        $data = $request->validate([
            'instagram_account_id' => 'required|integer',
            'igsid'                => 'required|string|max:64',
        ]);
        $account = InstagramAccount::forWorkspace($this->wsId())->where('id', $data['instagram_account_id'])->first();
        if (!$account) return response()->json(['ok' => false, 'message' => 'Account not in your workspace.'], 422);

        \App\Models\InstagramMessage::where('instagram_account_id', $account->id)->where('igsid', $data['igsid'])->delete();
        // hidden_at (NOT archived_at) so it disappears from every view; the flag
        // also keeps it hidden if a background sync re-pulls the messages.
        \App\Models\InstagramContact::updateOrCreate(
            ['instagram_account_id' => $account->id, 'igsid' => $data['igsid']],
            ['workspace_id' => $account->workspace_id, 'hidden_at' => now(), 'archived_at' => null]
        );
        return response()->json(['ok' => true]);
    }

    /** Shared toggler for the nullable-timestamp conversation flags. */
    private function toggleThreadFlag(Request $request, string $col)
    {
        $data = $request->validate([
            'instagram_account_id' => 'required|integer',
            'igsid'                => 'required|string|max:64',
        ]);
        $account = InstagramAccount::forWorkspace($this->wsId())->where('id', $data['instagram_account_id'])->first();
        if (!$account) return response()->json(['ok' => false, 'message' => 'Account not in your workspace.'], 422);

        $c = \App\Models\InstagramContact::firstOrNew(['instagram_account_id' => $account->id, 'igsid' => $data['igsid']]);
        $c->workspace_id = $account->workspace_id;
        $on = $c->{$col} === null;
        $c->{$col} = $on ? now() : null;
        $c->save();
        return response()->json(['ok' => true, 'on' => $on]);
    }

    /** JSON: recent media for the automations "Limit to a post" thumbnail picker. */
    public function automationMedia(Request $request)
    {
        $account = InstagramAccount::forWorkspace($this->wsId())
            ->where('id', (int) $request->query('account_id', 0))->first();
        if (!$account) return response()->json(['ok' => false, 'items' => []], 422);

        $media = \Illuminate\Support\Facades\Cache::remember(
            "ig_media_{$account->id}", 300,
            fn () => (new \App\Services\Instagram\InstagramService($account))->getMedia(50)
        );
        $items = [];
        foreach ((array) $media as $m) {
            if (empty($m['id'])) continue;
            $items[] = [
                'id'      => (string) $m['id'],
                'thumb'   => (string) ($m['thumbnail_url'] ?? $m['media_url'] ?? ''),
                'type'    => (string) ($m['media_type'] ?? ''),
                'caption' => \Illuminate\Support\Str::limit((string) ($m['caption'] ?? ''), 60),
            ];
        }
        return response()->json(['ok' => true, 'items' => $items]);
    }

    /**
     * The connected account's ACTIVE stories, for the Instagram-style story
     * viewer on the Story-reply automation. Owned by user_id (IgDesk has no
     * workspaces). Cached 5 min so tapping the ring doesn't hammer Graph.
     */
    public function automationStories(Request $request)
    {
        $account = InstagramAccount::where('user_id', Auth::id())
            ->where('id', (int) $request->query('account_id', 0))->first();
        if (!$account) return response()->json(['ok' => false, 'items' => []], 422);

        $stories = \Illuminate\Support\Facades\Cache::remember(
            "ig_stories_{$account->id}", 300,
            fn () => (new \App\Services\Instagram\InstagramService($account))->getStories()
        );
        $items = [];
        foreach ((array) $stories as $s) {
            if (empty($s['id'])) continue;
            $isVideo = strtoupper((string) ($s['media_type'] ?? '')) === 'VIDEO';
            $items[] = [
                'id'        => (string) $s['id'],
                'type'      => $isVideo ? 'video' : 'image',
                'media_url' => (string) ($s['media_url'] ?? ''),
                'thumb'     => (string) ($s['thumbnail_url'] ?? (!$isVideo ? ($s['media_url'] ?? '') : '')),
                'timestamp' => (string) ($s['timestamp'] ?? ''),
            ];
        }
        return response()->json([
            'ok'      => true,
            'account' => [
                'username' => (string) ($account->username ?: $account->ig_user_id),
                'avatar'   => (string) ($account->profile_pic_url ?? ''),
            ],
            'items'   => $items,
        ]);
    }

    public function inboxReply(Request $request)
    {
        $wsId = $this->wsId();
        $data = $request->validate([
            'instagram_account_id' => 'required|integer',
            'igsid'                => 'required|string|max:64',
            'body'                 => 'nullable|string|max:1000',
            'human_agent'          => 'nullable|boolean',
            'media_file'           => 'nullable|file|mimes:jpg,jpeg,png,webp,mp4,mov,m4v,mp3,m4a,webm,ogg,pdf|max:25600',
            'media_url'            => 'nullable|url|max:2048',
            'media_type'           => 'nullable|in:image,video,audio,file',
            'qr_title'             => 'nullable|array',
            'qr_title.*'           => 'nullable|string|max:20',
            'qr_payload'           => 'nullable|array',
            'qb_title'             => 'nullable|array',
            'qb_title.*'           => 'nullable|string|max:20',
            'qb_type'              => 'nullable|array',
            'qb_value'             => 'nullable|array',
            'ce_title'             => 'nullable|array',
            'ce_title.*'           => 'nullable|string|max:80',
            'ce_image'             => 'nullable|array',
            'ce_subtitle'          => 'nullable|array',
            'ce_url'               => 'nullable|array',
        ]);
        $account = InstagramAccount::forWorkspace($wsId)->where('id', $data['instagram_account_id'])->first();
        if (!$account) return $this->igReplyOut($request, false, 'Account not in your workspace.');

        $svc   = new \App\Services\Instagram\InstagramService($account);
        $igsid = $data['igsid'];
        $body  = (string) ($data['body'] ?? '');

        // A human is replying → hand off: pause the per-conversation AI so it
        // doesn't talk over the operator (Team-Inbox handoff). Re-enable via the
        // composer's AI toggle.
        \App\Models\InstagramContact::updateOrCreate(
            ['instagram_account_id' => $account->id, 'igsid' => $igsid],
            ['workspace_id' => $account->workspace_id, 'ai_enabled' => false]
        );

        // 1) Media attachment (image / video / audio / file).
        if ($request->hasFile('media_file')) {
            $disk = function_exists('media_disk') ? media_disk() : 'public';
            $path = $request->file('media_file')->store('instagram-dm', $disk);
            // Instagram FETCHES the file from this URL. Serve it through our own
            // symlink-free route (public/storage may be missing or unfollowed on
            // shared hosts) so it's reliably reachable without `storage:link`.
            $url  = ($disk === 'public')
                ? url('/instagram/dm-media/' . ltrim($path, '/'))
                : (function_exists('media_url') ? media_url($path) : asset('storage/' . $path));
            $mime = (string) $request->file('media_file')->getMimeType();
            $type = str_starts_with($mime, 'image/') ? 'image'
                : (str_starts_with($mime, 'video/') ? 'video'
                : (str_starts_with($mime, 'audio/') ? 'audio' : 'file'));

            $r = $svc->sendMediaDm($igsid, $type, $url);
            if (!empty($r['ok'])) {
                // Store the real media URL so our own sent media renders in-thread
                // (not just an "[image]" marker).
                \App\Models\InstagramMessage::log($account, $igsid, 'out', '', 'manual', $r['mid'] ?? null, $type, $url);
                return $this->igReplyOut($request, true, 'Attachment sent.');
            }
            return $this->igReplyOut($request, false, 'Attachment failed: ' . ($r['error'] ?? 'unknown') . ' (url: ' . $url . ')');
        }

        // 1b) Remote media by URL — e.g. a GIF chosen from the picker (sent as
        // an MP4 video so Instagram accepts it). No local upload / re-hosting.
        $mediaUrl = trim((string) ($data['media_url'] ?? ''));
        if ($mediaUrl !== '' && preg_match('#^https://#i', $mediaUrl)) {
            $mtype = (string) ($data['media_type'] ?? 'video');
            $r = $svc->sendMediaDm($igsid, $mtype, $mediaUrl);
            if (!empty($r['ok'])) {
                \App\Models\InstagramMessage::log($account, $igsid, 'out', '', 'manual', $r['mid'] ?? null, $mtype, $mediaUrl);
                return $this->igReplyOut($request, true, 'GIF sent.');
            }
            return $this->igReplyOut($request, false, 'GIF failed: ' . ($r['error'] ?? 'unknown'));
        }

        // 2) Quick-reply buttons (need body text + at least one title).
        $titles = array_values(array_filter((array) ($data['qr_title'] ?? []), fn ($t) => trim((string) $t) !== ''));
        if (!empty($titles) && $body !== '') {
            $payloads = (array) $request->input('qr_payload', []);
            $replies  = [];
            foreach ($titles as $i => $t) $replies[] = ['title' => $t, 'payload' => (string) ($payloads[$i] ?? $t)];
            $r = $svc->sendQuickReplies($igsid, $body, $replies);
            if (!empty($r['ok'])) {
                \App\Models\InstagramMessage::log($account, $igsid, 'out', $body, 'manual', $r['mid'] ?? null, null, null, [
                    'tpl'     => 'quick_replies',
                    'buttons' => array_map(fn ($x) => ['title' => (string) $x['title']], $replies),
                ]);
                return $this->igReplyOut($request, true, 'Quick replies sent.');
            }
            return $this->igReplyOut($request, false, 'Send failed: ' . ($r['error'] ?? 'unknown'));
        }

        // 2b) Button template (need body text + at least one button title).
        $btnTitles = array_values(array_filter((array) $request->input('qb_title', []), fn ($t) => trim((string) $t) !== ''));
        if (!empty($btnTitles) && $body !== '') {
            $types  = (array) $request->input('qb_type', []);
            $values = (array) $request->input('qb_value', []);
            $buttons = [];
            foreach ($btnTitles as $i => $t) {
                $bt = ($types[$i] ?? 'postback') === 'web_url' ? 'web_url' : 'postback';
                $b  = ['type' => $bt, 'title' => $t];
                if ($bt === 'web_url') $b['url'] = (string) ($values[$i] ?? '');
                else $b['payload'] = (string) ($values[$i] ?? $t);
                $buttons[] = $b;
            }
            $r = $svc->sendButtonTemplate($igsid, $body, $buttons);
            if (!empty($r['ok'])) {
                \App\Models\InstagramMessage::log($account, $igsid, 'out', $body, 'manual', $r['mid'] ?? null, null, null, [
                    'tpl'     => 'buttons',
                    'buttons' => array_map(fn ($b) => ['title' => (string) $b['title'], 'url' => $b['url'] ?? null], $buttons),
                ]);
                return $this->igReplyOut($request, true, 'Buttons sent.');
            }
            return $this->igReplyOut($request, false, 'Send failed: ' . ($r['error'] ?? 'unknown'));
        }

        // 2c) Generic-template carousel (2-10 element cards; needs no body).
        $elTitles = array_values(array_filter((array) $request->input('ce_title', []), fn ($t) => trim((string) $t) !== ''));
        if (!empty($elTitles)) {
            $imgs = (array) $request->input('ce_image', []);
            $subs = (array) $request->input('ce_subtitle', []);
            $urls = (array) $request->input('ce_url', []);
            $elements = [];
            foreach ($elTitles as $i => $t) {
                $el = ['title' => mb_substr($t, 0, 80)];
                if (!empty($imgs[$i])) $el['image_url'] = (string) $imgs[$i];
                if (!empty($subs[$i])) $el['subtitle'] = mb_substr((string) $subs[$i], 0, 80);
                if (!empty($urls[$i])) $el['default_action'] = ['type' => 'web_url', 'url' => (string) $urls[$i]];
                $elements[] = $el;
            }
            $r = $svc->sendGenericTemplate($igsid, $elements);
            if (!empty($r['ok'])) {
                \App\Models\InstagramMessage::log($account, $igsid, 'out', '', 'manual', $r['mid'] ?? null, null, null, [
                    'tpl'   => 'carousel',
                    'cards' => array_map(fn ($el) => [
                        'title'    => (string) ($el['title'] ?? ''),
                        'subtitle' => (string) ($el['subtitle'] ?? ''),
                        'image'    => (string) ($el['image_url'] ?? ''),
                        'url'      => $el['default_action']['url'] ?? null,
                    ], $elements),
                ]);
                return $this->igReplyOut($request, true, 'Carousel sent.');
            }
            return $this->igReplyOut($request, false, 'Send failed: ' . ($r['error'] ?? 'unknown'));
        }

        // 3) Plain text (optionally as 7-day human-agent message).
        if ($body === '') return $this->igReplyOut($request, false, 'Type a message or attach a file.');
        $r = ($data['human_agent'] ?? false) ? $svc->sendHumanAgent($igsid, $body) : $svc->sendDm($igsid, $body);
        if (!empty($r['ok'])) {
            \App\Models\InstagramMessage::log($account, $igsid, 'out', $body, 'manual', $r['mid'] ?? null);
            return $this->igReplyOut($request, true, 'Reply sent.');
        }
        return $this->igReplyOut($request, false, 'Send failed: ' . ($r['error'] ?? 'unknown'));
    }

    /** React (or un-react) to an inbound DM. */
    public function inboxReact(Request $request)
    {
        $d = $request->validate([
            'instagram_account_id' => 'required|integer',
            'igsid'                => 'required|string|max:64',
            'message_id'           => 'required|string|max:191',
            'reaction'             => 'nullable|string|max:40',
            'remove'               => 'nullable|boolean',
        ]);
        if (!$acc = $this->igAccount($request)) return back()->withErrors(['instagram' => 'Account not found.']);
        $svc = new InstagramService($acc);
        $remove = (bool) ($d['remove'] ?? false);
        // Instagram wants the literal emoji (a keyword like 'love' returns
        // "Invalid reaction"); the client sends the emoji, we default to ❤️.
        $emoji = $d['reaction'] ?? '❤️';
        $r = $remove
            ? $svc->removeReaction($d['igsid'], $d['message_id'])
            : $svc->sendReaction($d['igsid'], $d['message_id'], $emoji);
        // Persist so the chip survives a reload (matches the customer's view).
        if (!empty($r['ok'])) {
            \App\Models\InstagramMessage::where('instagram_account_id', $acc->id)
                ->where('mid', $d['message_id'])
                ->update(['reaction' => $remove ? null : $emoji]);
        }
        return $this->igResult($r, $remove ? 'Reaction removed.' : 'Reaction sent.');
    }

    /** Per-message 3-dot menu: contacts the operator can forward to (24h window). */
    public function inboxForwardTargets(Request $request)
    {
        if (!$acc = $this->igAccount($request)) return response()->json(['contacts' => []]);
        $rows = $this->igWindowContacts($acc, now()->subHours(24));
        return response()->json([
            'contacts' => $rows->map(fn ($c) => [
                'igsid' => (string) $c->igsid,
                'name'  => (string) $c->label,
            ])->values(),
        ]);
    }

    /** Forward a message's text and/or media to another IG contact. */
    public function inboxForward(Request $request)
    {
        $d = $request->validate([
            'instagram_account_id' => 'required|integer',
            'to_igsid'             => 'required|string|max:64',
            'body'                 => 'nullable|string|max:1000',
            'media_url'            => 'nullable|url|max:2048',
            'media_type'           => 'nullable|in:image,video,audio,file',
        ]);
        if (!$acc = $this->igAccount($request)) return response()->json(['ok' => false, 'message' => __('Account not found.')], 422);
        $svc  = new InstagramService($acc);
        $to   = $d['to_igsid'];
        $body = trim((string) ($d['body'] ?? ''));
        $mediaUrl = trim((string) ($d['media_url'] ?? ''));
        $sentAny = false; $err = '';
        // Media first (mirrors how the original arrived), then any caption/text.
        if ($mediaUrl !== '' && preg_match('#^https://#i', $mediaUrl)) {
            $mtype = (string) ($d['media_type'] ?? 'image');
            $r = $svc->sendMediaDm($to, $mtype, $mediaUrl);
            if (!empty($r['ok'])) { $sentAny = true; \App\Models\InstagramMessage::log($acc, $to, 'out', '', 'forward', $r['mid'] ?? null, $mtype, $mediaUrl); }
            else $err = (string) ($r['error'] ?? '');
        }
        if ($body !== '') {
            $r = $svc->sendDm($to, $body);
            if (!empty($r['ok'])) { $sentAny = true; \App\Models\InstagramMessage::log($acc, $to, 'out', $body, 'forward', $r['mid'] ?? null); }
            else $err = (string) ($r['error'] ?? '');
        }
        if ($sentAny) {
            \App\Models\InstagramContact::updateOrCreate(
                ['instagram_account_id' => $acc->id, 'igsid' => $to],
                ['workspace_id' => $acc->workspace_id, 'last_message_at' => now()]
            );
            return response()->json(['ok' => true, 'message' => __('Message forwarded.')]);
        }
        return response()->json(['ok' => false, 'message' => ($err !== '' ? $err : __('Nothing to forward.')) . ' ' . __('The 24-hour window may be closed.')], 422);
    }

    /** Translate a message's text to the operator's language (inline, on demand). */
    public function inboxTranslate(Request $request)
    {
        $d = $request->validate([
            'body' => 'required|string|max:2000',
            'to'   => 'nullable|string|max:12',
        ]);
        $to  = strtolower(trim((string) ($d['to'] ?? ''))) ?: (app()->getLocale() ?: 'en');
        $out = InstagramGate::translate($d['body'], 'auto', $to);
        if ($out === null || trim((string) $out) === '') {
            return response()->json(['ok' => false, 'message' => __('Translation is unavailable — enable a translation provider in admin.')], 422);
        }
        return response()->json(['ok' => true, 'text' => $out, 'to' => $to]);
    }

    /** Pin / unpin a message inside the thread (operator-side marker). */
    public function inboxPin(Request $request)
    {
        $d = $request->validate([
            'instagram_account_id' => 'required|integer',
            'message_id'           => 'required|string|max:191',
            'pin'                  => 'nullable|boolean',
        ]);
        if (!$acc = $this->igAccount($request)) return response()->json(['ok' => false, 'message' => __('Account not found.')], 422);
        $pin = (bool) ($d['pin'] ?? true);
        \App\Models\InstagramMessage::where('instagram_account_id', $acc->id)
            ->where('mid', $d['message_id'])
            ->update(['pinned_at' => $pin ? now() : null]);
        return response()->json(['ok' => true, 'pinned' => $pin, 'message' => $pin ? __('Message pinned.') : __('Message unpinned.')]);
    }

    /** Report a message/conversation for the team's review. Instagram has no
     *  report-message API — this flags it internally + logs for follow-up. */
    public function inboxReport(Request $request)
    {
        $d = $request->validate([
            'instagram_account_id' => 'required|integer',
            'igsid'                => 'nullable|string|max:64',
            'message_id'           => 'nullable|string|max:191',
        ]);
        if (!$acc = $this->igAccount($request)) return response()->json(['ok' => false, 'message' => __('Account not found.')], 422);
        \Illuminate\Support\Facades\Log::warning('[IG-REPORT] message reported for review', [
            'workspace' => $acc->workspace_id, 'account' => $acc->id,
            'igsid'     => $d['igsid'] ?? null, 'mid' => $d['message_id'] ?? null,
            'by'        => optional($request->user())->id,
        ]);
        return response()->json(['ok' => true, 'message' => __('Reported for review.')]);
    }

    /**
     * GIF picker proxy — the inbox composer's GIF button uses the official GIPHY
     * JS SDK (@giphy/js-components Grid) whose fetchGifs() expects GIPHY's native
     * GifsResult shape. We proxy the raw GIPHY response verbatim so the API key
     * stays server-side (the SDK would otherwise need a client-exposed key).
     * Empty query → trending. Configure GIPHY_API_KEY in .env; falls back to
     * GIPHY's public beta key.
     */
    public function inboxGifSearch(Request $request)
    {
        $q      = trim((string) $request->query('q', ''));
        $offset = max(0, (int) $request->query('offset', 0));
        $limit  = min(50, max(1, (int) $request->query('limit', 12)));
        // Admin-saved key (Settings → Instagram) wins; then .env; then GIPHY's public demo key.
        $key = (string) (InstagramGate::setting('instagram_giphy_key', '')
            ?: config('services.giphy.key')
            ?: env('GIPHY_API_KEY')
            ?: 'dc6zaTOxFJmzC');
        $endpoint = $q !== ''
            ? 'https://api.giphy.com/v1/gifs/search'
            : 'https://api.giphy.com/v1/gifs/trending';
        try {
            $resp = \Illuminate\Support\Facades\Http::timeout(8)->get($endpoint, array_filter([
                'api_key' => $key,
                'q'       => $q !== '' ? $q : null,
                'limit'   => $limit,
                'offset'  => $offset,
                'rating'  => 'pg-13',
                'bundle'  => 'messaging_non_clips',
            ], fn ($v) => $v !== null && $v !== ''));
            if (!$resp->ok()) {
                return response()->json(['data' => [], 'pagination' => ['total_count' => 0, 'count' => 0, 'offset' => $offset], 'meta' => ['status' => 502]]);
            }
            // Return GIPHY's payload verbatim — it already matches the SDK's GifsResult.
            return response()->json($resp->json());
        } catch (\Throwable $e) {
            return response()->json(['data' => [], 'pagination' => ['total_count' => 0, 'count' => 0, 'offset' => $offset], 'meta' => ['status' => 500, 'msg' => $e->getMessage()]]);
        }
    }

    /** Composer — post to Instagram + optionally wire a comment→DM rule on the new post. */
    public function composer()
    {
        $wsId = $this->wsId();
        $accounts = InstagramAccount::forWorkspace($wsId)->where('status', 'connected')->orderBy('id')->get();
        $igFlows  = InstagramGate::flows()->where('workspace_id', $wsId)->where('flow_type', 'instagram')->orderByDesc('id')->get();
        $scheduled = \App\Models\InstagramScheduledPost::where('workspace_id', $wsId)
            ->where('status', 'pending')->orderBy('scheduled_at')->limit(10)->get();
        // Daily publishing quota for the first connected account (cached 10 min to avoid an API hit per load).
        $publishLimit = null;
        if ($acc = $accounts->first()) {
            $publishLimit = \Illuminate\Support\Facades\Cache::remember('ig_pub_limit_' . $acc->id, 600, fn () => (new InstagramService($acc))->publishingLimit());
        }
        // Synced catalog products — for tagging shoppable photo posts (Phase 7).
        $products = \App\Models\InstagramProduct::forWorkspace($wsId)
            ->whereNotNull('product_id')->where('product_id', '!=', '')
            ->orderByDesc('synced_at')->limit(60)->get(['id', 'name', 'image_url', 'currency', 'price']);
        // AI composer tools are the `ai` entitlement — locked (upgrade CTA) when
        // the plan doesn't include it. The endpoints enforce this too.
        $aiAllowed = InstagramGate::allows('ai', auth()->user()?->current_workspace);
        return view('instagram.composer', compact('accounts', 'igFlows', 'scheduled', 'publishLimit', 'products', 'aiAllowed'));
    }

    public function composerPublish(Request $request)
    {
        $wsId = $this->wsId();
        $data = $request->validate([
            'instagram_account_id' => 'required|integer',
            'media_type'           => 'nullable|in:image,reels,story,carousel',
            'image_url'            => 'nullable|url|max:1024',
            'video_url'            => 'nullable|url|max:1024',
            'carousel_urls'        => 'nullable|string|max:4000',
            'caption'              => 'nullable|string|max:2200',
            // Uploaded media (alternative to pasting a URL).
            'media_file'           => 'nullable|file|mimes:jpg,jpeg,png,webp,mp4,mov,m4v|max:102400',
            'media_files.*'        => 'nullable|file|mimes:jpg,jpeg,png,webp,mp4,mov,m4v|max:102400',
            // Optional comment→DM automation to wire onto the new post.
            'auto_keyword'         => 'nullable|string|max:500',
            'auto_public_reply'    => 'nullable|string|max:500',
            'auto_dm'              => 'nullable|string|max:1000',
            'auto_flow_id'         => 'nullable|integer',
            // Schedule fields (WaDesk-style separate date + time + timezone).
            'send_date'            => 'nullable|date',
            'send_time'            => 'nullable|date_format:H:i',
            'timezone'             => 'nullable|timezone',
            // Reel advanced options (forwarded to publishReel for media_type=reels).
            'cover_url'            => 'nullable|url|max:1024',
            'thumb_offset'        => 'nullable|integer|min:0',
            'share_to_feed'        => 'nullable|boolean',
            'audio_name'           => 'nullable|string|max:120',
            // Shoppable post — catalog products to tag (photo posts only).
            'product_ids'          => 'nullable|array|max:5',
            'product_ids.*'        => 'integer',
        ]);
        $type = $data['media_type'] ?? 'image';

        // Uploaded media → store on the active (cloud/local) disk and use its
        // public URL. Meta publishes from a public HTTPS link, so this works
        // end-to-end on an https host (and cloud storage returns an https URL).
        $mediaDisk = function_exists('media_disk') ? media_disk() : 'public';
        $mkUrl = fn ($p) => function_exists('media_url') ? media_url($p) : asset('storage/' . $p);
        // Record uploaded media into the personal Files library (best-effort; a
        // failed index write must never block publishing).
        $recordFile = function ($f, string $path) use ($mediaDisk) {
            \App\Models\UserFile::record([
                'user_id'       => (int) auth()->id(),
                'disk'          => $mediaDisk,
                'path'          => $path,
                'original_name' => $f->getClientOriginalName(),
                'mime'          => $f->getMimeType(),
                'size'          => $f->getSize(),
                'folder'        => 'Composer',
            ]);
        };
        if ($request->hasFile('media_file')) {
            $f = $request->file('media_file');
            $path = $f->store('instagram-media', $mediaDisk);
            $recordFile($f, $path);
            $url = $mkUrl($path);
            if (str_starts_with((string) $f->getMimeType(), 'video')) {
                $data['video_url'] = $url;
            } else {
                $data['image_url'] = $url;
            }
        }
        if ($request->hasFile('media_files')) {
            $urls = [];
            foreach ($request->file('media_files') as $f) {
                $path = $f->store('instagram-media', $mediaDisk);
                $recordFile($f, $path);
                $urls[] = $mkUrl($path);
            }
            if ($urls) {
                $data['carousel_urls'] = trim(($data['carousel_urls'] ?? '') . "\n" . implode("\n", $urls));
            }
        }

        $account = InstagramAccount::forWorkspace($wsId)->where('id', $data['instagram_account_id'])->first();
        if (!$account) return back()->withErrors(['instagram' => 'Account not in your workspace.'])->withInput();

        // Per-type required media URL(s) + HTTPS check (Meta requires public HTTPS).
        if ($err = self::validateMediaInput($type, $data)) {
            return back()->withErrors(['instagram' => $err])->withInput();
        }

        // Combine the separate date + time + timezone into one instant. The
        // user picks a wall-clock time in their chosen zone; we convert it to
        // the app timezone for storage so the no-cron sweep fires on schedule.
        $scheduleAt = null;
        if (!empty($data['send_date']) && !empty($data['send_time'])) {
            $tz = $data['timezone'] ?? config('app.timezone');
            try {
                $scheduleAt = \Illuminate\Support\Carbon::parse($data['send_date'] . ' ' . $data['send_time'], $tz)
                    ->setTimezone(config('app.timezone'));
            } catch (\Throwable $e) {
                $scheduleAt = null;
            }
        }

        // Schedule for later → hold it; the no-cron sweep publishes it when due.
        if ($scheduleAt && $scheduleAt->isFuture()) {
            \App\Models\InstagramScheduledPost::create([
                'workspace_id'         => $wsId,
                'instagram_account_id' => $account->id,
                'media_type'           => $type,
                'image_url'            => $data['image_url'] ?? null,
                'video_url'            => $data['video_url'] ?? null,
                'media_urls'           => $type === 'carousel' ? self::parseCarousel((string) ($data['carousel_urls'] ?? '')) : null,
                'caption'              => $data['caption'] ?? null,
                'scheduled_at'         => $scheduleAt,
                'status'               => 'pending',
                'auto_keyword'         => $data['auto_keyword'] ?? null,
                'auto_public_reply'    => $data['auto_public_reply'] ?? null,
                'auto_dm'              => $data['auto_dm'] ?? null,
                'auto_flow_id'         => !empty($data['auto_flow_id']) ? (int) $data['auto_flow_id'] : null,
            ]);
            return redirect('/instagram')->with('status', ucfirst($type) . ' scheduled for ' . $scheduleAt->toDayDateTimeString() . '.');
        }

        // Phase 7 — shoppable post: resolve chosen products to catalog product
        // tags, spread across the image so they don't overlap (x/y in 0–1).
        if ($type === 'image' && !empty($data['product_ids'])) {
            $prods = \App\Models\InstagramProduct::forWorkspace($wsId)
                ->whereIn('id', (array) $data['product_ids'])
                ->whereNotNull('product_id')->where('product_id', '!=', '')
                ->get(['product_id'])->values();
            $tags = [];
            foreach ($prods as $i => $p) {
                $tags[] = [
                    'product_id' => (string) $p->product_id,
                    'x'          => round(0.3 + ($i % 3) * 0.2, 2),
                    'y'          => round(0.35 + intdiv($i, 3) * 0.2, 2),
                ];
            }
            if ($tags) $data['product_tags'] = $tags;
        }

        // Content protection — stamp the operator's watermark onto a COPY of any
        // image before it goes to Meta. Fail-safe: images without an active
        // config, or any error, publish untouched (see applyWatermark()).
        $data = self::applyWatermark($account, $type, $data);

        $res = self::publishByType(new \App\Services\Instagram\InstagramService($account), $type, $data);
        if (empty($res['ok'])) {
            return back()->withErrors(['instagram' => 'Publish failed: ' . ($res['error'] ?? 'unknown')])->withInput();
        }
        $mediaId = (string) ($res['media_id'] ?? '');

        // Wire the comment→DM rule onto THIS post if the user filled it in.
        if (!empty($data['auto_dm']) || (!empty($data['auto_flow_id']) && !empty($data['auto_keyword']))) {
            $isFlow = !empty($data['auto_flow_id']);
            InstagramAutomation::create([
                'workspace_id'         => $wsId,
                'instagram_account_id' => $account->id,
                'type'                 => $isFlow ? 'flow' : 'comment_to_dm',
                'name'                 => 'Post ' . substr($mediaId, -6) . ' · comment→DM',
                'trigger_keyword'      => $data['auto_keyword'] ?? null,
                'match_mode'           => !empty($data['auto_keyword']) ? 'contains' : 'any',
                'post_id'              => $mediaId,                 // scope to the post we just made
                'public_reply'         => $data['auto_public_reply'] ?? null,
                'dm_message'           => $data['auto_dm'] ?? '',
                'flow_id'              => $isFlow ? (int) $data['auto_flow_id'] : null,
                'is_active'            => true,
            ]);
            return redirect('/instagram')->with('status', 'Posted to Instagram + comment→DM automation armed on the new post.');
        }

        return redirect('/instagram')->with('status', 'Posted to Instagram. Media ID ' . $mediaId . '.');
    }

    /** Validate the required media URL(s) for the chosen media type. Null = OK. */
    public static function validateMediaInput(string $type, array $data): ?string
    {
        $https = fn ($u) => is_string($u) && str_starts_with($u, 'https://');
        return match ($type) {
            'reels'    => $https($data['video_url'] ?? '') ? null : 'Reel needs a public HTTPS MP4/MOV video URL.',
            'story'    => ($https($data['image_url'] ?? '') || $https($data['video_url'] ?? '')) ? null : 'Story needs a public HTTPS image OR video URL.',
            'carousel' => count(self::parseCarousel((string) ($data['carousel_urls'] ?? ''))) >= 2 ? null : 'Carousel needs 2–10 public HTTPS image/video URLs (one per line).',
            default    => $https($data['image_url'] ?? '') ? null : 'Image URL must be a public HTTPS JPEG.',
        };
    }

    /** Parse a textarea of URLs (one per line/comma) into [['type'=>image|video,'url'=>], …]. */
    public static function parseCarousel(string $raw): array
    {
        $out = [];
        foreach (preg_split('/[\r\n,]+/', $raw) as $line) {
            $u = trim((string) $line);
            if (!str_starts_with($u, 'https://')) continue;
            $isVid = (bool) preg_match('/\.(mp4|mov|m4v)(\?|$)/i', $u);
            $out[] = ['type' => $isVid ? 'video' : 'image', 'url' => $u];
            if (count($out) >= 10) break;
        }
        return $out;
    }

    /**
     * Dispatch a publish by media type to the verified InstagramService method.
     * Static so the scheduled-post command reuses the exact same routing.
     * $data keys: image_url, video_url, carousel_urls OR media_urls(array), caption.
     */
    /**
     * Stamp the account's watermark onto the image(s) in $data before publish,
     * returning $data with the image URLs swapped for the watermarked copies.
     *
     * Only image-bearing posts are touched — feed images, story IMAGES and the
     * image items of a carousel; reels/videos are left alone. Entirely fail-safe:
     * WatermarkService::apply() returns null when there's no active config, GD is
     * missing, or anything throws, and we then keep the original URL. Static so
     * the Node sweep + manual "publish now" reuse the exact same behaviour.
     */
    public static function applyWatermark(InstagramAccount $account, string $type, array $data): array
    {
        try {
            if ($type === 'reels') {
                return $data; // video-only, nothing to overlay
            }

            // Feed image + story image: swap image_url for a watermarked copy.
            if (in_array($type, ['image', 'story'], true) && !empty($data['image_url'])) {
                $wm = \App\Services\Instagram\WatermarkService::apply((string) $data['image_url'], $account);
                if ($wm) $data['image_url'] = $wm;
            }

            // Carousel: watermark each IMAGE item (leave video items untouched).
            if ($type === 'carousel') {
                $items = is_array($data['media_urls'] ?? null)
                    ? $data['media_urls']
                    : self::parseCarousel((string) ($data['carousel_urls'] ?? ''));
                foreach ($items as $i => $it) {
                    if (($it['type'] ?? 'image') !== 'image' || empty($it['url'])) continue;
                    $wm = \App\Services\Instagram\WatermarkService::apply((string) $it['url'], $account);
                    if ($wm) $items[$i]['url'] = $wm;
                }
                $data['media_urls'] = $items;
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('[IG-WATERMARK] applyWatermark skipped: ' . $e->getMessage());
        }
        return $data;
    }

    public static function publishByType(\App\Services\Instagram\InstagramService $svc, string $type, array $data): array
    {
        $caption = (string) ($data['caption'] ?? '');
        switch ($type) {
            case 'reels':
                $opts = array_filter([
                    'cover_url'     => $data['cover_url'] ?? null,
                    'thumb_offset'  => isset($data['thumb_offset']) && $data['thumb_offset'] !== '' ? (int) $data['thumb_offset'] : null,
                    'share_to_feed' => array_key_exists('share_to_feed', $data) ? (bool) $data['share_to_feed'] : null,
                    'audio_name'    => $data['audio_name'] ?? null,
                ], fn ($v) => $v !== null && $v !== '');
                return $svc->publishReel((string) ($data['video_url'] ?? ''), $caption, $opts);
            case 'story':
                $vid = !empty($data['video_url']);
                return $svc->publishStory((string) ($vid ? $data['video_url'] : ($data['image_url'] ?? '')), $vid);
            case 'carousel':
                $items = is_array($data['media_urls'] ?? null)
                    ? $data['media_urls']
                    : self::parseCarousel((string) ($data['carousel_urls'] ?? ''));
                return $svc->publishCarousel($items, $caption);
            default:
                return $svc->publishImage((string) ($data['image_url'] ?? ''), $caption, (array) ($data['product_tags'] ?? []));
        }
    }

    /** Analytics — real IG insights (reach / engagement) + our DM volume. */
    public function analytics(Request $request)
    {
        $wsId = $this->wsId();
        $accounts = InstagramAccount::forWorkspace($wsId)->where('status', 'connected')->orderBy('id')->get();
        $accId    = (int) $request->query('account', 0);
        $account  = $accId ? $accounts->firstWhere('id', $accId) : $accounts->first();

        $insights = [];
        if ($account) {
            // Cache 10 min — insights are slow + rate-limited.
            $insights = \Illuminate\Support\Facades\Cache::remember(
                "ig_insights_{$account->id}", 600,
                fn () => (new \App\Services\Instagram\InstagramService($account))->accountInsights()
            );
        }
        $reach   = array_sum(array_column($insights['reach'] ?? [], 'v'));
        // `profile_views` was deprecated at account level (2025-01-08). Use the
        // verified total_value `accounts_engaged` counter instead.
        $profile = (int) ($insights['_totals']['accounts_engaged'] ?? 0);

        // Our own DM volume — 14-day daily buckets (in vs out) from the log,
        // plus a 24-slot hour-of-day distribution for the "busiest hours" chart.
        $labels = []; $inSeries = []; $outSeries = [];
        for ($i = 13; $i >= 0; $i--) $labels[now()->subDays($i)->toDateString()] = 0;
        $dmIn = $labels; $dmOut = $labels;
        $hourly = array_fill(0, 24, 0);
        if ($account) {
            $rows = \App\Models\InstagramMessage::where('instagram_account_id', $account->id)
                ->where('created_at', '>=', now()->subDays(14)->startOfDay())
                ->get(['direction', 'created_at']);
            foreach ($rows as $r) {
                $hourly[(int) $r->created_at->format('G')]++;
                $d = $r->created_at->toDateString();
                if (!array_key_exists($d, $dmIn)) continue;
                if ($r->direction === 'in') $dmIn[$d]++; else $dmOut[$d]++;
            }
        }
        $labels    = array_keys($dmIn);
        $inSeries  = array_values($dmIn);
        $outSeries = array_values($dmOut);

        // Recent posts for the per-post insights drill-down.
        $media = $account
            ? \Illuminate\Support\Facades\Cache::remember("ig_media_{$account->id}", 600, fn () => (new InstagramService($account))->getMedia(12))
            : [];

        return view('instagram.analytics', compact('accounts', 'account', 'insights', 'reach', 'profile', 'labels', 'inSeries', 'outSeries', 'media', 'hourly'));
    }

    /** Per-account in-window reachable counts (people who DMed in the last 24h). */
    private function igReachCounts($accounts, \Illuminate\Support\Carbon $cutoff): array
    {
        $reach = [];
        foreach ($accounts as $acc) {
            $reach[$acc->id] = \App\Models\InstagramMessage::where('instagram_account_id', $acc->id)
                ->where('direction', 'in')->where('created_at', '>=', $cutoff)->distinct('igsid')->count('igsid');
        }
        return $reach;
    }

    /**
     * In-window contacts with their resolved @username / name — NOT the last
     * message body (the old picker showed the message text, which was useless).
     */
    private function igWindowContacts(InstagramAccount $account, \Illuminate\Support\Carbon $cutoff)
    {
        $rows = \App\Models\InstagramMessage::where('instagram_account_id', $account->id)
            ->where('direction', 'in')->where('created_at', '>=', $cutoff)
            ->orderByDesc('id')->limit(300)->get(['igsid', 'body']);
        $byId  = $rows->unique('igsid')->take(80)->values();
        $names = \App\Models\InstagramContact::where('instagram_account_id', $account->id)
            ->whereIn('igsid', $byId->pluck('igsid')->all())->get()->keyBy('igsid');
        return $byId->map(function ($m) use ($names) {
            $c = $names->get($m->igsid);
            $label = 'IG user ' . substr($m->igsid, -6);
            if ($c && !empty($c->username)) $label = '@' . ltrim($c->username, '@');
            elseif ($c && !empty($c->name)) $label = (string) $c->name;
            return (object) [
                'igsid' => $m->igsid,
                'label' => $label,
                'last'  => \Illuminate\Support\Str::limit((string) $m->body, 42),
            ];
        });
    }

    /** Aggregate bulk-DM stats + per-broadcast counts (KPI cards + AJAX poll). */
    private function broadcastStatsPayload(int $wsId, array $reach): array
    {
        $bcs = \App\Models\InstagramBroadcast::where('workspace_id', $wsId)->get(['id', 'total', 'sent', 'failed', 'status']);
        $readBy = \App\Models\InstagramBroadcastRecipient::whereIn('broadcast_id', $bcs->pluck('id'))
            ->whereNotNull('read_at')->selectRaw('broadcast_id, count(*) as c')->groupBy('broadcast_id')->pluck('c', 'broadcast_id');
        $sumTotal = (int) $bcs->sum('total');
        $sumSent = (int) $bcs->sum('sent');
        $sumFailed = (int) $bcs->sum('failed');
        $stats = [
            'total'      => $bcs->count(),
            'recipients' => $sumTotal,
            'sent'       => $sumSent,
            'failed'     => $sumFailed,
            'pending'    => max(0, $sumTotal - $sumSent - $sumFailed),
            'read'       => (int) $readBy->sum(),
            'reach'      => array_sum($reach),
        ];
        $rows = [];
        foreach ($bcs as $b) {
            $rows[$b->id] = [
                'sent'   => (int) $b->sent,
                'failed' => (int) $b->failed,
                'total'  => (int) $b->total,
                'read'   => (int) ($readBy[$b->id] ?? 0),
                'status' => (string) $b->status,
                'pct'    => $b->total > 0 ? min(100, (int) round($b->sent / max(1, $b->total) * 100)) : 0,
            ];
        }
        return ['stats' => $stats, 'rows' => $rows];
    }

    /** Bulk-DM index — analytics KPIs + broadcast list (mirrors /broadcasts). */
    public function broadcast()
    {
        $wsId = $this->wsId();
        $accounts = InstagramAccount::forWorkspace($wsId)->where('status', 'connected')->orderBy('id')->get();
        $reach = $this->igReachCounts($accounts, now()->subHours(24));
        $payload = $this->broadcastStatsPayload($wsId, $reach);
        $stats = $payload['stats'];
        $readByBc = $payload['rows']; // id => [read, ...]

        $accountName = $accounts->keyBy('id');
        $broadcasts  = \App\Models\InstagramBroadcast::where('workspace_id', $wsId)->orderByDesc('id')->paginate(15);
        return view('instagram.broadcast', compact('accounts', 'reach', 'broadcasts', 'stats', 'accountName', 'readByBc'));
    }

    /** JSON stats for live polling — updates counts + read receipts without reload. */
    public function broadcastStats()
    {
        $wsId = $this->wsId();
        $reach = $this->igReachCounts(InstagramAccount::forWorkspace($wsId)->where('status', 'connected')->get(), now()->subHours(24));
        return response()->json($this->broadcastStatsPayload($wsId, $reach));
    }

    /** Bulk-DM composer — stepper-style create form (mirrors /broadcasts/create). */
    public function broadcastCreate(Request $request)
    {
        $wsId = $this->wsId();
        $accounts = InstagramAccount::forWorkspace($wsId)->where('status', 'connected')->orderBy('id')->get();
        $cutoff = now()->subHours(24);
        $reach = $this->igReachCounts($accounts, $cutoff);

        // The inbox's New-message picker hands off here when MORE THAN ONE
        // recipient is ticked: ?account=<id>&to=<igsid,igsid>. Instagram has no
        // group thread over the Messaging API, so "message these 5 people" is
        // five individual DMs — which is exactly this page. Arriving with the
        // right account selected and the people already ticked keeps it one
        // continuous action instead of making them pick everyone twice.
        $wanted = (int) $request->query('account');
        $acc    = ($wanted ? $accounts->firstWhere('id', $wanted) : null) ?: $accounts->first();
        $contacts = $acc ? $this->igWindowContacts($acc, $cutoff) : collect();

        $preselect = array_values(array_filter(array_map('trim', explode(',', (string) $request->query('to', '')))));
        $preAccountId = (int) ($acc->id ?? 0);

        return view('instagram.broadcast-create', compact('accounts', 'reach', 'contacts', 'preselect', 'preAccountId'));
    }

    public function broadcastSend(Request $request)
    {
        $wsId = $this->wsId();
        $data = $request->validate([
            'instagram_account_id' => 'required|integer',
            'body'                 => 'required|string|max:1000',
            'segment'              => 'nullable|in:all,pick',
            'igsids'               => 'nullable|array',
            'igsids.*'             => 'string|max:64',
        ]);
        $account = InstagramAccount::forWorkspace($wsId)->where('id', $data['instagram_account_id'])->first();
        if (!$account) return back()->withErrors(['instagram' => 'Account not in your workspace.']);

        // Everyone currently inside the 24h window (ban-safe base set).
        $cutoff   = now()->subHours(24);
        $inWindow = \App\Models\InstagramMessage::where('instagram_account_id', $account->id)
            ->where('direction', 'in')->where('created_at', '>=', $cutoff)
            ->distinct()->pluck('igsid')->values()->all();

        // Segment: "pick" keeps only chosen IGSIDs that are STILL in-window (policy stays intact).
        if (($data['segment'] ?? 'all') === 'pick' && !empty($data['igsids'])) {
            $igsids = array_values(array_intersect($inWindow, $data['igsids']));
        } else {
            $igsids = $inWindow;
        }

        if (empty($igsids)) {
            return back()->withErrors(['instagram' => 'No contacts inside the 24-hour window match — bulk DMs can only reach people who messaged you in the last 24h.'])->withInput();
        }

        $bcast = \App\Models\InstagramBroadcast::create([
            'workspace_id'         => $wsId,
            'instagram_account_id' => $account->id,
            'body'                 => $data['body'],
            'recipients'           => $igsids,
            'total'                => count($igsids),
            'cursor'               => 0,
            'sent'                 => 0,
            'failed'               => 0,
            'status'               => 'pending',
        ]);

        // Per-recipient ledger — one pending row each (drives the drill-down view).
        $now  = now();
        $rows = array_map(fn ($ig) => [
            'broadcast_id' => $bcast->id, 'igsid' => $ig, 'status' => 'pending',
            'created_at' => $now, 'updated_at' => $now,
        ], $igsids);
        foreach (array_chunk($rows, 500) as $chunk) {
            \App\Models\InstagramBroadcastRecipient::insert($chunk);
        }

        return redirect()->route('instagram.broadcast')->with('status', 'Bulk DM queued to ' . count($igsids) . ' in-window contacts. It drains safely in the background (Meta limit ~200/hr).');
    }

    /** Per-recipient drill-down for one bulk DM. */
    public function broadcastShow(int $id)
    {
        $bcast = \App\Models\InstagramBroadcast::where('workspace_id', $this->wsId())->where('id', $id)->firstOrFail();
        $account = InstagramAccount::forWorkspace($this->wsId())->find($bcast->instagram_account_id);
        $recipients = \App\Models\InstagramBroadcastRecipient::where('broadcast_id', $id)->orderBy('id')->paginate(50);
        return view('instagram.broadcast-show', compact('bcast', 'account', 'recipients'));
    }

    public function automationToggle(int $id)
    {
        $a = InstagramAutomation::where('workspace_id', $this->wsId())->findOrFail($id);
        $a->update(['is_active' => !$a->is_active]);
        return back()->with('status', $a->is_active ? 'Automation turned on.' : 'Automation paused.');
    }

    public function automationDestroy(int $id)
    {
        InstagramAutomation::where('workspace_id', $this->wsId())->where('id', $id)->delete();
        return back()->with('status', 'Automation deleted.');
    }
}
