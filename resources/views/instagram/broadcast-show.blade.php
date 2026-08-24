<x-layouts.instagram :title="__('Bulk DM detail')" ig-active="broadcast" page="instagram-broadcast-show">
    @php
        $pillFor = [
            'sent'           => 'bg-wa-bubble text-wa-deep',
            'failed'         => 'bg-ig-pink/10 text-ig-pink',
            'skipped_window' => 'bg-paper-100 text-ink-500',
            'pending'        => 'bg-amber-100 text-amber-700',
        ];
        $total   = max(1, (int) $bcast->total);
        $pending = max(0, (int) $bcast->total - (int) $bcast->sent - (int) $bcast->failed);
        $sentPct = round($bcast->sent / $total * 100);
        $failPct = round($bcast->failed / $total * 100);
        $handle  = $account ? ('@' . ltrim($account->username ?? $account->name ?? ('#' . $account->id), '@')) : '—';
    @endphp
    <div class="max-w-[1140px] mx-auto px-6 py-6">

        {{-- ===== HEADER ===== --}}
        <section class="pb-4">
            <a href="{{ url('/instagram/broadcast') }}" class="inline-flex items-center gap-1 text-[12px] text-ink-500 hover:text-ink-900 mb-2"><svg viewBox="0 0 12 12" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M7.5 2.5l-3.5 3.5 3.5 3.5"/></svg>{{ __('All bulk DMs') }}</a>
            <div class="flex flex-wrap items-center gap-2.5 mb-1.5 text-[11px] mono uppercase tracking-[0.18em] text-ink-500">
                <span>{{ __('Bulk DM') }}</span><span class="w-1 h-1 rounded-full bg-ink-400"></span><span>#{{ $bcast->id }}</span>
                <span class="w-1 h-1 rounded-full bg-ink-400"></span><span class="normal-case tracking-normal text-ink-600">{{ $handle }}</span>
                @if ($bcast->created_at)<span class="w-1 h-1 rounded-full bg-ink-400"></span><span class="normal-case tracking-normal">{{ $bcast->created_at->diffForHumans() }}</span>@endif
                <span class="pill {{ ($bcast->status === 'done') ? 'bg-wa-bubble text-wa-deep' : (($bcast->status === 'running') ? 'bg-amber-100 text-amber-700' : 'bg-paper-100 text-ink-600') }} capitalize">{{ $bcast->status }}</span>
            </div>
            <h1 class="serif text-[30px] leading-tight">{{ __('Bulk DM') }} <span class="ig-text italic">#{{ $bcast->id }}</span></h1>
        </section>

        <x-admin.flash />

        {{-- ===== KPIs ===== --}}
        <section class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-3">
            <div class="bg-white hairline rounded-2xl p-4"><div class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Recipients') }}</div><div class="serif text-[28px] leading-none mt-1.5 tabular">{{ number_format($bcast->total) }}</div></div>
            <div class="bg-white hairline rounded-2xl p-4"><div class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Sent') }}</div><div class="serif text-[28px] leading-none mt-1.5 tabular text-wa-deep">{{ number_format($bcast->sent) }}</div></div>
            <div class="bg-white hairline rounded-2xl p-4"><div class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Failed') }}</div><div class="serif text-[28px] leading-none mt-1.5 tabular {{ $bcast->failed ? 'text-ig-pink' : '' }}">{{ number_format($bcast->failed) }}</div></div>
            <div class="bg-white hairline rounded-2xl p-4"><div class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Pending') }}</div><div class="serif text-[28px] leading-none mt-1.5 tabular {{ $pending ? 'text-amber-600' : '' }}">{{ number_format($pending) }}</div></div>
            <div class="bg-white hairline rounded-2xl p-4"><div class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Delivered') }}</div><div class="serif text-[28px] leading-none mt-1.5 tabular ig-text">{{ $sentPct }}%</div></div>
        </section>

        {{-- ===== PROGRESS ===== --}}
        <section class="bg-white hairline rounded-2xl p-4 mb-5">
            <div class="flex flex-wrap items-center justify-between gap-2 text-[11px] text-ink-500 mb-2">
                <span>{{ number_format($bcast->sent) }} {{ __('of') }} {{ number_format($bcast->total) }} {{ __('delivered') }}</span>
                <span>{{ __('Drains safely in the background — Meta limit ~200/hr') }}</span>
            </div>
            <div class="h-2.5 rounded-full bg-paper-100 overflow-hidden flex">
                <div class="h-full bg-wa-green" style="width: {{ $sentPct }}%"></div>
                <div class="h-full bg-ig-pink" style="width: {{ $failPct }}%"></div>
            </div>
        </section>

        {{-- ===== MESSAGE + RECIPIENTS ===== --}}
        <section class="grid grid-cols-12 gap-3">
            {{-- Message card --}}
            <div class="col-span-12 lg:col-span-4">
                <div class="bg-white hairline rounded-2xl p-5 lg:sticky lg:top-4">
                    <div class="mono text-[10px] uppercase tracking-widest text-ink-500 mb-3">{{ __('Message sent') }}</div>
                    <div class="bubble-out rounded-3xl rounded-tr-lg px-4 py-3 text-white text-[13.5px] whitespace-pre-wrap break-words shadow-sm">{{ $bcast->body }}</div>
                    <div class="mt-4 pt-3 border-t border-paper-100 space-y-2 text-[12px]">
                        <div class="flex items-center justify-between"><span class="text-ink-500">{{ __('From') }}</span><span class="font-medium">{{ $handle }}</span></div>
                        <div class="flex items-center justify-between"><span class="text-ink-500">{{ __('Recipients') }}</span><span class="font-medium tabular">{{ number_format($bcast->total) }}</span></div>
                        @if ($bcast->created_at)<div class="flex items-center justify-between"><span class="text-ink-500">{{ __('Started') }}</span><span class="font-medium">{{ $bcast->created_at->format('M j, H:i') }}</span></div>@endif
                    </div>
                    @if ($bcast->failed)
                        <div class="mt-3 rounded-xl bg-ig-pink/5 border border-ig-pink/20 px-3 py-2.5 text-[11.5px] text-ink-600">
                            <span class="text-ig-pink font-medium">{{ $bcast->failed }} {{ __('failed') }}.</span> {{ __('Bulk DMs only reach people who messaged you in the last 24h (Instagram rule).') }}
                        </div>
                    @endif
                </div>
            </div>

            {{-- Recipients table --}}
            <div class="col-span-12 lg:col-span-8">
                <div class="bg-white hairline rounded-2xl overflow-hidden">
                    <div class="px-4 py-3 hairline-b flex items-center justify-between">
                        <span class="text-[13.5px] font-semibold">{{ __('Recipients') }}</span>
                        <span class="text-[11px] text-ink-500">{{ $recipients->total() }} {{ __('total') }}</span>
                    </div>
                    <div class="grid grid-cols-12 px-4 py-2 hairline-b mono text-[9.5px] uppercase tracking-widest text-ink-500">
                        <div class="col-span-5">{{ __('Recipient (IGSID)') }}</div>
                        <div class="col-span-2">{{ __('Status') }}</div>
                        <div class="col-span-5">{{ __('Detail') }}</div>
                    </div>
                    @forelse ($recipients as $r)
                        <div class="grid grid-cols-12 px-4 py-3 hairline-b items-start text-[12.5px] gap-2">
                            <div class="col-span-5 mono truncate text-ink-700" title="{{ $r->igsid }}">{{ $r->igsid }}</div>
                            <div class="col-span-2"><span class="pill {{ $pillFor[$r->status] ?? 'bg-paper-100 text-ink-600' }}">{{ str_replace('_', ' ', $r->status) }}</span></div>
                            <div class="col-span-5 text-ink-500 break-words leading-snug">{{ $r->error ?: ($r->sent_at ? __('Delivered') . ' · ' . $r->sent_at->diffForHumans() : '—') }}</div>
                        </div>
                    @empty
                        <div class="px-4 py-14 text-center text-[13px] text-ink-500">{{ __('No recipient rows recorded for this bulk DM.') }}</div>
                    @endforelse
                </div>
                <div class="mt-4">{{ $recipients->links() }}</div>
            </div>
        </section>
    </div>
</x-layouts.instagram>
