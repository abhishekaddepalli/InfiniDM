@php
    // DB-tolerant brand + assets: a 500 caused by the database being down must
    // NOT crash the error page itself, so every dynamic call is rescue()-wrapped
    // and falls back to the .env app name / no asset.
    $__brand = rescue(fn () => function_exists('site_name') ? site_name() : config('app.name'), config('app.name', 'InstaMagic'), false);
    $__fav   = rescue(fn () => function_exists('site_favicon') ? site_favicon() : null, null, false);
    $__logo  = rescue(fn () => function_exists('site_logo') ? site_logo() : null, null, false);
    $__authed = rescue(fn () => auth()->check(), false, false);
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    @if ($__fav)<link rel="icon" href="{{ $__fav }}">@endif
    <title>{{ $code }} · {{ $__brand }}</title>
    <style>
        *{box-sizing:border-box}
        body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;
            font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',system-ui,sans-serif;
            background:radial-gradient(1200px 600px at 50% -10%,#fdf2f8 0%,#f5f4f7 55%,#f5f4f7 100%);color:#1a1523}
        .card{max-width:520px;width:100%;text-align:center;background:#fff;border:1px solid #ebe3ee;border-radius:20px;
            padding:40px 32px;box-shadow:0 24px 60px -30px rgba(131,58,180,.35)}
        .mark{width:60px;height:60px;border-radius:16px;margin:0 auto 20px;display:grid;place-items:center;
            background:linear-gradient(135deg,#F58529 0%,#DD2A7B 50%,#8134AF 100%);box-shadow:0 12px 30px -12px rgba(193,53,132,.5)}
        .eyebrow{font-family:ui-monospace,monospace;font-size:10px;letter-spacing:.18em;text-transform:uppercase;color:#9a93a8;margin-bottom:6px}
        .code{font-size:92px;line-height:.9;font-weight:800;letter-spacing:-.02em;
            background:linear-gradient(135deg,#DD2A7B,#8134AF);-webkit-background-clip:text;background-clip:text;color:transparent}
        h1{font-size:25px;margin:14px 0 8px;letter-spacing:-.01em}
        p{font-size:14px;color:#6b6478;line-height:1.6;margin:0 auto;max-width:400px}
        .actions{margin-top:26px;display:flex;gap:10px;justify-content:center;flex-wrap:wrap}
        a.btn{display:inline-flex;align-items:center;gap:7px;padding:11px 20px;border-radius:999px;font-size:13px;font-weight:600;text-decoration:none;cursor:pointer}
        a.ghost{border:1px solid #ebe3ee;background:#fff;color:#46394f}
        a.ghost:hover{background:#faf8fc}
        a.primary{background:linear-gradient(135deg,#DD2A7B,#8134AF);color:#fff}
        a.primary:hover{opacity:.92}
        .foot{margin-top:26px;padding-top:18px;border-top:1px solid #ebe3ee;font-size:11.5px;color:#9a93a8;
            font-family:ui-monospace,monospace;letter-spacing:.14em;text-transform:uppercase}
    </style>
</head>
<body>
    <div class="card">
        <div class="mark">
            @if ($__logo)
                <img src="{{ $__logo }}" alt="" style="max-height:34px;max-width:48px;object-fit:contain">
            @else
                <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="5.5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="#fff" stroke="none"/></svg>
            @endif
        </div>
        <div class="eyebrow">{{ $eyebrow ?? __('Something went wrong') }}</div>
        <div class="code">{{ $code }}</div>
        <h1>{{ $headline }}</h1>
        <p>{{ $body }}</p>
        <div class="actions">
            <a class="btn ghost" href="{{ url('/') }}">{{ __('Go home') }}</a>
            @php
                $__ctaUrl   = $ctaUrl   ?? ($__authed ? url('/instagram') : url('/login'));
                $__ctaLabel = $ctaLabel ?? ($__authed ? __('Dashboard') : __('Sign in'));
            @endphp
            <a class="btn primary" href="{{ $__ctaUrl }}">{{ $__ctaLabel }} &rarr;</a>
        </div>
        <div class="foot">{{ $__brand }}</div>
    </div>
</body>
</html>
