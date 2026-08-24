<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Standalone identity. In WaDesk the host app owns App\Models\User and this
 * file is never shipped — build.py excludes standalone/ from the addon
 * archive, which is also why it may not live in the shared tree: the path
 * collides with the core one and assert_no_core_collisions() would refuse.
 */
class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',
        'role_id',
        'package_id',
        'trial_ends_at',
        'plan_ends_at',
        'avatar',
        'mobile',
        'gender',
        'address',
        'country',
        'state',
        'city',
        'zip',
        'notes',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'trial_ends_at' => 'datetime',
            'plan_ends_at' => 'datetime',
        ];
    }

    /** The operator who runs the SaaS. Gates every /admin route. */
    public function isAdmin(): bool
    {
        return (bool) $this->is_admin;
    }

    /** The plan this customer is on (null until assigned / for the admin). */
    public function package()
    {
        return $this->belongsTo(\App\Models\Package::class);
    }

    public function role()
    {
        return $this->belongsTo(\App\Models\Role::class);
    }

    /**
     * Standalone Instaflow has NO workspaces — each USER is their own tenant.
     * Every feature scopes on `workspace_id`, read as `current_workspace_id ?? 0`.
     * Resolving that to the user's own id makes `workspace_id` == `user_id` on
     * every read AND write, giving hard per-user isolation with no cross-user
     * leak — and no mass query rewrite.
     *
     * PREVIOUS BUG: this was undefined → null → `?? 0`, so EVERY user's rows were
     * written under workspace 0 and every user saw every other user's chats.
     * A one-time backfill migration re-stamps the old workspace-0 rows to their
     * owner's id so nothing is orphaned.
     */
    public function getCurrentWorkspaceIdAttribute(): int
    {
        return (int) $this->getKey();
    }

    /** Permission check — platform admins bypass; otherwise defer to the role. */
    public function can2($permissionKey): bool
    {
        if ($this->is_admin) return true;
        return (bool) optional($this->role)->grants($permissionKey);
    }

    // There is deliberately NO currentWorkspace() relation here.
    //
    // Standalone ships no workspaces table and no Workspace model, so a
    // belongsTo(Workspace::class) fatals on instantiation — and because the
    // two Blade consumers and InstagramReposterController read the workspace
    // while building a call ARGUMENT, that Error lands outside the try/catch
    // in InstagramGate::allows() and 500s the page rather than failing closed.
    //
    // Leaving both the relation and `current_workspace_id` undefined makes
    // Eloquent return null for every spelling the shared feature code uses:
    //
    //   $user->current_workspace_id  ->  null, and the ~11 call sites read it
    //                                    as `?? 0`, so every row is written
    //                                    under workspace 0
    //   $user->current_workspace     ->  null  (getRelationValue finds no such
    //   $user->currentWorkspace           method and stops there, no error)
    //
    // Reads must land on the same 0 the writes use or accounts become
    // invisible, and they do: _rail.blade.php takes `(int) ($ws->id ?? 0)`,
    // and header.blade.php guards its chip with `@if ($currentWs)` so the
    // switcher simply does not render on a product that cannot switch.
    //
    // The flows migration keeps a nullable workspace_id for a future
    // multi-brand standalone build. Restoring a relation here is therefore
    // fair game LATER, but only together with the model, a workspaces
    // migration, a users.current_workspace_id column and a backfill of the
    // existing workspace-0 rows — adding it alone re-breaks the boot, and
    // adding it without the backfill orphans every account already stored.
}
