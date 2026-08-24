<x-layouts.instagram :title="__('Contacts')" ig-active="contacts" page="instagram-contacts">
    {{-- Everyone who has DMed a connected account. Open one to set attribute
         values by hand (Name/Email/Phone + custom). --}}
    <div class="max-w-none mx-auto px-4 sm:px-6 lg:px-7 py-6">

        <div class="flex items-start justify-between gap-4 flex-wrap mb-5">
            <div>
                <div class="mono text-[10px] uppercase tracking-[0.16em] text-ink-400">{{ __('Instagram') }} / {{ __('Contacts') }}</div>
                <h1 class="serif text-[26px] leading-tight mt-1">{{ __('Your') }} <span class="ig-text italic">{{ __('contacts') }}</span></h1>
                <p class="text-[12.5px] text-ink-500 mt-1.5 max-w-[560px] leading-relaxed">{{ __('Open a contact to view and set their attribute values.') }}</p>
            </div>
            <a href="{{ route('instagram.attributes.index') }}" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-full hairline text-[12.5px] font-medium hover:bg-paper-50 transition shrink-0">{{ __('Manage attributes') }}</a>
        </div>

        @if (session('status'))
            <div class="hairline rounded-xl bg-wa-mint/10 text-wa-deep px-4 py-2.5 text-[12.5px] mb-4">{{ session('status') }}</div>
        @endif

        <form method="GET" class="mb-4">
            <input type="text" name="q" value="{{ $q }}" placeholder="{{ __('Search name, @username, email, phone…') }}"
                   class="w-full max-w-[420px] px-3.5 py-2.5 hairline rounded-xl bg-paper-0 text-[13px] focus:outline-none focus:border-ig-pink" />
        </form>

        @if ($contacts->isEmpty())
            <div class="hairline rounded-2xl bg-paper-0 px-4 py-10 text-center">
                <p class="text-[13px] font-semibold">{{ __('No contacts yet') }}</p>
                <p class="text-[12px] text-ink-500 mt-1.5">{{ __('People appear here after they DM a connected Instagram account.') }}</p>
            </div>
        @else
            <div class="hairline rounded-2xl bg-paper-0 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-[12.5px]">
                        <thead>
                            <tr class="text-left text-ink-500 mono font-mono text-[10px] uppercase tracking-[0.12em] border-b border-paper-200">
                                <th class="px-4 py-2.5 font-medium">{{ __('Contact') }}</th>
                                <th class="px-4 py-2.5 font-medium">{{ __('Name') }}</th>
                                <th class="px-4 py-2.5 font-medium">{{ __('Email') }}</th>
                                <th class="px-4 py-2.5 font-medium">{{ __('Phone') }}</th>
                                <th class="px-4 py-2.5 font-medium">{{ __('Last message') }}</th>
                                <th class="px-4 py-2.5 font-medium text-right">{{ __('Action') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-paper-100">
                            @foreach ($contacts as $c)
                                <tr class="hover:bg-paper-50/60">
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2.5 min-w-0">
                                            <div class="w-8 h-8 rounded-full bg-paper-100 overflow-hidden shrink-0 grid place-items-center">
                                                @if ($c->avatar_url)
                                                    <img src="{{ $c->avatar_url }}" alt="" class="w-full h-full object-cover" referrerpolicy="no-referrer" />
                                                @else
                                                    <svg viewBox="0 0 16 16" class="w-4 h-4 text-ink-400" fill="currentColor"><circle cx="8" cy="5.2" r="3"/><path d="M2.5 14a5.5 5.5 0 0 1 11 0z"/></svg>
                                                @endif
                                            </div>
                                            <span class="font-semibold truncate">{{ $c->displayName() }}</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-ink-700">{{ $c->name ?: '—' }}</td>
                                    <td class="px-4 py-3 text-ink-700">{{ $c->email ?: '—' }}</td>
                                    <td class="px-4 py-3 text-ink-700 font-mono text-[11.5px]">{{ $c->phone ?: '—' }}</td>
                                    <td class="px-4 py-3 text-ink-500 font-mono text-[11.5px]">{{ optional($c->last_message_at)->diffForHumans() ?: '—' }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <a href="{{ route('instagram.contacts.edit', $c->id) }}" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg hairline text-[11.5px] font-medium hover:bg-paper-50 transition">{{ __('Edit') }}</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-5">{{ $contacts->links() }}</div>
        @endif
    </div>
</x-layouts.instagram>
