<x-layouts.admin :title="__('Admin · Coupons')" admin-key="coupons">

    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-4 sm:px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ route('admin.overview') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 3l3 3-3 3"/></svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Coupons') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <main class="px-4 sm:px-7 py-7 space-y-5">

        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">{{ __('Admin · Billing & plans') }}</div>
                <h1 class="font-serif font-normal tracking-[-0.01em] text-[28px] sm:text-[40px] leading-[1.0]">{{ __('Discount') }}
                    <span class="italic ig-text">{{ __('coupons') }}</span></h1>
                <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">{{ __('Percentage or fixed-amount discount codes customers can apply against any subscription package.') }}</p>
            </div>
            <div class="flex items-center gap-2 shrink-0 pb-1">
                <a href="{{ route('admin.coupons.create') }}"
                    class="px-4 py-2 rounded-full ig-grad-soft text-white text-[12px] font-semibold hover:opacity-90 flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 3v10M3 8h10"/></svg>
                    {{ __('Add coupon') }}
                </a>
            </div>
        </div>

        @if (session('success'))
            <div class="rounded-2xl border border-wa-green/40 bg-wa-bubble text-wa-deep px-4 py-2 text-[12.5px]">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="rounded-2xl border border-accent-coral/40 bg-accent-coral/10 text-accent-coral px-4 py-2 text-[12.5px]">{{ session('error') }}</div>
        @endif

        @php
            $expiredCount = \App\Models\Coupon::whereNotNull('expires_at')->where('expires_at', '<', now())->count();
        @endphp

        <section class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            <div class="bg-paper-0 border border-wa-green/40 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Active coupons') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">{{ number_format($stats['active']) }}</div>
                <div class="text-[11px] text-wa-deep mt-2">{{ __('currently redeemable') }}</div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Total coupons') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">{{ number_format($stats['total']) }}</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ __('in catalog') }}</div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Redemptions') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">{{ number_format($stats['redemptions']) }}</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ __('times used') }}</div>
            </div>
            <div class="bg-paper-0 border border-accent-amber/40 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Expired') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">{{ number_format($expiredCount) }}</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ __('past expiry date') }}</div>
            </div>
        </section>

        <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card">
            <div class="px-5 py-4 border-b border-paper-200">
                <div class="font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500">{{ __('All coupons') }}</div>
                <h2 class="font-serif text-[20px] leading-tight mt-0.5">{{ __('Discount codes') }}</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-[12.5px] table-fixed min-w-[760px]">
                    <thead class="bg-paper-50 text-ink-500 border-b border-paper-200">
                        <tr>
                            <th class="text-left px-3 py-2.5 w-[150px]">{{ __('Code') }}</th>
                            <th class="text-left px-2 py-2.5">{{ __('Description') }}</th>
                            <th class="text-left px-2 py-2.5 w-[100px]">{{ __('Type') }}</th>
                            <th class="text-right px-2 py-2.5 w-[100px]">{{ __('Value') }}</th>
                            <th class="text-right px-2 py-2.5 w-[130px]">{{ __('Redemptions') }}</th>
                            <th class="text-center px-2 py-2.5 w-[90px]">{{ __('Status') }}</th>
                            <th class="text-center px-2 py-2.5 w-[70px]"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-paper-200">
                        @forelse ($coupons as $c)
                            <tr class="hover:bg-paper-50/60">
                                <td class="px-3 py-2 font-mono font-bold text-[12px] text-ink-900">{{ $c->code }}</td>
                                <td class="px-2 py-2">
                                    <div class="text-[10.5px] text-ink-500 font-mono truncate max-w-[260px]">{{ $c->description ?: __('no description') }}</div>
                                </td>
                                <td class="px-2 py-2 text-[11.5px]">{{ $c->type === 'fixed' ? __('Fixed') : __('Percent') }}</td>
                                <td class="px-2 py-2 text-right font-mono">
                                    @if ($c->type === 'percent')
                                        {{ rtrim(rtrim(number_format((float) $c->value, 2), '0'), '.') }}%
                                    @else
                                        {{ rtrim(rtrim(number_format((float) $c->value, 2), '0'), '.') }}
                                    @endif
                                </td>
                                <td class="px-2 py-2 text-right font-mono">{{ (int) $c->redeemed_count }} / {{ is_null($c->max_redemptions) ? '∞' : (int) $c->max_redemptions }}</td>
                                <td class="px-2 py-2 text-center">
                                    @if ($c->is_active)
                                        <span class="px-2 py-0.5 rounded-full bg-wa-mint text-wa-deep text-[10px] font-semibold">{{ __('Active') }}</span>
                                    @else
                                        <span class="px-2 py-0.5 rounded-full bg-paper-100 text-ink-500 text-[10px] font-semibold">{{ __('Inactive') }}</span>
                                    @endif
                                </td>
                                <td class="px-2 py-2 text-center">
                                    <div class="inline-flex items-center gap-1">
                                        <a href="{{ route('admin.coupons.edit', $c) }}" title="{{ __('Edit') }}"
                                            class="w-8 h-8 rounded-full hover:bg-paper-50 grid place-items-center text-ink-600 hover:text-wa-deep">
                                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M9.5 3.5 12.5 6.5 6 13H3v-3z"/></svg>
                                        </a>
                                        <form action="{{ route('admin.coupons.destroy', $c) }}" method="POST"
                                            onsubmit="return confirm('{{ __('Delete this coupon?') }}')">
                                            @csrf @method('DELETE')
                                            <button type="submit" title="{{ __('Delete') }}"
                                                class="w-8 h-8 rounded-full hover:bg-accent-coral/10 grid place-items-center text-ink-600 hover:text-accent-coral">
                                                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 4h10M6 4V2.8h4V4M5 6v8h6V6"/></svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-6 text-center text-ink-500">{{ __('No coupons yet.') }} <a href="{{ route('admin.coupons.create') }}" class="ig-text font-semibold">{{ __('Create the first one →') }}</a></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="px-4 py-3 border-t border-paper-200 bg-paper-50/40 flex flex-wrap gap-2 items-center justify-between rounded-b-2xl">
                <div class="text-[11px] font-mono text-ink-500">
                    {{ __('Showing') }} {{ $coupons->firstItem() ?? 0 }}–{{ $coupons->lastItem() ?? 0 }} {{ __('of') }} {{ number_format($coupons->total()) }} {{ __('coupons') }}
                </div>
                <div>{{ $coupons->onEachSide(1)->links() }}</div>
            </div>
        </div>
    </main>

</x-layouts.admin>
