<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FlowTemplate;
use Illuminate\Http\Request;

/** Curated, reusable flow templates customers can clone into their own flows. */
class FlowTemplateController extends Controller
{
    public function index()
    {
        $templates = FlowTemplate::orderBy('sort')->orderBy('name')->get();
        $stats = [
            'total'  => $templates->count(),
            'active' => $templates->where('is_active', true)->count(),
        ];
        return view('admin.flow-templates.index', compact('templates', 'stats'));
    }

    public function create()
    {
        return view('admin.flow-templates.form', [
            'template' => new FlowTemplate(['channel' => 'instagram', 'category' => 'general', 'is_active' => true]),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        FlowTemplate::create($data);
        return redirect()->route('admin.flow-templates.index')->with('success', __('Template created.'));
    }

    public function edit(FlowTemplate $flowTemplate)
    {
        return view('admin.flow-templates.form', ['template' => $flowTemplate]);
    }

    public function update(Request $request, FlowTemplate $flowTemplate)
    {
        $data = $this->validated($request);
        $flowTemplate->update($data);
        return redirect()->route('admin.flow-templates.index')->with('success', __('Template updated.'));
    }

    public function destroy(FlowTemplate $flowTemplate)
    {
        $flowTemplate->delete();
        return back()->with('success', __('Template deleted.'));
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name'        => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:400'],
            'category'    => ['required', 'in:' . implode(',', array_keys(FlowTemplate::CATEGORIES))],
            'channel'     => ['required', 'in:instagram,whatsapp'],
            'is_active'   => ['nullable', 'boolean'],
            'sort'        => ['nullable', 'integer'],
        ]);
    }
}
