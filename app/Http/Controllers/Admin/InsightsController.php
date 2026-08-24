<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * Unified admin dashboard — Overview, Financial, Premium, Analytics and AI on
 * one page with client-side tabs. Rather than duplicate any query logic, each
 * tab's data is produced by its existing controller method and lifted off the
 * returned View via ->getData(), then handed to the merged blade scoped per tab
 * (the tabs reuse variable names like $kpis/$plans/$window, so they must stay
 * isolated). Charts lazy-render as each tab is shown (see admin-charts.js).
 */
class InsightsController extends Controller
{
    public function index(Request $request)
    {
        $tabs = ['overview', 'financial', 'premium', 'ai'];
        $tab  = in_array($request->get('tab'), $tabs, true) ? $request->get('tab') : 'overview';

        $ov   = app(AdminController::class)->overview()->getData();
        $fin  = app(FinancialController::class)->financial($request)->getData();
        $prem = app(FinancialController::class)->premium($request)->getData();
        $ai   = app(SystemDashController::class)->aiDashboard($request)->getData();

        return view('admin.insights', compact('tab', 'ov', 'fin', 'prem', 'ai'));
    }
}
