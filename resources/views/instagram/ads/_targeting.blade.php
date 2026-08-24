@php
    /** Shared targeting + placement + advanced fields for the ads create/edit forms.
        Expects an optional $campaign (null on create). */
    $campaign = $campaign ?? null;
    $t   = is_array($campaign?->targeting) ? $campaign->targeting : [];
    $pp  = array_map('strtolower', (array) ($campaign?->publisher_platforms ?? ['instagram']));
    $ip  = array_map('strtolower', (array) ($campaign?->instagram_positions ?? []));
    $cats = (array) ($campaign?->special_ad_categories ?? []);
    $countriesCsv = is_array($t['countries'] ?? null) ? implode(', ', $t['countries']) : '';
    $interestsCsv = is_array($t['interests'] ?? null) ? implode(', ', $t['interests']) : '';
    // old() for these two is an ARRAY on a validation redirect (they're merged to
    // arrays before validation), so normalise back to the comma text the inputs show.
    $oldCountries = old('target_countries');
    $oldCountries = is_array($oldCountries) ? implode(', ', $oldCountries) : ($oldCountries ?? $countriesCsv);
    $oldInterests = old('interests');
    $oldInterests = is_array($oldInterests) ? implode(', ', $oldInterests) : ($oldInterests ?? $interestsCsv);
@endphp

<div class="bg-paper-0 hairline rounded-2xl p-5">
    <div class="serif text-[18px] mb-4">{{ __('Audience') }}</div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:0.75rem;">
        <label class="block">
            <span class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Countries') }}</span>
            <input type="text" name="target_countries" value="{{ $oldCountries }}" placeholder="US, GB, IN" class="field mt-1 !text-[13px]">
        </label>
        <label class="block">
            <span class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Age min') }}</span>
            <input type="number" name="age_min" min="13" max="65" value="{{ old('age_min', $t['age_min'] ?? 18) }}" class="field mt-1 !text-[13px]">
        </label>
        <label class="block">
            <span class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Age max') }}</span>
            <input type="number" name="age_max" min="13" max="65" value="{{ old('age_max', $t['age_max'] ?? 65) }}" class="field mt-1 !text-[13px]">
        </label>
        <label class="block">
            <span class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Gender') }}</span>
            <select name="gender" class="field mt-1 !text-[13px]">
                @foreach (['all' => __('All'), 'female' => __('Women'), 'male' => __('Men')] as $g => $lbl)
                    <option value="{{ $g }}" @selected(old('gender', $t['gender'] ?? 'all') === $g)>{{ $lbl }}</option>
                @endforeach
            </select>
        </label>
    </div>

    <label class="block mt-3">
        <span class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Interests') }}</span>
        <textarea name="interests" rows="2" placeholder="{{ __('Fitness, Skincare, Online shopping') }}" class="field mt-1 !text-[13px]">{{ $oldInterests }}</textarea>
        <span class="text-[10.5px] text-ink-400">{{ __('Comma-separated. Resolved to Meta interests automatically.') }}</span>
    </label>

    {{-- Placement --}}
    <div class="mt-4 pt-4 hairline-t">
        <div class="mono text-[10px] uppercase tracking-widest text-ink-500 mb-2">{{ __('Placement') }}</div>
        <div class="flex flex-wrap gap-3">
            @foreach (['instagram' => __('Instagram'), 'facebook' => __('Facebook')] as $p => $lbl)
                <label class="inline-flex items-center gap-2 text-[12.5px]">
                    <input type="checkbox" name="publisher_platforms[]" value="{{ $p }}" @checked(in_array($p, old('publisher_platforms', $pp), true))>
                    {{ $lbl }}
                </label>
            @endforeach
        </div>
        <div class="mono text-[10px] uppercase tracking-widest text-ink-500 mt-3 mb-2">{{ __('Instagram surfaces') }}</div>
        <div class="flex flex-wrap gap-3">
            @foreach (['stream' => __('Feed'), 'story' => __('Stories'), 'reels' => __('Reels'), 'explore' => __('Explore')] as $pos => $lbl)
                <label class="inline-flex items-center gap-2 text-[12.5px]">
                    <input type="checkbox" name="instagram_positions[]" value="{{ $pos }}" @checked(in_array($pos, old('instagram_positions', $ip), true))>
                    {{ $lbl }}
                </label>
            @endforeach
        </div>
        <p class="text-[10.5px] text-ink-400 mt-2">{{ __('Leave surfaces unchecked for automatic (Advantage+) placement.') }}</p>
    </div>

    {{-- Advanced --}}
    <details class="mt-4 pt-4 hairline-t">
        <summary class="cursor-pointer mono text-[10px] uppercase tracking-widest text-ink-500 select-none">{{ __('Advanced (budget, bidding, categories)') }}</summary>
        <div class="mt-3" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:0.75rem;">
            <label class="block">
                <span class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Ad set name') }}</span>
                <input type="text" name="adset_name" value="{{ old('adset_name', $t['_adset_name'] ?? '') }}" class="field mt-1 !text-[13px]">
            </label>
            <label class="block">
                <span class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Budget level') }}</span>
                <select name="budget_level" class="field mt-1 !text-[13px]">
                    <option value="adset" @selected(old('budget_level', $campaign?->budget_level ?? 'adset') === 'adset')>{{ __('Ad set') }}</option>
                    <option value="campaign" @selected(old('budget_level', $campaign?->budget_level) === 'campaign')>{{ __('Campaign (CBO)') }}</option>
                </select>
            </label>
            <label class="block">
                <span class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Bid strategy') }}</span>
                <select name="bid_strategy" class="field mt-1 !text-[13px]">
                    <option value="LOWEST_COST_WITHOUT_CAP" @selected(old('bid_strategy', $campaign?->bid_strategy ?? 'LOWEST_COST_WITHOUT_CAP') === 'LOWEST_COST_WITHOUT_CAP')>{{ __('Lowest cost') }}</option>
                    <option value="LOWEST_COST_WITH_BID_CAP" @selected(old('bid_strategy', $campaign?->bid_strategy) === 'LOWEST_COST_WITH_BID_CAP')>{{ __('Bid cap') }}</option>
                    <option value="COST_CAP" @selected(old('bid_strategy', $campaign?->bid_strategy) === 'COST_CAP')>{{ __('Cost cap') }}</option>
                </select>
            </label>
            <label class="block">
                <span class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Bid amount') }}</span>
                <input type="number" name="bid_amount" min="0" step="0.01" value="{{ old('bid_amount', $campaign?->bid_amount ? number_format($campaign->bid_amount / 100, 2, '.', '') : '') }}" class="field mt-1 !text-[13px]">
            </label>
        </div>
        <div class="mt-3">
            <span class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Special ad categories') }}</span>
            <div class="flex flex-wrap gap-3 mt-1">
                @foreach (['HOUSING' => __('Housing'), 'CREDIT' => __('Credit'), 'EMPLOYMENT' => __('Employment'), 'ISSUES_ELECTIONS_POLITICS' => __('Politics')] as $cat => $lbl)
                    <label class="inline-flex items-center gap-2 text-[12.5px]">
                        <input type="checkbox" name="special_ad_categories[]" value="{{ $cat }}" @checked(in_array($cat, old('special_ad_categories', $cats), true))>
                        {{ $lbl }}
                    </label>
                @endforeach
            </div>
        </div>
    </details>
</div>
