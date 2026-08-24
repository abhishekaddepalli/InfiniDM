<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A platform role — a named set of permissions assigned to users from the admin
 * User form. Lightweight (no Spatie): permissions are a JSON list of dotted keys
 * drawn from Role::catalog(). The Administrator role holds the wildcard "*".
 */
class Role extends Model
{
    protected $fillable = ['name', 'description', 'permissions', 'is_system'];

    protected $casts = [
        'permissions' => 'array',
        'is_system'   => 'boolean',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** Does this role grant a permission key? "*" grants everything. */
    public function grants(string $key): bool
    {
        $perms = $this->permissions ?: [];
        return in_array('*', $perms, true) || in_array($key, $perms, true);
    }

    /**
     * The permission catalog, grouped for the create/edit checkboxes.
     *
     * These are ADMIN-PANEL permissions ONLY — each key maps to a section of the
     * admin sidebar. Roles decide which admin areas a staff member may open. The
     * customer-facing app is NOT role-gated at all: every user sees every feature,
     * and access there is decided purely by their PLAN (a feature not in the plan
     * routes them to the pricing page). So there are deliberately no user-side
     * feature keys here.
     */
    public static function catalog(): array
    {
        return [
            'Overview' => [
                'admin.dashboard.view' => 'View admin dashboard',
                'admin.analytics.view' => 'View analytics & reports',
                'admin.health.view'    => 'View system health',
            ],
            'Users & Access' => [
                'admin.users.manage' => 'Manage users',
                'admin.roles.manage' => 'Manage roles & permissions',
            ],
            'Billing & Plans' => [
                'admin.billing.manage'  => 'Manage billing & invoices',
                'admin.packages.manage' => 'Manage plans & packages',
            ],
            'Content & Config' => [
                'admin.templates.manage' => 'Manage flow templates',
                'admin.instagram.manage' => 'Manage Instagram API settings',
                'admin.localization.manage' => 'Manage localization',
                'admin.storage.manage'   => 'Manage cloud storage',
            ],
            'Platform' => [
                'admin.settings.manage' => 'Manage platform settings',
                'admin.security.manage' => 'Manage security',
                'admin.audit.view'      => 'View audit log',
            ],
        ];
    }

    /** Flat list of every valid permission key (for validation). */
    public static function allKeys(): array
    {
        $keys = ['*'];
        foreach (self::catalog() as $group) {
            $keys = array_merge($keys, array_keys($group));
        }
        return $keys;
    }
}
