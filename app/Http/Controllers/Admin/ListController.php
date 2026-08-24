<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InstagramAccount;
use App\Models\InstagramAutomation;
use App\Models\InstagramLead;
use App\Models\InstagramOrder;
use App\Services\Instagram\InstagramGate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Read-only admin lists — Accounts, Flows, Automations, Leads, Orders,
 * Broadcasts. Each builds a config for the shared admin.list view, so the whole
 * set shares one WaDesk-style table layout.
 */
class ListController extends Controller
{
    private function ago($v): string
    {
        if (! $v) return '—';
        try { return e(Carbon::parse($v)->diffForHumans()); } catch (\Throwable $e) { return e((string) $v); }
    }

    private function pill(?string $status): string
    {
        $s = strtolower((string) $status);
        $ok    = ['connected', 'active', 'published', 'paid', 'completed', 'sent', 'done'];
        $warn  = ['pending', 'processing', 'running', 'draft', 'review'];
        $bad   = ['failed', 'error', 'disconnected', 'expired', 'cancelled', 'canceled'];
        [$bg, $fg] = in_array($s, $ok, true)   ? ['bg-wa-mint', 'text-wa-deep']
                   : (in_array($s, $bad, true)  ? ['bg-accent-coral/10', 'text-accent-coral']
                   : (in_array($s, $warn, true) ? ['bg-accent-amber/15', 'text-accent-amber']
                   : ['bg-paper-100', 'text-ink-600']));
        return '<span class="px-2 py-0.5 rounded-full ' . $bg . ' ' . $fg . ' text-[10.5px] font-semibold">' . e(ucfirst($s ?: '—')) . '</span>';
    }

    private function avatar(string $label, ?string $sub = null): string
    {
        $init = strtoupper(mb_substr(trim($label) ?: '?', 0, 2));
        $out  = '<div class="flex items-center gap-2.5 min-w-0">';
        $out .= '<span class="w-8 h-8 rounded-full ig-grad text-white grid place-items-center text-[11px] font-bold shrink-0">' . e($init) . '</span>';
        $out .= '<div class="min-w-0"><div class="font-semibold leading-tight truncate">' . e($label ?: '—') . '</div>';
        if ($sub !== null && $sub !== '') $out .= '<div class="text-[10.5px] text-ink-500 font-mono truncate">' . e($sub) . '</div>';
        return $out . '</div></div>';
    }

    private function render(array $cfg)
    {
        return view('admin.list', $cfg);
    }

    public function accounts()
    {
        $rows = InstagramAccount::latest()->paginate(20);
        return $this->render([
            'title' => __('Admin · Accounts'), 'adminKey' => 'accounts', 'crumb' => __('Accounts'),
            'heading' => __('Connected'), 'accent' => __('accounts'),
            'desc' => __('Every Instagram account linked across the platform.'),
            'stats' => [
                ['label' => __('Total accounts'), 'value' => number_format(InstagramAccount::count())],
                ['label' => __('Connected'), 'value' => number_format(InstagramAccount::where('status', 'connected')->count())],
            ],
            'cols' => [
                ['label' => __('Account'), 'cell' => fn ($r) => $this->avatar('@' . ($r->username ?: $r->ig_user_id), $r->name)],
                ['label' => __('Login'), 'th' => 'w-[110px]', 'cell' => fn ($r) => e(ucfirst($r->login_type ?: '—'))],
                ['label' => __('Status'), 'th' => 'w-[110px]', 'cell' => fn ($r) => $this->pill($r->status)],
                ['label' => __('Followers'), 'th' => 'w-[110px] text-right', 'td' => 'text-right font-mono', 'cell' => fn ($r) => number_format((int) ($r->followers_count ?? 0))],
                ['label' => __('Added'), 'th' => 'w-[120px]', 'td' => 'font-mono text-[10.5px] text-ink-600', 'cell' => fn ($r) => $this->ago($r->created_at)],
            ],
            'rows' => $rows, 'empty' => __('No accounts connected yet.'),
        ]);
    }

