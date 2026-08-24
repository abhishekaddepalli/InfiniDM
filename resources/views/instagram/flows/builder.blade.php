{{--
    Flow builder canvas — STANDALONE Instagram product only.

    The #root data-* contract below is NOT decorative: user-flows-builder.js
    reads every one of these on boot. Renaming or dropping one makes the
    builder come up blank with no error, which is the hardest failure of all to
    diagnose. Kept byte-identical to core's builder.blade.php for that reason.

    data-flow-type is hard-pinned to "instagram" and data-ext-instagram to "1":
    this product has no WhatsApp and no call flows, so there is no channel to
    choose and nothing to gate.
--}}
<x-layouts.instagram :title="$flowId ? __('Edit flow') : __('New flow')" page="instagram-flow-builder" :bare="true">

    <div id="root"
        data-flow-id="{{ $flowId }}"
        data-flow-name="{{ $flowName }}"
        data-flow-category="{{ $category }}"
        data-flow-published="{{ $isPublished ? '1' : '0' }}"
        data-flow-type="instagram"
        data-ext-instagram="1"
        data-flow-json="{{ json_encode($flowJson, JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG) }}">

        <div class="h-screen w-screen grid place-items-center">
            <div class="text-center">
                <div class="font-serif text-[18px] text-ink-700">{{ __('Loading flow builder...') }}</div>
                <div class="text-[12px] text-ink-500 mt-1">{{ __('If this does not clear, the builder bundle failed to load.') }}</div>
            </div>
        </div>
    </div>

    {{-- Pre-built and shipped inside the package — the client never runs a
         build, and Laravel's @vite manifest only ever contains core's entries. --}}
    @push('scripts')
        <script type="module" src="{{ asset('extensions/instagram/instagram-flow-builder.js') }}"></script>
    @endpush
</x-layouts.instagram>
