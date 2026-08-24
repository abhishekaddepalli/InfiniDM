<?php

namespace App\Support;

/**
 * Front-of-site content registry — the SINGLE source of truth for every
 * editable line on the public marketing pages (home, features, about, contact,
 * legal) plus the shared header/footer.
 *
 * Each entry: key => [label, type, default]. Types: text | textarea | html |
 * url | bool. The public Blade views read a value with front('key'); the admin
 * "Front pages" form iterates groups() and renders one input per field, saving
 * back to the Setting store under the "front_" prefix. Because both sides read
 * this one array, a field added here shows up in the editor AND on the page with
 * no other change.
 *
 * Storage: values live in the existing settings table as front_<key>. Nothing
 * here needs a migration.
 */
class FrontContent
{
    /**
     * Groups shown as tabs/sections in the admin editor, in display order.
     * key => human label.
     */
    public static function groups(): array
    {
        return [
            'brand'        => 'Brand & header',
            'hero'         => 'Hero',
            'marquee'      => 'Trust strip',
            'features'     => 'Features section',
            'demo'         => 'Live demo section',
            'how'          => 'How it works',
            'stats'        => 'Stats band',
            'testimonials' => 'Testimonials',
            'pricing'      => 'Pricing intro',
            'faq'          => 'FAQ',
            'cta'          => 'Closing CTA',
            'footer'       => 'Footer',
            'about'        => 'About page',
            'featurespg'   => 'Features page',
            'pricingpg'    => 'Pricing page',
            'contact'      => 'Contact page',
            'shared'       => 'Shared (CTA & quote)',
            'legal'        => 'Legal pages',
            'seo'          => 'SEO & sharing',
            'cookies'      => 'Cookie consent',
            'pwa'          => 'PWA (installable app)',
        ];
    }

