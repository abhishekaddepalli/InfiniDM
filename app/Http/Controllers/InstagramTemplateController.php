<?php

namespace App\Http\Controllers;

use App\Models\InstagramTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Saved Instagram DM templates (text / quick replies / button template). These
 * are workspace-scoped snippets the inbox composer inserts — Instagram has no
 * Meta-approval template flow, so there is no submit/status step.
 */
class InstagramTemplateController extends Controller
{
    private function wsId(): int
    {
        return (int) (Auth::user()?->current_workspace_id ?? 0);
    }

    public function index()
    {
        $templates = InstagramTemplate::forWorkspace($this->wsId())->orderByDesc('id')->get();
        $typeCounts = [
            'all'            => $templates->count(),
            'text'           => $templates->where('type', 'text')->count(),
            'quick_replies'  => $templates->where('type', 'quick_replies')->count(),
            'buttons'        => $templates->where('type', 'buttons')->count(),
        ];
        return view('instagram.templates', compact('templates', 'typeCounts'));
    }

    /** Dedicated create page (WaDesk-library style — no inline form on the index). */
    public function create()
    {
        return view('instagram.templates-form', ['template' => null]);
    }

    /** Edit page — same form, pre-filled. */
    public function edit(int $id)
    {
        $template = InstagramTemplate::forWorkspace($this->wsId())->where('id', $id)->firstOrFail();
        return view('instagram.templates-form', compact('template'));
    }

    /** JSON list for the inbox composer picker. */
    public function listJson()
    {
        $templates = InstagramTemplate::forWorkspace($this->wsId())
            ->orderBy('name')->get(['id', 'name', 'type', 'body', 'items']);
        return response()->json(['templates' => $templates]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        InstagramTemplate::create($data + ['workspace_id' => $this->wsId()]);
        return redirect()->route('instagram.templates')->with('status', 'Template saved.');
    }

    public function update(Request $request, int $id)
    {
        $tpl = InstagramTemplate::forWorkspace($this->wsId())->where('id', $id)->firstOrFail();
        $tpl->update($this->validated($request));
        return redirect()->route('instagram.templates')->with('status', 'Template updated.');
    }

    public function destroy(int $id)
    {
        InstagramTemplate::forWorkspace($this->wsId())->where('id', $id)->delete();
        return back()->with('status', 'Template deleted.');
    }

    /**
     * Validate + normalise the payload. `items` arrives as parallel arrays from
     * the form; we fold them into the {title,payload} / {type,title,value} shape
     * the inbox reply endpoint already understands, dropping blank rows.
     */
    private function validated(Request $request): array
    {
        $v = $request->validate([
            'name'        => 'required|string|max:120',
            'type'        => 'required|in:text,quick_replies,buttons',
            'body'        => 'required|string|max:1000',
            'qr_title'    => 'nullable|array',
            'qr_title.*'  => 'nullable|string|max:20',
            'qr_payload'  => 'nullable|array',
            'btn_type'    => 'nullable|array',
            'btn_type.*'  => 'nullable|in:postback,web_url',
            'btn_title'   => 'nullable|array',
            'btn_title.*' => 'nullable|string|max:20',
            'btn_value'   => 'nullable|array',
        ]);

        $items = [];
        if ($v['type'] === 'quick_replies') {
            foreach ((array) $request->input('qr_title', []) as $i => $t) {
                $t = trim((string) $t);
                if ($t === '') continue;
                $items[] = ['title' => mb_substr($t, 0, 20), 'payload' => trim((string) ($request->input('qr_payload')[$i] ?? $t))];
                if (count($items) >= 13) break; // Meta cap: 13 quick replies
            }
        } elseif ($v['type'] === 'buttons') {
            foreach ((array) $request->input('btn_title', []) as $i => $t) {
                $t = trim((string) $t);
                if ($t === '') continue;
                $items[] = [
                    'type'  => (string) ($request->input('btn_type')[$i] ?? 'postback'),
                    'title' => mb_substr($t, 0, 20),
                    'value' => trim((string) ($request->input('btn_value')[$i] ?? '')),
                ];
                if (count($items) >= 3) break; // Meta cap: 3 buttons per generic template
            }
        }

        return [
            'name' => $v['name'],
            'type' => $v['type'],
            'body' => $v['body'],
            'items' => $items ?: null,
        ];
    }
}
