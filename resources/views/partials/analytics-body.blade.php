{{--
    Analytics / tracking — start-of-<body> injection: GTM <noscript> fallback +
    admin-entered custom body HTML. Gated on analytics_enabled.
--}}
@php
    $anEnabled = (bool) \App\Models\Setting::get('analytics_enabled', true);
    $anGtm     = trim((string) \App\Models\Setting::get('analytics_gtm_id', ''));
    $anBody    = (string) \App\Models\Setting::get('analytics_body_scripts', '');
@endphp
@if ($anEnabled)
    @if ($anGtm !== '')
        <noscript><iframe src="https://www.googletagmanager.com/ns.html?id={{ $anGtm }}" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
    @endif
    @if (trim($anBody) !== '')
        {!! $anBody !!}
    @endif
@endif
