<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use App\Models\ContactMessage;
use App\Models\Package;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Public marketing site: home, about, contact, blog and the legal pages. All
 * copy is admin-editable through the FrontContent registry (admin → Front pages).
 */
class FrontendController extends Controller
{
    /** The marketing home page. */
    public function home()
    {
        return view('frontend.home');
    }

    /** Features page — its own page (not a home anchor). */
    public function features()
    {
        return view('frontend.features');
    }

    /**
     * Public pricing page — the editorial Starter/Pro/Scale strip driven by the
     * admin-managed plans (Package). No plans yet → the blade falls back to a
     * shipped three-tier default so the page never looks empty.
     */
    public function pricing()
    {
        $packages = Package::where('is_active', true)
            ->orderBy('sort')->orderBy('id')
            ->get()
            ->values();

        // Exactly ONE card is highlighted — the first admin-featured plan, or the
        // middle tier when none is flagged. A wall of highlighted cards looks bad.
        $highlightIdx = $packages->search(fn (Package $p) => (bool) $p->is_featured);
        if ($highlightIdx === false) {
            $highlightIdx = $packages->count() ? intdiv($packages->count() - 1, 2) : -1;
        }

        $plans = $packages->map(function (Package $p, int $i) use ($highlightIdx) {
            $amount   = $p->chargeableAmount();
            $free     = $amount <= 0;
            $currency = strtoupper((string) ($p->currency ?: 'USD'));
            $symbols  = ['USD' => '$', 'EUR' => '€', 'GBP' => '£', 'INR' => '₹', 'AUD' => 'A$', 'CAD' => 'C$'];
            $sym      = $symbols[$currency] ?? ($currency . ' ');
            $hl       = $i === $highlightIdx;

            // Humanise the feature-flag keys the admin ticked on the package
            // into readable rows (access_ai_agents → "Ai agents").
            $features = collect((array) $p->features)
                ->filter()
                ->map(fn ($k) => ucfirst(trim(str_replace(['access_', 'can_', '_'], ['', '', ' '], (string) $k))))
                ->filter()
                ->take(6)
                ->map(fn ($label) => ['label' => $label, 'included' => true])
                ->values()
                ->all();

            return [
                'name'        => $p->name,
                'tagline'     => (string) ($p->description ?: ''),
                'price'       => $free ? ($sym . '0') : ($sym . rtrim(rtrim(number_format($amount, 2), '0'), '.')),
                'period'      => $free ? __('/forever') : $p->periodLabel(),
                'badge'       => $hl ? __('Most picked') : ($free ? __('free') : __('plan')),
                'highlighted' => $hl,
                'features'    => $features,
                'cta_label'   => $free ? __('Start free →') : __('Start free trial →'),
                'cta_href'    => \Route::has('register') ? route('register') : url('/'),
            ];
        })->all();

        return view('frontend.pricing', compact('plans'));
    }

    /** About page (hidden with a 404 when the admin has turned it off). */
    public function about()
    {
        abort_unless(front_bool('about_show'), 404);
        return view('frontend.about');
    }

    /** Contact page with a working message form. */
    public function contact()
    {
        abort_unless(front_bool('contact_show'), 404);
        return view('frontend.contact');
    }

