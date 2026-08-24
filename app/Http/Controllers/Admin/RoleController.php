<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Admin → Roles & Permissions. Lightweight CRUD over the `roles` table; roles are
 * assigned to users on the admin User form (role_id). System roles (Administrator /
 * Member) are protected from rename/delete.
 */
class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::withCount('users')->orderByDesc('is_system')->orderBy('name')->get();

        // Platform admins (is_admin) never carry a role_id — they bypass the role
        // system entirely and always have full access. But showing "0 users" on the
        // Administrator role is confusing when you ARE an admin, so fold the platform
        // admin headcount into that system role's effective count.
        $adminCount = (int) User::where('is_admin', true)->count();
        foreach ($roles as $r) {
            $isAdminRole = $r->is_system && in_array('*', $r->permissions ?? [], true);
            $r->effective_users = (int) $r->users_count + ($isAdminRole ? $adminCount : 0);
        }

        $stats = [
            'total'    => $roles->count(),
            'system'   => $roles->where('is_system', true)->count(),
            'custom'   => $roles->where('is_system', false)->count(),
            'assigned' => (int) User::where(fn ($q) => $q->whereNotNull('role_id')->orWhere('is_admin', true))->count(),
        ];
        return view('admin.roles.index', compact('roles', 'stats'));
    }

    public function create()
    {
        $role    = new Role(['permissions' => []]);
        $catalog = Role::catalog();
        return view('admin.roles.create', compact('role', 'catalog'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        Role::create([
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'permissions' => $this->cleanPerms($data['permissions'] ?? []),
            'is_system'   => false,
        ]);

        return redirect()->route('admin.roles.index')->with('status', __('Role created.'));
    }

    public function edit(Role $role)
    {
        $catalog = Role::catalog();
        return view('admin.roles.edit', compact('role', 'catalog'));
    }

    public function update(Request $request, Role $role)
    {
        $data = $this->validated($request, $role);

        // System roles keep their name; only permissions/description are editable.
        $role->fill([
            'description' => $data['description'] ?? null,
            'permissions' => $this->cleanPerms($data['permissions'] ?? []),
        ]);
        if (! $role->is_system) {
            $role->name = $data['name'];
        }
        $role->save();

        return redirect()->route('admin.roles.index')->with('status', __('Role updated.'));
    }

    public function destroy(Role $role)
    {
        if ($role->is_system) {
            return back()->with('error', __('System roles cannot be deleted.'));
        }
        // Unassign the role from any users first so no one is left pointing at a
        // deleted role.
        User::where('role_id', $role->id)->update(['role_id' => null]);
        $role->delete();

        return redirect()->route('admin.roles.index')->with('status', __('Role deleted.'));
    }

    private function validated(Request $request, ?Role $role = null): array
    {
        return $request->validate([
            'name'          => ['required', 'string', 'max:80', Rule::unique('roles', 'name')->ignore($role?->id)],
            'description'   => ['nullable', 'string', 'max:255'],
            'permissions'   => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(Role::allKeys())],
        ]);
    }

    /** Drop unknown keys; if "*" is present collapse to just ["*"]. */
    private function cleanPerms(array $perms): array
    {
        $perms = array_values(array_intersect($perms, Role::allKeys()));
        return in_array('*', $perms, true) ? ['*'] : $perms;
    }
}
