<x-layouts.instagram :title="__('Razorpay Subscription')" ig-active="plans">
    <main class="max-w-[600px] mx-auto px-4 py-16 text-center">
        <div class="bg-paper-0 border border-paper-200 rounded-2xl p-8 shadow-card space-y-5">
            <div class="w-14 h-14 rounded-full bg-wa-mint text-wa-deep mx-auto grid place-items-center">
                <svg viewBox="0 0 16 16" class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 8l3 3 7-7"/></svg>
            </div>
            <h2 class="font-serif text-[24px]">{{ __('Starting Razorpay Subscription...') }}</h2>
            <p class="text-[13px] text-ink-600">
                {{ __('Order') }} <strong class="font-mono">#{{ $order->order_number }}</strong> — {{ __('Amount:') }}
                <strong class="font-mono">{!! \App\Support\FormatSettings::formatIn($order->total_amount, $order->currency) !!}</strong>
            </p>
            <p class="text-[12px] text-ink-500">{{ __('If the payment modal does not open automatically, click the button below:') }}</p>

            <form id="razorpay-sub-form" action="{{ $callback }}" method="POST">
                @csrf
                <input type="hidden" name="razorpay_payment_id" id="razorpay_payment_id">
                <input type="hidden" name="razorpay_subscription_id" id="razorpay_subscription_id" value="{{ $subscription['id'] ?? '' }}">
                <input type="hidden" name="razorpay_signature" id="razorpay_signature">
                <button type="button" id="rzp-sub-button" class="px-6 py-3 rounded-full bg-wa-deep text-paper-0 text-[13px] font-semibold hover:bg-wa-teal transition">
                    {{ __('Pay & Subscribe with Razorpay') }}
                </button>
            </form>
        </div>
    </main>

    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <script>
        (function() {
            var options = {
                "key": "{{ $key_id }}",
                "subscription_id": "{{ $subscription['id'] ?? '' }}",
                "name": "{{ \App\Models\Setting::get('site_name', config('app.name', 'InstaDM')) }}",
                "description": "{{ __('Subscription') }} #{{ $order->order_number }}",
                "handler": function (response) {
                    document.getElementById('razorpay_payment_id').value = response.razorpay_payment_id;
                    document.getElementById('razorpay_subscription_id').value = response.razorpay_subscription_id;
                    document.getElementById('razorpay_signature').value = response.razorpay_signature;
                    document.getElementById('razorpay-sub-form').submit();
                },
                "prefill": {
                    "name": "{{ $order->customer_name }}",
                    "email": "{{ $order->customer_email }}"
                },
                "theme": {
                    "color": "#E1306C"
                },
                "modal": {
                    "ondismiss": function() {
                        window.location.href = "{{ route('checkout.cancel', $order->id) }}";
                    }
                }
            };
            var rzp1 = new Razorpay(options);
            document.getElementById('rzp-sub-button').onclick = function(e){
                rzp1.open();
                e.preventDefault();
            };
            setTimeout(function() { rzp1.open(); }, 400);
        })();
    </script>
</x-layouts.instagram>
