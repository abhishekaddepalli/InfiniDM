<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

/** Read-only view of the platform audit trail. */
class AuditLogController extends Controller
{
    public function index(Request $r)
    {
        $q = trim((string) $r->get('q', ''));

        $logs = AuditLog::query()
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x
                ->where('action', 'like', "%$q%")
                ->orWhere('actor_name', 'like', "%$q%")
                ->orWhere('target', 'like', "%$q%")))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        $stats = [
            'total' => AuditLog::count(),
            'today' => AuditLog::whereDate('created_at', now()->toDateString())->count(),
        ];

        return view('admin.audit-log', compact('logs', 'stats', 'q'));
    }
}
