<?php

namespace App\Http\Controllers;

use App\Models\InstagramAccount;
use App\Models\InstagramLead;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Instagram Leads — the list of everyone captured through a DM lead-capture
 * flow (Ask → Capture lead) or a Meta Lead Ad. Simple, filterable, and each row
 * links out to its pipeline deal when one was auto-created.
 */
class InstagramLeadsController extends Controller
{
    /** IDs of the accounts this user owns — the tenancy boundary for leads. */
    private function accountIds(): array
    {
        return InstagramAccount::where('user_id', (int) auth()->id())->pluck('id')->all();
    }

    /** GET /instagram/leads */
    public function index(Request $request): \Illuminate\Contracts\View\View
    {
        $ids    = $this->accountIds() ?: [0];
        $source = (string) $request->query('source', '');
        $status = (string) $request->query('status', '');
        $q      = trim((string) $request->query('q', ''));

        $leads = InstagramLead::whereIn('instagram_account_id', $ids)
            ->when($source !== '', fn ($x) => $x->where('source', $source))
            ->when($status !== '', fn ($x) => $x->where('status', $status))
            ->when($q !== '', function ($x) use ($q) {
                $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';
                $x->where(fn ($w) => $w->where('full_name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like));
            })
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        $counts = [
            'all'     => InstagramLead::whereIn('instagram_account_id', $ids)->count(),
            'dm'      => InstagramLead::whereIn('instagram_account_id', $ids)->where('source', 'dm')->count(),
            'lead_ad' => InstagramLead::whereIn('instagram_account_id', $ids)->where('source', 'lead_ad')->count(),
            'new'     => InstagramLead::whereIn('instagram_account_id', $ids)->where('status', 'new')->count(),
        ];

        return view('instagram.leads.index', compact('leads', 'counts', 'source', 'status', 'q'));
    }

    /** POST /instagram/leads/{lead}/status — move a lead along its own funnel. */
    public function setStatus(Request $request, int $lead): RedirectResponse
    {
        $data = $request->validate([
            'status' => 'required|in:new,contacted,qualified,won,lost',
        ]);
        $row = InstagramLead::whereIn('instagram_account_id', $this->accountIds() ?: [0])->findOrFail($lead);
        $row->forceFill(['status' => $data['status']])->save();

        return back()->with('status', 'Lead marked ' . $data['status'] . '.');
    }
}
