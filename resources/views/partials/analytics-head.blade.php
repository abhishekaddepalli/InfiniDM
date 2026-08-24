{{--
    Analytics / tracking — <head> injection.
    Renders the tags configured at /admin/settings/analytics. Gated on the
    analytics_enabled toggle. Admin-entered custom head HTML is output raw
    (admin-only, trusted). Included once near the end of <head>.
--}}
@php
    $anEnabled = (bool) \App\Models\Setting::get('analytics_enabled', true);
    $anGa4     = trim((string) \App\Models\Setting::get('analytics_ga4_id', ''));
    $anGtm     = trim((string) \App\Models\Setting::get('analytics_gtm_id', ''));
    $anPixel   = trim((string) \App\Models\Setting::get('analytics_meta_pixel_id', ''));
    $anClarity = trim((string) \App\Models\Setting::get('analytics_clarity_id', ''));
    $anHead    = (string) \App\Models\Setting::get('analytics_head_scripts', '');
@endphp
@if ($anEnabled)
    @if ($anGtm !== '')
        <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','{{ $anGtm }}');</script>
    @endif
    @if ($anGa4 !== '')
        <script async src="https://www.googletagmanager.com/gtag/js?id={{ $anGa4 }}"></script>
        <script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','{{ $anGa4 }}');</script>
    @endif
    @if ($anPixel !== '')
        <script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init','{{ $anPixel }}');fbq('track','PageView');</script>
        <noscript><img height="1" width="1" style="display:none" src="https://www.facebook.com/tr?id={{ $anPixel }}&ev=PageView&noscript=1"/></noscript>
    @endif
    @if ($anClarity !== '')
        <script>(function(c,l,a,r,i,t,y){c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y)})(window,document,"clarity","script","{{ $anClarity }}");</script>
    @endif
    @if (trim($anHead) !== '')
        {!! $anHead !!}
    @endif
@endif
