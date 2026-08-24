<x-layouts.instagram :title="__('Edit contact')" ig-active="contacts" page="instagram-contact-edit">
    <div class="max-w-[640px] mx-auto px-4 sm:px-6 lg:px-7 py-6">

        <a href="{{ route('instagram.contacts.index') }}" class="inline-flex items-center gap-1.5 text-[12px] text-ink-500 hover:text-ink-900 transition mb-4">
            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M10 4L6 8l4 4"/></svg>{{ __('All contacts') }}
        </a>

        <div class="flex items-center gap-3 mb-6">
            <div class="w-12 h-12 rounded-full bg-paper-100 overflow-hidden shrink-0 grid place-items-center">
                @if ($contact->avatar_url)
                    <img src="{{ $contact->avatar_url }}" alt="" class="w-full h-full object-cover" referrerpolicy="no-referrer" />
                @else
                    <svg viewBox="0 0 16 16" class="w-5 h-5 text-ink-400" fill="currentColor"><circle cx="8" cy="5.2" r="3"/><path d="M2.5 14a5.5 5.5 0 0 1 11 0z"/></svg>
                @endif
            </div>
            <div class="min-w-0">
                <div class="serif text-[20px] leading-tight truncate">{{ $contact->displayName() }}</div>
                <div class="mono text-[11px] text-ink-400">IGSID {{ $contact->igsid }}</div>
            </div>
        </div>

        @if (session('status'))
            <div class="hairline rounded-xl bg-wa-mint/10 text-wa-deep px-4 py-2.5 text-[12.5px] mb-4">{{ session('status') }}</div>
        @endif
        @foreach ($errors->all() as $e)
            <div class="hairline rounded-xl bg-accent-coral/10 text-accent-coral px-4 py-2.5 text-[12.5px] mb-2">{{ $e }}</div>
        @endforeach

        <form method="POST" action="{{ route('instagram.contacts.update', $contact->id) }}" class="grid gap-4">
            @csrf @method('PUT')

            {{-- built-ins --}}
            <div class="flex items-center gap-2.5">
                <span class="mono text-[9.5px] uppercase tracking-[0.16em] text-ink-400 shrink-0">{{ __('Built-in') }}</span>
                <span class="h-px flex-1 bg-paper-200"></span>
            </div>
            <label class="block">
                <span class="text-[12px] font-medium text-ink-700">{{ __('Name') }} <span class="mono text-[10px] ig-text">@php echo '{'.'{name}'.'}'; @endphp</span></span>
                <input type="text" name="name" value="{{ old('name', $contact->name) }}" maxlength="191" class="mt-1 w-full px-3.5 py-2.5 hairline rounded-xl bg-paper-0 text-[13px] focus:outline-none focus:border-ig-pink" />
            </label>
            <label class="block">
                <span class="text-[12px] font-medium text-ink-700">{{ __('Email') }} <span class="mono text-[10px] ig-text">@php echo '{'.'{email}'.'}'; @endphp</span></span>
                <input type="email" name="email" value="{{ old('email', $contact->email) }}" maxlength="191" class="mt-1 w-full px-3.5 py-2.5 hairline rounded-xl bg-paper-0 text-[13px] focus:outline-none focus:border-ig-pink" />
            </label>
            <label class="block">
                <span class="text-[12px] font-medium text-ink-700">{{ __('Phone') }} <span class="mono text-[10px] ig-text">@php echo '{'.'{phone}'.'}'; @endphp</span></span>
                <input type="text" name="phone" value="{{ old('phone', $contact->phone) }}" maxlength="32" class="mt-1 w-full px-3.5 py-2.5 hairline rounded-xl bg-paper-0 text-[13px] focus:outline-none focus:border-ig-pink" />
            </label>

            {{-- custom --}}
            @if ($custom->isNotEmpty())
                <div class="flex items-center gap-2.5 mt-1">
                    <span class="mono text-[9.5px] uppercase tracking-[0.16em] text-ink-400 shrink-0">{{ __('Custom attributes') }}</span>
                    <span class="h-px flex-1 bg-paper-200"></span>
                </div>
                @php $bag = is_array($contact->attributes) ? $contact->attributes : []; @endphp
                @foreach ($custom as $a)
                    @php $tag = '{'.'{'.$a->key.'}'.'}'; @endphp
                    <label class="block">
                        <span class="text-[12px] font-medium text-ink-700">{{ $a->label }} <span class="mono text-[10px] ig-text">{{ $tag }}</span></span>
                        <input type="text" name="attr[{{ $a->key }}]" value="{{ old('attr.'.$a->key, $bag[$a->key] ?? '') }}" maxlength="500" class="mt-1 w-full px-3.5 py-2.5 hairline rounded-xl bg-paper-0 text-[13px] focus:outline-none focus:border-ig-pink" />
                    </label>
                @endforeach
            @else
                <p class="text-[11.5px] text-ink-400">{{ __('No custom attributes yet.') }} <a href="{{ route('instagram.attributes.index') }}" class="ig-text hover:underline">{{ __('Add some') }}</a>.</p>
            @endif

            <div class="flex items-center gap-2 mt-2">
                <button type="submit" class="inline-flex items-center gap-1.5 px-5 py-2.5 rounded-xl ig-grad-soft text-white text-[12.5px] font-semibold shadow-soft hover:opacity-90 transition">{{ __('Save contact') }}</button>
                <a href="{{ route('instagram.contacts.index') }}" class="px-4 py-2.5 rounded-xl hairline text-[12.5px] font-medium hover:bg-paper-50 transition">{{ __('Cancel') }}</a>
            </div>
        </form>
    </div>
</x-layouts.instagram>
