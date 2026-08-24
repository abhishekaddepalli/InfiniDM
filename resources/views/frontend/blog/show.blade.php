{{--
 Public blog post — editorial article layout ported from the WaDesk frontend
 design, recoloured to Instagram + wired to the standalone BlogPost.
--}}
@extends('frontend.layout')
@section('title', $post->title)
@section('head')
    @if ($post->excerpt)<meta name="description" content="{{ \Illuminate\Support\Str::limit(strip_tags($post->excerpt), 155) }}">@endif
@endsection

@php
    $img = fn ($p) => $p && $p->cover_image
        ? (str_starts_with($p->cover_image, 'http') ? $p->cover_image : brand_asset($p->cover_image))
        : null;
    $readMins = fn ($p) => max(1, (int) ceil(str_word_count(strip_tags((string) $p->body)) / 200));
@endphp

@section('content')

    {{-- Prose typography for the article body (Tailwind CDN has no prose plugin). --}}
    @push('scripts')
        <style>
            .ig-article { color: #46394F; font-size: 17px; line-height: 1.78; }
            .ig-article > * + * { margin-top: 1.35em; }
            .ig-article h2 { font-family: "Instrument Serif", serif; font-size: 32px; line-height: 1.12; letter-spacing: -0.02em; color: #1A1320; margin-top: 1.8em; }
            .ig-article h3 { font-family: "Instrument Serif", serif; font-size: 25px; line-height: 1.2; color: #1A1320; margin-top: 1.6em; }
            .ig-article h4 { font-weight: 700; font-size: 18px; color: #1A1320; margin-top: 1.4em; }
            .ig-article a { color: #C13584; text-decoration: underline; text-underline-offset: 2px; }
            .ig-article strong { color: #1A1320; font-weight: 700; }
            .ig-article ul, .ig-article ol { padding-left: 1.4em; }
            .ig-article ul { list-style: disc; } .ig-article ol { list-style: decimal; }
            .ig-article li { margin-top: 0.5em; }
            .ig-article img { border-radius: 16px; max-width: 100%; height: auto; }
            .ig-article blockquote { border-left: 3px solid #C13584; padding-left: 1.1em; color: #6B5E74; font-style: italic; }
            .ig-article pre { background: #1A1320; color: #F4EFF6; padding: 1.1em 1.3em; border-radius: 14px; overflow-x: auto; font-family: "JetBrains Mono", monospace; font-size: 13.5px; }
            .ig-article :not(pre) > code { background: #F4EFF6; padding: 0.12em 0.4em; border-radius: 6px; font-family: "JetBrains Mono", monospace; font-size: 0.9em; }
            .ig-article hr { border: 0; border-top: 1px solid #EBE3EE; margin: 2em 0; }
        </style>
    @endpush

    <article class="bg-paper-0">
        <div class="relative overflow-hidden">
            <div class="absolute inset-0 grid-bg opacity-25 pointer-events-none"></div>
            <div class="absolute -top-28 -right-24 w-[440px] h-[440px] rounded-full bg-wa-mint/40 blur-bub"></div>

            <header class="relative max-w-[760px] mx-auto px-4 sm:px-6 pt-20 pb-8">
                <nav class="flex items-center gap-2 text-[11px] mono uppercase tracking-widest text-ink-500 mb-7">
                    <a href="{{ url('/') }}" class="hover:text-wa-deep">{{ __('Home') }}</a>
                    <span class="text-ink-400">/</span>
                    <a href="{{ route('blog.index') }}" class="hover:text-wa-deep">{{ __('Blog') }}</a>
                    <span class="text-ink-400">/</span>
                    <span class="text-ink-700 normal-case tracking-normal truncate max-w-[220px]">{{ $post->title }}</span>
                </nav>

                <h1 class="serif text-[40px] sm:text-[56px] leading-[0.98] tracking-[-0.025em] text-ink-900">{{ $post->title }}</h1>

                @if ($post->excerpt)
                    <p class="text-[17px] text-ink-700 leading-relaxed mt-5">{{ $post->excerpt }}</p>
                @endif

                <div class="mt-7 flex flex-wrap items-center gap-3 text-[12px] mono text-ink-500">
                    <span class="text-ink-700">{{ brand_name() }}</span>
                    <span class="text-ink-400">·</span>
                    <span>{{ optional($post->published_at)->format('M j, Y') }}</span>
                    <span class="text-ink-400">·</span>
                    <span>{{ $readMins($post) }} {{ __('min read') }}</span>
                </div>
            </header>
        </div>

        @if ($c = $img($post))
            <div class="max-w-[920px] mx-auto px-4 sm:px-6">
                <img src="{{ $c }}" alt="{{ $post->title }}" class="w-full rounded-3xl border border-paper-200 object-cover">
            </div>
        @endif

        <div class="max-w-[760px] mx-auto px-4 sm:px-6 py-12">
            <div class="ig-article max-w-[720px]">{!! $post->body !!}</div>
        </div>
    </article>

    {{-- Related --}}
    @if (!empty($related) && $related->count())
        <section class="bg-white">
            <div class="max-w-[1360px] mx-auto px-4 sm:px-6 lg:px-7 py-16 hairline-t">
                <div class="flex items-end justify-between mb-8">
                    <h2 class="serif text-[32px] sm:text-[44px] leading-tight tracking-[-0.02em]">
                        {{ __('Keep') }} <span class="italic text-wa-deep">{{ __('reading') }}</span></h2>
                    <a href="{{ route('blog.index') }}" class="text-[13px] font-semibold text-wa-deep hover:underline">{{ __('All posts →') }}</a>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach ($related as $r)
                        <a href="{{ route('blog.show', $r->slug) }}"
                            class="group flex flex-col hairline rounded-3xl bg-paper-50 overflow-hidden hover:border-wa-deep transition">
                            <div class="aspect-[16/10] overflow-hidden">
                                @if ($c = $img($r))
                                    <img src="{{ $c }}" alt="{{ $r->title }}" class="w-full h-full object-cover group-hover:scale-[1.04] transition duration-500">
                                @else
                                    <div class="w-full h-full ig-grad-soft"></div>
                                @endif
                            </div>
                            <div class="flex flex-col flex-1 p-6">
                                <h3 class="serif text-[22px] leading-tight group-hover:text-wa-deep transition">{{ $r->title }}</h3>
                                <div class="mt-auto pt-5 flex items-center gap-2.5 text-[11px] mono text-ink-500">
                                    <span>{{ optional($r->published_at)->format('M j, Y') }}</span>
                                    <span class="text-ink-400">·</span>
                                    <span>{{ $readMins($r) }} {{ __('min read') }}</span>
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- Back CTA --}}
    <section class="bg-paper-0">
        <div class="max-w-[1360px] mx-auto px-4 sm:px-6 lg:px-7 py-14 text-center">
            <a href="{{ route('blog.index') }}"
                class="inline-flex items-center gap-2 px-5 py-3 rounded-full bg-wa-deep text-paper-0 text-[13.5px] font-semibold hover:bg-wa-teal">
                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4l-4 4 4 4" /></svg>
                {{ __('Back to all posts') }}
            </a>
        </div>
    </section>

@endsection
