<x-layouts.admin :title="__('Admin · Roles & Permissions')" admin-key="roles">
    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-4 sm:px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 3l3 3-3 3" /></svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ __('Roles & Permissions') }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2" data-admin-header-right></div>
    </header>

    <main class="px-4 sm:px-7 py-7 space-y-5">
        {{-- Title + primary action --}}
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">{{ __('Admin · Access control') }}</div>
                <h1 class="font-serif font-normal tracking-[-0.01em] text-[28px] sm:text-[40px] leading-[1.0]">{{ __('Roles &') }}
                    <span class="italic ig-text">{{ __('permissions') }}</span></h1>
                <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">{{ __('Create roles, choose what each can access, then assign a role to a user from the user form. Platform admins always have full access.') }}</p>
            </div>
            <div class="flex items-center gap-2 shrink-0 pb-1">
                <a href="{{ route('admin.roles.create') }}" class="px-4 py-2 rounded-full bg-wa-deep text-paper-0 text-[12px] font-semibold hover:bg-wa-teal flex items-center gap-2">
                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 3v10M3 8h10" /></svg>
                    {{ __('New role') }}
                </a>
            </div>
        </div>

        @if (session('status'))
            <div class="rounded-2xl border border-wa-green/40 bg-wa-bubble text-wa-deep px-4 py-2 text-[12.5px]">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="rounded-2xl border border-accent-coral/40 bg-accent-coral/10 text-accent-coral px-4 py-2 text-[12.5px]">{{ session('error') }}</div>
        @endif

        {{-- Stat cards --}}
        <section class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Total roles') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">{{ number_format($stats['total']) }}</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ __('all roles') }}</div>
            </div>
            <div class="bg-paper-0 border border-wa-green/40 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Custom') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">{{ number_format($stats['custom']) }}</div>
                <div class="text-[11px] text-wa-deep mt-2">{{ __('you created') }}</div>
            </div>
            <div class="bg-paper-0 border border-paper-200 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('System') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">{{ number_format($stats['system']) }}</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ __('protected') }}</div>
            </div>
            <div class="bg-paper-0 border border-accent-amber/40 rounded-2xl p-4 shadow-card">
                <div class="text-[11px] text-ink-600 font-medium">{{ __('Users assigned') }}</div>
                <div class="font-serif text-[34px] leading-none mt-1">{{ number_format($stats['assigned']) }}</div>
                <div class="text-[11px] text-ink-500 mt-2">{{ __('have a role') }}</div>
            </div>
        </section>

        {{-- Roles table --}}
        <div class="bg-paper-0 border border-paper-200 rounded-2xl shadow-card">
            <div class="overflow-x-auto">
                <table class="w-full text-[12.5px] min-w-[720px]">
                    <thead>
                        <tr class="text-left font-mono text-[10px] uppercase tracking-[0.14em] text-ink-500 border-b border-paper-200">
                            <th class="px-4 py-3">{{ __('Role') }}</th>
                            <th class="px-4 py-3">{{ __('Permissions') }}</th>
                            <th class="px-4 py-3">{{ __('Users') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($roles as $role)
                            @php $isAll = in_array('*', $role->permissions ?? [], true); @endphp
                            <tr class="border-b border-paper-100 last:border-0 hover:bg-paper-50">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <span class="w-8 h-8 rounded-lg bg-wa-bubble text-wa-deep grid place-items-center shrink-0">
                                            <svg viewBox="0 0 16 16" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="6" cy="6" r="2.4"/><path d="M2.4 13.2a3.6 3.6 0 0 1 7.2 0"/><path d="M11 5.4a2.2 2.2 0 0 1 0 4M12 13.2a3.4 3.4 0 0 0-1.9-3"/></svg>
                                        </span>
                                        <div>
                                            <div class="flex items-center gap-2">
                                                <span class="font-semibold text-ink-900">{{ $role->name }}</span>
                                                @if ($role->is_system)
                                                    <span class="text-[9px] font-mono uppercase tracking-wider px-1.5 py-0.5 rounded bg-paper-100 text-ink-500">{{ __('System') }}</span>
                                                @endif
                                            </div>
                                            @if ($role->description)
                                                <div class="text-[11.5px] text-ink-500 mt-0.5">{{ $role->description }}</div>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-ink-700">
                                    @if ($isAll)
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-wa-deep/10 text-wa-deep text-[11px] font-medium">{{ __('All permissions') }}</span>
                                    @else
                                        {{ trans_choice('{0}No permissions|{1}:count permission|[2,*]:count permissions', count($role->permissions ?? []), ['count' => count($role->permissions ?? [])]) }}
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-ink-700">{{ number_format($role->effective_users ?? $role->users_count) }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center justify-end gap-1">
                                        <a href="{{ route('admin.roles.edit', $role) }}" class="px-3 py-1.5 rounded-full hairline border border-paper-200 bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Edit') }}</a>
                                        @unless ($role->is_system)
                                            <form method="POST" action="{{ route('admin.roles.destroy', $role) }}" class="inline"
                                                data-danger="1" data-confirm-title="{{ __('Delete role?') }}" data-confirm-text="{{ __('Yes, delete') }}"
                                                data-confirm="{{ __('Delete the ":name" role? Users on this role will be left with no role until you assign a new one.', ['name' => $role->name]) }}">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="w-8 h-8 rounded-full grid place-items-center text-accent-coral hover:bg-accent-coral/10" title="{{ __('Delete') }}">
                                                    <svg viewBox="0 0 16 16" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M2.5 4h11M6 4V2.5h4V4M4.3 4l.6 9.5h6.2l.6-9.5" /></svg>
                                                </button>
                                            </form>
                                        @endunless
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-12 text-center text-ink-500">{{ __('No roles yet.') }}
                                <a href="{{ route('admin.roles.create') }}" class="text-wa-deep font-semibold hover:underline">{{ __('Create the first one') }}</a>.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</x-layouts.admin>
