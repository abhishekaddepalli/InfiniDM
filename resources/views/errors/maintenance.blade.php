<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @if (function_exists('site_favicon') && ($fav = site_favicon()))<link rel="icon" href="{{ $fav }}">@endif
    <title>{{ __('Be right back') }} — {{ $brand ?? config('app.name') }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif;
            background: radial-gradient(1200px 600px at 50% -10%, #fdf2f8 0%, #f5f4f7 55%, #f5f4f7 100%);
            color: #1a1523; }
        .card { max-width: 460px; text-align: center; }
        .mark { width: 72px; height: 72px; border-radius: 20px; margin: 0 auto 22px; display: grid; place-items: center;
            background: linear-gradient(135deg, #F58529 0%, #DD2A7B 50%, #8134AF 100%); box-shadow: 0 12px 30px -12px rgba(193,53,132,.5); }
        h1 { font-size: 26px; margin: 0 0 10px; letter-spacing: -0.01em; }
        p { font-size: 14.5px; color: #6b6478; line-height: 1.6; margin: 0; }
        .dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #DD2A7B; margin-right: 7px; animation: pulse 1.4s ease-in-out infinite; }
        @keyframes pulse { 0%,100% { opacity: .35; } 50% { opacity: 1; } }
        .tag { font-family: ui-monospace, monospace; font-size: 11px; letter-spacing: .18em; text-transform: uppercase; color: #9a93a8; margin-bottom: 18px; }
    </style>
</head>
<body>
    <div class="card">
        <div class="mark">
            @if (function_exists('site_logo') && ($lg = site_logo()))
                <img src="{{ $lg }}" alt="" style="max-height:40px;max-width:56px;object-fit:contain">
            @else
                <svg viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="5.5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="#fff" stroke="none"/></svg>
            @endif
        </div>
        <div class="tag"><span class="dot"></span>{{ __('Maintenance') }}</div>
        <h1>{{ __('We\'ll be right back') }}</h1>
        <p>{{ __(':brand is down for a short bit of scheduled maintenance. Please check back soon.', ['brand' => $brand ?? config('app.name')]) }}</p>
    </div>
</body>
</html>
