{{-- Public footer — WaDesk structure, Instaflow colours + routes/content. --}}
<footer class="relative bg-background border-t border-border pt-24 pb-12 overflow-hidden">
    <div class="absolute bottom-0 left-0 w-full h-[420px] bg-gradient-to-t from-primary/5 to-transparent pointer-events-none -z-10"></div>
    <div class="absolute top-0 right-0 w-[400px] h-[400px] bg-primary/5 blur-[120px] rounded-full -z-10"></div>

    <div class="container mx-auto px-4 sm:px-6">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-16 mb-20">
            {{-- Brand --}}
            <div class="lg:col-span-4 space-y-7">
                <a href="{{ url('/') }}" class="flex items-center gap-2.5">
                    @if($logo = site_logo())
                        <img src="{{ $logo }}" alt="{{ brand_name() }}" class="h-9 max-w-[160px] object-contain">
                    @else
                        <span class="ig-grad w-9 h-9 rounded-xl grid place-items-center"><svg viewBox="0 0 24 24" class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none"/></svg></span>
                        <span class="text-[19px] font-semibold tracking-tight text-foreground">{{ brand_name() }}</span>
                    @endif
                </a>
                <p class="text-muted-foreground text-base leading-relaxed max-w-sm">{{ front('footer_blurb') }}</p>
                <div class="flex items-center gap-3">
                    @foreach([['footer_social_ig','M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z','rect'],['footer_social_x','x',''],['footer_social_in','in','']] as $s)
                        @php($url = front($s[0]))
                        <a href="{{ $url }}" target="_blank" rel="noopener" class="h-10 w-10 rounded-xl bg-muted border border-border flex items-center justify-center text-muted-foreground hover:bg-primary hover:text-primary-foreground hover:border-primary transition-all text-[11px] font-bold uppercase">{{ $s[0] === 'footer_social_ig' ? 'ig' : ($s[0] === 'footer_social_x' ? 'x' : 'in') }}</a>
                    @endforeach
                </div>
            </div>

            {{-- Link columns --}}
            <div class="lg:col-span-4 grid grid-cols-2 gap-8">
                <div class="space-y-5">
                    <h3 class="text-xs font-bold uppercase tracking-[0.2em] text-foreground">{{ __('Pages') }}</h3>
                    <ul class="space-y-3.5">
                        @foreach([[url('/'),__('Home')],[route('page.features'),front('nav_features')],[url('/').'#pricing',front('nav_pricing')],[route('blog.index'),front('nav_blog')]] as $l)
                            <li><a href="{{ $l[0] }}" class="text-sm font-medium text-muted-foreground hover:text-primary transition-colors flex items-center group"><span class="h-px w-0 bg-primary mr-0 group-hover:w-3 group-hover:mr-2 transition-all"></span>{{ $l[1] }}</a></li>
                        @endforeach
                        @if(front_bool('about_show'))<li><a href="{{ route('page.about') }}" class="text-sm font-medium text-muted-foreground hover:text-primary transition-colors flex items-center group"><span class="h-px w-0 bg-primary mr-0 group-hover:w-3 group-hover:mr-2 transition-all"></span>{{ front('nav_about') }}</a></li>@endif
                        @if(front_bool('contact_show'))<li><a href="{{ route('page.contact') }}" class="text-sm font-medium text-muted-foreground hover:text-primary transition-colors flex items-center group"><span class="h-px w-0 bg-primary mr-0 group-hover:w-3 group-hover:mr-2 transition-all"></span>{{ front('nav_contact') }}</a></li>@endif
                    </ul>
                </div>
                <div class="space-y-5">
                    <h3 class="text-xs font-bold uppercase tracking-[0.2em] text-foreground">{{ __('Legal') }}</h3>
                    <ul class="space-y-3.5">
                        @foreach([['privacy',front('legal_privacy_title')],['terms',front('legal_terms_title')],['cookies',front('legal_cookies_title')],['refund',front('legal_refund_title')]] as $l)
                            <li><a href="{{ route('page.legal', $l[0]) }}" class="text-sm font-medium text-muted-foreground hover:text-primary transition-colors flex items-center group"><span class="h-px w-0 bg-primary mr-0 group-hover:w-3 group-hover:mr-2 transition-all"></span>{{ $l[1] }}</a></li>
                        @endforeach
                    </ul>
                </div>
            </div>

            {{-- CTA card --}}
            <div class="lg:col-span-4 lg:pl-8">
                <div class="p-8 rounded-[1.75rem] bg-muted border border-border relative overflow-hidden">
                    <div class="relative z-10 space-y-5">
                        <h3 class="text-xl font-bold tracking-tight text-foreground">{{ front('cta_title') }}</h3>
                        <p class="text-sm text-muted-foreground">{{ front('cta_subtitle') }}</p>
                        <a href="{{ route('register') }}" class="inline-flex h-12 items-center justify-center rounded-xl ig-grad-soft btn-grad px-6 text-sm font-bold text-white shadow-lg shadow-primary/20 hover:opacity-90 transition-opacity">
                            {{ front('cta_primary') }}
                            <svg viewBox="0 0 24 24" class="ml-2 h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                        </a>
                    </div>
                    <div class="absolute top-0 right-0 -mr-8 -mt-8 h-32 w-32 bg-primary/10 blur-3xl rounded-full"></div>
                </div>
            </div>
        </div>

        <div class="pt-10 border-t border-border">
            <div class="flex flex-wrap justify-center items-center gap-x-6 gap-y-3 text-center">
                <p class="text-sm text-muted-foreground font-medium">&copy; {{ date('Y') }} {{ brand_name() }}. {{ front('footer_copyright') }}</p>
                <div class="h-4 w-px bg-border hidden sm:block"></div>
                <div class="flex items-center gap-2"><div class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></div><span class="text-[10px] font-bold uppercase tracking-widest text-emerald-500/80">{{ __('All Systems Operational') }}</span></div>
            </div>
        </div>
    </div>
</footer>
