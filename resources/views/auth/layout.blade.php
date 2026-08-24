{{--
    Signed-out shell — split screen (form left, showcase right).

    A thin wrapper: it owns <head> + the shared <body class="min-h-screen flex">
    and each page fills @section('body') with its OWN two columns, because the
    login and register showcases differ (mockups login.html / register.html).

    Deliberately NOT <x-layouts.instagram>: that layout resolves five host-only
    Blade components and five host-only helpers, and reads auth()->user() — none
    of which exist on a page whose whole purpose is that nobody is signed in yet.
    It re-uses the same stylesheet and tokens instead.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @if ($fav = (function_exists('site_favicon') ? site_favicon() : null))
        <link rel="icon" href="{{ $fav }}">
    @endif
    <title>@yield('title') — {{ brand_name() }}</title>

    {{-- Same pre-built stylesheet the feature layout loads, by plain URL: @vite
         reads a content-hashed manifest only a core build writes, and a
         standalone install never runs `npm run build`. filemtime rather than a
         version string so rebuilt assets still bust the cache. --}}
    @php
        $igAsset = function (string $file) {
            $rel = 'extensions/instagram/' . $file;
            $abs = public_path($rel);
            $v = is_file($abs) ? filemtime($abs) : 0;
            return asset($rel) . '?v=' . $v;
        };
    @endphp
    <link rel="stylesheet" href="{{ $igAsset('instaflow.css') }}">
</head>

{{-- Pinned to light: this shell is white regardless of any saved preference,
     and there is no signed-in user here to read one from. --}}
<body data-theme="paper" class="min-h-screen flex font-sans antialiased text-ink-900" style="background:#fff">

    @yield('body')

    {{-- Password reveal + strength meter. A module, not inline onclick, per the
         project's JS rule; the fields still work with the bundle blocked. --}}
    @if (is_file(public_path('extensions/instagram/instaflow-auth.js')))
        <script type="module" src="{{ $igAsset('instaflow-auth.js') }}"></script>
    @endif
</body>

</html>
