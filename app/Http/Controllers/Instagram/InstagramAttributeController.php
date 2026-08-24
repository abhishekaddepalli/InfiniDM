<?php

namespace App\Http\Controllers\Instagram;

use App\Http\Controllers\Controller;
use App\Models\InstagramAttribute;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * WaDesk-style contact attributes for IgDesk. The three built-ins
 * (name / email / phone) are always present; this manages the operator's own
 * CUSTOM fields, which flows can capture via an Ask question node and reuse as
 * {{key}} everywhere.
 *
 * Scoped by user_id — IgDesk has no workspaces (every row is
 * workspace_id=0), the same key the account/flow pickers use.
 */
class InstagramAttributeController extends Controller
{
    private function ownerId(): int
    {
        return (int) auth()->id();
    }

    /** Owner-scoped custom attributes, alphabetical. */
    private function attributes()
    {
        return InstagramAttribute::where('user_id', $this->ownerId())
            ->orderBy('label')->get();
    }

    /** Management page. */
    public function index()
    {
        return view('instagram.attributes', [
            'attributes' => $this->attributes(),
            'builtIns'   => InstagramAttribute::BUILT_INS,
        ]);
    }

    /** Create a custom attribute from a human label. */
    public function store(Request $request)
    {
        $data = $request->validate(['label' => 'required|string|max:120']);

        $key = InstagramAttribute::slug($data['label']);
        if ($key === '') {
            return back()->with('error', 'That label has no usable letters or numbers — try another.');
        }
        if (isset(InstagramAttribute::BUILT_INS[$key])) {
            return back()->with('error', ucfirst($key) . ' is a built-in attribute and always exists.');
        }

        InstagramAttribute::firstOrCreate(
            ['user_id' => $this->ownerId(), 'key' => $key],
            ['workspace_id' => 0, 'label' => trim($data['label'])]
        );

        return back()->with('status', 'Attribute "' . e(trim($data['label'])) . '" added.');
    }

    /** Rename a custom attribute's label (the key/slug stays fixed so flows keep working). */
    public function update(Request $request, int $id)
    {
        $data = $request->validate(['label' => 'required|string|max:120']);

        $attr = InstagramAttribute::where('user_id', $this->ownerId())->where('id', $id)->first();
        if (!$attr) return back()->with('error', 'Attribute not found.');

        $attr->label = trim($data['label']);
        $attr->save();

        return back()->with('status', 'Attribute renamed.');
    }

    /** Delete a custom attribute definition (existing captured values stay on contacts). */
    public function destroy(int $id)
    {
        InstagramAttribute::where('user_id', $this->ownerId())->where('id', $id)->delete();
        return back()->with('status', 'Attribute removed.');
    }

    /**
     * JSON list for the flow builder's Ask node "Save answer to" dropdown:
     * the three built-ins first, then every custom attribute.
     */
    public function apiList(): JsonResponse
    {
        $items = [];
        foreach (InstagramAttribute::BUILT_INS as $key => $label) {
            $items[] = ['key' => $key, 'label' => $label, 'builtin' => true];
        }
        foreach ($this->attributes() as $a) {
            $items[] = ['key' => (string) $a->key, 'label' => (string) $a->label, 'builtin' => false];
        }
        return response()->json(['ok' => true, 'attributes' => $items]);
    }
}
