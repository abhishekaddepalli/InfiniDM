@extends('frontend.layout')
@section('title', __('Privacy Policy'))

@section('content')
    @php
        $sections = [
            [
                'n' => '01', 'title' => __('What we collect'),
                'body' => '<p>' . __('We collect the information you give us and the data required to run automations on your behalf:') . '</p>
                <ul>
                <li><strong>' . __('Account data') . '</strong> — ' . __('name, email, password hash, billing details.') . '</li>
                <li><strong>' . __('Instagram data') . '</strong> — ' . __('your connected profile, and the messages, comments and story replies your automations respond to.') . '</li>
                <li><strong>' . __('Usage data') . '</strong> — ' . __('log data, device and browser information, and feature usage for reliability and support.') . '</li>
                </ul>',
            ],
            [
                'n' => '02', 'title' => __('How we use it'),
                'body' => '<p>' . __('To provide and improve the Service: delivering automations, syncing your inbox, generating analytics, processing payments, and providing support. We process this data as a processor on your behalf under our Data Processing Agreement.') . '</p>',
            ],
            [
                'n' => '03', 'title' => __('Meta / Instagram data'),
                'body' => '<p>' . __('When you connect Instagram through Meta login, we access only the permissions you grant and use them solely to operate the features you enable. We handle all Platform Data in line with Meta\'s Platform Terms and Developer Policies, and we do not sell it or use it for advertising.') . '</p>',
            ],
            [
                'n' => '04', 'title' => __('Sharing & sub-processors'),
                'body' => '<p>' . __('We never sell your data. We share it only with vetted sub-processors (hosting, payments, email, analytics) under contract, and when legally required. A current sub-processor list is available on request.') . '</p>',
            ],
            [
                'n' => '05', 'title' => __('Security'),
                'body' => '<p>' . __('Encryption in transit (TLS 1.3) and at rest (AES-256), least-privilege access, audit logging, and independent SOC 2 Type II assessment. Report any concern to') . ' <a href="mailto:' . brand_email('security') . '">' . brand_email('security') . '</a>.</p>',
            ],
            [
                'n' => '06', 'title' => __('Data retention'),
                'body' => '<p>' . __('We keep your data for as long as your account is active. On cancellation you can export your data for 30 days, after which it is permanently deleted, except where law requires longer retention.') . '</p>',
            ],
            [
                'n' => '07', 'title' => __('Your rights'),
                'body' => '<p>' . __('Depending on your region (GDPR, CCPA, DPDP Act 2023), you may access, correct, export, or delete your personal data, and object to certain processing. Email') . ' <a href="mailto:' . brand_email('privacy') . '">' . brand_email('privacy') . '</a> ' . __('and we will respond within 30 days.') . '</p>',
            ],
            [
                'n' => '08', 'title' => __('Cookies'),
                'body' => '<p>' . __('We use essential and, with your consent, analytics cookies. See our') . ' <a href="' . url('/legal/cookies') . '">' . __('Cookie Policy') . '</a> ' . __('for the full list and controls.') . '</p>',
            ],
            [
                'n' => '09', 'title' => __('Changes & contact'),
                'body' => '<p>' . __('We may update this policy; material changes are notified in advance. Questions? Email') . ' <a href="mailto:' . brand_email('privacy') . '">' . brand_email('privacy') . '</a>.</p>',
            ],
        ];
    @endphp

    <x-frontend.legal-page :title="__('Privacy Policy')"
        :subtitle="__('What we collect, why, and the control you keep over it. No surprises.')"
        :updatedAt="'March 14, 2026'" :effective="'April 1, 2026'" :sections="$sections" />
@endsection
