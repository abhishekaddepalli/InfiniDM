<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InstagramOrder;
use App\Models\Package;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Financial + Premium dashboards — WaDesk's structure, with the ApexCharts fed
 * from instagram_orders + packages. Chart series are emitted as window.* JSON
 * and rendered by the shared admin-charts.js module (page="admin-charts").
 */
class FinancialController extends Controller
{
    private function days(string $window): int
    {
        return ['7d' => 7, '30d' => 30, '90d' => 90, '1y' => 365][$window] ?? 30;
    }

    private function bucket(int $days): int
    {
        return $days <= 31 ? 1 : ($days <= 90 ? 7 : 30);
    }

    private function hasOrders(): bool
    {
        return Schema::hasTable('instagram_orders');
    }

    /** Daily/bucketed sum of a column for paid orders over a window. */
    private function moneySeries(int $days, string $status = 'paid'): array
    {
        $labels = [];
        $series = [];
        if (! $this->hasOrders()) {
            return ['labels' => $labels, 'series' => $series];
        }
        $bucket = $this->bucket($days);
        for ($i = $days; $i > 0; $i -= $bucket) {
            $end   = now()->subDays(max(0, $i - $bucket));
            $start = now()->subDays($i);
            $labels[] = $end->format('M j');
            $q = DB::table('instagram_orders')->where('created_at', '>', $start)->where('created_at', '<=', $end);
            if ($status === 'paid')     $q->where('payment_status', 'paid');
            if ($status === 'refunded') $q->where('status', 'refunded');
            $series[] = round((float) $q->sum('total'), 2);
        }
        return ['labels' => $labels, 'series' => $series];
    }

    public function financial(Request $request)
    {
        $window = in_array($request->get('window'), ['7d', '30d', '90d', '1y'], true) ? $request->get('window') : '30d';
        $days   = $this->days($window);
        $cur    = (string) (Setting::get('default_currency', '') ?: 'USD');

        $revenueDaily = $this->moneySeries($days, 'paid');
        $refundsDaily = $this->moneySeries($days, 'refunded');

        $revWindow = array_sum($revenueDaily['series']);
        // Previous period for the delta.
        $prev = 0.0;
        if ($this->hasOrders()) {
            $prev = (float) DB::table('instagram_orders')->where('payment_status', 'paid')
                ->where('created_at', '>', now()->subDays($days * 2))->where('created_at', '<=', now()->subDays($days))
                ->sum('total');
        }
        $delta = $prev > 0 ? round((($revWindow - $prev) / $prev) * 100, 2) : ($revWindow > 0 ? 100 : 0);

        $mrr = Schema::hasTable('packages')
            ? (float) Package::withCount('users')->where('is_active', true)->get()->sum(fn ($p) => (float) $p->price * (int) $p->users_count)
            : 0;
        $refundsWindow  = array_sum($refundsDaily['series']);
        $outstanding    = $this->hasOrders() ? (float) DB::table('instagram_orders')->where('payment_status', '!=', 'paid')->sum('total') : 0;

        $disp = fn ($v) => $cur . ' ' . number_format((float) $v, 2);
        $kpis = [
            'revenue'     => ['display' => $disp($revWindow), 'delta' => $delta, 'positive' => $delta >= 0],
            'mrr'         => ['display' => $disp($mrr)],
            'arr'         => ['display' => $disp($mrr * 12)],
            'refunds'     => ['display' => $disp($refundsWindow)],
            'outstanding' => ['display' => $disp($outstanding)],
        ];

        // Gateway split (by amount) + status mix (by count) over the window.
        $gateways  = ['labels' => [], 'series' => []];
        $statusMix = ['labels' => [], 'series' => []];
        $topCustomers = collect();
        $recentOrders = collect();
        if ($this->hasOrders()) {
            $since = now()->subDays($days);
            foreach (DB::table('instagram_orders')->where('payment_status', 'paid')->where('created_at', '>=', $since)
                ->selectRaw('COALESCE(NULLIF(payment_provider, ""), "unknown") as g, SUM(total) as amt')->groupBy('g')->orderByDesc('amt')->get() as $r) {
                $gateways['labels'][] = ucfirst((string) $r->g);
                $gateways['series'][] = round((float) $r->amt, 2);
            }
            foreach (DB::table('instagram_orders')->where('created_at', '>=', $since)
                ->selectRaw('COALESCE(NULLIF(status, ""), "unknown") as s, COUNT(*) as n')->groupBy('s')->orderByDesc('n')->get() as $r) {
                $statusMix['labels'][] = ucfirst((string) $r->s);
                $statusMix['series'][] = (int) $r->n;
            }
            $topCustomers = DB::table('instagram_orders')->where('payment_status', 'paid')->where('created_at', '>=', $since)
                ->selectRaw('COALESCE(NULLIF(customer_name, ""), "Guest") as name, COUNT(*) as orders, SUM(total) as total')
                ->groupBy('name')->orderByDesc('total')->limit(6)->get()
                ->map(fn ($r) => ['name' => $r->name, 'orders' => (int) $r->orders, 'total' => $disp($r->total)]);
            $recentOrders = InstagramOrder::latest()->limit(8)->get()->map(fn ($o) => [
                'number'   => $o->order_ref ?: ('#' . $o->id),
                'customer' => $o->customer_name ?: '—',
                'amount'   => strtoupper((string) $o->currency) . ' ' . number_format((float) $o->total, 2),
                'status'   => (string) ($o->payment_status ?: $o->status ?: 'pending'),
                'date'     => optional($o->created_at)->diffForHumans() ?? '—',
            ]);
        }

        $revenueDailyMeta = ['total' => $disp($revWindow)] + $revenueDaily;

        return view('admin.financial', compact('window', 'cur', 'kpis', 'revenueDaily', 'refundsDaily', 'gateways', 'statusMix', 'topCustomers', 'recentOrders', 'revenueDailyMeta'));
    }