    public function flows()
    {
        $rows = InstagramGate::flows()->latest()->paginate(20);
        $total = (clone InstagramGate::flows())->count();
        $published = (clone InstagramGate::flows())->where('is_published', true)->count();
        return $this->render([
            'title' => __('Admin · Flows'), 'adminKey' => 'flows', 'crumb' => __('Flows'),
            'heading' => __('Automation'), 'accent' => __('flows'),
            'desc' => __('Every flow built across the platform.'),
            'stats' => [
                ['label' => __('Total flows'), 'value' => number_format($total)],
                ['label' => __('Published'), 'value' => number_format($published)],
            ],
            'cols' => [
                ['label' => __('Flow'), 'cell' => fn ($r) => $this->avatar((string) ($r->flow_name ?: 'Flow #' . $r->id))],
                ['label' => __('Type'), 'th' => 'w-[130px]', 'cell' => fn ($r) => e(ucfirst((string) ($r->flow_type ?: $r->category ?: '—')))],
                ['label' => __('Trigger'), 'th' => 'w-[180px]', 'td' => 'font-mono text-[11px] text-ink-600 truncate', 'cell' => fn ($r) => e((string) ($r->trigger_value ?: $r->trigger_kind ?: '—'))],
                ['label' => __('Status'), 'th' => 'w-[110px]', 'cell' => fn ($r) => $this->pill($r->is_published ? 'published' : 'draft')],
                ['label' => __('Created'), 'th' => 'w-[120px]', 'td' => 'font-mono text-[10.5px] text-ink-600', 'cell' => fn ($r) => $this->ago($r->created_at)],
            ],
            'rows' => $rows, 'empty' => __('No flows yet.'),
        ]);
    }

    public function automations()
    {
        $rows = InstagramAutomation::latest()->paginate(20);
        return $this->render([
            'title' => __('Admin · Automations'), 'adminKey' => 'automations', 'crumb' => __('Automations'),
            'heading' => __('Keyword'), 'accent' => __('automations'),
            'desc' => __('Comment-to-DM and keyword rules across the platform.'),
            'stats' => [
                ['label' => __('Total rules'), 'value' => number_format(InstagramAutomation::count())],
                ['label' => __('Active'), 'value' => number_format(InstagramAutomation::where('is_active', true)->count())],
            ],
            'cols' => [
                ['label' => __('Automation'), 'cell' => fn ($r) => $this->avatar((string) ($r->name ?: 'Rule #' . $r->id))],
                ['label' => __('Type'), 'th' => 'w-[130px]', 'cell' => fn ($r) => e(ucfirst(str_replace('_', ' ', (string) ($r->type ?: '—'))))],
                ['label' => __('Keyword'), 'th' => 'w-[160px]', 'td' => 'font-mono text-[11px] text-ink-600 truncate', 'cell' => fn ($r) => e((string) ($r->trigger_keyword ?: '—'))],
                ['label' => __('Fired'), 'th' => 'w-[90px] text-right', 'td' => 'text-right font-mono', 'cell' => fn ($r) => number_format((int) ($r->fired_count ?? 0))],
                ['label' => __('Status'), 'th' => 'w-[100px]', 'cell' => fn ($r) => $this->pill($r->is_active ? 'active' : 'paused')],
            ],
            'rows' => $rows, 'empty' => __('No automations yet.'),
        ]);
    }

    public function leads()
    {
        $rows = InstagramLead::latest()->paginate(20);
        return $this->render([
            'title' => __('Admin · Leads'), 'adminKey' => 'leads', 'crumb' => __('Leads'),
            'heading' => __('Captured'), 'accent' => __('leads'),
            'desc' => __('Leads collected from DMs, forms, and Lead Ads.'),
            'stats' => [
                ['label' => __('Total leads'), 'value' => number_format(InstagramLead::count())],
                ['label' => __('New'), 'value' => number_format(InstagramLead::where('status', 'new')->count())],
            ],
            'cols' => [
                ['label' => __('Lead'), 'cell' => fn ($r) => $this->avatar((string) ($r->full_name ?: 'Lead #' . $r->id), $r->email ?: $r->phone)],
                ['label' => __('Source'), 'th' => 'w-[140px]', 'cell' => fn ($r) => e(ucfirst(str_replace('_', ' ', (string) ($r->source ?: '—'))))],
                ['label' => __('Contact'), 'th' => 'w-[180px]', 'td' => 'font-mono text-[11px] text-ink-600 truncate', 'cell' => fn ($r) => e((string) ($r->phone ?: $r->email ?: '—'))],
                ['label' => __('Status'), 'th' => 'w-[110px]', 'cell' => fn ($r) => $this->pill($r->status)],
                ['label' => __('Captured'), 'th' => 'w-[120px]', 'td' => 'font-mono text-[10.5px] text-ink-600', 'cell' => fn ($r) => $this->ago($r->created_at)],
            ],
            'rows' => $rows, 'empty' => __('No leads yet.'),
        ]);
    }

