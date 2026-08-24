<x-layouts.instagram :title="__('Edit campaign')" ig-active="ads" page="instagram-ads-create">
    @php $adType = $campaign->adType(); @endphp
    <div class="max-w-[1200px] mx-auto px-6 py-6">
        <a href="{{ route('instagram.ads.show', $campaign->id) }}" class="inline-flex items-center gap-1.5 text-[12px] text-ink-500 hover:text-ink-800 mb-3">
            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M10 4l-4 4 4 4"/></svg>{{ __('Back to campaign') }}
        </a>

        <div class="mb-5">
            <div class="mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">{{ brand_name() }} · {{ __('Edit campaign') }}</div>
            <h1 class="serif text-[28px] sm:text-[36px] leading-none">{{ $campaign->name }}</h1>
        </div>

        <x-admin.flash />
        @if ($errors->any())
            <div class="bg-accent-coral/10 hairline rounded-2xl p-4 mb-4 text-[12.5px] text-accent-coral">
                <ul class="list-disc list-inside space-y-0.5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        @if ($campaign->facebook_id && $campaign->status !== 'DRAFT')
            <p class="text-[11.5px] text-ink-500 mb-3">{{ __('Saving rebuilds the ad on Meta with your changes (it is torn down + recreated paused).') }}</p>
        @endif

        <form method="POST" action="{{ route('instagram.ads.update', $campaign->id) }}" enctype="multipart/form-data" data-ads-form>
            @csrf
            @method('PUT')
            <div style="display:flex;flex-wrap:wrap;gap:1rem;align-items:flex-start;">
                <div style="flex:1 1 440px;min-width:0;display:flex;flex-direction:column;gap:1rem;">

                    <div class="bg-paper-0 hairline rounded-2xl p-5">
                        <div class="serif text-[18px] mb-3">{{ __('Ad type') }}</div>
                        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:0.75rem;">
                            @foreach (['ig_direct' => __('Click-to-Instagram-DM'), 'link' => __('Traffic')] as $val => $lbl)
                                <label class="hairline rounded-xl p-3 cursor-pointer block" data-adtype-card="{{ $val }}">
                                    <span class="flex items-center gap-2">
                                        <input type="radio" name="ad_type" value="{{ $val }}" @checked(old('ad_type', $adType) === $val) data-adtype-radio>
                                        <span class="font-semibold text-[13px]">{{ $lbl }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="bg-paper-0 hairline rounded-2xl p-5">
                        <div class="serif text-[18px] mb-4">{{ __('Creative') }}</div>
                        <label class="block">
                            <span class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Campaign name') }}</span>
                            <input type="text" name="name" value="{{ old('name', $campaign->name) }}" required class="field mt-1 !text-[13px]">
                        </label>
                        <label class="block mt-3">
                            <span class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Headline') }}</span>
                            <input type="text" name="creative_title" value="{{ old('creative_title', $campaign->creative_title) }}" maxlength="255" class="field mt-1 !text-[13px]">
                        </label>
                        <label class="block mt-3">
                            <span class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Primary text') }}</span>
                            <textarea name="creative_body" rows="3" maxlength="4096" class="field mt-1 !text-[13px]">{{ old('creative_body', $campaign->creative_body) }}</textarea>
                        </label>
                        <div data-when-adtype="ig_direct" class="mt-3">
                            <label class="block">
                                <span class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('DM welcome message') }}</span>
                                <textarea name="dm_welcome" rows="2" maxlength="500" class="field mt-1 !text-[13px]">{{ old('dm_welcome', $campaign->dm_welcome) }}</textarea>
                            </label>
                        </div>
                        <div data-when-adtype="link" class="mt-3">
                            <label class="block">
                                <span class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Destination URL') }}</span>
                                <input type="url" name="creative_link_url" value="{{ old('creative_link_url', $campaign->creative_link_url) }}" placeholder="https://" class="field mt-1 !text-[13px]">
                            </label>
                        </div>
                        <label class="block mt-3">
                            <span class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Call to action') }}</span>
                            <select name="dm_cta" class="field mt-1 !text-[13px]">
                                @foreach (['MESSAGE_PAGE' => __('Send message'), 'LEARN_MORE' => __('Learn more'), 'SHOP_NOW' => __('Shop now'), 'SIGN_UP' => __('Sign up'), 'GET_QUOTE' => __('Get quote'), 'CONTACT_US' => __('Contact us'), 'SUBSCRIBE' => __('Subscribe')] as $cta => $lbl)
                                    <option value="{{ $cta }}" @selected(old('dm_cta', $campaign->dm_cta) === $cta)>{{ $lbl }}</option>
                                @endforeach
                            </select>
                        </label>
                        <div class="mt-3">
                            <span class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Ad image') }}</span>
                            <div class="flex items-center gap-3 mt-1">
                                <span class="w-20 h-20 rounded-xl hairline bg-paper-50 bg-center bg-cover grid place-items-center text-ink-300 shrink-0" data-image-preview
                                      @if ($campaign->creative_image) style="background-image:url('{{ \Illuminate\Support\Facades\Storage::disk('public')->url($campaign->creative_image) }}')" @endif>
                                    @unless ($campaign->creative_image)<svg viewBox="0 0 24 24" class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="M3 17l5-4 4 3 3-2 6 5"/></svg>@endunless
                                </span>
                                <div>
                                    <input type="file" name="creative_image_file" accept="image/*" class="text-[12px]" data-image-input>
                                    <p class="text-[10.5px] text-ink-400 mt-1">{{ __('Leave empty to keep the current image.') }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    @include('instagram.ads._targeting', ['campaign' => $campaign])
                </div>

                <div style="flex:0 1 320px;min-width:280px;display:flex;flex-direction:column;gap:1rem;">
                    <div class="bg-paper-0 hairline rounded-2xl p-5">
                        <div class="serif text-[18px] mb-4">{{ __('Settings') }}</div>
                        <label class="block">
                            <span class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Instagram account') }}</span>
                            <select name="instagram_account_id" required class="field mt-1 !text-[13px]">
                                @foreach ($accounts as $a)
                                    <option value="{{ $a->id }}" @selected(old('instagram_account_id', $campaign->instagram_account_id) == $a->id)>{{ '@'.($a->username ?: $a->ig_user_id) }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="block mt-3">
                            <span class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Daily budget') }}</span>
                            <input type="number" name="daily_budget" min="1" step="1" value="{{ old('daily_budget', (int) $campaign->daily_budget) }}" required class="field mt-1 !text-[13px]">
                        </label>
                        <label class="block mt-3" data-when-adtype="link">
                            <span class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Optimise for') }}</span>
                            <select name="optimization_goal" class="field mt-1 !text-[13px]">
                                @foreach (['LINK_CLICKS' => __('Link clicks'), 'REACH' => __('Reach')] as $g => $lbl)
                                    <option value="{{ $g }}" @selected(old('optimization_goal', $campaign->optimization_goal) === $g)>{{ $lbl }}</option>
                                @endforeach
                            </select>
                        </label>
                        <button type="submit" class="w-full mt-5 px-5 py-2.5 rounded-full ig-grad text-white text-[13px] font-semibold hover:opacity-90">{{ __('Save changes') }}</button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</x-layouts.instagram>
