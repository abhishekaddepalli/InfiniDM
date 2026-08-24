{{-- Shared WaDesk-style page header. Pass: $eyebrow?, $title, $highlight?, $lead?, $meta? --}}
<section class="pt-32 pb-4">
    <div class="container mx-auto px-4 sm:px-6 max-w-4xl">
        <div class="space-y-4 transition-all duration-700 transform" x-data="{ shown:false }" x-init="setTimeout(()=>shown=true,0)"
             :class="shown ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-4'">
            @if(!empty($eyebrow))
                <span class="inline-block text-xs font-bold uppercase tracking-[0.2em] text-primary">{{ $eyebrow }}</span>
            @endif
            <h1 class="text-4xl sm:text-5xl font-bold tracking-tight text-foreground">{{ $title }}@if(!empty($highlight)) <span class="ig-text italic">{{ $highlight }}</span>@endif</h1>
            @if(!empty($lead))<p class="text-lg text-muted-foreground max-w-2xl leading-relaxed">{{ $lead }}</p>@endif
            @if(!empty($meta))<p class="text-sm text-muted-foreground">{{ $meta }}</p>@endif
        </div>
    </div>
</section>
