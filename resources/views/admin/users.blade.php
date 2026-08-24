<x-layouts.admin :title="__('Admin · Users')" admin-key="users">

    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-4 sm:px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ route('admin.overview') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 3l3 3-3 3" /></svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Users') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <main class="px-4 sm:px-7 py-7 space-y-5">

        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">{{ __('Admin · Platform users') }}</div>
                <h1 class="font-serif font-normal tracking-[-0.01em] text-[28px] sm:text-[40px] leading-[1.0]">{{ __('All') }}
                    <span class="italic ig-text">{{ __('users') }}</span></h1>
                <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">{{ __('Manage every account on the platform: plans, admin access, and verification.') }}</p>
            </div>
            <div class="flex items-center gap-2 shrink-0 pb-1">
                <a href="{{ route('admin.users.create') }}"
                    class="px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 3v10M3 8h10" /></svg>
                    {{ __('Add user') }}
                </a>
            </div>
        </div>

        @if (session('success'))
            <div class="rounded-2xl border border-wa-green/40 bg-wa-bubble text-wa-deep px-4 py-2 text-[12.5px]">{{ session('success') }}</div>
        @endif

        {{-- 5 stat cards — same row as WaDesk's users page --}}
        <section class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Total users') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">{{ number_format($stats['total']) }}</div>
                <div class="text-[11px] text-wa-deep mt-2">+{{ $stats['thisMonth'] }} {{ __('this month') }}</div>
            </div>
            <div class="bg-paper-0 border border-wa-green/40 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Active') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">{{ number_format($stats['active']) }}</div>
                <div class="text-[11px] text-wa-deep mt-2">{{ $stats['total'] > 0 ? round($stats['active'] / $stats['total'] * 100, 1) : 0 }}% {{ __('verified') }}</div>
            </div>
            <div class="bg-paper-0 border border-accent-amber/40 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Pending') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">{{ number_format($stats['pending']) }}</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ __('unverified email') }}</div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Admins') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">{{ number_format($stats['admin']) }}</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ __('operators') }}</div>
            </div>
            <div class="bg-paper-0 border border-accent-coral/40 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Customers') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">{{ number_format($stats['customers']) }}</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ __('paying accounts') }}</div>
            </div>
        </section>

        {{-- Filter / search bar — same pill bar as WaDesk --}}
        <form method="get" action="{{ route('admin.users') }}"
            class="hairline border border-paper-200 rounded-2xl bg-paper-0 p-2 flex flex-wrap items-center gap-1 shadow-card">
            @php
                $pills = ['all' => __('All'), 'admin' => __('Admins'), 'customer' => __('Customers'), 'verified' => __('Verified'), 'unverified' => __('Unverified')];
                $counts = ['all' => $stats['total'], 'admin' => $stats['admin'], 'customer' => $stats['customers'], 'verified' => $stats['active'], 'unverified' => $stats['pending']];
            @endphp
            @foreach ($pills as $k => $label)
                <button type="submit" name="role" value="{{ $k }}" @class([
                    'inline-flex items-center gap-1.5 px-4 py-[7px] rounded-full text-[13px] cursor-pointer transition',
                    'bg-ink-900 text-paper-0' => $role === $k,
                    'text-ink-600 hover:bg-paper-50' => $role !== $k,
                ])>
                    {{ $label }}
                    <span class="font-mono text-[11px] opacity-80">({{ number_format($counts[$k]) }})</span>
                </button>
            @endforeach
            <div class="flex-1 min-w-0"></div>
            <div class="relative">
                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-ink-500" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="7" cy="7" r="5" /><path d="m11 11 3 3" /></svg>
                <input name="q" value="{{ $q }}" placeholder="{{ __('Search name, email...') }}"
                    class="hairline border border-paper-200 rounded-full pl-9 pr-3 py-1.5 text-[12px] bg-paper-0 w-full max-w-72 sm:w-72 focus:outline-none focus:border-wa-deep" />
            </div>
        </form>

        {{-- Table --}}
        <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card">
            <div class="overflow-x-auto">
                <table class="w-full text-[12.5px] table-fixed min-w-[780px]">
                    <thead class="bg-paper-50 text-ink-500 border-b border-paper-200">
                        <tr>
                            <th class="text-left px-3 py-2.5 w-[48px]"></th>
                            <th class="text-left px-2 py-2.5">{{ __('Name & contact') }}</th>
                            <th class="text-left px-2 py-2.5 w-[200px]">{{ __('Plan') }}</th>
                            <th class="text-left px-2 py-2.5 w-[110px]">{{ __('Joined') }}</th>
                            <th class="text-center px-2 py-2.5 w-[60px]" title="{{ __('Verification') }}">{{ __('Vfd') }}</th>
                            <th class="text-center px-2 py-2.5 w-[80px]">{{ __('Admin') }}</th>
                            <th class="text-center px-2 py-2.5 w-[44px]"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-paper-200">
                        @forelse ($users as $u)
                            @php
                                $initials = collect(explode(' ', trim((string) $u->name)))->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('');
                                $initials = $initials !== '' ? mb_strtoupper($initials) : '?';
                                $verified = ! is_null($u->email_verified_at);
                            @endphp
                            <tr class="hover:bg-paper-50/60">
                                <td class="px-2 py-2">
                                    <span class="w-9 h-9 rounded-full ig-grad text-white grid place-items-center text-[11.5px] font-bold">{{ $initials }}</span>
                                </td>
                                <td class="px-2 py-2 min-w-0">
                                    <div class="font-semibold leading-tight text-[12.5px] truncate">{{ $u->name }}</div>
                                    <div class="text-[10.5px] text-ink-500 mt-0.5 font-mono truncate">
                                        <a href="mailto:{{ $u->email }}" class="hover:text-wa-deep">{{ $u->email }}</a>
                                    </div>
                                </td>
                                <td class="px-2 py-2">
                                    <form action="{{ route('admin.users.update', $u->id) }}" method="POST" class="flex items-center gap-1.5">
                                        @csrf @method('PATCH')
                                        <input type="hidden" name="is_admin" value="{{ $u->is_admin ? 1 : 0 }}">
                                        <select name="package_id" onchange="this.form.submit()"
                                            class="flex-1 min-w-0 rounded-lg border border-paper-200 bg-paper-0 px-2.5 py-1.5 text-[12px] focus:outline-none focus:border-wa-deep">
                                            <option value="">{{ __('No plan') }}</option>
                                            @foreach ($packages as $p)
                                                <option value="{{ $p->id }}" @selected((string) $u->package_id === (string) $p->id)>{{ $p->name }}</option>
                                            @endforeach
                                        </select>
                                    </form>
                                </td>
                                <td class="px-2 py-2 font-mono text-[10.5px] text-ink-600 whitespace-nowrap">{{ $u->created_at?->diffForHumans() }}</td>
                                <td class="px-2 py-2 text-center">
                                    @if ($verified)
                                        <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-wa-bubble text-wa-deep" title="{{ __('Email verified') }}">
                                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2"><path d="M3.5 8l3 3 6-6" /></svg>
                                        </span>
                                    @else
                                        <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-accent-amber/15 text-accent-amber" title="{{ __('Email pending') }}">
                                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="8" cy="8" r="6" /><path d="M8 5v3.5M8 11h.01" /></svg>
                                        </span>
                                    @endif
                                </td>
                                <td class="px-2 py-2 text-center">
                                    <form action="{{ route('admin.users.update', $u->id) }}" method="POST" class="inline">
                                        @csrf @method('PATCH')
                                        <input type="hidden" name="package_id" value="{{ $u->package_id }}">
                                        <input type="hidden" name="is_admin" value="0">
                                        <label class="relative inline-block w-9 h-5 align-middle cursor-pointer">
                                            <input type="checkbox" name="is_admin" value="1" class="peer opacity-0 w-0 h-0" @checked($u->is_admin) onchange="this.form.submit()">
                                            <span class="absolute inset-0 bg-paper-200 rounded-full transition before:content-[''] before:absolute before:h-4 before:w-4 before:left-0.5 before:bottom-0.5 before:bg-paper-0 before:rounded-full before:transition peer-checked:bg-wa-deep peer-checked:before:translate-x-[16px]"></span>
                                        </label>
                                    </form>
                                </td>
                                <td class="px-2 py-2 text-center">
                                    <div class="relative inline-block" data-row-menu>
                                        <button type="button" class="w-8 h-8 rounded-full hover:bg-paper-50 grid place-items-center mx-auto" data-row-menu-toggle title="{{ __('Actions') }}">
                                            <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-ink-600" fill="currentColor"><circle cx="3" cy="8" r="1.2" /><circle cx="8" cy="8" r="1.2" /><circle cx="13" cy="8" r="1.2" /></svg>
                                        </button>
                                        <div data-row-menu-panel class="hidden absolute right-0 top-full mt-1 z-50 w-[200px] bg-paper-0 border border-paper-200 rounded-xl shadow-soft py-1 text-left">
                                            <a href="{{ route('admin.users.edit', $u->id) }}" class="flex items-center gap-2.5 px-3 py-2 text-[12.5px] text-ink-700 hover:bg-paper-50">
                                                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-ink-500" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M9.5 3.5 12.5 6.5 6 13H3v-3z"/><path d="M8.5 4.5 11.5 7.5"/></svg>{{ __('Edit user') }}
                                            </a>
                                            <a href="mailto:{{ $u->email }}" class="flex items-center gap-2.5 px-3 py-2 text-[12.5px] text-ink-700 hover:bg-paper-50">
                                                <svg viewBox="0 0 16 16" class="w-3.5 h-3.5 text-ink-500" fill="none" stroke="currentColor" stroke-width="1.6"><rect x="2" y="3.5" width="12" height="9" rx="1.5"/><path d="M2.5 4.5 8 9l5.5-4.5"/></svg>{{ __('Email user') }}
                                            </a>
                                            <div class="border-t border-paper-200 my-1"></div>
                                            <form action="{{ route('admin.users.destroy', $u->id) }}" method="POST" onsubmit="return confirm('{{ __('Delete') }} {{ addslashes($u->name) }}? {{ __('This cannot be undone.') }}')">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="w-full text-left flex items-center gap-2.5 px-3 py-2 text-[12.5px] text-accent-coral hover:bg-accent-coral/10">
                                                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 4h10M6 4V2.8h4V4M5 6v8h6V6" /></svg>{{ __('Delete user') }}
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-8 text-center text-ink-500">{{ __('No users match your filters.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="px-4 py-3 border-t border-paper-200 bg-paper-50/40 flex flex-wrap gap-2 items-center justify-between rounded-b-2xl">
                <div class="text-[11px] font-mono text-ink-500">
                    {{ __('Showing') }} {{ $users->firstItem() ?? 0 }}–{{ $users->lastItem() ?? 0 }} {{ __('of') }} {{ number_format($users->total()) }} {{ __('users') }}
                </div>
                <div>{{ $users->onEachSide(1)->links() }}</div>
            </div>
        </div>
    </main>

</x-layouts.admin>
