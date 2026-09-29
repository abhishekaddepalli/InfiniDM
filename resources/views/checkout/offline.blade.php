<x-layouts.instagram :title="__('Offline Payment')" ig-active="plans">
    <main class="max-w-[650px] mx-auto px-4 py-12">
        <div class="bg-paper-0 border border-paper-200 rounded-2xl p-6 sm:p-8 shadow-card space-y-6">
            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-wa-deep">{{ __('Offline Payment') }}</div>
            <h1 class="font-serif text-[28px] leading-tight">{{ __('Order Placed') }}</h1>
            <p class="text-[13px] text-ink-600">
                {{ __('Order Number:') }} <strong class="font-mono">#{{ $order->order_number }}</strong>
            </p>

            <div class="bg-paper-50 border border-paper-200 rounded-xl p-4 text-[13px] leading-relaxed text-ink-800">
                {{ $instructions }}
            </div>

            <a href="{{ route('instagram.plans') }}" class="inline-block px-6 py-3 rounded-full bg-wa-deep text-paper-0 text-[13px] font-semibold hover:bg-wa-teal transition">
                {{ __('Return to Plans') }}
            </a>
        </div>
    </main>
</x-layouts.instagram>
