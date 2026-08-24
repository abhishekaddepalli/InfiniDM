@php
    $fld  = 'w-full px-3 py-2 rounded-lg border border-paper-200 bg-paper-0 text-[13px] focus:outline-none focus:border-wa-deep focus:ring-4 focus:ring-wa-deep/10';
    $perms = old('permissions', $role->permissions ?? []);
    $isAll = in_array('*', $perms, true);
@endphp

<form id="roleForm" action="{{ $action }}" method="POST" class="max-w-[900px]">
    @csrf
    @isset($method) @method($method) @endisset

    @if ($errors->any())
        <div class="mb-4 rounded-xl border border-accent-coral/30 bg-accent-coral/10 px-4 py-2.5 text-[13px] text-accent-coral">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="rounded-2xl border border-paper-200 bg-paper-0 p-5 mb-5">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label for="name" class="text-[11.5px] font-semibold text-ink-700 mb-[5px] block">{{ __('Role name') }}</label>
                <input id="name" name="name" type="text" value="{{ old('name', $role->name) }}" class="{{ $fld }}"
                    placeholder="{{ __('e.g. Support agent') }}" {{ ($role->is_system ?? false) ? 'readonly' : 'required' }}>
                @if ($role->is_system ?? false)
                    <div class="text-[10.5px] text-ink-500 mt-1">{{ __('System role — name is locked.') }}</div>
                @endif
            </div>
            <div>
                <label for="description" class="text-[11.5px] font-semibold text-ink-700 mb-[5px] block">{{ __('Description') }}</label>
                <input id="description" name="description" type="text" value="{{ old('description', $role->description) }}" class="{{ $fld }}"
                    placeholder="{{ __('What this role is for') }}">
            </div>
        </div>
    </div>

    <div class="rounded-2xl border border-paper-200 bg-paper-0 p-5">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h2 class="text-[14px] font-semibold text-ink-900">{{ __('Permissions') }}</h2>
                <p class="text-[11.5px] text-ink-500">{{ __('Tick what this role can do.') }}</p>
            </div>
            <label class="flex items-center gap-2 text-[12px] font-medium text-ink-700 cursor-pointer">
                <input type="checkbox" name="permissions[]" value="*" id="perm-all" class="rounded border-paper-300 text-wa-deep focus:ring-wa-deep" @checked($isAll)>
                {{ __('Full access (all permissions)') }}
            </label>
        </div>

        <div id="perm-groups" class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-5 {{ $isAll ? 'opacity-40 pointer-events-none' : '' }}">
            @foreach ($catalog as $group => $items)
                <div>
                    <div class="text-[10px] font-mono uppercase tracking-[0.14em] text-ink-500 mb-2">{{ $group }}</div>
                    <div class="space-y-1.5">
                        @foreach ($items as $key => $label)
                            <label class="flex items-center gap-2 text-[13px] text-ink-800 cursor-pointer">
                                <input type="checkbox" name="permissions[]" value="{{ $key }}"
                                    class="perm-item rounded border-paper-300 text-wa-deep focus:ring-wa-deep"
                                    @checked(in_array($key, $perms, true))>
                                {{ __($label) }}
                            </label>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</form>

<script>
    // "Full access" dims + disables the individual checkboxes (they're irrelevant
    // when "*" is granted). No build step — inline, page-local behaviour.
    (function () {
        var all    = document.getElementById('perm-all');
        var groups = document.getElementById('perm-groups');
        if (!all || !groups) return;
        function sync() {
            groups.classList.toggle('opacity-40', all.checked);
            groups.classList.toggle('pointer-events-none', all.checked);
        }
        all.addEventListener('change', sync);
    })();
</script>
