<x-layouts.admin :title="__('Admin · Edit role')" admin-key="roles" page="admin-roles">
    <header class="h-16 bg-paper-0 hairline-b border-b border-paper-200 flex items-center px-4 sm:px-7 gap-4 sticky top-0 z-30">
        <div class="flex items-center gap-2 text-[12px] font-mono text-ink-500 shrink-0">
            <a href="{{ url('/admin') }}" class="uppercase tracking-[0.16em] hover:text-ink-900">{{ __('Admin') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 3l3 3-3 3" /></svg>
            <a href="{{ route('admin.roles.index') }}" class="hover:text-ink-900">{{ __('Roles') }}</a>
            <svg viewBox="0 0 12 12" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 3l3 3-3 3" /></svg>
            <span class="text-ink-900 normal-case tracking-normal">{{ $role->name }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2">
            <a href="{{ route('admin.roles.index') }}" class="px-3.5 py-1.5 hairline border border-paper-200 rounded-full bg-paper-0 hover:bg-paper-50 text-[12px] font-medium">{{ __('Cancel') }}</a>
            <button type="submit" form="roleForm" class="px-3.5 py-1.5 rounded-full bg-wa-deep hover:bg-wa-teal text-paper-0 text-[12px] font-semibold">{{ __('Save changes') }}</button>
        </div>
    </header>

    <div class="px-4 sm:px-7 pt-7 pb-2">
        <div class="font-mono text-[10px] uppercase tracking-[0.18em] text-ink-500 mb-2">{{ __('Admin · Roles · Edit') }}</div>
        <h1 class="font-serif font-normal tracking-[-0.01em] text-[28px] sm:text-[36px] leading-[1.0]">{{ __('Edit') }}
            <span class="italic ig-text">{{ $role->name }}</span></h1>
        <p class="text-[13px] text-ink-600 mt-2 max-w-2xl">{{ __('Update the name, description and what this role can access.') }}</p>
    </div>

    <main class="px-4 sm:px-7 pb-7">
        @include('admin.roles._form', ['action' => route('admin.roles.update', $role), 'method' => 'PATCH'])
    </main>
</x-layouts.admin>
