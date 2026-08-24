<x-layouts.instagram :title="__('Connect ad account')" ig-active="ads" page="instagram-ads-connect">
    <div class="max-w-[820px] mx-auto px-6 py-6">
        <a href="{{ route('instagram.ads') }}" class="inline-flex items-center gap-1.5 text-[12px] text-ink-500 hover:text-ink-800 mb-3">
            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M10 4l-4 4 4 4"/></svg>{{ __('Back to ads') }}
        </a>

        <div class="mb-5">
            <div class="mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">{{ brand_name() }} · {{ __('Ads setup') }}</div>
            <h1 class="serif text-[28px] sm:text-[36px] leading-none">{{ __('Connect your') }} <span class="ig-text italic">{{ __('ad account') }}</span></h1>
            <p class="text-[13px] text-ink-600 mt-2">{{ __('Pick the Meta ad account your Instagram account runs ads through. We read it from your connected token.') }}</p>
        </div>

        <x-admin.flash />
        @if ($errors->any())
            <div class="bg-accent-coral/10 hairline rounded-2xl p-4 mb-4 text-[12.5px] text-accent-coral">
                <ul class="list-disc list-inside space-y-0.5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        @if ($accounts->isEmpty())
            <div class="bg-paper-0 hairline rounded-2xl p-12 text-center">
                <span class="ig-grad w-14 h-14 rounded-2xl grid place-items-center text-white mx-auto mb-3"><svg viewBox="0 0 24 24" class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/></svg></span>
                <div class="serif text-[22px]">{{ __('Connect an Instagram account first') }}</div>
                <p class="text-[12.5px] text-ink-500 mt-1">{{ __('Link a Professional / Creator account, then wire its ad account here.') }}</p>
                <a href="{{ url('/instagram/connect') }}" class="mt-4 inline-flex items-center gap-2 px-5 py-2.5 rounded-full ig-grad-soft text-white text-[12.5px] font-semibold hover:opacity-90">{{ __('Connect Instagram') }}</a>
            </div>
        @else
            {{-- Account switcher --}}
            @if ($accounts->count() > 1)
                <div class="flex items-center gap-2 flex-wrap mb-4">
                    @foreach ($accounts as $a)
                        <a href="{{ route('instagram.ads.connect', ['account' => $a->id]) }}"
                           class="px-3 py-1.5 rounded-full text-[12px] font-semibold {{ $account && $account->id === $a->id ? 'ig-grad-soft text-white' : 'hairline bg-paper-0 text-ink-600 hover:bg-paper-50' }}">
                            {{ '@'.($a->username ?: $a->ig_user_id) }}
                        </a>
                    @endforeach
                </div>
            @endif

            @if ($account)
                <div class="bg-paper-0 hairline rounded-2xl p-5">
                    <div class="flex items-center justify-between gap-2 mb-4">
                        <div class="serif text-[18px]">{{ '@'.($account->username ?: $account->ig_user_id) }}</div>
                        @if (trim((string) $account->ad_account_id) !== '')
                            <span class="text-[10px] px-2 py-0.5 rounded-full bg-wa-bubble text-wa-deep">{{ __('Connected') }}</span>
                        @endif
                    </div>

                    @if (trim((string) $account->ad_account_id) !== '')
                        <div class="hairline rounded-xl p-3 mb-4 flex items-center justify-between gap-2">
                            <div>
                                <div class="mono text-[9px] uppercase tracking-widest text-ink-500">{{ __('Current ad account') }}</div>
                                <div class="mono text-[13px] text-ink-800">act_{{ $account->ad_account_id }}</div>
                            </div>
                            <form method="POST" action="{{ route('instagram.ads.disconnect') }}">@csrf
                                <input type="hidden" name="instagram_account_id" value="{{ $account->id }}">
                                <button class="text-[11.5px] font-semibold text-accent-coral hover:opacity-80">{{ __('Remove') }}</button>
                            </form>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('instagram.ads.keys') }}">
                        @csrf
                        <input type="hidden" name="instagram_account_id" value="{{ $account->id }}">

                        @if (!empty($discovered['ad_accounts']))
                            <div class="mono text-[10px] uppercase tracking-widest text-ink-500 mb-2">{{ __('Ad accounts we found') }}</div>
                            <div class="space-y-1.5 mb-3">
                                @foreach ($discovered['ad_accounts'] as $acct)
                                    @php $normId = preg_replace('/^act_/', '', (string) $acct['id']); @endphp
                                    <label class="hairline rounded-xl p-3 flex items-center gap-2.5 cursor-pointer">
                                        <input type="radio" name="ad_account_id" value="{{ $normId }}" @checked($account->ad_account_id === $normId)>
                                        <span class="min-w-0">
                                            <span class="block text-[13px] font-semibold truncate">{{ $acct['name'] }}</span>
                                            <span class="block mono text-[10.5px] text-ink-500">act_{{ $normId }}</span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        @else
                            <div class="bg-amber-100 rounded-xl p-3 mb-3 text-[12px] text-amber-700">
                                {{ __('We could not read any ad accounts from this connection. Its token may lack the ads_management permission — reconnect with ads access, or paste the id manually below.') }}
                                @if (!empty($discovered['error']))<div class="mono text-[10.5px] mt-1 opacity-70">{{ $discovered['error'] }}</div>@endif
                            </div>
                        @endif

                        <label class="block">
                            <span class="mono text-[10px] uppercase tracking-widest text-ink-500">{{ __('Or paste an ad account id') }}</span>
                            <input type="text" name="ad_account_id_manual" value="" placeholder="act_1234567890 {{ __('or') }} 1234567890" class="field mt-1 !text-[13px]">
                            <span class="text-[10.5px] text-ink-400">{{ __('The act_ prefix is optional. Leave blank to auto-adopt a single ad account.') }}</span>
                        </label>

                        <button type="submit" class="mt-4 px-5 py-2.5 rounded-full ig-grad text-white text-[13px] font-semibold hover:opacity-90">{{ __('Save ad account') }}</button>
                    </form>

                    @if (empty($account->page_id))
                        <p class="text-[11.5px] text-amber-700 mt-4">{{ __('Note: this account has no linked Facebook Page. Instagram ads still need a Page identity — Meta uses a Page-backed Instagram account as a fallback.') }}</p>
                    @endif
                </div>
            @endif
        @endif
    </div>
</x-layouts.instagram>
