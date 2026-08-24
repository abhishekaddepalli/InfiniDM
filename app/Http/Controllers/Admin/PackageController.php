<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Package;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** SaaS plans CRUD. */
class PackageController extends Controller
{
    public function index()
    {
        $packages = Package::withCount('users')->orderBy('sort')->orderBy('price')->get();
        $mrr = (float) $packages->where('is_active', true)->sum(fn ($p) => (float) $p->price * (int) $p->users_count);
        $stats = [
            'total'      => $packages->count(),
            'active'     => $packages->where('is_active', true)->count(),
            'trial'      => $packages->filter(fn ($p) => (int) $p->trial_days > 0)->count(),
            'archived'   => $packages->where('is_active', false)->count(),
            'subscribed' => (int) $packages->sum('users_count'),
            'mrr'        => $mrr,
            'currency'   => (string) (\App\Models\Setting::get('default_currency', '') ?: ($packages->first()->currency ?? 'USD')),
        ];
        return view('admin.packages.index', compact('packages', 'stats'));
    }

    /** Status toggle from the detailed table. */
    public function toggle(Package $package)
    {
        $package->is_active = ! $package->is_active;
        $package->save();
        return back()->with('success', __('Plan updated.'));
    }

    public function create()
    {
        return view('admin.packages.form', ['package' => new Package([
            'currency' => 'USD', 'interval' => 'month', 'is_active' => true, 'features' => [],
        ])]);
    }

    public function edit(Package $package)
    {
        return view('admin.packages.form', compact('package'));
    }

    public function store(Request $request)
    {
        $package = new Package();
        $this->fill($package, $request);
        $package->save();
        $this->syncDefault($package);
        return redirect()->route('admin.packages.index')->with('success', __('Plan created.'));
    }

    public function update(Request $request, Package $package)
    {
        $this->fill($package, $request);
        $package->save();
        $this->syncDefault($package);
        return redirect()->route('admin.packages.index')->with('success', __('Plan updated.'));
    }

    public function destroy(Package $package)
    {
        if ($package->users()->exists()) {
            return back()->with('error', __('Cannot delete a plan that has customers on it. Move them first.'));
        }
        $package->delete();
        return back()->with('success', __('Plan deleted.'));
    }

    private function fill(Package $package, Request $request): void
    {
        $data = $request->validate([
            'name'            => ['required', 'string', 'max:120'],
            'description'     => ['nullable', 'string', 'max:500'],
            'price'           => ['required', 'numeric', 'min:0'],
            'currency'        => ['required', 'string', 'max:8'],
            'interval'        => ['required', 'in:month,year,lifetime'],
            'trial_days'      => ['nullable', 'integer', 'min:0', 'max:365'],
            'max_accounts'    => ['nullable', 'integer', 'min:0'],
            'max_flows'       => ['nullable', 'integer', 'min:0'],
            'max_automations' => ['nullable', 'integer', 'min:0'],
            'monthly_dms'     => ['nullable', 'integer', 'min:0'],
            'team_seats'      => ['nullable', 'integer', 'min:0'],
            'features'        => ['nullable', 'array'],
            'features.*'      => ['string', 'in:' . implode(',', array_keys(Package::FEATURES))],
            'is_active'       => ['nullable', 'boolean'],
            'is_default'      => ['nullable', 'boolean'],
            'is_featured'     => ['nullable', 'boolean'],
            'sort'            => ['nullable', 'integer'],
        ]);

        $package->fill([
            'name'            => $data['name'],
            'slug'            => $package->slug ?: Str::slug($data['name']) . '-' . Str::random(4),
            'description'     => $data['description'] ?? null,
            'price'           => $data['price'],
            'currency'        => strtoupper($data['currency']),
            'interval'        => $data['interval'],
            'trial_days'      => $data['trial_days'] ?? 0,
            // An empty numeric limit means unlimited, stored as NULL.
            'max_accounts'    => $data['max_accounts'] ?? null,
            'max_flows'       => $data['max_flows'] ?? null,
            'max_automations' => $data['max_automations'] ?? null,
            'monthly_dms'     => $data['monthly_dms'] ?? null,
            'team_seats'      => $data['team_seats'] ?? null,
            'features'        => array_values($data['features'] ?? []),
            'is_active'       => (bool) ($data['is_active'] ?? false),
            'is_default'      => (bool) ($data['is_default'] ?? false),
            'is_featured'     => (bool) ($data['is_featured'] ?? false),
            'sort'            => $data['sort'] ?? 0,
        ]);
    }

    /** Exactly one default plan — the one new signups get. */
    private function syncDefault(Package $package): void
    {
        if ($package->is_default) {
            Package::where('id', '!=', $package->id)->update(['is_default' => false]);
        }
    }
}
