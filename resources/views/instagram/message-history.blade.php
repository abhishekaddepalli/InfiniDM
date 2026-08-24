<x-layouts.instagram :title="__('Message History')" ig-active="message-history" page="instagram-message-history">
    <div class="px-4 sm:px-7 py-7">
        <div class="flex items-start justify-between gap-4 mb-5 flex-wrap">
            <div>
                <h1 class="font-serif text-[26px] leading-tight text-ink-900">{{ __('Message History') }}</h1>
                <p class="text-[13px] text-ink-500 mt-1">{{ __('Every DM sent and received across your connected accounts.') }}</p>
            </div>
            <a href="{{ route('instagram.inbox') }}" class="px-3.5 py-2 rounded-full hairline border border-paper-200 bg-paper-0 hover:bg-paper-50 text-[12px] font-medium inline-flex items-center gap-1.5">
                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 5h10v7H6l-3 2z" /></svg>
                {{ __('Open inbox') }}
            </a>
        </div>

        {{-- Filters --}}
        <form method="GET" class="flex flex-wrap items-center gap-2 mb-4">
            <select name="account" onchange="this.form.submit()" class="px-3 py-2 rounded-lg border border-paper-200 bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep">
                <option value="0">{{ __('All accounts') }}</option>
                @foreach ($accounts as $a)
                    <option value="{{ $a->id }}" @selected($selectedAccountId === $a->id)>{{ '@' . ltrim($a->username ?: $a->name, '@') }}</option>
                @endforeach
            </select>
            <select name="direction" onchange="this.form.submit()" class="px-3 py-2 rounded-lg border border-paper-200 bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep">
                <option value="">{{ __('All messages') }}</option>
                <option value="in"  @selected($direction === 'in')>{{ __('Received') }}</option>
                <option value="out" @selected($direction === 'out')>{{ __('Sent') }}</option>
            </select>
            <div class="relative">
                <input type="text" name="q" value="{{ $q }}" placeholder="{{ __('Search message text…') }}"
                    class="px-3 py-2 pl-8 rounded-lg border border-paper-200 bg-paper-0 text-[13px] w-56 focus:outline-none focus:border-wa-deep">
                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 absolute left-2.5 top-1/2 -translate-y-1/2 text-ink-400" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="7" cy="7" r="4.5" /><path d="M11 11l3 3" /></svg>
            </div>
            <button type="submit" class="px-3.5 py-2 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold">{{ __('Filter') }}</button>
            @if ($q || $direction || $selectedAccountId)
                <a href="{{ route('instagram.message-history') }}" class="text-[12px] text-ink-500 hover:text-ink-900">{{ __('Reset') }}</a>
            @endif
            <span class="ml-auto text-[12px] font-mono text-ink-500">{{ number_format($total) }} {{ __('messages') }}</span>
        </form>

        {{-- Table --}}
        <div class="rounded-2xl border border-paper-200 bg-paper-0 overflow-hidden">
            <table class="w-full text-[13px]">
                <thead>
                    <tr class="text-left font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500 border-b border-paper-200">
                        <th class="px-4 py-3">{{ __('Contact') }}</th>
                        <th class="px-4 py-3">{{ __('Direction') }}</th>
                        <th class="px-4 py-3">{{ __('Message') }}</th>
                        <th class="px-4 py-3 whitespace-nowrap">{{ __('When') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($messages as $m)
                        <tr class="border-b border-paper-100 last:border-0 hover:bg-paper-50 align-top">
                            <td class="px-4 py-3">
                                <div class="font-semibold text-ink-900">{{ $m->contact_name }}</div>
                                <div class="text-[11px] text-ink-500">
                                    @if ($m->contact_handle) {{ $m->contact_handle }} @endif
                                    @if ($m->account_name) <span class="text-ink-400">· @ {{ ltrim($m->account_name, '@') }}</span> @endif
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                @if ($m->direction === 'out')
                                    <span class="inline-flex items-center gap-1 text-[11px] font-medium px-2 py-0.5 rounded-full bg-wa-deep/10 text-wa-deep">{{ __('Sent') }}</span>
                                @else
                                    <span class="inline-flex items-center gap-1 text-[11px] font-medium px-2 py-0.5 rounded-full bg-paper-100 text-ink-600">{{ __('Received') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-ink-800 max-w-[420px]">
                                @if ($m->body)
                                    <span class="line-clamp-2">{{ $m->body }}</span>
                                @elseif ($m->attachment_type)
                                    <span class="text-ink-500 italic">{{ ucfirst($m->attachment_type) }}</span>
                                @else
                                    <span class="text-ink-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-ink-500 whitespace-nowrap">{{ optional($m->sent_at ?? $m->created_at)->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-12 text-center text-ink-500">
                            @if (count($accounts) === 0)
                                {{ __('Connect an Instagram account to see message history.') }}
                            @else
                                {{ __('No messages match these filters.') }}
                            @endif
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($messages instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator && $messages->hasPages())
            <div class="mt-4">{{ $messages->links() }}</div>
        @endif
    </div>
</x-layouts.instagram>
