@extends('frontend.layout')
@section('title', __('Refund Policy'))

@section('content')
    @php
        $sections = [
            [
                'n' => '01', 'title' => __('Free trial'),
                'body' => '<p>' . __('Every plan starts with a free trial — no credit card required. You will not be charged until the trial ends and you choose a paid plan, so the best way to evaluate :brand is simply to try it.', ['brand' => brand_name()]) . '</p>',
            ],
            [
                'n' => '02', 'title' => __('14-day money-back guarantee'),
                'body' => '<p>' . __('If you are not satisfied with a paid subscription, email us within 14 days of your first payment and we will refund it in full — no forms, no interrogation.') . '</p>',
            ],
            [
                'n' => '03', 'title' => __('Monthly & annual plans'),
                'body' => '<ul>
                <li>' . __('Monthly plans: cancel anytime; your plan stays active until the end of the current period. Partial months are not refunded.') . '</li>
                <li>' . __('Annual plans: eligible for a pro-rated refund of unused full months within the first 30 days.') . '</li>
                </ul>',
            ],
            [
                'n' => '04', 'title' => __('Usage-based add-ons'),
                'body' => '<p>' . __('Add-on packs and usage credits are consumed as you use them and are non-refundable once used. Unused, unexpired credits may be refunded on request within 30 days of purchase.') . '</p>',
            ],
            [
                'n' => '05', 'title' => __('Non-refundable cases'),
                'body' => '<ul>
                <li>' . __('Accounts suspended for violating our Terms or Acceptable Use.') . '</li>
                <li>' . __('Requests made after the applicable window above.') . '</li>
                <li>' . __('Fees already paid to third parties (e.g. payment-processing fees).') . '</li>
                </ul>',
            ],
            [
                'n' => '06', 'title' => __('How to request a refund'),
                'body' => '<p>' . __('Email') . ' <a href="mailto:' . brand_email('billing') . '">' . brand_email('billing') . '</a> ' . __('from your account email with your invoice number. We process approved refunds to the original payment method within 5–10 business days.') . '</p>',
            ],
        ];
    @endphp

    <x-frontend.legal-page :title="__('Refund Policy')"
        :subtitle="__('Try it free, and if a paid plan is not right for you, get your money back. Here are the details.')"
        :updatedAt="'March 14, 2026'" :effective="'April 1, 2026'" :sections="$sections" />
@endsection