    public function orders()
    {
        $rows = InstagramOrder::latest()->paginate(20);
        return $this->render([
            'title' => __('Admin · Orders'), 'adminKey' => 'orders', 'crumb' => __('Orders'),
            'heading' => __('In-DM'), 'accent' => __('orders'),
            'desc' => __('Orders placed through the Instagram commerce flow.'),
            'stats' => [
                ['label' => __('Total orders'), 'value' => number_format(InstagramOrder::count())],
                ['label' => __('Paid'), 'value' => number_format(InstagramOrder::where('payment_status', 'paid')->count())],
            ],
            'cols' => [
                ['label' => __('Order'), 'cell' => fn ($r) => $this->avatar((string) ($r->order_ref ?: '#' . $r->id), $r->customer_name)],
                ['label' => __('Total'), 'th' => 'w-[130px] text-right', 'td' => 'text-right font-mono', 'cell' => fn ($r) => e(strtoupper((string) ($r->currency ?: '')) . ' ' . number_format((float) ($r->total ?? 0), 2))],
                ['label' => __('Payment'), 'th' => 'w-[120px]', 'cell' => fn ($r) => $this->pill($r->payment_status)],
                ['label' => __('Status'), 'th' => 'w-[110px]', 'cell' => fn ($r) => $this->pill($r->status)],
                ['label' => __('Placed'), 'th' => 'w-[120px]', 'td' => 'font-mono text-[10.5px] text-ink-600', 'cell' => fn ($r) => $this->ago($r->created_at)],
            ],
            'rows' => $rows, 'empty' => __('No orders yet.'),
        ]);
    }

    public function broadcasts()
    {
        $rows = DB::table('instagram_broadcasts')->orderByDesc('id')->paginate(20);
        return $this->render([
            'title' => __('Admin · Broadcasts'), 'adminKey' => 'broadcasts', 'crumb' => __('Broadcasts'),
            'heading' => __('DM'), 'accent' => __('broadcasts'),
            'desc' => __('Bulk DM campaigns sent across the platform.'),
            'stats' => [
                ['label' => __('Total broadcasts'), 'value' => number_format(DB::table('instagram_broadcasts')->count())],
                ['label' => __('Messages sent'), 'value' => number_format((int) DB::table('instagram_broadcasts')->sum('sent'))],
            ],
            'cols' => [
                ['label' => __('Message'), 'td' => 'truncate max-w-0', 'cell' => fn ($r) => '<span class="truncate block">' . e(\Illuminate\Support\Str::limit((string) $r->body, 70)) . '</span>'],
                ['label' => __('Recipients'), 'th' => 'w-[110px] text-right', 'td' => 'text-right font-mono', 'cell' => fn ($r) => number_format((int) ($r->total ?? 0))],
                ['label' => __('Sent'), 'th' => 'w-[90px] text-right', 'td' => 'text-right font-mono text-wa-deep', 'cell' => fn ($r) => number_format((int) ($r->sent ?? 0))],
                ['label' => __('Status'), 'th' => 'w-[110px]', 'cell' => fn ($r) => $this->pill($r->status)],
                ['label' => __('Created'), 'th' => 'w-[120px]', 'td' => 'font-mono text-[10.5px] text-ink-600', 'cell' => fn ($r) => $this->ago($r->created_at ?? null)],
            ],
            'rows' => $rows, 'empty' => __('No broadcasts yet.'),
        ]);
    }
}
