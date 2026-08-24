{{--
    Admin shell — sidebar + main. Structure mirrors WaDesk's admin; the colours
    are Instagram, for free: instaflow.css already remaps wa-deep / wa-mint /
    paper / ink onto the Instagram palette, so the same utility classes render
    pink instead of green.

    Standalone, so it loads instaflow.css by plain URL and pulls in no host-only
    components or helpers.
--}}
@php $__adminActive = $active ?? ''; @endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('Admin')) — {{ site_name() }}</title>
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

<body data-theme="paper" class="min-h-screen font-sans antialiased bg-paper-50 text-ink-900">
    <div class="flex min-h-screen">

        <x-admin-sidebar :active="$__adminActive" />

        <main class="flex-1 min-w-0 flex flex-col">
            {{-- Top bar --}}
            <header class="h-14 shrink-0 bg-paper-0 border-b border-paper-200 flex items-center gap-3 px-5 sticky top-0 z-30">
                <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500">
                    <span class="uppercase tracking-[0.16em]">{{ __('Admin') }}</span>
                    <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 3l3 3-3 3"/></svg>
                    <span class="text-ink-900 normal-case tracking-normal">@yield('crumb', __('Overview'))</span>
                </div>
                <div class="ml-auto flex items-center gap-3">
                    <a href="{{ url('/instagram') }}" class="text-[12px] font-semibold text-ink-600 hover:text-ink-900 inline-flex items-center gap-1.5">
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="2.5" y="2.5" width="11" height="11" rx="3.5"/><circle cx="8" cy="8" r="2.6"/></svg>
                        {{ __('Open app') }}
                    </a>
                    <span class="w-8 h-8 rounded-full ig-grad-soft text-white text-[11px] font-semibold grid place-items-center">
                        {{ \Illuminate\Support\Str::of(auth()->user()->name ?? 'A')->trim()->limit(2, '')->upper() }}
                    </span>
                </div>
            </header>

            @if (session('success') || session('error'))
                <div class="px-5 pt-4">
                    <div class="rounded-xl px-4 py-3 text-[12.5px] {{ session('error') ? 'bg-accent-coral/10 border border-accent-coral/25 text-accent-coral' : 'bg-wa-mint border border-wa-deep/25 text-wa-deep' }}">
                        {{ session('error') ?: session('success') }}
                    </div>
                </div>
            @endif

            <div class="flex-1 p-5 sm:p-7">
                @yield('content')
            </div>
        </main>
    </div>
</body>

</html>
