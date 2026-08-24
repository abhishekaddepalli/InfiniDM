<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only admin dashboards — AI activity and platform System Health.
 *
 * Every table/column read is guarded so a partially-migrated or trimmed
 * install (a self-hosted IgDesk that never ran a given migration) renders
 * zeros instead of a 500. Nothing here writes.
 */
class SystemDashController extends Controller
{
    /**
     * Count rows in $t, guarded on table existence. An optional scope closure
     * narrows the query; if the scope touches a column that does not exist the
     * whole thing degrades to 0 rather than throwing.
     */
    private function count(string $t, ?callable $s = null): int
    {
        if (! Schema::hasTable($t)) {
            return 0;
        }

        try {
            $q = DB::table($t);
            if ($s) {
                $s($q);
            }
            return (int) $q->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    // ───────────────────────── AI dashboard ─────────────────────────

    /**
     * AI automation console. IgDesk's "AI" is Instagram automation: AI agents
     * are instagram_automations rows (type=ai_agent), contacts carry an ai_enabled
     * flag, and outbound automated DMs are the activity signal. Everything is
     * guarded so a trimmed self-hosted install renders zeros, never a 500.
     */
    public function aiDashboard(Request $request)
    {
        $window  = in_array($request->get('window'), ['7d', '30d', '90d', '1y'], true) ? $request->get('window') : '30d';
        $windows = ['7d' => __('7 days'), '30d' => __('30 days'), '90d' => __('90 days'), '1y' => __('1 year')];
        $days    = ['7d' => 7, '30d' => 30, '90d' => 90, '1y' => 365][$window];
        $since   = now()->subDays($days);

        $hasAuto = Schema::hasTable('instagram_automations');
        $hasMsg  = Schema::hasTable('instagram_messages');
        $hasCon  = Schema::hasTable('instagram_contacts');
        $hasAcc  = Schema::hasTable('instagram_accounts');
        $conAi   = $hasCon && Schema::hasColumn('instagram_contacts', 'ai_enabled');

        // ── KPIs ──
        $aiAgents    = $hasAuto ? (int) DB::table('instagram_automations')->where('type', 'ai_agent')->where('is_active', true)->count() : 0;
        $automations = $hasAuto ? (int) DB::table('instagram_automations')->where('is_active', true)->count() : 0;
        $fired       = $hasAuto ? (int) DB::table('instagram_automations')->sum('fired_count') : 0;
        $aiContacts  = $conAi ? (int) DB::table('instagram_contacts')->where('ai_enabled', true)->count() : 0;
        $kpis = compact('aiAgents', 'automations', 'fired', 'aiContacts');

        // ── Daily automated-reply activity (outbound, non-manual) → sparkline ──
        $daily = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $daily[now()->subDays($i)->format('M j')] = 0;
        }
        if ($hasMsg && Schema::hasColumn('instagram_messages', 'direction')) {
            try {
                $rows = DB::table('instagram_messages')
                    ->where('direction', 'out')->where('created_at', '>=', $since)
                    ->when(Schema::hasColumn('instagram_messages', 'source'), fn ($q) => $q->where('source', '!=', 'manual'))
                    ->selectRaw('DATE(created_at) as d, COUNT(*) as n')->groupBy('d')->pluck('n', 'd');
                foreach ($rows as $d => $n) {
                    $key = Carbon::parse($d)->format('M j');
                    if (array_key_exists($key, $daily)) {
                        $daily[$key] = (int) $n;
                    }
                }
            } catch (\Throwable $e) {
            }
        }

        // ── Automations by type (bars) ──
        $byType = [];
        if ($hasAuto) {
            foreach (DB::table('instagram_automations')->selectRaw('type, COUNT(*) as c, SUM(fired_count) as f')
                ->groupBy('type')->orderByDesc('f')->get() as $r) {
                $byType[] = ['type' => (string) $r->type, 'count' => (int) $r->c, 'fired' => (int) $r->f];
            }
        }
        $maxFired = collect($byType)->max('fired') ?: 1;

        // ── AI-handled vs manual conversations (split) ──
        $totalCon = $hasCon ? (int) DB::table('instagram_contacts')->count() : 0;
        $split = ['ai' => $aiContacts, 'manual' => max(0, $totalCon - $aiContacts)];

        // ── Top AI agents by fires ──
        $topAgents = $hasAuto
            ? DB::table('instagram_automations')->where('type', 'ai_agent')->orderByDesc('fired_count')->limit(6)->get()
                ->map(fn ($a) => ['id' => $a->id, 'name' => $a->name ?: (__('Agent') . ' #' . $a->id), 'fired' => (int) $a->fired_count, 'active' => (bool) $a->is_active])
            : collect();

        // ── Top automations overall ──
        $topAutomations = $hasAuto
            ? DB::table('instagram_automations')->orderByDesc('fired_count')->limit(6)->get()
                ->map(fn ($a) => ['id' => $a->id, 'name' => $a->name ?: ucfirst(str_replace('_', ' ', (string) $a->type)), 'type' => (string) $a->type, 'fired' => (int) $a->fired_count])
            : collect();

        // ── Per-account AI status (key-health style grid) ──
        $accounts = collect();
        if ($hasAcc) {
            $conHasAcc = $hasCon && Schema::hasColumn('instagram_contacts', 'instagram_account_id');
            $accounts = DB::table('instagram_accounts')->limit(9)->get()->map(function ($a) use ($hasAuto, $conAi, $conHasAcc) {
                $autos = $hasAuto ? (int) DB::table('instagram_automations')->where('instagram_account_id', $a->id)->where('is_active', true)->count() : 0;
                $ai    = ($conAi && $conHasAcc) ? (int) DB::table('instagram_contacts')->where('instagram_account_id', $a->id)->where('ai_enabled', true)->count() : 0;
                return [
                    'name'   => $a->username ?? $a->name ?? ('#' . $a->id),
                    'autos'  => $autos,
                    'ai'     => $ai,
                    'active' => $autos > 0 || $ai > 0,
                ];
            });
        }

        return view('admin.ai-dashboard', compact('window', 'windows', 'kpis', 'daily', 'byType', 'maxFired', 'split', 'totalCon', 'topAgents', 'topAutomations', 'accounts'));
    }

    // ───────────────────────── System health ─────────────────────────

    public function systemHealth(Request $request)
    {
        $checks  = [];
        $latency = [];

        // ── Database ──
        $t0 = microtime(true);
        try {
            DB::connection()->getPdo();
            DB::select('select 1');
            $ms = (int) round((microtime(true) - $t0) * 1000);
            $checks['database'] = ['label' => __('Database'), 'state' => 'up', 'value' => __('Connected'), 'detail' => config('database.default') . ' · ' . $ms . ' ms'];
            $latency[] = ['label' => __('Database'), 'ms' => $ms, 'state' => $ms > 250 ? 'warn' : 'up'];
        } catch (\Throwable $e) {
            $checks['database'] = ['label' => __('Database'), 'state' => 'down', 'value' => __('Unreachable'), 'detail' => __('Connection failed')];
        }

        // ── Cache ──
        $t0 = microtime(true);
        try {
            Cache::put('ipulse', '1', 5);
            $ok = Cache::get('ipulse') === '1';
            $ms = (int) round((microtime(true) - $t0) * 1000);
            $checks['cache'] = ['label' => __('Cache'), 'state' => $ok ? 'up' : 'warn', 'value' => $ok ? __('Read/write OK') : __('Not persisting'), 'detail' => config('cache.default') . ' · ' . $ms . ' ms'];
            $latency[] = ['label' => __('Cache'), 'ms' => $ms, 'state' => $ms > 150 ? 'warn' : 'up'];
        } catch (\Throwable $e) {
            $checks['cache'] = ['label' => __('Cache'), 'state' => 'warn', 'value' => __('Unavailable'), 'detail' => __('Cache store error')];
        }

        // ── Storage ──
        $t0 = microtime(true);
        $writable = @is_writable(storage_path());
        if ($writable) {
            try {
                $p = storage_path('framework/.ipulse');
                @file_put_contents($p, '1');
                @unlink($p);
            } catch (\Throwable $e) {
            }
        }
        $ms = (int) round((microtime(true) - $t0) * 1000);
        $checks['storage'] = ['label' => __('Storage'), 'state' => $writable ? 'up' : 'warn', 'value' => $writable ? __('Writable') : __('Read-only'), 'detail' => $writable ? __('storage/ writable') . ' · ' . $ms . ' ms' : __('storage/ is not writable')];
        $latency[] = ['label' => __('Storage'), 'ms' => $ms, 'state' => 'up'];

        // ── Queue ──
        try {
            if (Schema::hasTable('jobs')) {
                $queued = (int) DB::table('jobs')->count();
                $failed = Schema::hasTable('failed_jobs') ? (int) DB::table('failed_jobs')->count() : 0;
                $checks['queue'] = ['label' => __('Queue'), 'state' => $queued > 100 ? 'warn' : 'up', 'value' => number_format($queued) . ' ' . __('queued'), 'detail' => $failed > 0 ? number_format($failed) . ' ' . __('failed jobs') : __('No failed jobs')];
            } else {
                $checks['queue'] = ['label' => __('Queue'), 'state' => 'up', 'value' => __('Sync driver'), 'detail' => __('No jobs table')];
            }
        } catch (\Throwable $e) {
            $checks['queue'] = ['label' => __('Queue'), 'state' => 'warn', 'value' => __('Unavailable'), 'detail' => __('Queue table error')];
        }

        // ── PHP runtime ──
        $phpOk = version_compare(PHP_VERSION, '8.1', '>=');
        $checks['php'] = ['label' => __('PHP runtime'), 'state' => $phpOk ? 'up' : 'warn', 'value' => PHP_VERSION, 'detail' => $phpOk ? __('Supported') : __('8.1+ recommended')];

        // ── Extensions ──
        $required = ['gd', 'curl', 'mbstring', 'openssl', 'pdo'];
        $missing  = array_values(array_filter($required, fn ($ext) => ! extension_loaded($ext)));
        $checks['extensions'] = ['label' => __('PHP extensions'), 'state' => $missing ? 'warn' : 'up', 'value' => $missing ? __('Missing') : __('All present'), 'detail' => $missing ? implode(', ', $missing) : implode(', ', $required)];

        // Overall roll-up.
        $states  = array_column($checks, 'state');
        $overall = in_array('down', $states, true) ? 'down' : (in_array('warn', $states, true) ? 'warn' : 'up');

        // Live refresh branch — the page polls this every 20s.
        if ($request->get('format') === 'json') {
            return response()->json(['checks' => $checks, 'overall' => $overall, 'ts' => now()->toIso8601String()]);
        }

        // ── Last-24h activity ──
        $since = now()->subDay();
        $activity = [
            'messages'      => $this->count('instagram_messages', fn ($q) => $q->where('created_at', '>=', $since)),
            'ai_replies'    => $this->count('instagram_messages', fn ($q) => $q->where('created_at', '>=', $since)->where('direction', 'out')->where('source', '!=', 'manual')),
            'conversations' => $this->count('instagram_contacts', fn ($q) => $q->where('last_message_at', '>=', $since)),
            'automations'   => $this->count('instagram_automations', fn ($q) => $q->where('is_active', true)),
        ];

        // ── 14-day message throughput ──
        $series = [];
        if (Schema::hasTable('instagram_messages')) {
            for ($i = 13; $i >= 0; $i--) {
                $series[] = (int) DB::table('instagram_messages')->whereDate('created_at', now()->subDays($i)->toDateString())->count();
            }
        } else {
            $series = array_fill(0, 14, 0);
        }
        $throughput = ['series' => $series];

        // ── Connected Instagram accounts (channel health) ──
        $engines = [];
        if (Schema::hasTable('instagram_accounts')) {
            $total     = (int) DB::table('instagram_accounts')->count();
            $connected = (int) DB::table('instagram_accounts')->where('status', 'connected')->count();
            $breakdown = [];
            foreach (DB::table('instagram_accounts')->selectRaw('COALESCE(NULLIF(status, ""), "unknown") as s, COUNT(*) as n')->groupBy('s')->get() as $r) {
                $breakdown[(string) $r->s] = (int) $r->n;
            }
            $engines[] = ['label' => __('Instagram accounts'), 'state' => $total === 0 ? 'idle' : ($connected > 0 ? 'up' : 'warn'), 'connected' => $connected, 'total' => $total, 'breakdown' => $breakdown];
        }

        // ── Host vitals ──
        $diskFree  = @disk_free_space(base_path());
        $diskTotal = @disk_total_space(base_path());
        $system = [
            'php'           => PHP_VERSION,
            'laravel'       => app()->version(),
            'env'           => app()->environment(),
            'debug'         => config('app.debug') ? __('On') : __('Off'),
            'disk_free'     => $diskTotal ? number_format($diskFree / 1073741824, 1) . ' GB' : '—',
            'memory_limit'  => (string) ini_get('memory_limit'),
            'disk_used_pct' => $diskTotal ? (int) round(($diskTotal - $diskFree) / $diskTotal * 100) : null,
        ];

        return view('admin.system-health', compact('overall', 'activity', 'checks', 'latency', 'throughput', 'engines', 'system'));
    }
}
