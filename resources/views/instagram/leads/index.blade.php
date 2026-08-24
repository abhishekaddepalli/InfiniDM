<x-layouts.instagram :title="__('Leads')" ig-active="leads" page="instagram-leads">
    <div class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-7">

        <div class="mb-5">
            <div class="mono font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500">{{ __('Instagram · Growth') }}</div>
            <h1 class="serif font-serif font-normal tracking-[-0.01em] text-[26px] leading-tight">{{ __('Leads') }}</h1>
            <p class="text-[13px] text-ink-600 mt-1 max-w-2xl">
                {{ __('Everyone captured through a DM lead-capture flow or a Meta Lead Ad. Build the funnel in') }}
                <a href="{{ url('/flows') }}" class="text-ig-purple underline">{{ __('Flows') }}</a>
                {{ __('with the “Ask a question” → “Capture lead” nodes.') }}
            </p>
        </div>

        @if (session('status'))
            <div class="mb-4 bg-wa-mint border border-wa-green/30 rounded-lg px-4 py-2 text-[12.5px] text-wa-deep font-mono">{{ session('status') }}</div>
        @endif

        {{-- Filters --}}
        @php
            $chip = function ($label, $key, $val, $count) use ($source, $status) {
                $on = ($key === 'source' ? $source : $status) === $val;
                $qs = array_filter([$key => $val !== '' ? $val : null] + request()->except('page', $key));
                return [$label, url('/instagram/leads') . (($u = http_build_query($qs)) ? '?' . $u : ''), $count, $on];
            };
        @endphp
        <div class="flex flex-wrap items-center gap-2 mb-4">
            @foreach ([
                $chip(__('All'), 'source', '', $counts['all']),
                $chip(__('From DMs'), 'source', 'dm', $counts['dm']),
                $chip(__('Lead Ads'), 'source', 'lead_ad', $counts['lead_ad']),
                $chip(__('New'), 'status', 'new', $counts['new']),
            ] as [$label, $href, $count, $on])
                <a href="{{ $href }}" @class([
                    'inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-[12px] font-medium border transition',
                    'ig-grad-soft text-white border-transparent' => $on,
                    'bg-paper-0 border-paper-200 text-ink-700 hover:bg-paper-50' => !$on,
                ])>
                    <span>{{ $label }}</span>
                    <span class="font-mono text-[10px] px-1.5 py-0.5 rounded-full {{ $on ? 'bg-white/25 text-white' : 'bg-paper-100 text-ink-600' }}">{{ $count }}</span>
                </a>
            @endforeach

            <form method="GET" action="{{ url('/instagram/leads') }}" class="ml-auto">
                @if ($source)<input type="hidden" name="source" value="{{ $source }}">@endif
                @if ($status)<input type="hidden" name="status" value="{{ $status }}">@endif
                <input name="q" value="{{ $q }}" placeholder="{{ __('Search name / email / phone') }}"
                    class="field w-[240px] max-w-full text-[12.5px]" type="search">
            </form>
        </div>

        @if ($leads->isEmpty())
            <div class="border border-dashed border-paper-200 rounded-2xl bg-paper-0 py-12 text-center">
                <div class="text-[13px] text-ink-600">{{ __('No leads captured yet.') }}</div>
                <p class="text-[12px] text-ink-500 mt-1 max-w-md mx-auto">
                    {{ __('Create an Instagram flow that asks a few questions, then drop in the “Capture lead” node — every reply lands here (and in your pipeline).') }}
                </p>
                <a href="{{ url('/flows') }}" class="mt-3 inline-flex items-center gap-1.5 px-3.5 py-2 rounded-full ig-grad-soft text-white text-[12px] font-semibold">{{ __('Build a lead flow') }}</a>
            </div>
        @else
            <div class="border border-paper-200 rounded-2xl bg-paper-0 shadow-card overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-[12.5px]">
                        <thead>
                            <tr class="text-left text-ink-500 mono font-mono text-[10px] uppercase tracking-[0.12em] border-b border-paper-200">
                                <th class="px-4 py-2.5 font-medium">{{ __('Lead') }}</th>
                                <th class="px-4 py-2.5 font-medium">{{ __('Contact') }}</th>
                                <th class="px-4 py-2.5 font-medium">{{ __('Source') }}</th>
                                <th class="px-4 py-2.5 font-medium">{{ __('Captured') }}</th>
                                <th class="px-4 py-2.5 font-medium">{{ __('Status') }}</th>
                                <th class="px-4 py-2.5 font-medium text-right">{{ __('Deal') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-paper-100">
                            @foreach ($leads as $lead)
                                <tr class="hover:bg-paper-50/60">
                                    <td class="px-4 py-3">
                                        <div class="font-semibold text-ink-900">{{ $lead->full_name ?: __('Unnamed lead') }}</div>
                                        @if (!empty($lead->notes))
                                            <div class="text-[11px] text-ink-500 truncate max-w-[240px]" title="{{ $lead->notes }}">{{ $lead->notes }}</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-ink-700">
                                        @if ($lead->email)<div class="truncate max-w-[200px]">{{ $lead->email }}</div>@endif
                                        @if ($lead->phone)<div class="font-mono text-[11.5px] text-ink-600">{{ $lead->phone }}</div>@endif
                                        @if (!$lead->email && !$lead->phone)<span class="text-ink-400">—</span>@endif
                                    </td>
                                    <td class="px-4 py-3">
                                        @if ($lead->source === 'dm')
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10.5px] font-mono bg-wa-mint text-wa-deep border border-wa-green/30">{{ __('DM flow') }}</span>
                                        @else
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10.5px] font-mono bg-accent-coral/10 text-accent-coral border border-accent-coral/30">{{ __('Lead Ad') }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-ink-500 font-mono text-[11.5px]">{{ optional($lead->lead_created_at ?? $lead->created_at)->diffForHumans() }}</td>
                                    <td class="px-4 py-3">
                                        <form method="POST" action="{{ route('instagram.leads.status', $lead->id) }}" class="flex items-center gap-1.5">
                                            @csrf
                                            <select name="status" class="field text-[11.5px] py-1 pr-6">
                                                @foreach (['new' => __('New'), 'contacted' => __('Contacted'), 'qualified' => __('Qualified'), 'won' => __('Won'), 'lost' => __('Lost')] as $sv => $sl)
                                                    <option value="{{ $sv }}" @selected(($lead->status ?: 'new') === $sv)>{{ $sl }}</option>
                                                @endforeach
                                            </select>
                                            <button class="px-2 py-1 rounded-lg bg-ink-900 text-white text-[11px] font-semibold">{{ __('Save') }}</button>
                                        </form>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        @if ($lead->deal_id)
                                            <a href="{{ url('/deals/' . $lead->deal_id) }}" class="text-ig-purple underline text-[11.5px]">{{ __('Deal') }} #{{ $lead->deal_id }}</a>
                                        @else
                                            <span class="text-ink-400">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-4">{{ $leads->links() }}</div>
        @endif
    </div>
</x-layouts.instagram>
