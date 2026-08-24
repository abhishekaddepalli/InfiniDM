@props([
    'inline' => false,
])

{{--
    Standalone stand-in for WaDesk's <x-admin.flash>.

    Nineteen shared views open with this component, so it is not an admin-only
    concern despite the namespace — without it every one of them, including
    admin/settings/instagram.blade.php, dies on 'Unable to locate a class or
    view for component [admin.flash]'. That screen is the only way to enter the
    Meta app_id / app_secret / verify_token, so a missing file here means the
    install can never be configured.

    It lives under standalone/ for the same reason the admin layout does: core
    owns resources/views/components/admin/flash.blade.php, and shipping our own
    at that path would trip assert_no_core_collisions() and refuse the addon
    archive. The overlay re-roots it there for the standalone build only, so
    WaDesk keeps rendering core's copy untouched.

    The prop and session contract deliberately mirrors core's rather than the
    narrower success/error pair, because shared controllers flash 'status' far
    more often than anything else — narrowing it would make three dozen save
    confirmations vanish silently in standalone while still working in WaDesk.
--}}

@php
    // 'status' is the Laravel convention and what the shared Instagram
    // controllers flash; 'success' is accepted too since a few older admin
    // paths use it. Both render as the green banner.
    $status = session('status') ?: session('success');
    $error = session('error');
    $warning = session('warning');

    $base = $inline
        ? 'rounded-lg px-3 py-1.5 text-[11.5px] font-mono inline-flex items-center gap-2'
        : 'rounded-xl px-4 py-3 text-[12.5px] font-mono flex items-center gap-2.5 mb-4';
@endphp

@if ($status)
    <div class="{{ $base }} bg-wa-mint border border-wa-green/30 text-wa-deep">
        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8">
            <circle cx="8" cy="8" r="6" />
            <path d="M5.5 8.5l2 2 3-4" />
        </svg>
        <span>{{ $status }}</span>
    </div>
@endif

@if ($error)
    <div class="{{ $base }} bg-accent-coral/10 border border-accent-coral/30 text-accent-coral">
        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8">
            <circle cx="8" cy="8" r="6" />
            <path d="M8 5v3.5M8 11v.5" />
        </svg>
        <span>{{ $error }}</span>
    </div>
@endif

@if ($warning)
    {{-- Core paints this text #7B5A14 for contrast, but that arbitrary value is
         not in the prebuilt bundle and standalone never runs a Tailwind pass,
         so the class would resolve to nothing and leave the text default-inked.
         text-accent-amber is the nearest token that ships. --}}
    <div class="{{ $base }} bg-accent-amber/15 border border-accent-amber/40 text-accent-amber">
        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8">
            <path d="M8 2.5L14 13H2L8 2.5z" />
            <path d="M8 7v3M8 11.5v.5" />
        </svg>
        <span>{{ $warning }}</span>
    </div>
@endif

{{-- Validation errors, which core's flash leaves to each page. The settings
     screen validates app_id length and the graph-version pattern but renders
     no error surface of its own, so without this a rejected save bounces back
     looking like a no-op. $errors is always defined here — every route that
     reaches this component is in the 'web' group. --}}
@if ($errors->any())
    <div class="{{ $base }} bg-accent-coral/10 border border-accent-coral/30 text-accent-coral !items-start">
        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="1.8">
            <circle cx="8" cy="8" r="6" />
            <path d="M8 5v3.5M8 11v.5" />
        </svg>
        <span class="space-y-1">
            @foreach ($errors->all() as $message)
                <span class="block leading-relaxed">{{ $message }}</span>
            @endforeach
        </span>
    </div>
@endif
