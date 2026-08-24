<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SaaS admin — overview, users, analytics.
 *
 * Every count is table-guarded: the shop / lead / broadcast tables ship with
 * their features, so a build without them must not fatal the dashboard.
 */
class AdminController extends Controller
{
    /** COUNT(*) for a table, 0 if the table is absent. */
    private function count(string $table, ?callable $scope = null): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }
        $q = DB::table($table);
        if ($scope) {
            $scope($q);
        }
        return (int) $q->count();
    }

    /**
     * A KPI with a real month-over-month delta, shaped like WaDesk's dashboard
     * cards (value + signed % pill). Returns [value, delta, positive].
     */
    private function kpi(string $table, ?callable $scope = null): array
    {
        if (! Schema::hasTable($table)) {
            return ['value' => 0, 'delta' => 0.0, 'positive' => true];
        }
        $thisMonth = (int) $this->scoped($table, $scope)->where($table . '.created_at', '>=', now()->startOfMonth())->count();
        $lastMonth = (int) $this->scoped($table, $scope)
            ->whereBetween($table . '.created_at', [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()])
            ->count();
        $delta = $lastMonth > 0 ? round((($thisMonth - $lastMonth) / $lastMonth) * 100, 1) : ($thisMonth > 0 ? 100.0 : 0.0);
        return [
            'value'    => (int) $this->scoped($table, $scope)->count(),
            'delta'    => $delta,
            'positive' => $delta >= 0,
        ];
    }

    private function scoped(string $table, ?callable $scope = null)
    {
        $q = DB::table($table);
        if ($scope) $scope($q);
        return $q;
    }

    public function overview()
    {
        $stats = [
            'users'       => $this->count('users'),
            'customers'   => $this->count('users', fn ($q) => $q->where('is_admin', false)),
            'accounts'    => $this->count('instagram_accounts'),
            'automations' => $this->count('instagram_automations'),
            'flows'       => $this->count('flows'),
            'messages'    => $this->count('instagram_messages'),
            'leads'       => $this->count('instagram_leads'),
            'orders'      => $this->count('instagram_orders'),
            'packages'    => $this->count('packages', fn ($q) => $q->where('is_active', true)),
        ];

        // KPI row — 4 cards with signed % deltas, like WaDesk's dashboard.
        $kpis = [
            'customers' => $this->kpi('users', fn ($q) => $q->where('is_admin', false)),
            'accounts'  => $this->kpi('instagram_accounts'),
            'leads'     => $this->kpi('instagram_leads'),
            'orders'    => $this->kpi('instagram_orders'),
        ];

        // New signups per day for the last 14 days — SVG spark, no chart library.
        $signups = [];
        if (Schema::hasTable('users')) {
            for ($i = 13; $i >= 0; $i--) {
                $day = now()->subDays($i)->toDateString();
                $signups[] = [
                    'day' => now()->subDays($i)->format('M j'),
                    'n'   => (int) DB::table('users')->whereDate('created_at', $day)->count(),
                ];
            }
        }

        // Plan distribution — the side panel next to the spark.
        $plans = Schema::hasTable('packages')
            ? Package::withCount('users')->orderBy('sort')->get()
            : collect();

        // Three secondary tiles — the 3-column row WaDesk carries.
        $secondary = [
            ['label' => __('Automations'), 'value' => $stats['automations'], 'hint' => __('keyword & comment rules')],
            ['label' => __('Flows'),       'value' => $stats['flows'],       'hint' => __('built across the platform')],
            ['label' => __('DMs handled'), 'value' => $stats['messages'],    'hint' => __('inbound + outbound')],
        ];

        // Admin alerts — the priority panel beside the table. Real, cheap signals.
        $alerts = [];
        $pending = $this->count('users', fn ($q) => $q->whereNull('email_verified_at'));
        if ($pending > 0) {
            $alerts[] = ['severity' => 'low', 'title' => $pending . ' ' . __('unverified accounts'), 'detail' => __('Users who have not confirmed their email yet.')];
        }
        if (! \App\Services\Instagram\InstagramGate::setting('instagram_app_id')) {
            $alerts[] = ['severity' => 'high', 'title' => __('Instagram app not configured'), 'detail' => __('No account can connect until the Meta App credentials are set in Settings → Instagram.')];
        }
        if ($stats['packages'] === 0) {
            $alerts[] = ['severity' => 'high', 'title' => __('No active plans'), 'detail' => __('Create a package so new signups have something to land on.')];
        }

        $recent = Schema::hasTable('users')
            ? User::with('package')->latest()->limit(6)->get()
            : collect();

        return view('admin.overview', compact('stats', 'kpis', 'signups', 'plans', 'secondary', 'alerts', 'recent'));
    }

    public function users(\Illuminate\Http\Request $request)
    {
        $q    = trim((string) $request->get('q', ''));
        $role = (string) $request->get('role', 'all');

        $base = User::query();

        $query = (clone $base)
            ->when($q !== '', function ($w) use ($q) {
                $w->where(function ($x) use ($q) {
                    $x->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%");
                });
            })
            ->when($role === 'admin', fn ($w) => $w->where('is_admin', true))
            ->when($role === 'customer', fn ($w) => $w->where('is_admin', false))
            ->when($role === 'verified', fn ($w) => $w->whereNotNull('email_verified_at'))
            ->when($role === 'unverified', fn ($w) => $w->whereNull('email_verified_at'));

        $users = $query->with('package')->latest()->paginate(20)->withQueryString();
        $packages = Package::orderBy('sort')->get();

        // Stat cards — same 5-card row as WaDesk's users page.
        $stats = [
            'total'     => (clone $base)->count(),
            'active'    => (clone $base)->whereNotNull('email_verified_at')->count(),
            'pending'   => (clone $base)->whereNull('email_verified_at')->count(),
            'admin'     => (clone $base)->where('is_admin', true)->count(),
            'customers' => (clone $base)->where('is_admin', false)->count(),
            'thisMonth' => (clone $base)->where('created_at', '>=', now()->startOfMonth())->count(),
        ];

        return view('admin.users', compact('users', 'packages', 'stats', 'q', 'role'));
    }

    public function createUser()
    {
        $packages = Package::orderBy('sort')->get();
        $roles    = \App\Models\Role::orderByDesc('is_system')->orderBy('name')->get();
        return view('admin.users.create', ['user' => new User(), 'packages' => $packages, 'roles' => $roles]);
    }

    /** Profile-field validation shared by store + edit. */
    private function userProfileRules(): array
    {
        return [
            'mobile'  => ['nullable', 'string', 'max:40'],
            'gender'  => ['nullable', 'in:m,f,o'],
            'address' => ['nullable', 'string', 'max:1000'],
            'country' => ['nullable', 'string', 'max:80'],
            'state'   => ['nullable', 'string', 'max:80'],
            'city'    => ['nullable', 'string', 'max:80'],
            'zip'     => ['nullable', 'string', 'max:20'],
            'notes'   => ['nullable', 'string', 'max:2000'],
        ];
    }

    private function userProfileData(array $data): array
    {
        return collect(['mobile', 'gender', 'address', 'country', 'state', 'city', 'zip', 'notes'])
            ->mapWithKeys(fn ($k) => [$k => $data[$k] ?? null])->all();
    }

    public function storeUser(\Illuminate\Http\Request $request)
    {
        $data = $request->validate([
            'name'       => ['required', 'string', 'max:120'],
            'email'      => ['required', 'email', 'max:160', 'unique:users,email'],
            'password'   => ['required', 'string', 'min:6', 'confirmed'],
            'package_id' => ['nullable', 'exists:packages,id'],
            'role_id'    => ['nullable', 'exists:roles,id'],
            'is_admin'   => ['nullable', 'boolean'],
            'active'     => ['nullable', 'boolean'],
            'avatar'     => ['nullable', 'image', 'max:2048'],
        ] + $this->userProfileRules());

        $user = User::create([
            'name'       => $data['name'],
            'email'      => $data['email'],
            'password'   => bcrypt($data['password']),
            'package_id' => $data['package_id'] ?? null,
            'role_id'    => $data['role_id'] ?? null,
            'is_admin'   => (bool) ($data['is_admin'] ?? false),
            'avatar'     => $request->hasFile('avatar') ? $request->file('avatar')->store('avatars', 'public') : null,
        ] + $this->userProfileData($data));

        // "Active immediately" (default on) verifies the email so no verify screen.
        if ((bool) ($data['active'] ?? true)) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        return redirect()->route('admin.users')->with('success', __('User created.'));
    }

    public function editUser(User $user)
    {
        $packages = Package::orderBy('sort')->get();
        $roles    = \App\Models\Role::orderByDesc('is_system')->orderBy('name')->get();
        return view('admin.users.edit', compact('user', 'packages', 'roles'));
    }

    /** Full edit form save + the inline plan/admin toggles (same route). */
    public function updateUser(\Illuminate\Http\Request $request, User $user)
    {
        $isFullEdit = $request->has('name');
        $data = $request->validate([
            'name'       => ['sometimes', 'required', 'string', 'max:120'],
            'email'      => ['sometimes', 'required', 'email', 'max:160', \Illuminate\Validation\Rule::unique('users', 'email')->ignore($user->id)],
            'password'   => ['sometimes', 'nullable', 'string', 'min:6', 'confirmed'],
            'package_id' => ['nullable', 'exists:packages,id'],
            'role_id'    => ['nullable', 'exists:roles,id'],
            'is_admin'   => ['nullable', 'boolean'],
            'avatar'     => ['sometimes', 'nullable', 'image', 'max:2048'],
        ] + ($isFullEdit ? $this->userProfileRules() : []));

        if ($request->hasFile('avatar')) {
            $user->avatar = $request->file('avatar')->store('avatars', 'public');
        }

        if (array_key_exists('name', $data)) {
            $user->name = $data['name'];
        }
        if (array_key_exists('email', $data)) {
            $user->email = $data['email'];
        }
        if (! empty($data['password'])) {
            $user->password = bcrypt($data['password']);
        }
        if ($isFullEdit) {
            $user->fill($this->userProfileData($data));
        }

        $user->package_id = $data['package_id'] ?? null;
        // Role only changes when the form actually carries the field (the full edit
        // form does) — so an inline toggle POST never wipes the assigned role.
        if ($request->has('role_id')) {
            $user->role_id = $data['role_id'] ?? null;
        }
        // The last admin must not be able to demote themselves and lock everyone out.
        if (array_key_exists('is_admin', $data)) {
            $newFlag = (bool) ($data['is_admin'] ?? false);
            $others  = User::where('is_admin', true)->where('id', '!=', $user->id)->exists();
            $user->is_admin = (! $newFlag && $user->id === $request->user()->id && ! $others)
                ? true
                : $newFlag;
        }
        $user->save();

        // A full edit form redirects to the list; inline toggles just go back.
        return $request->has('name')
            ? redirect()->route('admin.users')->with('success', __('User updated.'))
            : back()->with('success', __('User updated.'));
    }

    /** Delete a user — but never yourself or the last admin. */
    public function destroyUser(\Illuminate\Http\Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            return back()->with('error', __('You cannot delete your own account.'));
        }
        if ($user->is_admin && ! User::where('is_admin', true)->where('id', '!=', $user->id)->exists()) {
            return back()->with('error', __('Cannot delete the last admin.'));
        }
        $user->delete();
        return back()->with('success', __('User deleted.'));
    }

    /** Bucketed time-series over a window: [['d'=>label,'n'=>count], ...]. */
    private function timeSeries(string $table, int $days, ?callable $scope = null): \Illuminate\Support\Collection
    {
        if (! Schema::hasTable($table)) {
            return collect();
        }
        $bucket = $this->bucketFor($days);
        $points = collect();
        for ($i = $days; $i > 0; $i -= $bucket) {
            $end   = now()->subDays(max(0, $i - $bucket));
            $start = now()->subDays($i);
            $q = DB::table($table)->where('created_at', '>', $start)->where('created_at', '<=', $end);
            if ($scope) $scope($q);
            $points->push(['d' => $end->format('M j'), 'n' => (int) $q->count()]);
        }
        return $points;
    }

    /** Bucket size (days) so a window never produces a crowded axis. */
    private function bucketFor(int $days): int
    {
        return $days <= 7 ? 1 : ($days <= 31 ? 3 : ($days <= 90 ? 7 : 30));
    }

    public function analytics(\Illuminate\Http\Request $request)
    {
        $days = (int) $request->get('days', 30);
        if (! in_array($days, [7, 30, 90, 365], true)) {
            $days = 30;
        }
        $since = now()->subDays($days);

        $kpi = [
            'customers'       => $this->count('users', fn ($q) => $q->where('is_admin', false)),
            'customers_new'   => $this->count('users', fn ($q) => $q->where('is_admin', false)->where('created_at', '>=', $since)),
            'users_total'     => $this->count('users'),
            'users_new'       => $this->count('users', fn ($q) => $q->where('created_at', '>=', $since)),
            'accounts'        => $this->count('instagram_accounts'),
            'accounts_online' => $this->count('instagram_accounts', fn ($q) => $q->where('status', 'connected')),
            'dms_window'      => $this->count('instagram_messages', fn ($q) => $q->where('created_at', '>=', $since)),
            'dms_total'       => $this->count('instagram_messages'),
            'automations'     => $this->count('instagram_automations'),
            'flows'           => $this->count('flows'),
            'leads'           => $this->count('instagram_leads'),
            'orders'          => $this->count('instagram_orders'),
        ];

        // Signup series over the window.
        $signupSeries = $this->timeSeries('users', $days);

        // Message volume in/out over the window (aligned buckets).
        $bucket = $this->bucketFor($days);
        $series = [];
        if (Schema::hasTable('instagram_messages')) {
            for ($i = $days; $i > 0; $i -= $bucket) {
                $end   = now()->subDays(max(0, $i - $bucket));
                $start = now()->subDays($i);
                $series[] = [
                    'day' => $end->format('M j'),
                    'in'  => (int) DB::table('instagram_messages')->where('direction', 'in')->where('created_at', '>', $start)->where('created_at', '<=', $end)->count(),
                    'out' => (int) DB::table('instagram_messages')->where('direction', 'out')->where('created_at', '>', $start)->where('created_at', '<=', $end)->count(),
                ];
            }
        }

        // Plan distribution.
        $byPlan = Schema::hasTable('packages')
            ? Package::withCount('users')->orderBy('sort')->get()
            : collect();
        $planDistribution = $byPlan->map(fn ($p) => (object) ['plan' => $p->name, 'n' => (int) $p->users_count]);

        // Top accounts by followers.
        $topAccounts = Schema::hasTable('instagram_accounts')
            ? \App\Models\InstagramAccount::orderByDesc('followers_count')->limit(8)->get()
            : collect();

        return view('admin.analytics', compact('days', 'kpi', 'signupSeries', 'series', 'byPlan', 'planDistribution', 'topAccounts'));
    }
}
