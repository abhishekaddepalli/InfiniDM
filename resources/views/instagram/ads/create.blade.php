<x-layouts.instagram :title="__('New campaign')" ig-active="ads" page="instagram-ads-create">
    <div class="max-w-[1200px] mx-auto px-6 py-6">
        <a href="{{ route('instagram.ads') }}" class="inline-flex items-center gap-1.5 text-[12px] text-ink-500 hover:text-ink-800 mb-3">
            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M10 4l-4 4 4 4"/></svg>{{ __('Back to ads') }}
        </a>

        <div class="mb-5">
            <div class="mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">{{ brand_name() }} · {{ __('New campaign') }}</div>
            <h1 class="serif text-[30px] sm:text-[38px] leading-none">{{ __('Create an') }} <span class="ig-text italic">{{ __('Instagram ad') }}</span></h1>
        </div>

        <x-admin.flash />
        @if ($errors->any())
            <div class="bg-accent-coral/10 hairline rounded-2xl p-4 mb-4 text-[12.5px] text-accent-coral">
                <div class="font-semibold mb-1">{{ __('Please fix the following:') }}</div>
                <ul class="list-disc list-inside space-y-0.5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <form method="POST" action="{{ route('instagram.ads.store') }}" enctype="multipart/form-data" data-ads-form>
            @csrf
            <div style="display:flex;flex-wrap:wrap;gap:1rem;align-items:flex-start;">
                {{-- ===== MAIN ===== --}}
                <div style="flex:1 1 440px;min-width:0;display:flex;flex-direction:column;gap:1rem;">

                    {{-- Ad type --}}
                    <div class="bg-paper-0 hairline rounded-2xl p-5">
                        <div class="serif text-[18px] mb-3">{{ __('Ad type') }}</div>
                        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:0.75rem;">
                            @php
                                $types = [
                                    'ig_direct' => [__('Click-to-Instagram-DM'), __('The tap opens an Instagram DM your automations answer.')],
                                    'link'      => [__('Traffic'), __('Send people to a website or landing page.')],
                                ];
                            @endphp
                            @foreach ($types as $val => $meta)
                                <label class="hairline rounded-xl p-3 cursor-pointer block" data-adtype-card="{{ $val }}">
                                    <span class="flex items-center gap-2">
                                        <input type="radio" name="ad_type" value="{{ $val }}" @checked(old('ad_type', $adType) === $val) data-adtype-radio>
                                        <span class="font-semibold text-[13px]">{{ $meta[0] }}</span>
                                    </span>
                                    <span class="block text-[11.5px] text-ink-500 mt-1">{{ $meta[1] }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    {{-- Creative --}}
                    <div class="bg-paper-0 hairline rounded-2xl p-5">
                        <div class="serif text-[18px] mb-4">{{ __('Creative') }}</div>

                        <label class="block">
                            <span class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Campaign name') }}</span>
                            <input type="text" name="name" value="{{ old('name') }}" required class="field mt-1 !text-[13px]" data-ai-field="campaign_name">
                        </label>

                        <label class="block mt-3">
                            <span class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Headline') }}</span>
                            <input type="text" name="creative_title" value="{{ old('creative_title') }}" maxlength="255" class="field mt-1 !text-[13px]" data-ai-field="headline">
                        </label>

                        <label class="block mt-3">
                            <span class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Primary text') }}</span>
                            <textarea name="creative_body" rows="3" maxlength="4096" class="field mt-1 !text-[13px]" data-ai-field="body">{{ old('creative_body') }}</textarea>
                        </label>

                        {{-- IG-DM only --}}
                        <div data-when-adtype="ig_direct" class="mt-3">
                            <label class="block">
                                <span class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('DM welcome message') }}</span>
                                <textarea name="dm_welcome" rows="2" maxlength="500" placeholder="{{ __('Hi! Thanks for your interest — how can we help?') }}" class="field mt-1 !text-[13px]" data-ai-field="dm_welcome">{{ old('dm_welcome') }}</textarea>
                            </label>
                        </div>

                        {{-- Link only --}}
                        <div data-when-adtype="link" class="mt-3">
                            <label class="block">
                                <span class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Destination URL') }}</span>
                                <input type="url" name="creative_link_url" value="{{ old('creative_link_url') }}" placeholder="https://" class="field mt-1 !text-[13px]">
                            </label>
                        </div>

                        <label class="block mt-3">
                            <span class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Call to action') }}</span>
                            <select name="dm_cta" class="field mt-1 !text-[13px]">
                                @foreach (['MESSAGE_PAGE' => __('Send message'), 'LEARN_MORE' => __('Learn more'), 'SHOP_NOW' => __('Shop now'), 'SIGN_UP' => __('Sign up'), 'GET_QUOTE' => __('Get quote'), 'CONTACT_US' => __('Contact us'), 'SUBSCRIBE' => __('Subscribe')] as $cta => $lbl)
                                    <option value="{{ $cta }}" @selected(old('dm_cta') === $cta)>{{ $lbl }}</option>
                                @endforeach
                            </select>
                        </label>

                        <div class="mt-3">
                            <span class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Ad image') }}</span>
                            <div class="flex items-center gap-3 mt-1">
                                <span class="w-20 h-20 rounded-xl hairline bg-paper-50 bg-center bg-cover grid place-items-center text-ink-300 shrink-0" data-image-preview>
                                    <svg viewBox="0 0 24 24" class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="M3 17l5-4 4 3 3-2 6 5"/></svg>
                                </span>
                                <div>
                                    <input type="file" name="creative_image_file" accept="image/*" class="text-[12px]" data-image-input>
                                    <p class="text-[10.5px] text-ink-400 mt-1">{{ __('1080×1080+ JPG/PNG. Required to publish (drafts can skip it).') }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Audience / targeting --}}
                    @include('instagram.ads._targeting', ['campaign' => null])
                </div>

                {{-- ===== SIDEBAR ===== --}}
                <div style="flex:0 1 320px;min-width:280px;display:flex;flex-direction:column;gap:1rem;">
                    <div class="bg-paper-0 hairline rounded-2xl p-5">
                        <div class="serif text-[18px] mb-4">{{ __('Settings') }}</div>

                        <label class="block">
                            <span class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Instagram account') }}</span>
                            <select name="instagram_account_id" required class="field mt-1 !text-[13px]">
                                @foreach ($accounts as $a)
                                    <option value="{{ $a->id }}" @selected(old('instagram_account_id') == $a->id)>{{ '@'.($a->username ?: $a->ig_user_id) }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label class="block mt-3">
                            <span class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Daily budget') }}</span>
                            <input type="number" name="daily_budget" min="1" step="1" value="{{ old('daily_budget', 5) }}" required class="field mt-1 !text-[13px]">
                        </label>

                        <label class="block mt-3" data-when-adtype="link">
                            <span class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Optimise for') }}</span>
                            <select name="optimization_goal" class="field mt-1 !text-[13px]">
                                @foreach (['LINK_CLICKS' => __('Link clicks'), 'REACH' => __('Reach')] as $g => $lbl)
                                    <option value="{{ $g }}" @selected(old('optimization_goal') === $g)>{{ $lbl }}</option>
                                @endforeach
                            </select>
                        </label>

                        <div class="mt-4">
                            <span class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Launch as') }}</span>
                            <div class="flex flex-col gap-1.5 mt-1.5">
                                @foreach (['PAUSED' => __('Paused on Meta — review before it spends'), 'ACTIVE' => __('Active — go live immediately'), 'DRAFT' => __('Draft — save locally, do not push')] as $s => $lbl)
                                    <label class="inline-flex items-start gap-2 text-[12.5px]">
                                        <input type="radio" name="status" value="{{ $s }}" @checked(old('status', 'PAUSED') === $s) class="mt-0.5">
                                        <span>{{ $lbl }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <button type="submit" class="w-full mt-5 px-5 py-2.5 rounded-full ig-grad text-white text-[13px] font-semibold hover:opacity-90">{{ __('Create campaign') }}</button>
                    </div>

                    {{-- Write with AI (optional; enhanced by the create JS module) --}}
                    <details class="bg-paper-0 hairline rounded-2xl p-5" data-ai-panel>
                        <summary class="cursor-pointer serif text-[16px] select-none flex items-center gap-2">
                            <svg viewBox="0 0 16 16" class="w-4 h-4 text-ig-pink" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M8 2l1.5 3.5L13 7l-3.5 1.5L8 12l-1.5-3.5L3 7l3.5-1.5z"/></svg>
                            {{ __('Write with AI') }}
                        </summary>
                        <div class="mt-3 space-y-2">
                            <input type="text" data-ai-input="business_name" placeholder="{{ __('Business name') }}" class="field !text-[13px]">
                            <input type="text" data-ai-input="product" placeholder="{{ __('Product / offer') }}" class="field !text-[13px]">
                            <input type="text" data-ai-input="audience" placeholder="{{ __('Audience (optional)') }}" class="field !text-[13px]">
                            <button type="button" data-ai-generate data-endpoint="{{ route('instagram.ads.ai-generate') }}" class="w-full px-4 py-2 rounded-full ig-grad-soft text-white text-[12.5px] font-semibold hover:opacity-90">{{ __('Generate copy') }}</button>
                            <p class="text-[10.5px] text-ink-400" data-ai-status>{{ __('Fills the headline, primary text + DM message. Needs an AI key in Admin → API keys.') }}</p>
                        </div>
                    </details>
                </div>
            </div>
        </form>
    </div>
</x-layouts.instagram>
