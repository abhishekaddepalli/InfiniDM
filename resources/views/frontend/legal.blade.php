@extends('frontend.layout')
@section('title', $title)

@section('content')
@include('frontend.partials.page-hero', [
    'eyebrow' => __('Legal'),
    'title'   => $title,
    'meta'    => __('Last updated') . ': ' . \Illuminate\Support\Carbon::parse(setting('legal_updated_at', now()))->format('F j, Y'),
])

<main class="pb-24">
    <div class="container mx-auto px-4 sm:px-6 max-w-4xl">
        <div class="prose mt-6">{!! $body !!}</div>

        <div class="mt-16 p-8 rounded-[1.75rem] bg-muted border border-border flex flex-col sm:flex-row sm:items-center justify-between gap-6">
            <div>
                <h3 class="text-lg font-bold text-foreground">{{ __('Questions about this policy?') }}</h3>
                <p class="text-sm text-muted-foreground mt-1">{{ __('Our team is happy to help — reach out any time.') }}</p>
            </div>
            <a href="{{ route('page.contact') }}" class="shrink-0 inline-flex h-11 items-center justify-center rounded-xl ig-grad-soft btn-grad px-6 text-sm font-bold text-white shadow-lg shadow-primary/20 hover:opacity-90 transition-opacity">{{ __('Contact us') }}</a>
        </div>
    </div>
</main>
@endsection
