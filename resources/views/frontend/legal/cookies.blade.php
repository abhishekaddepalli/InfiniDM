@extends('frontend.layout')
@section('title', __('Cookie Policy'))

@section('content')
    @php
        $sections = [
            [
                'n' => '01', 'title' => __('What cookies are'),
                'body' => '<p>' . __('Cookies are small text files stored on your device. We use them, and similar technologies like local storage, to keep you signed in, remember your preferences, and understand how the site is used.') . '</p>',
            ],
            [
                'n' => '02', 'title' => __('Essential cookies'),
                'body' => '<p>' . __('Required for the site to work — authentication, security (CSRF), session state and your cookie choices. These cannot be switched off, and no consent is required for them.') . '</p>',
            ],
            [
                'n' => '03', 'title' => __('Analytics cookies'),
                'body' => '<p>' . __('With your consent, we use privacy-respecting analytics to measure traffic and improve the product. They are only set after you accept on the cookie banner, and you can withdraw consent at any time.') . '</p>',
            ],
            [
                'n' => '04', 'title' => __('Managing cookies'),
                'body' => '<p>' . __('Use the cookie banner to accept or decline non-essential cookies. You can also block or delete cookies in your browser settings, though some features may stop working if you block essential cookies.') . '</p>',
            ],
            [
                'n' => '05', 'title' => __('Contact'),
                'body' => '<p>' . __('Questions about this policy? Email') . ' <a href="mailto:' . brand_email('privacy') . '">' . brand_email('privacy') . '</a>.</p>',
            ],
        ];
    @endphp

    <x-frontend.legal-page :title="__('Cookie Policy')"
        :subtitle="__('The cookies we set, why, and how to control them.')"
        :updatedAt="'March 14, 2026'" :effective="'April 1, 2026'" :sections="$sections" />
@endsection
