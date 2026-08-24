<x-layouts.instagram :title="__('Files')" ig-active="files" page="instagram-files">
    {{-- Personal media library. Everything you upload here, plus media the
         composer uploads and the AI image tool generates, scoped to you. --}}
    @php
        $used   = (int) ($stats['usedBytes'] ?? 0);
        // Storage-type breakdown rows with a share-of-bytes percentage.
        $typeRows = [];
        foreach ($byType as $label => $row) {
            $bytes = (int) ($row['bytes'] ?? 0);
            $pct   = $used > 0 ? (int) round($bytes / $used * 100) : 0;
            $typeRows[] = ['label' => $label, 'count' => (int) ($row['count'] ?? 0), 'bytes' => $bytes, 'pct' => $pct];
        }
        // A muted swatch per type so the bar and legend agree.
        $typeColors = [
            'Images'    => '#e1477e',
            'Videos'    => '#7c5cff',
            'Audio'     => '#2bb673',
            'Documents' => '#f0a020',
            'Other'     => '#94a3b8',
        ];
    @endphp

    <div class="px-4 sm:px-6 lg:px-7 py-6" style="width:100%;max-width:100%;">

        {{-- Header --}}
        <div class="flex items-start justify-between gap-4 flex-wrap mb-5">
            <div>
                <div class="mono text-[10px] uppercase tracking-[0.16em] text-ink-400">{{ __('Instagram') }} / {{ __('Files') }}</div>
                <h1 class="serif text-[26px] leading-tight mt-1">{{ __('Your') }} <span class="ig-text italic">{{ __('media library') }}</span></h1>
                @php $nFiles = (int) ($stats['files'] ?? 0); @endphp
                <p class="text-[12.5px] text-ink-500 mt-1.5 max-w-[560px] leading-relaxed">
                    {{ $nFiles > 0 ? __(':n files in your library.', ['n' => number_format($nFiles)]) : __('No files yet — upload some to get started.') }}
                </p>
            </div>

            {{-- Upload — a real form (works without JS); the JS just auto-submits. --}}
            <form method="POST" action="{{ url('/instagram/files/upload') }}" enctype="multipart/form-data"
                  id="ig-files-upload" class="flex items-center gap-2 shrink-0">
                @csrf
                {{-- Type a new folder name (or pick an existing one) to file these
                     uploads under it — leaving it blank drops them in "All". --}}
                <input type="text" name="folder" list="ig-folder-options" value="{{ $folder }}"
                       placeholder="{{ __('Folder (optional)') }}" maxlength="120"
                       class="px-3 py-2 rounded-full hairline bg-paper-0 text-[12px] w-40 focus:outline-none focus:border-wa-deep"
                       aria-label="{{ __('Folder for these uploads') }}">
                <datalist id="ig-folder-options">
                    @foreach (($folderList ?? []) as $f)<option value="{{ $f }}"></option>@endforeach
                </datalist>
                <input type="file" name="files[]" id="ig-files-input" multiple accept="image/*,video/*" class="hidden"
                       aria-label="{{ __('Choose files to upload') }}">
                <label for="ig-files-input"
                       class="inline-flex items-center gap-1.5 px-4 py-2 rounded-full ig-grad text-white text-[12.5px] font-medium cursor-pointer hover:opacity-95 transition">
                    <svg viewBox="0 0 16 16" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M8 10.5V2.8M5 5.6 8 2.6l3 3M3 10.5v1.7A1.3 1.3 0 0 0 4.3 13.5h7.4A1.3 1.3 0 0 0 13 12.2v-1.7"/></svg>
                    {{ __('Upload files') }}
                </label>
                <noscript><button type="submit" class="px-3 py-2 rounded-full hairline text-[12.5px]">{{ __('Go') }}</button></noscript>
            </form>
        </div>

        {{-- Flash messages --}}
        @if (session('status'))
            <div class="hairline rounded-xl bg-wa-mint/10 text-wa-deep px-4 py-2.5 text-[12.5px] mb-4">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="hairline rounded-xl bg-red-50 text-red-700 px-4 py-2.5 text-[12.5px] mb-4">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="hairline rounded-xl bg-red-50 text-red-700 px-4 py-2.5 text-[12.5px] mb-4">{{ $errors->first() }}</div>
        @endif

        {{-- Inline flex sizing — IgDesk ships a PRE-BUILT CSS bundle, so arbitrary
             Tailwind grid values (lg:grid-cols-[300px_1fr]) aren't compiled and would
             collapse the layout. Inline styles are build-proof. --}}
        <div style="display:flex;flex-wrap:wrap;gap:1.5rem;align-items:flex-start;">

            {{-- ===== Left: My files workspace card ===== --}}
            <aside class="hairline rounded-2xl bg-paper-0 p-5 shadow-card" style="flex:0 0 300px;max-width:100%;">
                <div class="flex items-center gap-2.5 mb-4">
                    <span class="w-9 h-9 rounded-xl bg-wa-mint text-wa-deep grid place-items-center shrink-0">
                        <svg viewBox="0 0 16 16" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M2.4 4.2A1.2 1.2 0 0 1 3.6 3h2.5l1.2 1.5h4.9a1.2 1.2 0 0 1 1.2 1.2v6a1.2 1.2 0 0 1-1.2 1.2H3.6a1.2 1.2 0 0 1-1.2-1.2z"/></svg>
                    </span>
                    <div>
                        <div class="font-semibold text-[13.5px] text-ink-900">{{ __('My files') }}</div>
                        <div class="mono text-[10px] uppercase tracking-[0.14em] text-ink-400">{{ __('Overview') }}</div>
                    </div>
                </div>

                {{-- Overview metrics --}}
                <dl class="space-y-2.5 text-[12.5px]">
                    <div class="flex items-center justify-between">
                        <dt class="text-ink-500">{{ __('Files') }}</dt>
                        <dd class="font-semibold text-ink-900">{{ number_format((int) ($stats['files'] ?? 0)) }}</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-ink-500">{{ __('Folders') }}</dt>
                        <dd class="font-semibold text-ink-900">{{ number_format((int) ($stats['folders'] ?? 0)) }}</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-ink-500">{{ __('File ratio (images)') }}</dt>
                        <dd class="font-semibold text-ink-900">{{ (int) ($stats['ratio'] ?? 0) }}%</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-ink-500">{{ __('Storage used') }}</dt>
                        <dd class="font-semibold text-ink-900">{{ $stats['usedHuman'] ?? '0 B' }}</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-ink-500">{{ __('Average file size') }}</dt>
                        <dd class="font-semibold text-ink-900">{{ $stats['avgHuman'] ?? '0 B' }}</dd>
                    </div>
                </dl>

                {{-- Storage types breakdown --}}
                <div class="mt-5 pt-4 border-t border-paper-100">
                    <div class="mono text-[10px] uppercase tracking-[0.14em] text-ink-400 mb-2.5">{{ __('Storage types') }}</div>

                    @if (empty($typeRows))
                        <p class="text-[12px] text-ink-500">{{ __('Nothing stored yet.') }}</p>
                    @else
                        {{-- Stacked share bar --}}
                        <div class="flex w-full h-2 rounded-full overflow-hidden bg-paper-100 mb-3">
                            @foreach ($typeRows as $t)
                                @php $c = $typeColors[$t['label']] ?? '#94a3b8'; @endphp
                                <span title="{{ $t['label'] }}" style="width: {{ max($t['pct'], 1) }}%; background: {{ $c }};"></span>
                            @endforeach
                        </div>
                        <ul class="space-y-1.5">
                            @foreach ($typeRows as $t)
                                @php $c = $typeColors[$t['label']] ?? '#94a3b8'; @endphp
                                <li class="flex items-center gap-2 text-[12px]">
                                    <span class="w-2.5 h-2.5 rounded-sm shrink-0" style="background: {{ $c }};"></span>
                                    <span class="text-ink-700">{{ $t['label'] }}</span>
                                    <span class="text-ink-400 mono text-[10.5px]">{{ $t['count'] }}</span>
                                    <span class="ml-auto text-ink-500">{{ $t['pct'] }}%</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </aside>

            {{-- ===== Right: search + folders + grid ===== --}}
            <section style="flex:1 1 420px;min-width:0;">
                {{-- Search + folder chips --}}
                <div class="flex items-center gap-3 flex-wrap mb-4">
                    <form method="GET" class="flex-1 min-w-[220px]">
                        @if ($folder !== '')<input type="hidden" name="folder" value="{{ $folder }}">@endif
                        <input type="text" name="q" value="{{ $q }}" placeholder="{{ __('Search filename, type, folder…') }}"
                               class="w-full max-w-[420px] px-3.5 py-2.5 hairline rounded-xl bg-paper-0 text-[13px] focus:outline-none focus:border-ig-pink" />
                    </form>
                </div>

                @if (!empty($folderList))
                    <div class="flex items-center gap-2 flex-wrap mb-4">
                        <a href="{{ url('/instagram/files') }}{{ $q !== '' ? '?q=' . urlencode($q) : '' }}"
                           class="px-3 py-1.5 rounded-full hairline text-[11.5px] {{ $folder === '' ? 'bg-wa-deep text-white' : 'hover:bg-paper-50' }}">{{ __('All') }}</a>
                        @foreach ($folderList as $f)
                            @php
                                $qs = ['folder' => $f];
                                if ($q !== '') $qs['q'] = $q;
                                $href = url('/instagram/files') . '?' . http_build_query($qs);
                            @endphp
                            <a href="{{ $href }}"
                               class="px-3 py-1.5 rounded-full hairline text-[11.5px] {{ $folder === $f ? 'bg-wa-deep text-white' : 'hover:bg-paper-50' }}">{{ $f }}</a>
                        @endforeach
                    </div>
                @endif

                @if ($files->isEmpty())
                    <div class="hairline rounded-2xl bg-paper-0 px-4 py-14 text-center">
                        <div class="w-12 h-12 rounded-2xl bg-paper-100 grid place-items-center mx-auto mb-3">
                            <svg viewBox="0 0 16 16" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.4" class="text-ink-400"><rect x="2.2" y="3" width="11.6" height="10" rx="2"/><path d="m4.5 11 2.4-2.6 1.6 1.6 2-2.2 1.6 1.9"/><circle cx="6" cy="6.2" r="1"/></svg>
                        </div>
                        <p class="text-[13px] font-semibold">{{ $q !== '' || $folder !== '' ? __('No files match') : __('No files yet') }}</p>
                        <p class="text-[12px] text-ink-500 mt-1.5">{{ __('Upload images or videos, or publish through the composer — they land here.') }}</p>
                    </div>
                @else
                    {{-- Bulk bar. This whole grid is one POST form so "Delete selected"
                         works even without JS; the JS only wires check-all + confirms. --}}
                    <form method="POST" action="{{ url('/instagram/files/bulk-delete') }}" id="ig-files-bulk"
                          data-confirm="{{ __('Delete the selected files? This cannot be undone.') }}">
                        @csrf

                        <div class="flex items-center justify-between gap-3 flex-wrap mb-3">
                            <label class="inline-flex items-center gap-2 text-[12.5px] text-ink-600 select-none cursor-pointer">
                                <input type="checkbox" id="ig-files-check-all" class="w-4 h-4 rounded border-paper-300 text-ig-pink focus:ring-0">
                                {{ __('Check all') }}
                            </label>
                            <div class="flex items-center gap-2">
                                <span id="ig-files-count" class="text-[12px] text-ink-400"></span>
                                <button type="submit" id="ig-files-delete-selected"
                                        class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-full hairline text-[12px] font-medium text-red-600 hover:bg-red-50 transition disabled:opacity-40 disabled:cursor-not-allowed" disabled>
                                    <svg viewBox="0 0 16 16" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 4.5h10M6.2 4.5V3.2h3.6v1.3M4.3 4.5l.5 8.2a1 1 0 0 0 1 .95h4.4a1 1 0 0 0 1-.95l.5-8.2"/></svg>
                                    {{ __('Delete selected') }}
                                </button>
                            </div>
                        </div>

                        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:1rem;">
                            @foreach ($files as $file)
                                <div class="ig-file-card group relative hairline rounded-2xl bg-paper-0 overflow-hidden shadow-card hover:shadow-soft transition flex flex-col">
                                    {{-- Select checkbox --}}
                                    <label class="absolute top-2 left-2 z-10 bg-paper-0/90 rounded-md p-1 shadow-card cursor-pointer">
                                        <input type="checkbox" name="ids[]" value="{{ $file->id }}" class="ig-file-check w-4 h-4 rounded border-paper-300 text-ig-pink focus:ring-0 align-middle">
                                    </label>

                                    {{-- Thumbnail --}}
                                    <div class="bg-paper-100 grid place-items-center overflow-hidden" style="aspect-ratio:1/1;">
                                        @if ($file->is_image)
                                            <img src="{{ $file->url }}" alt="{{ $file->original_name }}" loading="lazy"
                                                 class="w-full h-full object-cover" referrerpolicy="no-referrer">
                                        @elseif ($file->is_video)
                                            <div class="w-full h-full grid place-items-center text-ink-400">
                                                <svg viewBox="0 0 20 20" width="34" height="34" fill="none" stroke="currentColor" stroke-width="1.3"><rect x="2.5" y="4" width="15" height="12" rx="2.4"/><path d="M8.4 7.6 12.6 10l-4.2 2.4z" fill="currentColor" stroke="none"/></svg>
                                            </div>
                                        @else
                                            <div class="w-full h-full grid place-items-center text-ink-400">
                                                <svg viewBox="0 0 20 20" width="30" height="30" fill="none" stroke="currentColor" stroke-width="1.3"><path d="M5 2.6h6l4 4v10.8a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V3.6a1 1 0 0 1 1-1z"/><path d="M11 2.6v4h4"/></svg>
                                            </div>
                                        @endif
                                    </div>

                                    {{-- Meta --}}
                                    <div class="p-3 flex flex-col gap-1 grow">
                                        <div class="font-semibold text-[12px] text-ink-900 truncate" title="{{ $file->original_name }}">{{ $file->original_name ?: basename($file->path) }}</div>
                                        <div class="flex items-center gap-1.5 text-[10.5px] text-ink-400 mono uppercase tracking-[0.06em]">
                                            <span>{{ $file->kind_label }}</span>
                                            <span aria-hidden="true">·</span>
                                            <span>{{ $file->human_size }}</span>
                                        </div>
                                        <div class="text-[10.5px] text-ink-400">{{ optional($file->created_at)->format('M j, Y') }}</div>

                                        {{-- Actions --}}
                                        <div class="flex items-center gap-1 mt-2 pt-2 border-t border-paper-100">
                                            {{-- Edit: opens original in a new tab (no-JS); JS upgrades to inline rename. --}}
                                            <a href="{{ $file->url }}" target="_blank" rel="noopener"
                                               class="ig-file-edit inline-flex items-center gap-1 px-2 py-1 rounded-lg text-[11px] text-ink-600 hover:bg-paper-50 transition"
                                               data-id="{{ $file->id }}" data-name="{{ $file->original_name }}" title="{{ __('Edit / open') }}">
                                                <svg viewBox="0 0 16 16" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M11.2 2.6 13.4 4.8 6.1 12.1 3 13l.9-3.1z"/></svg>
                                                {{ __('Edit') }}
                                            </a>
                                            <a href="{{ url('/instagram/files/' . $file->id . '/download') }}"
                                               class="inline-flex items-center gap-1 px-2 py-1 rounded-lg text-[11px] text-ink-600 hover:bg-paper-50 transition" title="{{ __('Download') }}">
                                                <svg viewBox="0 0 16 16" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M8 2.6v7M5 6.6 8 9.6l3-3M3 12.4h10"/></svg>
                                                {{ __('Get') }}
                                            </a>
                                            {{-- Move into / out of a folder (JS prompt; no-JS falls back to the form below). --}}
                                            <button type="button"
                                                    class="ig-file-move inline-flex items-center gap-1 px-2 py-1 rounded-lg text-[11px] text-ink-600 hover:bg-paper-50 transition"
                                                    data-id="{{ $file->id }}" data-folder="{{ $file->folder }}" title="{{ __('Move to folder') }}">
                                                <svg viewBox="0 0 16 16" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M1.8 4.4A1.2 1.2 0 0 1 3 3.2h2.6l1.2 1.5h5.2A1.2 1.2 0 0 1 14.2 5.9v6.5a1.2 1.2 0 0 1-1.2 1.2H3a1.2 1.2 0 0 1-1.2-1.2z"/></svg>
                                                {{ __('Move') }}
                                            </button>
                                            {{-- Real submit (works without JS) via the out-of-tree per-file
                                                 form below; the JS only adds a confirm. --}}
                                            <button type="submit" form="igfd-{{ $file->id }}"
                                                    class="ig-file-delete ml-auto inline-flex items-center gap-1 px-2 py-1 rounded-lg text-[11px] text-red-600 hover:bg-red-50 transition"
                                                    data-name="{{ $file->original_name }}" title="{{ __('Delete') }}">
                                                <svg viewBox="0 0 16 16" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 4.5h10M6.2 4.5V3.2h3.6v1.3M4.3 4.5l.5 8.2a1 1 0 0 0 1 .95h4.4a1 1 0 0 0 1-.95l.5-8.2"/></svg>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </form>

                    {{-- One real DELETE form per file, out of the bulk form's tree (nested
                         forms are invalid). Each card's delete button targets its form by id,
                         so delete works with JS OFF; the JS just adds a confirm dialog. --}}
                    @foreach ($files as $file)
                        <form id="igfd-{{ $file->id }}" method="POST" action="{{ url('/instagram/files/' . $file->id) }}" class="hidden">
                            @csrf
                            @method('DELETE')
                        </form>
                    @endforeach

                    <div class="mt-6">{{ $files->links() }}</div>
                @endif
            </section>
        </div>
    </div>
</x-layouts.instagram>