    public function premium(Request $request)
    {
        $cur      = (string) (Setting::get('default_currency', '') ?: 'USD');
        $plans    = Schema::hasTable('packages') ? Package::withCount('users')->orderBy('sort')->get() : collect();
        $featured = $plans->where('is_featured', true);
        $paidPlans = $plans->where('price', '>', 0);

        $premiumSubs = (int) $featured->sum('users_count');
        $totalSubs   = (int) $plans->sum('users_count');

        // Plan mix (subscribers) + plan revenue (price × subs).
        $withSubs = $plans->filter(fn ($p) => (int) $p->users_count > 0);
        $planMix = [
            'labels' => $withSubs->pluck('name')->values()->all(),
            'series' => $withSubs->pluck('users_count')->map(fn ($n) => (int) $n)->values()->all(),
        ];
        $planRevenue = [
            'labels' => $plans->pluck('name')->values()->all(),
            'series' => $plans->map(fn ($p) => round((float) $p->price * (int) $p->users_count, 2))->values()->all(),
        ];

        // New paid signups per day (14 days) — approximated by new accounts on a paid plan.
        $paidIds = $paidPlans->pluck('id')->all();
        $upgradesDaily = ['labels' => [], 'series' => []];
        if (Schema::hasTable('users')) {
            for ($i = 13; $i >= 0; $i--) {
                $day = now()->subDays($i);
                $upgradesDaily['labels'][] = $day->format('M j');
                $upgradesDaily['series'][] = $paidIds
                    ? (int) User::whereIn('package_id', $paidIds)->whereDate('created_at', $day->toDateString())->count()
                    : 0;
            }
        }

        $kpis = [
            'subscribers' => $premiumSubs,
            'featured'    => $featured->count(),
            'paidPlans'   => $paidPlans->count(),
            'conversion'  => $totalSubs > 0 ? round($premiumSubs / $totalSubs * 100) . '%' : '0%',
        ];

        return view('admin.premium', compact('cur', 'plans', 'featured', 'paidPlans', 'kpis', 'planMix', 'planRevenue', 'upgradesDaily', 'premiumSubs', 'totalSubs'));
    }
}
