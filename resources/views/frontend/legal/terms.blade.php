@extends('frontend.layout')
@section('title', __('Terms of Service'))

@section('content')
    @php
        $sections = [
            [
                'n' => '01', 'title' => __('Acceptance of terms'),
                'body' => '<p>' . __('By creating an account, accessing, or using the :brand platform ("Service"), you agree to be bound by these Terms of Service ("Terms"). If you are accepting on behalf of a company, you represent that you have authority to bind that entity.', ['brand' => brand_name()]) . '</p>
                <p>' . __('If you do not agree to these Terms, do not use the Service.') . '</p>',
            ],
            [
                'n' => '02', 'title' => __('Account & registration'),
                'body' => '<p>' . __('To use the Service you must register for an account and connect an Instagram Professional account through Meta login, providing accurate and complete information.') . '</p>
                <ul>
                <li>' . __('You are responsible for safeguarding your account credentials and for all activity under your account.') . '</li>
                <li>' . __('You must notify us immediately at :email of any unauthorised access.', ['email' => brand_email('security')]) . '</li>
                <li>' . __('You must be at least 18 years old to create an account.') . '</li>
                </ul>',
            ],
            [
                'n' => '03', 'title' => __('Subscription & billing'),
                'body' => '<p>' . __('Paid plans are billed in advance on a recurring monthly or annual basis. Usage-based add-ons are deducted as you consume them.') . '</p>
                <h3>' . __('Billing cycle') . '</h3>
                <p>' . __('Subscription fees auto-renew at the end of each billing period unless cancelled before the renewal date. You can cancel anytime from your account settings.') . '</p>
                <h3>' . __('Taxes') . '</h3>
                <p>' . __('All fees are exclusive of applicable taxes (GST, VAT, sales tax). Taxes are added at checkout based on your billing address.') . '</p>',
            ],
            [
                'n' => '04', 'title' => __('Use of the service'),
                'body' => '<p>' . __('You agree to use the Service in compliance with all applicable laws, including data-protection laws (GDPR, CCPA, DPDP Act 2023), anti-spam laws, and Meta\'s Platform Terms and Instagram API policies.') . '</p>
                <p>' . __(':brand is a multi-tenant SaaS — you receive a non-exclusive, non-transferable, revocable licence to use the Service per your plan tier.', ['brand' => brand_name()]) . '</p>',
            ],
            [
                'n' => '05', 'title' => __('Acceptable use'),
                'body' => '<p>' . __('You may not use :brand to send spam, unsolicited messages, phishing, malware, or content that violates Instagram Community Guidelines or Meta\'s Messaging policies. Automated messages must respect the platform\'s messaging windows and opt-in rules.', ['brand' => brand_name()]) . '</p>
                <p>' . __('Violations may result in immediate suspension of your account without refund.') . '</p>',
            ],
            [
                'n' => '06', 'title' => __('Customer data & privacy'),
                'body' => '<p>' . __('You retain all rights to data uploaded to the Service. We process this data as a processor under our') . ' <a href="' . url('/legal/privacy') . '">' . __('Privacy Policy') . '</a> ' . __('and standard Data Processing Agreement (available on request).') . '</p>
                <ul>
                <li>' . __('Data residency: EU, US, or India — selected per account on Scale plans.') . '</li>
                <li>' . __('Encryption: in transit (TLS 1.3) and at rest (AES-256).') . '</li>
                <li>' . __('We never sell your data or your audience\'s data.') . '</li>
                </ul>',
            ],
            [
                'n' => '07', 'title' => __('Intellectual property'),
                'body' => '<p>' . __('The Service, including its software, design, and content, is owned by :brand and protected by intellectual-property laws. You may not copy, reverse-engineer, or resell any part of the Service without written permission.', ['brand' => brand_name()]) . '</p>',
            ],
            [
                'n' => '08', 'title' => __('Termination'),
                'body' => '<p>' . __('You may cancel at any time. We may suspend or terminate your account for breach of these Terms. On termination you may export your data for 30 days, after which it is permanently deleted.') . '</p>',
            ],
            [
                'n' => '09', 'title' => __('Limitation of liability'),
                'body' => '<p>' . __('The Service is provided "as is". To the fullest extent permitted by law, :brand is not liable for indirect, incidental, or consequential damages, and our total liability is limited to the fees you paid in the 12 months preceding the claim.', ['brand' => brand_name()]) . '</p>',
            ],
            [
                'n' => '10', 'title' => __('Changes & contact'),
                'body' => '<p>' . __('We may update these Terms; material changes are notified by email or in-app at least 14 days in advance. Continued use after changes take effect constitutes acceptance.') . '</p>
                <p>' . __('Questions? Email') . ' <a href="mailto:' . brand_email('legal') . '">' . brand_email('legal') . '</a>.</p>',
            ],
        ];
    @endphp

    <x-frontend.legal-page :title="__('Terms of Service')"
        :subtitle="__('The agreement between you and :brand when you use the platform. Written to be read — not to hide anything.', ['brand' => brand_name()])"
        :updatedAt="'March 14, 2026'" :effective="'April 1, 2026'" :sections="$sections" />
@endsection
