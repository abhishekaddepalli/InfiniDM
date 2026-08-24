<x-layouts.instagram :title="__('Commerce')" ig-active="commerce" page="instagram-commerce">
    <div class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-7">

        <div class="mb-5">
            <div class="mono font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500">{{ __('Instagram · Studio') }}</div>
            <h1 class="serif font-serif font-normal tracking-[-0.01em] text-[26px] leading-tight">{{ __('Commerce') }}</h1>
            <p class="text-[13px] text-ink-600 mt-1 max-w-2xl">
                {{ __('Connect a Commerce Manager catalog, then sync its products so you can send them as shoppable cards in DMs.') }}
            </p>
        </div>

        @if (session('status'))
            <div class="mb-4 bg-wa-mint border border-wa-green/30 rounded-lg px-4 py-2 text-[12.5px] text-wa-deep font-mono">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-4 bg-accent-coral/10 border border-accent-coral/40 rounded-lg px-4 py-3 text-[12.5px] text-accent-coral">
                @foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach
            </div>
        @endif

        @forelse ($accounts as $acc)
            <div class="mb-4 border border-paper-200 rounded-2xl bg-paper-0 p-5 shadow-card">
                <div class="flex items-center gap-3 mb-3">
                    <div class="font-semibold text-[14px]">{{ '@' . ($acc->username ?: $acc->name ?: $acc->ig_user_id) }}</div>
                    @if ($acc->catalog_synced_at)
                        <span class="pill bg-wa-mint text-wa-deep text-[10.5px] px-2 py-0.5 rounded-full mono">{{ __('synced') }} {{ $acc->catalog_synced_at->diffForHumans() }}</span>
                    @endif
                </div>

                <form method="POST" action="{{ route('instagram.commerce.catalog') }}" class="flex flex-wrap items-end gap-2 mb-3">
                    @csrf
                    <input type="hidden" name="account_id" value="{{ $acc->id }}">
                    <div class="flex-1 min-w-[240px]">
                        <label class="block mono font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500 mb-1">{{ __('Commerce Manager Catalog ID') }}</label>
                        <input name="catalog_id" value="{{ $acc->catalog_id }}" placeholder="{{ __('e.g. 1234567890') }}"
                            class="field w-full" inputmode="numeric">
                    </div>
                    <button class="px-3 py-2 rounded-lg bg-ink-900 text-white text-[12px] font-semibold">{{ __('Save') }}</button>
                    @if ($acc->catalog_id)
                        <button form="ig-sync-{{ $acc->id }}" class="px-3 py-2 rounded-lg ig-grad-soft text-white text-[12px] font-semibold">{{ __('Sync products') }}</button>
                    @endif
                </form>
                <form id="ig-sync-{{ $acc->id }}" method="POST" action="{{ route('instagram.commerce.sync') }}" class="hidden">
                    @csrf<input type="hidden" name="account_id" value="{{ $acc->id }}">
                </form>

                <p class="text-[11.5px] text-ink-500">
                    {{ __('Find your Catalog ID in') }} <a href="https://business.facebook.com/commerce" target="_blank" rel="noopener" class="text-ig-purple underline">Commerce Manager</a>
                    {{ __('→ Catalog → Settings. The catalog must belong to the same business as this Instagram account.') }}
                </p>

                {{-- Zero-config storefront: type a keyword → get the catalog. No flow to build. --}}
                @php $sa = (array) data_get($acc->meta_json, 'shop_auto', []); @endphp
                <form method="POST" action="{{ route('instagram.commerce.shop-auto') }}" class="mt-4 pt-4 border-t border-paper-200">
                    @csrf
                    <input type="hidden" name="account_id" value="{{ $acc->id }}">
                    <input type="hidden" name="enabled" value="0">
                    <label class="flex items-center gap-2.5 cursor-pointer mb-2">
                        <input type="checkbox" name="enabled" value="1" @checked(!empty($sa['enabled']))>
                        <span class="text-[13px] font-semibold">{{ __('Auto-send my catalog on a keyword') }}</span>
                    </label>
                    <p class="text-[11.5px] text-ink-500 mb-2.5">{{ __('When someone DMs one of these words, they instantly get your products — no flow needed.') }}</p>
                    <div class="flex flex-wrap items-end gap-2">
                        <div class="flex-1 min-w-[200px]">
                            <label class="block mono font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500 mb-1">{{ __('Keywords (comma-separated)') }}</label>
                            <input name="keywords" value="{{ $sa['keywords'] ?? 'shop, catalog, menu, products, price' }}" class="field w-full text-[12.5px]">
                        </div>
                        <div class="flex-1 min-w-[200px]">
                            <label class="block mono font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500 mb-1">{{ __('Intro line (optional)') }}</label>
                            <input name="intro" value="{{ $sa['intro'] ?? '' }}" placeholder="{{ __('Here’s what we have in stock:') }}" class="field w-full text-[12.5px]">
                        </div>
                        <button class="px-3 py-2 rounded-lg bg-ink-900 text-white text-[12px] font-semibold">{{ __('Save') }}</button>
                    </div>
                </form>
            </div>
        @empty
            <div class="border border-dashed border-paper-200 rounded-2xl bg-paper-0 py-10 text-center text-[13px] text-ink-500">
                {{ __('Connect an Instagram account first.') }}
            </div>
        @endforelse

        {{-- Synced products preview --}}
        <div class="mt-6">
            <div class="mono font-mono text-[10px] uppercase tracking-[0.16em] text-ink-500 mb-2">{{ __('Synced products') }} ({{ $products->count() }})</div>
            @if ($products->isEmpty())
                <div class="text-[12.5px] text-ink-500">{{ __('No products synced yet.') }}</div>
            @else
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                    @foreach ($products as $p)
                        <div class="border border-paper-200 rounded-xl bg-paper-0 overflow-hidden">
                            <div class="aspect-square bg-paper-100">
                                @if ($p->image_url)<img src="{{ $p->image_url }}" alt="" class="w-full h-full object-cover" loading="lazy">@endif
                            </div>
                            <div class="p-2">
                                <div class="text-[12px] font-medium truncate" title="{{ $p->name }}">{{ $p->name }}</div>
                                <div class="text-[11px] text-ink-500 mono">{{ $p->currency }} {{ $p->price !== null ? number_format((float) $p->price, 2) : '—' }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-layouts.instagram>