    /** Store a contact submission (honeypot + validation + optional email). */
    public function contactStore(Request $request)
    {
        abort_unless(front_bool('contact_show'), 404);

        // Honeypot — bots fill hidden fields; humans never see this one.
        if (filled($request->input('website'))) {
            return back()->with('contact_ok', true);
        }

        $data = $request->validate([
            'name'    => ['required', 'string', 'max:120'],
            'email'   => ['required', 'email', 'max:190'],
            'subject' => ['nullable', 'string', 'max:190'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $msg = ContactMessage::create($data + ['ip' => $request->ip()]);

        // Best-effort notify the support inbox; never fail the request on mail error.
        $to = trim((string) front('contact_email'));
        if ($to !== '' && filter_var($to, FILTER_VALIDATE_EMAIL)) {
            try {
                Mail::raw(
                    "New contact message from {$msg->name} <{$msg->email}>\n\nSubject: " . ($msg->subject ?: '—') . "\n\n{$msg->message}",
                    function ($m) use ($to, $msg) {
                        $m->to($to)->subject('New contact message — ' . brand_name())->replyTo($msg->email, $msg->name);
                    }
                );
            } catch (\Throwable $e) {
                Log::warning('Contact form mail failed: ' . $e->getMessage());
            }
        }

        return back()->with('contact_ok', true);
    }

    /** Blog listing — published posts only, newest first, with a featured hero. */
    public function blog(Request $request)
    {
        // Newest published post headlines the page (page 1 only); the grid shows
        // the rest so the featured post isn't duplicated below.
        $featured = $request->integer('page', 1) > 1
            ? null
            : BlogPost::where('status', 'published')->orderByDesc('published_at')->first();

        $posts = BlogPost::where('status', 'published')
            ->when($featured, fn ($q) => $q->where('id', '!=', $featured->id))
            ->orderByDesc('published_at')
            ->paginate(9);

        return view('frontend.blog.index', compact('posts', 'featured'));
    }

    /** A single published blog post + a few related reads. */
    public function blogShow(string $slug)
    {
        $post = BlogPost::where('slug', $slug)->where('status', 'published')->firstOrFail();
        $post->increment('views');

        $related = BlogPost::where('status', 'published')
            ->where('id', '!=', $post->id)
            ->orderByDesc('published_at')
            ->limit(3)
            ->get();

        return view('frontend.blog.show', compact('post', 'related'));
    }

    /** Dynamic web-app manifest (PWA install). Driven by admin PWA settings. */
    public function manifest()
    {
        $theme = front('pwa_theme_color') ?: '#C13584';
        $bg    = front('pwa_bg_color') ?: '#FBF8FC';
        $icon  = site_logo() ?: site_favicon();

        $manifest = [
            'name'             => front('pwa_name') ?: brand_name(),
            'short_name'       => front('pwa_short_name') ?: brand_name(),
            'description'      => front('seo_description') ?: front('tagline'),
            'start_url'        => url('/'),
            'scope'            => rtrim(url('/'), '/') . '/',
            'display'          => 'standalone',
            'background_color' => $bg,
            'theme_color'      => $theme,
            'icons'            => $icon ? [
                ['src' => $icon, 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => $icon, 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
            ] : [],
        ];

        return response()->json($manifest, 200, [
            'Content-Type'  => 'application/manifest+json',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /**
     * The service worker. Served from a route (not a static file) so we can send
     * Service-Worker-Allowed and widen its scope to the app root even on a
     * sub-folder install. Intentionally minimal — network-first, no aggressive
     * caching, so it can never serve stale app pages.
     */
    public function serviceWorker()
    {
        $js = <<<'JS'
self.addEventListener('install', function (e) { self.skipWaiting(); });
self.addEventListener('activate', function (e) { e.waitUntil(self.clients.claim()); });
self.addEventListener('fetch', function (e) {
  // Network-first; fall back to whatever the browser cache already holds.
  e.respondWith(fetch(e.request).catch(function () { return caches.match(e.request); }));
});
JS;

        return response($js, 200, [
            'Content-Type'           => 'application/javascript; charset=utf-8',
            'Service-Worker-Allowed' => rtrim(parse_url(url('/'), PHP_URL_PATH) ?: '/', '/') . '/',
            'Cache-Control'          => 'no-cache',
        ]);
    }

    /** A legal page (terms | privacy | cookies | refund). */
    public function legal(string $type)
    {
        $allowed = ['terms', 'privacy', 'cookies', 'refund'];
        abort_unless(in_array($type, $allowed, true), 404);

        // Each type has its own editorial blade (frontend/legal/<type>.blade.php)
        // that assembles numbered sections via <x-frontend.legal-page>.
        return view('frontend.legal.' . $type);
    }
}
