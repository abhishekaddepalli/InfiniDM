<x-layouts.instagram :title="__('Bank Transfer')" ig-active="plans">
    <main class="max-w-[650px] mx-auto px-4 py-12">
        <div class="bg-paper-0 border border-paper-200 rounded-2xl p-6 sm:p-8 shadow-card space-y-6">
            <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-wa-deep">{{ __('Bank Transfer') }}</div>
            <h1 class="font-serif text-[28px] leading-tight">{{ __('Complete your bank transfer') }}</h1>
            <p class="text-[13px] text-ink-600">
                {{ __('Please send') }} <strong class="font-mono">{!! \App\Support\FormatSettings::formatIn($order->total_amount, $order->currency) !!}</strong>
                {{ __('to the bank account details below. Include your Order Number as payment reference.') }}
            </p>

            <div class="bg-paper-50 border border-paper-200 rounded-xl p-4 text-[13px] font-mono space-y-2 text-ink-800">
                <div><span class="text-ink-500">{{ __('Order Number:') }}</span> <strong>#{{ $order->order_number }}</strong></div>
                @if(!empty($creds['beneficiary_name']))<div><span class="text-ink-500">{{ __('Beneficiary:') }}</span> {{ $creds['beneficiary_name'] }}</div>@endif
                @if(!empty($creds['bank_name']))<div><span class="text-ink-500">{{ __('Bank:') }}</span> {{ $creds['bank_name'] }}</div>@endif
                @if(!empty($creds['account_number']))<div><span class="text-ink-500">{{ __('Account Number:') }}</span> {{ $creds['account_number'] }}</div>@endif
                @if(!empty($creds['ifsc_or_swift']))<div><span class="text-ink-500">{{ __('IFSC / SWIFT:') }}</span> {{ $creds['ifsc_or_swift'] }}</div>@endif
                @if(!empty($creds['branch']))<div><span class="text-ink-500">{{ __('Branch:') }}</span> {{ $creds['branch'] }}</div>@endif
            </div>

            @if(!empty($creds['notes']))
                <div class="text-[12px] text-ink-600 bg-accent-amber/10 border border-accent-amber/30 rounded-xl p-3">
                    {{ $creds['notes'] }}
                </div>
            @endif

            <a href="{{ route('instagram.plans') }}" class="inline-block px-6 py-3 rounded-full bg-wa-deep text-paper-0 text-[13px] font-semibold hover:bg-wa-teal transition">
                {{ __('Done — View Plans') }}
            </a>
        </div>
    </main>
</x-layouts.instagram>