    /**
     * The full field map: group => [ key => [label, type, default] ].
     * Keys are stored as front_<key>.
     */
    public static function fields(): array
    {
        return [
            'brand' => [
                'tagline'        => ['Header tagline (screen-reader / meta)', 'text', 'Instagram on autopilot'],
                'nav_features'   => ['Nav · Features label', 'text', 'Features'],
                'nav_pricing'    => ['Nav · Pricing label', 'text', 'Pricing'],
                'nav_blog'       => ['Nav · Blog label', 'text', 'Blog'],
                'nav_about'      => ['Nav · About label', 'text', 'About'],
                'nav_contact'    => ['Nav · Contact label', 'text', 'Contact'],
                'nav_signin'     => ['Button · Sign in', 'text', 'Sign in'],
                'nav_cta'        => ['Button · Get started', 'text', 'Start free'],
            ],

            'hero' => [
                'hero_badge'      => ['Badge pill', 'text', 'Now with AI auto-replies'],
                'hero_title'      => ['Headline (before accent)', 'text', 'Your Instagram,'],
                'hero_accent'     => ['Headline accent word', 'text', 'autopilot'],
                'hero_subtitle'   => ['Sub-headline', 'textarea', 'Schedule posts, auto-reply to DMs and comments, run ads, and track what actually grows your account — all from one calm dashboard.'],
                'hero_cta1'       => ['Primary button', 'text', 'Start free — no card'],
                'hero_cta2'       => ['Secondary button', 'text', 'Try the live demo'],
                'hero_rating'     => ['Rating number', 'text', '4.9'],
                'hero_proof'      => ['Social-proof line', 'text', 'Loved by 12,000+ creators & brands'],
            ],

            'marquee' => [
                'marquee_label'  => ['Strip label', 'text', 'Trusted by teams at'],
                'marquee_items'  => ['Logos / names (comma separated)', 'textarea', 'Nordwave, Bloomly, Pixelhaus, Studio Kern, Lumen & Co, Verta, Fernbright, Marigold'],
            ],

            'features' => [
                'feat_eyebrow'   => ['Eyebrow', 'text', 'Explore the product'],
                'feat_title'     => ['Title', 'text', 'Click through what Instaflow does'],
                'feat_subtitle'  => ['Subtitle', 'text', 'Pick a tool on the left — the preview updates live.'],
                'feat1_title'    => ['Tool 1 · title', 'text', 'Smart scheduling'],
                'feat1_desc'     => ['Tool 1 · description', 'text', "Post at each account's best time"],
                'feat2_title'    => ['Tool 2 · title', 'text', 'AI auto-replies'],
                'feat2_desc'     => ['Tool 2 · description', 'text', 'Answer DMs & comments in your voice'],
                'feat3_title'    => ['Tool 3 · title', 'text', 'Ad campaigns'],
                'feat3_desc'     => ['Tool 3 · description', 'text', 'Launch & A/B test in-app'],
                'feat4_title'    => ['Tool 4 · title', 'text', 'Real analytics'],
                'feat4_desc'     => ['Tool 4 · description', 'text', 'Saves & follows, not vanity metrics'],
            ],

            'demo' => [
                'demo_eyebrow'   => ['Eyebrow', 'text', 'Try it yourself'],
                'demo_title'     => ['Title', 'text', 'Type a DM. Watch Instaflow reply.'],
                'demo_subtitle'  => ['Subtitle', 'textarea', 'This is the real auto-reply engine. Send anything — it answers in your brand voice in under half a second.'],
                'demo_stat1'     => ['Stat 1 value', 'text', '0.4s'],
                'demo_stat1l'    => ['Stat 1 label', 'text', 'Avg reply time'],
                'demo_stat2'     => ['Stat 2 value', 'text', '24/7'],
                'demo_stat2l'    => ['Stat 2 label', 'text', 'Always on'],
                'demo_stat3'     => ['Stat 3 value', 'text', '92%'],
                'demo_stat3l'    => ['Stat 3 label', 'text', 'Resolved solo'],
            ],

            'how' => [
                'how_eyebrow'    => ['Eyebrow', 'text', 'Live in 3 minutes'],
                'how_title'      => ['Title', 'text', 'From connect to autopilot.'],
                'how_subtitle'   => ['Subtitle', 'text', 'Click a step — or just watch it play.'],
                'how1_title'     => ['Step 1 · title', 'text', 'Connect accounts'],
                'how1_desc'      => ['Step 1 · description', 'text', "Link up to 5 profiles with Instagram's official login — no password sharing."],
                'how2_title'     => ['Step 2 · title', 'text', 'Set your rules'],
                'how2_desc'      => ['Step 2 · description', 'text', 'Flip on auto-replies, pick a posting cadence, preview everything before it goes live.'],
                'how3_title'     => ['Step 3 · title', 'text', 'Let it run'],
                'how3_desc'      => ['Step 3 · description', 'text', 'Instaflow posts, replies, and reports while you sleep. Check in when you feel like it.'],
            ],

            'stats' => [
                'stat1_num'      => ['Stat 1 number', 'text', '12'],
                'stat1_suffix'   => ['Stat 1 suffix', 'text', 'k+'],
                'stat1_label'    => ['Stat 1 label', 'text', 'Creators & brands'],
                'stat2_num'      => ['Stat 2 number', 'text', '4.2'],
                'stat2_suffix'   => ['Stat 2 suffix', 'text', 'M'],
                'stat2_label'    => ['Stat 2 label', 'text', 'DMs auto-answered'],
                'stat3_num'      => ['Stat 3 number', 'text', '86'],
                'stat3_suffix'   => ['Stat 3 suffix', 'text', '%'],
                'stat3_label'    => ['Stat 3 label', 'text', 'Handled without a human'],
                'stat4_num'      => ['Stat 4 number', 'text', '9'],
                'stat4_suffix'   => ['Stat 4 suffix', 'text', 'hrs'],
                'stat4_label'    => ['Stat 4 label', 'text', 'Saved per week, avg.'],
            ],

            'testimonials' => [
                't1_quote' => ['Quote 1', 'textarea', "We went from drowning in DMs to answering 90% automatically — in my brand's voice. Instaflow paid for itself in a week."],
                't1_name'  => ['Name 1', 'text', 'Maya Reyes'],
                't1_role'  => ['Role 1', 'text', 'Founder, Bloomly · 214k followers'],
                't2_quote' => ['Quote 2', 'textarea', 'Scheduling a month of reels now takes one coffee. The best-time queue grew our reach 32% in six weeks.'],
                't2_name'  => ['Name 2', 'text', 'Jonas Kern'],
                't2_role'  => ['Role 2', 'text', 'Creative Director, Studio Kern · 88k followers'],
                't3_quote' => ['Quote 3', 'textarea', 'Auto-comments quietly handle spam and FAQs before I even open the app. It feels like a tiny, tireless team.'],
                't3_name'  => ['Name 3', 'text', 'Priya Nair'],
                't3_role'  => ['Role 3', 'text', 'Marketing Lead, Verta · 156k followers'],
            ],

            'pricing' => [
                'price_eyebrow'  => ['Eyebrow', 'text', 'Simple pricing'],
                'price_title'    => ['Title', 'text', 'Start free. Scale when ready.'],
                'price_note'     => ['Toggle note (annual saving)', 'text', '−20%'],
            ],

            'faq' => [
                'faq_eyebrow' => ['Eyebrow', 'text', 'Good to know'],
                'faq_title'   => ['Title', 'text', 'Questions, answered.'],
                'faq1_q' => ['Q1', 'text', 'Is Instaflow safe for my account?'],
                'faq1_a' => ['A1', 'textarea', "Yes. We connect through Instagram's official Graph API — we never ask for your password and stay well within platform limits."],
                'faq2_q' => ['Q2', 'text', 'Do auto-replies sound robotic?'],
                'faq2_a' => ['A2', 'textarea', 'You train the tone in a minute, and can review every message before it sends. Anything sensitive is instantly handed to you.'],
                'faq3_q' => ['Q3', 'text', 'Can I cancel anytime?'],
                'faq3_a' => ['A3', 'textarea', 'Anytime, one click, no phone call. The free plan stays free for as long as you like.'],
                'faq4_q' => ['Q4', 'text', 'How many accounts can I connect?'],
                'faq4_a' => ['A4', 'textarea', 'One on Starter, five on Pro, and unlimited on Team — switch between them from a single dashboard.'],
            ],

            'cta' => [
                'cta_title'     => ['Title', 'text', 'Put Instagram on autopilot'],
                'cta_subtitle'  => ['Subtitle', 'textarea', 'Join 12,000+ creators saving 9 hours a week. Free to start, no card required.'],
                'cta_primary'   => ['Primary button', 'text', 'Start free'],
                'cta_secondary' => ['Secondary button', 'text', 'See the dashboard'],
            ],

            'footer' => [
                'footer_blurb'    => ['About blurb', 'textarea', 'The calm way to run Instagram — scheduling, replies, ads, and analytics in one place.'],
                'footer_social_ig'=> ['Instagram URL', 'url', '#'],
                'footer_social_x' => ['X / Twitter URL', 'url', '#'],
                'footer_social_in'=> ['LinkedIn URL', 'url', '#'],
                'footer_copyright'=> ['Copyright line', 'text', 'Not affiliated with Instagram or Meta.'],
            ],

            'about' => [
                'about_show'                    => ['Show About page', 'bool', '1'],
                'about.hero.eyebrow'            => ['Hero · eyebrow', 'text', '— About us'],
                'about.hero.headline'           => ['Hero · headline (HTML)', 'html', 'We built <span class="italic text-wa-deep">one place</span><br>to run your whole<br><span class="italic">Instagram.</span>'],
                'about.hero.intro'              => ['Hero · intro', 'textarea', 'Comments, story replies, DMs, flows, and AI — one workspace that turns every Instagram interaction into a conversation, built to work together from day one.'],
                'about.origin-story.eyebrow'    => ['Origin · eyebrow', 'text', '— How we started'],
                'about.origin-story.para1'      => ['Origin · paragraph 1', 'textarea', 'In 2023, our founders were running a small boutique entirely through Instagram. Comments piling up under every post. DMs at midnight. Story replies lost forever. A spreadsheet nobody kept up with.'],
                'about.origin-story.para3'      => ['Origin · closing line (HTML)', 'html', 'A year later <span class="italic text-wa-deep">6,400 creators & brands</span> automate 90M DMs a month through it.'],
                'about.values.headline'         => ['Values · headline (HTML)', 'html', 'Strong opinions,<br>loosely <span class="italic text-wa-deep">held.</span>'],
                'about.timeline.headline'       => ['Timeline · headline (HTML)', 'html', 'From one boutique<br>to <span class="italic text-wa-deep">90M DMs / month.</span>'],
                'about.press.headline'          => ['Press · headline (HTML)', 'html', 'What people<br>are <span class="italic text-wa-deep">writing.</span>'],
                'about.backers.headline'        => ['Backers · headline (HTML)', 'html', 'Funded by people who<br>have <span class="italic text-wa-deep">shipped.</span>'],
            ],

            'featurespg' => [
                'features.hero.eyebrow'   => ['Hero · eyebrow', 'text', '— Features'],
                'features.hero.headline'  => ['Hero · headline (HTML)', 'html', 'Everything your<br>Instagram needs —<br><span class="italic text-wa-deep">one workspace.</span>'],
                'features.hero.intro'     => ['Hero · intro', 'textarea', 'Comment automation, story replies, DM flows, a unified inbox, an AI agent, broadcasts, templates and analytics — built to work together, never bolted on.'],
                'features.bento.eyebrow'  => ['Toolkit · eyebrow', 'text', 'The toolkit'],
                'features.bento.headline' => ['Toolkit · headline (HTML)', 'html', 'Eight tools. <span class="italic text-wa-deep">One login.</span>'],
                'features.pillars.eyebrow'=> ['Pillars · eyebrow', 'text', 'Why it works'],
                'features.pillars.headline'=> ['Pillars · headline (HTML)', 'html', 'Automate. Reply. <span class="italic text-wa-deep">Grow.</span>'],
            ],

            'pricingpg' => [
                'pricing.hero.eyebrow'  => ['Hero · eyebrow', 'text', '— Pricing'],
                'pricing.hero.headline' => ['Hero · headline (HTML)', 'html', 'Simple, honest,<br>and <span class="italic text-wa-deep">flat.</span>'],
                'pricing.hero.intro'    => ['Hero · intro', 'textarea', 'Pick a plan, connect Instagram, and go live in minutes. Free trial, no credit card, cancel anytime and keep your data.'],
                'pricing.honest_label'  => ['Honesty band · label', 'text', 'Honest about pricing'],
            ],

            'contact' => [
                'contact_show'          => ['Show Contact page', 'bool', '1'],
                'contact.hero.eyebrow'  => ['Hero · eyebrow', 'text', 'Contact us'],
                'contact.hero.headline' => ['Hero · headline (HTML)', 'html', 'A real human<br>replies inside <span class="italic text-wa-deep">four hours.</span>'],
                'contact.hero.intro'    => ['Hero · intro', 'textarea', 'Sales, support, partnerships, security — every email lands in the same inbox, and someone on our team replies. No tickets, no bots, no escalation queues.'],
                'contact.form.headline' => ['Form · headline (HTML)', 'html', 'Tell us what<br>you are <span class="italic text-wa-deep">shipping.</span>'],
                'contact_email'         => ['Support email', 'text', 'hello@instaflow.app'],
                'contact_phone'         => ['Phone (optional)', 'text', ''],
                'contact_address'       => ['Address (optional)', 'textarea', ''],
            ],

            'shared' => [
                'cta-final.kicker'          => ['CTA · kicker', 'text', 'Ready when you are'],
                'cta-final.headline'        => ['CTA · headline (HTML)', 'html', 'Turn your next<br><span class="italic text-wa-green">100,000</span> DMs into sales.'],
                'cta-final.subtitle'        => ['CTA · subtitle', 'textarea', 'Live in 4 minutes. No credit card. Cancel anytime, keep your data.'],
                'cta-final.primary_label'   => ['CTA · primary button', 'text', 'Start free trial'],
                'cta-final.secondary_label' => ['CTA · secondary button', 'text', 'Book a demo'],
                'pull-quote.quote'          => ['Quote · body (HTML)', 'html', 'We replaced ManyChat, a spreadsheet, and a part-time VA. Story-reply automation pushed our DM reply rate from <span class="italic">21%</span> to <span class="italic text-wa-deep">88%</span> — and every comment now turns into a conversation. Sales are up, and I sleep again.'],
                'pull-quote.author'         => ['Quote · author name', 'text', 'Priya Ramaswamy'],
                'pull-quote.author_role'    => ['Quote · author role', 'text', 'Founder, Bloom Studio · Mumbai'],
            ],

            'legal' => [
                'legal_terms_title'   => ['Terms · title', 'text', 'Terms & Conditions'],
                'legal_terms_body'    => ['Terms · body (HTML)', 'html', '<p>By using this service you agree to the following terms. Please read them carefully.</p>'],
                'legal_privacy_title' => ['Privacy · title', 'text', 'Privacy Policy'],
                'legal_privacy_body'  => ['Privacy · body (HTML)', 'html', '<p>We respect your privacy. This policy explains what we collect and how we use it.</p>'],
                'legal_cookies_title' => ['Cookies · title', 'text', 'Cookie Policy'],
                'legal_cookies_body'  => ['Cookies · body (HTML)', 'html', '<p>We use cookies to keep you signed in and to understand how the product is used.</p>'],
                'legal_refund_title'  => ['Refund · title', 'text', 'Refund Policy'],
                'legal_refund_body'   => ['Refund · body (HTML)', 'html', '<p>If you are not happy with a paid plan, contact us within 14 days for a full refund.</p>'],
            ],

            'seo' => [
                'seo_title'       => ['Meta title (blank = brand name)', 'text', ''],
                'seo_description' => ['Meta description', 'textarea', 'Schedule posts, auto-reply to DMs and comments, run ads and track real growth — all from one calm Instagram dashboard.'],
                'seo_keywords'    => ['Meta keywords (comma separated)', 'text', 'instagram scheduler, instagram automation, dm auto reply, instagram ads, instagram analytics'],
                'seo_og_image'    => ['Social share image URL (1200×630)', 'url', ''],
                'seo_twitter'     => ['Twitter/X handle (with @)', 'text', ''],
            ],

            'cookies' => [
                'cookie_show'    => ['Show the cookie-consent bar', 'bool', '1'],
                'cookie_text'    => ['Consent message', 'textarea', 'We use cookies to keep you signed in and to understand how the product is used. By continuing you agree to our use of cookies.'],
                'cookie_accept'  => ['Accept button label', 'text', 'Accept'],
                'cookie_decline' => ['Decline button label', 'text', 'Decline'],
                'cookie_link'    => ['Policy link URL (blank = Cookie page)', 'url', ''],
            ],

            'pwa' => [
                'pwa_enabled'     => ['Enable install-to-home-screen (PWA)', 'bool', '1'],
                'pwa_name'        => ['App name (blank = brand name)', 'text', ''],
                'pwa_short_name'  => ['Short name (home-screen label)', 'text', 'Instaflow'],
                'pwa_theme_color' => ['Theme colour', 'text', '#C13584'],
                'pwa_bg_color'    => ['Background colour', 'text', '#FBF8FC'],
            ],
        ];
    }

    /** Flat key => meta map across all groups. */
    public static function flat(): array
    {
        $flat = [];
        foreach (self::fields() as $group => $fields) {
            foreach ($fields as $key => $meta) {
                $flat[$key] = $meta + ['group' => $group];
            }
        }
        return $flat;
    }

    /** The built-in default for a key (before any admin edit). Index 2 = default. */
    public static function default(string $key, $fallback = ''): string
    {
        $flat = self::flat();
        return (string) ($flat[$key][2] ?? $fallback);
    }

    /**
     * Resolve a front value: the admin-saved setting front_<key>, else the
     * registry default, else the passed fallback. Never throws.
     */
    public static function get(string $key, $fallback = null): string
    {
        $def = self::default($key, $fallback ?? '');
        $val = setting('front_' . $key, $def);
        return $val === null ? (string) $def : (string) $val;
    }
}
