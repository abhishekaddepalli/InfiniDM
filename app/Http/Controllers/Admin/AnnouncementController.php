<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Illuminate\Http\Request;

/**
 * Marquee announcements — WaDesk's admin/announcements. Active rows scroll in a
 * bar across the top of every authenticated page.
 */
class AnnouncementController extends Controller
{
    public function index(Request $request)
    {
        $q       = trim((string) $request->get('q', ''));
        $statusF = (string) $request->get('status', 'all');

        $announcements = Announcement::query()
            ->when($q !== '', function ($w) use ($q) {
                $w->where(function ($x) use ($q) {
                    $x->where('text', 'like', "%{$q}%")->orWhere('link_label', 'like', "%{$q}%")->orWhere('link_url', 'like', "%{$q}%");
                });
            })
            ->when($statusF === 'active', fn ($w) => $w->where('is_active', true)
                ->where(fn ($x) => $x->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
                ->where(fn ($x) => $x->whereNull('expires_at')->orWhere('expires_at', '>=', now())))
            ->when($statusF === 'inactive', fn ($w) => $w->where('is_active', false))
            ->when($statusF === 'expired', fn ($w) => $w->whereNotNull('expires_at')->where('expires_at', '<', now()))
            ->orderBy('sort_order')->orderByDesc('id')
            ->paginate(20)->withQueryString();

        $stats = [
            'total'     => Announcement::count(),
            'active'    => Announcement::where('is_active', true)
                ->where(fn ($x) => $x->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
                ->where(fn ($x) => $x->whereNull('expires_at')->orWhere('expires_at', '>=', now()))->count(),
            'scheduled' => Announcement::whereNotNull('starts_at')->where('starts_at', '>', now())->count(),
            'expired'   => Announcement::whereNotNull('expires_at')->where('expires_at', '<', now())->count(),
        ];

        return view('admin.announcements.index', compact('announcements', 'stats', 'q', 'statusF'));
    }

    public function create()
    {
        return view('admin.announcements.create', ['announcement' => null]);
    }

    public function store(Request $request)
    {
        Announcement::create($this->validated($request));
        return redirect()->route('admin.announcements.index')->with('success', __('Announcement created.'));
    }

    public function edit(Announcement $announcement)
    {
        return view('admin.announcements.edit', compact('announcement'));
    }

    public function update(Request $request, Announcement $announcement)
    {
        $announcement->update($this->validated($request));
        return redirect()->route('admin.announcements.index')->with('success', __('Announcement updated.'));
    }

    public function toggle(Announcement $announcement)
    {
        $announcement->is_active = ! $announcement->is_active;
        $announcement->save();
        return back()->with('success', __('Announcement updated.'));
    }

    public function destroy(Announcement $announcement)
    {
        $announcement->delete();
        return back()->with('success', __('Announcement deleted.'));
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'text'        => ['required', 'string', 'max:500'],
            'tone'        => ['required', 'in:info,promo,warning,success'],
            'link_url'    => ['nullable', 'string', 'max:500'],
            'link_label'  => ['nullable', 'string', 'max:64'],
            'is_active'   => ['nullable', 'boolean'],
            'dismissible' => ['nullable', 'boolean'],
            'starts_at'   => ['nullable', 'date'],
            'expires_at'  => ['nullable', 'date'],
            'sort_order'  => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);
        return [
            'text'        => $data['text'],
            'tone'        => $data['tone'],
            'link_url'    => $data['link_url'] ?? null,
            'link_label'  => $data['link_label'] ?? null,
            'is_active'   => (bool) ($data['is_active'] ?? false),
            'dismissible' => (bool) ($data['dismissible'] ?? false),
            'starts_at'   => $data['starts_at'] ?? null,
            'expires_at'  => $data['expires_at'] ?? null,
            'sort_order'  => (int) ($data['sort_order'] ?? 0),
        ];
    }
}
