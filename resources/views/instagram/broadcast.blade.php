<x-layouts.instagram :title="__('Bulk DM')" ig-active="broadcast" page="instagram-broadcast">
    @php
        $statusStyle = [
            'done'    => ['bg-wa-bubble text-wa-deep', __('Completed')],
            'sending' => ['bg-ig-pink/10 text-ig-pink', __('Sending')],
            'pending' => ['bg-amber-100 text-amber-700', __('Queued')],
            'failed'  => ['bg-accent-coral/15 text-accent-coral', __('Failed')],
        ];
    @endphp

    <div class="max-w-[1500px] mx-auto px-6 py-6">
        <x-admin.flash />

        @if ($accounts->isEmpty())
            {{-- ===== EMPTY STATE ===== --}}
            <div class="bg-paper-0 hairline rounded-2xl p-12 text-center">
                <span class="ig-grad w-14 h-14 rounded-2xl grid place-items-center text-white mx-auto mb-3"><svg viewBox="0 0 24 24" class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/></svg></span>
                <div class="serif text-[24px]">{{ __('Connect an account to send bulk DMs') }}</div>
                <p class="text-[12.5px] text-ink-500 mt-1">{{ __('Link a Professional / Creator account, then message everyone inside the 24-hour window.') }}</p>
                <a href="{{ url('/instagram/connect') }}" class="mt-4 inline-flex items-center gap-2 px-5 py-2.5 rounded-full ig-grad-soft text-white text-[12.5px] font-semibold hover:opacity-90">{{ __('Connect Instagram') }}</a>
            </div>
        @else
            {{-- ===== MAIN (full width) ===== --}}
            <section>
                <div class="flex items-end justify-between mb-5 gap-4 flex-wrap">
                    <div>
                        <div class="mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">{{ brand_name() }} · {{ __('Bulk DM') }}</div>
                        <h1 class="serif text-[30px] sm:text-[40px] leading-[1.0]">{{ __('Bulk DM') }} <span class="ig-text italic">{{ __('broadcasts') }}</span></h1>
                        <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">{{ __('Send one DM to every contact inside the 24-hour window, then track sent, failed, and pending per recipient.') }}</p>
                    </div>
                    <a href="{{ route('instagram.broadcast.create') }}" class="px-4 py-2 rounded-full ig-grad-soft text-white text-[12px] font-semibold hover:opacity-90 flex items-center gap-2 shrink-0">
                        <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 3v10M3 8h10"/></svg>{{ __('New broadcast') }}
                    </a>
                </div>

                {{-- KPI cards (live-polled) --}}
                <div class="grid grid-cols-2 lg:grid-cols-7 gap-3 mb-4" data-bc-poll data-bc-stats-url="{{ route('instagram.broadcast.stats') }}">
                    <div class="ig-grad-soft text-white rounded-2xl p-4 relative overflow-hidden">
                        <div class="absolute inset-0 ig-dots opacity-25"></div>
                        <div class="relative">
                            <div class="mono text-[10px] uppercase tracking-widest text-white/70">{{ __('In window') }}</div>
                            <div class="serif text-[30px] leading-none mt-2 tabular" data-bc-stat="reach">{{ number_format($stats['reach']) }}</div>
                            <div class="text-[10.5px] text-white/80 mt-1">{{ __('reachable now') }}</div>
                        </div>
                    </div>
                    <div class="bg-paper-0 hairline rounded-2xl p-4">
                        <div class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Broadcasts') }}</div>
                        <div class="serif text-[30px] leading-none mt-2 tabular" data-bc-stat="total">{{ number_format($stats['total']) }}</div>
                        <div class="text-[10.5px] text-ink-500 mt-1">{{ __('all time') }}</div>
                    </div>
                    <div class="bg-paper-0 hairline rounded-2xl p-4">
                        <div class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Recipients') }}</div>
                        <div class="serif text-[30px] leading-none mt-2 tabular" data-bc-stat="recipients">{{ number_format($stats['recipients']) }}</div>
                        <div class="text-[10.5px] text-ink-500 mt-1">{{ __('targeted') }}</div>
                    </div>
                    <div class="bg-paper-0 hairline rounded-2xl p-4">
                        <div class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Sent') }}</div>
                        <div class="serif text-[30px] leading-none mt-2 tabular text-wa-deep" data-bc-stat="sent">{{ number_format($stats['sent']) }}</div>
                        <div class="text-[10.5px] text-ink-500 mt-1">{{ __('sent DMs') }}</div>
                    </div>
                    <div class="bg-paper-0 hairline rounded-2xl p-4">
                        <div class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Read') }}</div>
                        <div class="serif text-[30px] leading-none mt-2 tabular text-ig-pink" data-bc-stat="read">{{ number_format($stats['read']) }}</div>
                        <div class="text-[10.5px] text-ink-500 mt-1">{{ __('read receipts') }}</div>
                    </div>
                    <div class="bg-paper-0 hairline rounded-2xl p-4">
                        <div class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Pending') }}</div>
                        <div class="serif text-[30px] leading-none mt-2 tabular text-amber-600" data-bc-stat="pending">{{ number_format($stats['pending']) }}</div>
                        <div class="text-[10.5px] text-ink-500 mt-1">{{ __('awaiting send') }}</div>
                    </div>
                    <div class="bg-paper-0 hairline rounded-2xl p-4">
                        <div class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Failed') }}</div>
                        <div class="serif text-[30px] leading-none mt-2 tabular {{ $stats['failed'] ? 'text-accent-coral' : '' }}" data-bc-stat="failed">{{ number_format($stats['failed']) }}</div>
                        <div class="text-[10.5px] text-ink-500 mt-1">{{ __('window closed') }}</div>
                    </div>
                </div>

                {{-- list --}}
                <div class="bg-paper-0 hairline rounded-2xl overflow-hidden">
                    <div class="px-5 py-4 hairline-b flex items-center justify-between gap-4 flex-wrap">
                        <div>
                            <div class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Broadcast list') }}</div>
                            <h2 class="serif text-[22px] leading-tight mt-0.5">{{ __('Recent broadcasts') }}</h2>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-[12.5px]">
                            <thead class="bg-paper-50 hairline-b text-ink-500">
                                <tr>
                                    <th class="text-left mono text-[10px] uppercase tracking-widest px-5 py-3">{{ __('Message') }}</th>
                                    <th class="text-left mono text-[10px] uppercase tracking-widest px-3 py-3 w-[150px]">{{ __('Account') }}</th>
                                    <th class="text-left mono text-[10px] uppercase tracking-widest px-3 py-3 w-[90px]">{{ __('Recipients') }}</th>
                                    <th class="text-left mono text-[10px] uppercase tracking-widest px-3 py-3 w-[80px]">{{ __('Sent') }}</th>
                                    <th class="text-left mono text-[10px] uppercase tracking-widest px-3 py-3 w-[80px]">{{ __('Read') }}</th>
                                    <th class="text-left mono text-[10px] uppercase tracking-widest px-3 py-3 w-[80px]">{{ __('Failed') }}</th>
                                    <th class="text-left mono text-[10px] uppercase tracking-widest px-3 py-3 w-[110px]">{{ __('Status') }}</th>
                                    <th class="text-left mono text-[10px] uppercase tracking-widest px-3 py-3 w-[120px]">{{ __('When') }}</th>
                                    <th class="px-4 py-3 w-[50px]"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-paper-200">
                                @forelse ($broadcasts as $bc)
                                    @php
                                        $st = $statusStyle[$bc->status] ?? ['bg-paper-100 text-ink-600', ucfirst((string) $bc->status)];
                                        $acc = $accountName[$bc->instagram_account_id] ?? null;
                                        $pct = $bc->total > 0 ? min(100, round($bc->sent / max(1, $bc->total) * 100)) : 0;
                                        $readN = (int) ($readByBc[$bc->id]['read'] ?? 0);
                                    @endphp
                                    <tr class="hover:bg-paper-50" data-bc-row="{{ $bc->id }}">
                                        <td class="px-5 py-3">
                                            <div class="text-ink-900 truncate max-w-[340px]">{{ \Illuminate\Support\Str::limit($bc->body, 70) }}</div>
                                            <div class="mt-1.5 h-1 rounded-full bg-paper-100 overflow-hidden max-w-[200px]"><div class="h-full ig-grad-soft" data-bc-cell="bar" style="width:{{ $pct }}%"></div></div>
                                        </td>
                                        <td class="px-3 py-3 text-ink-700">{{ $acc ? '@'.($acc->username ?: $acc->ig_user_id) : '—' }}</td>
                                        <td class="px-3 py-3 mono">{{ number_format($bc->total) }}</td>
                                        <td class="px-3 py-3 mono text-wa-deep" data-bc-cell="sent">{{ number_format($bc->sent) }}</td>
                                        <td class="px-3 py-3 mono text-ig-pink" data-bc-cell="read">{{ number_format($readN) }}</td>
                                        <td class="px-3 py-3 mono {{ $bc->failed ? 'text-accent-coral' : 'text-ink-500' }}" data-bc-cell="failed">{{ number_format($bc->failed) }}</td>
                                        <td class="px-3 py-3" data-bc-cell="status"><span class="text-[10px] px-2 py-0.5 rounded-full {{ $st[0] }}">{{ $st[1] }}</span></td>
                                        <td class="px-3 py-3 mono text-[11px] text-ink-500">{{ $bc->created_at?->diffForHumans() }}</td>
                                        <td class="px-4 py-3 text-right">
                                            <a href="{{ route('instagram.broadcast.show', $bc->id) }}" class="text-ig-pink hover:opacity-80" title="{{ __('View') }}"><svg viewBox="0 0 16 16" class="w-4 h-4 inline" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M6 4l4 4-4 4"/></svg></a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="9" class="px-5 py-14 text-center">
                                        <div class="serif text-[20px] text-ink-700">{{ __('No broadcasts yet') }}</div>
                                        <p class="text-[12.5px] text-ink-500 mt-1">{{ __('Queue your first bulk DM and it will appear here with live counts.') }}</p>
                                        <a href="{{ route('instagram.broadcast.create') }}" class="inline-flex items-center gap-2 mt-4 px-5 py-2.5 rounded-full ig-grad-soft text-white text-[12.5px] font-semibold">{{ __('New broadcast') }}</a>
                                    </td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="mt-3">{{ $broadcasts->links() }}</div>
            </section>
        @endif
    </div>
</x-layouts.instagram>
