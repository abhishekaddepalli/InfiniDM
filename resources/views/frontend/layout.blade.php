{{--
    Public marketing layout — faithful port of the home.html <head>. The bespoke
    Tailwind config + CSS ships inline exactly as designed; this is a public,
    no-CSP page so the Tailwind Play CDN renders it pixel-perfect without fighting
    the app's compiled build. Every page sets @section('content'); page JS goes in
    @push('scripts'). Brand name/logo/favicon are dynamic (admin General settings).
--}}
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<meta name="csrf-token" content="{{ csrf_token() }}">
@php
    $metaTitle = trim($__env->yieldContent('title', brand_name()));
    $seoTitle  = front('seo_title') ?: $metaTitle;
    $seoDesc   = front('seo_description') ?: setting('seo_description', front('tagline'));
    $seoKw     = front('seo_keywords');
    $ogImage   = front('seo_og_image');
    $twitter   = front('seo_twitter');
    $pwaOn     = front_bool('pwa_enabled');
@endphp
<title>{{ $metaTitle }} — {{ front('tagline') }}</title>
<link rel="canonical" href="{{ url()->current() }}">
@if($seoDesc)<meta name="description" content="{{ $seoDesc }}">@endif
@if($seoKw)<meta name="keywords" content="{{ $seoKw }}">@endif
<meta property="og:type" content="website">
<meta property="og:site_name" content="{{ brand_name() }}">
<meta property="og:title" content="{{ $seoTitle }}">
@if($seoDesc)<meta property="og:description" content="{{ $seoDesc }}">@endif
<meta property="og:url" content="{{ url()->current() }}">
@if($ogImage)<meta property="og:image" content="{{ $ogImage }}">@endif
<meta name="twitter:card" content="{{ $ogImage ? 'summary_large_image' : 'summary' }}">
@if($twitter)<meta name="twitter:site" content="{{ $twitter }}">@endif
<meta name="twitter:title" content="{{ $seoTitle }}">
@if($seoDesc)<meta name="twitter:description" content="{{ $seoDesc }}">@endif
@if($ogImage)<meta name="twitter:image" content="{{ $ogImage }}">@endif
@if($fav = site_favicon())<link rel="icon" href="{{ $fav }}">@endif
@if($pwaOn)
<link rel="manifest" href="{{ route('pwa.manifest') }}">
<meta name="theme-color" content="{{ front('pwa_theme_color') ?: '#C13584' }}">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="{{ front('pwa_short_name') ?: brand_name() }}">
@if($appleIcon = (site_logo() ?: site_favicon()))<link rel="apple-touch-icon" href="{{ $appleIcon }}">@endif
@endif
<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Instrument+Serif:ital@0;1&family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<script>
tailwind.config = { darkMode: 'class', theme: { extend: {
  fontFamily: { serif:['"Instrument Serif"','serif'], sans:['Inter','sans-serif'], mono:['"JetBrains Mono"','monospace'] },
  colors: {
    // WaDesk design-token system, mapped to the Instaflow palette (RGB vars in
    // :root below). Lets ported WaDesk blades render verbatim in Instaflow colours.
    border:'rgb(var(--border) / <alpha-value>)', input:'rgb(var(--input) / <alpha-value>)', ring:'rgb(var(--ring) / <alpha-value>)',
    background:'rgb(var(--background) / <alpha-value>)', foreground:'rgb(var(--foreground) / <alpha-value>)',
    primary:{DEFAULT:'rgb(var(--primary) / <alpha-value>)','foreground':'rgb(var(--primary-foreground) / <alpha-value>)'},
    secondary:{DEFAULT:'rgb(var(--secondary) / <alpha-value>)','foreground':'rgb(var(--secondary-foreground) / <alpha-value>)'},
    muted:{DEFAULT:'rgb(var(--muted) / <alpha-value>)','foreground':'rgb(var(--muted-foreground) / <alpha-value>)'},
    accent:{DEFAULT:'rgb(var(--accent) / <alpha-value>)','foreground':'rgb(var(--accent-foreground) / <alpha-value>)',amber:'#FCAF45',coral:'#FD1D1D',sand:'#F4D9B0',plum:'#833AB4',sky:'#405DE6'},
    card:{DEFAULT:'rgb(var(--card) / <alpha-value>)','foreground':'rgb(var(--card-foreground) / <alpha-value>)'},
    popover:{DEFAULT:'rgb(var(--popover) / <alpha-value>)','foreground':'rgb(var(--popover-foreground) / <alpha-value>)'},
    // the bespoke home palette stays available for the home page's design
    ig:{ purple:'#833AB4', pink:'#E1306C', magenta:'#C13584', orange:'#F77737', amber:'#FCAF45', red:'#FD1D1D', blue:'#405DE6' },
    ink:{950:'#120C18',900:'#1A1320',800:'#2A2233',700:'#46394F',600:'#6B5E74',500:'#928699',400:'#B7AEBE',300:'#D9D2DD'},
    paper:{0:'#FFFFFF',50:'#FBF8FC',100:'#F4EFF6',200:'#EBE3EE',300:'#E2D8E8'},
    // WaDesk frontend tokens (wa-*/accent-amber/coral), recoloured to the
    // Instagram palette so ported WaDesk marketing blades render verbatim here.
    wa:{ deep:'#B12A78', teal:'#8A2160', green:'#E1306C', mint:'#FBE0EE', bubble:'#FCE7F1', chat:'#FBEAF3' }
  },
  borderRadius: { lg:'0.75rem', xl:'1rem', '2xl':'1.25rem' },
}}}
</script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
@verbatim
<style>
  html{scroll-behavior:smooth}
  body{font-family:Inter,sans-serif;color:#1A1320;background:#FBF8FC;-webkit-font-smoothing:antialiased;overflow-x:hidden}
  .serif{font-family:'Instrument Serif',serif;letter-spacing:-0.01em}
  .mono{font-family:'JetBrains Mono',monospace}
  a{color:inherit;text-decoration:none}
  .hairline{border:1px solid #EBE3EE}
  .hairline-b{border-bottom:1px solid #EBE3EE}
  .hairline-t{border-top:1px solid #EBE3EE}
  .hairline-r{border-right:1px solid #EBE3EE}
  .hairline-l{border-left:1px solid #EBE3EE}
  /* ---- WaDesk editorial marketing classes (ported for about/contact/legal) ---- */
  .grid-bg{background-image:linear-gradient(#EBE3EE 1px,transparent 1px),linear-gradient(90deg,#EBE3EE 1px,transparent 1px);background-size:56px 56px}
  .blur-bub{filter:blur(80px)}
  .feature-num{font-family:'Instrument Serif',serif;font-size:140px;line-height:.85;color:#EBE3EE;letter-spacing:-.04em}
  .feature-num-sm{font-family:'Instrument Serif',serif;font-size:64px;line-height:.85;color:#EBE3EE;letter-spacing:-.04em}
  .badge-num{font-family:'Instrument Serif',serif;font-size:13px;line-height:1;padding:3px 10px 4px;border:1px solid #EBE3EE;border-radius:999px;color:#B12A78;background:#FBF8FC;display:inline-block}
  .gradient-deep{background:linear-gradient(135deg,#833AB4 0%,#120C18 100%)}
  .glow-green{box-shadow:0 0 0 1px rgba(225,48,108,.4),0 0 24px -4px rgba(225,48,108,.5)}
  /* ---- Instagram-gradient recolour ----
     The ported WaDesk emphasis token `wa-deep` renders as a flat magenta.
     These overrides make every prominent surface — emphasis text, solid
     fills/buttons, and the big CTA — the Instagram gradient instead, so the
     whole marketing site is gradient (never flat magenta) in one place. */
  .text-wa-deep,.hover\:text-wa-deep:hover,.group:hover .group-hover\:text-wa-deep{
    background:linear-gradient(120deg,#833AB4,#E1306C,#F77737);
    -webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent;color:transparent}
  .bg-wa-deep,.hover\:bg-wa-deep:hover{
    background-image:linear-gradient(135deg,#833AB4 0%,#E1306C 55%,#F77737 100%)!important;
    background-color:transparent!important}
  .hover\:bg-wa-teal:hover{background-image:linear-gradient(135deg,#E1306C 0%,#F77737 100%)!important}
  .border-wa-deep,.hover\:border-wa-deep:hover,.focus\:border-wa-deep:focus{border-color:#C13584!important}
  .badge-num{color:#C13584}
  .ig-grad-cta{background:linear-gradient(135deg,#6A2C91 0%,#833AB4 22%,#C13584 52%,#E1306C 78%,#F77737 100%)}
  /* White card with an Instagram-gradient border (clean, high-contrast — used
     for the featured pricing tier instead of a heavy full-gradient fill). */
  .card-grad-border{border:2px solid transparent;background:linear-gradient(#fff,#fff) padding-box,linear-gradient(135deg,#833AB4,#E1306C,#F77737) border-box}
  .ig-grad{background:linear-gradient(135deg,#405DE6 0%,#833AB4 25%,#C13584 50%,#E1306C 70%,#F77737 90%,#FCAF45 100%)}
  .ig-grad-soft{background:linear-gradient(135deg,#833AB4 0%,#E1306C 55%,#F77737 100%)}
  .ig-text{background:linear-gradient(120deg,#833AB4,#E1306C,#F77737);-webkit-background-clip:text;background-clip:text;color:transparent}
  .ig-ring{background:linear-gradient(135deg,#F77737,#E1306C,#C13584,#833AB4);padding:2px;border-radius:9999px}
  .pill{display:inline-flex;align-items:center;gap:6px;padding:5px 12px;border-radius:999px;font-size:11px;font-weight:500}
  .tabular{font-variant-numeric:tabular-nums}
  .pulse-dot{animation:pulseD 1.6s ease-in-out infinite}
  @keyframes pulseD{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.4;transform:scale(1.35)}}
  .btn-grad{position:relative;overflow:hidden}
  .btn-grad::after{content:"";position:absolute;inset:0;background:linear-gradient(120deg,transparent,rgba(255,255,255,.35),transparent);transform:translateX(-130%);transition:.55s}
  .btn-grad:hover::after{transform:translateX(130%)}
  .card-hover{transition:transform .2s, box-shadow .2s, border-color .2s}
  .card-hover:hover{transform:translateY(-3px);box-shadow:0 20px 44px -26px rgba(193,53,132,0.4);border-color:#E1306C}
  .dot-pattern{background-image:radial-gradient(circle at 1px 1px, rgba(26,19,32,0.05) 1px, transparent 0);background-size:16px 16px}
  .glass{background:rgba(251,248,252,.8);backdrop-filter:blur(14px)}
  .float-a{animation:bob 7s ease-in-out infinite}
  .float-b{animation:bob2 9s ease-in-out infinite}
  @keyframes bob{0%,100%{transform:translateY(0)}50%{transform:translateY(-12px)}}
  @keyframes bob2{0%,100%{transform:translateY(0)}50%{transform:translateY(12px)}}
  .marquee{display:flex;gap:3.5rem;animation:slide 30s linear infinite;white-space:nowrap}
  @keyframes slide{to{transform:translateX(-50%)}}
  .reveal{opacity:0;transform:translateY(22px);transition:opacity .7s cubic-bezier(.2,.7,.3,1),transform .7s cubic-bezier(.2,.7,.3,1)}
  .reveal.in{opacity:1;transform:none}
  .ftab{position:relative;text-align:left;padding:18px 20px;border-radius:18px;transition:.2s;cursor:pointer;border:1px solid transparent}
  .ftab:hover{background:#fff}
  .ftab.on{background:#fff;border-color:#EBE3EE;box-shadow:0 16px 40px -28px rgba(26,19,32,.4)}
  .ftab.on::before{content:"";position:absolute;left:0;top:16px;bottom:16px;width:3px;border-radius:0 3px 3px 0;background:linear-gradient(180deg,#833AB4,#E1306C,#F77737)}
  .fpane{display:none}
  .fpane.on{display:block;animation:fade .45s ease}
  @keyframes fade{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:none}}
  .bar{transition:height 1s cubic-bezier(.2,.7,.3,1)}
  .msg{max-width:78%;padding:9px 13px;border-radius:16px;font-size:13px;line-height:1.35;opacity:0;transform:translateY(8px);animation:msgIn .4s forwards}
  @keyframes msgIn{to{opacity:1;transform:none}}
  .msg.them{background:#F4EFF6;border-bottom-left-radius:5px;align-self:flex-start}
  .msg.us{color:#fff;border-bottom-right-radius:5px;align-self:flex-end}
  .typing span{display:inline-block;width:6px;height:6px;border-radius:50%;background:#B7AEBE;margin:0 1px;animation:tp 1.2s infinite}
  .typing span:nth-child(2){animation-delay:.2s}.typing span:nth-child(3){animation-delay:.4s}
  @keyframes tp{0%,60%,100%{transform:translateY(0);opacity:.4}30%{transform:translateY(-4px);opacity:1}}
  .toggle-track{width:52px;height:28px;border-radius:999px;background:#EBE3EE;position:relative;cursor:pointer;transition:.25s}
  .toggle-track.on{background:linear-gradient(135deg,#833AB4,#E1306C)}
  .toggle-knob{position:absolute;top:3px;left:3px;width:22px;height:22px;border-radius:50%;background:#fff;box-shadow:0 2px 6px rgba(0,0,0,.2);transition:.25s}
  .toggle-track.on .toggle-knob{transform:translateX(24px)}
  .step{cursor:pointer;border-radius:18px;padding:16px 18px;border:1px solid transparent;transition:.2s;text-align:left;width:100%}
  .step:hover{background:#FBF8FC}
  .step.on{background:#FBF8FC;border-color:#EBE3EE;box-shadow:0 16px 40px -30px rgba(26,19,32,.4)}
  .sprog{height:3px;border-radius:99px;background:#EBE3EE;overflow:hidden;margin-top:12px;display:none}
  .step.on .sprog{display:block}
  .sprog i{display:block;height:100%;width:0;background:linear-gradient(90deg,#833AB4,#E1306C,#F77737)}
  .step.on .sprog i{animation:sfill 5s linear forwards}
  @keyframes sfill{to{width:100%}}
  .spane{display:none}
  .spane.on{display:block;animation:fade .45s ease}
  .tslide{animation:fade .5s ease}
  .tava{transition:.2s;opacity:.5;cursor:pointer}
  .tava.on{opacity:1;transform:scale(1.1)}
  .mini{width:38px;height:22px}
  .mini .toggle-knob{width:16px;height:16px}
  .mini.on .toggle-knob{transform:translateX(16px)}
  details summary::-webkit-details-marker{display:none}
  .prose-legal h2{font-family:'Instrument Serif',serif;font-size:26px;margin:1.4em 0 .4em}
  .prose-legal h3{font-weight:600;font-size:16px;margin:1.2em 0 .3em}
  .prose-legal p{color:#46394F;font-size:14.5px;line-height:1.7;margin:.7em 0}
  .prose-legal ul{list-style:disc;padding-left:1.3em;color:#46394F;font-size:14.5px;line-height:1.7}
  .prose-legal a{color:#E1306C}
  /* ---- WaDesk design tokens → Instaflow palette (RGB triplets) ---- */
  :root{
    --background:251 248 252; --foreground:26 19 32;
    --card:255 255 255; --card-foreground:26 19 32;
    --popover:255 255 255; --popover-foreground:26 19 32;
    --primary:193 53 132; --primary-foreground:255 255 255;
    --secondary:244 239 246; --secondary-foreground:26 19 32;
    --muted:244 239 246; --muted-foreground:107 94 116;
    --accent:225 48 108; --accent-foreground:255 255 255;
    --border:235 227 238; --input:235 227 238; --ring:193 53 132;
  }
  [x-cloak]{display:none !important}
  /* prose (Tailwind typography plugin isn't on the CDN — provide the essentials) */
  .prose{color:rgb(70 57 79);font-size:15px;line-height:1.75;max-width:none}
  .prose h1{font-family:'Instrument Serif',serif;font-size:34px;line-height:1.1;margin:0 0 .4em;color:rgb(26 19 32)}
  .prose h2{font-family:'Instrument Serif',serif;font-size:26px;margin:1.5em 0 .4em;color:rgb(26 19 32)}
  .prose h3{font-weight:700;font-size:17px;margin:1.3em 0 .3em;color:rgb(26 19 32)}
  .prose p{margin:.8em 0}
  .prose ul{list-style:disc;padding-left:1.35em;margin:.8em 0}
  .prose ol{list-style:decimal;padding-left:1.35em;margin:.8em 0}
  .prose li{margin:.35em 0}
  .prose a{color:#C13584;font-weight:600}
  .prose strong{color:rgb(26 19 32);font-weight:700}
  .prose blockquote{border-left:3px solid #E1306C;padding-left:1em;color:rgb(107 94 116);font-style:italic;margin:1em 0}
</style>
@endverbatim
@yield('head')
</head>
<body>

@include('frontend.partials.header')

@yield('content')

@include('frontend.partials.footer')

@include('frontend.partials.cookie-bar')

<script>window.__IF_PWA__ = { enabled: {{ $pwaOn ? 'true' : 'false' }}, sw: @json($pwaOn ? route('pwa.sw') : '') };</script>
<script src="{{ asset('frontend/site.js') }}?v=2"></script>
@stack('scripts')
</body>
</html>
