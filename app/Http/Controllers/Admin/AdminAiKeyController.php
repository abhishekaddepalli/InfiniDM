<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAiKey;
use Illuminate\Http\Request;

/**
 * Admin /admin/api-keys — global AI provider keys for the standalone build.
 * Every provider is pre-seeded; the operator pastes a key, picks a default
 * model, and saves (saving with a key auto-activates it). These are the
 * fallback keys the flow "AI" node uses.
 *
 *   GET   /admin/api-keys              → list providers
 *   PATCH /admin/api-keys/{id}         → save key + default_model + extra_config
 *   POST  /admin/api-keys/{id}/toggle  → activate / deactivate
 */
class AdminAiKeyController extends Controller
{
    /**
     * Default-model dropdowns per provider. Refreshed 2026-07-25 to current
     * model ids (Anthropic Opus 4.8 / Sonnet 5 / Haiku 4.5 added).
     */
    public const MODELS = [
        'openai' => [
            // GPT-5.x line — current flagships
            'gpt-5.5', 'gpt-5.5-pro', 'gpt-5.4', 'gpt-5.4-pro',
            'gpt-5.4-mini', 'gpt-5.4-nano', 'gpt-5-mini', 'gpt-5-nano',
            // GPT-4.1 — still recommended for tooling / function-calling
            'gpt-4.1', 'gpt-4.1-mini',
            // legacy (still callable)
            'gpt-4o', 'gpt-4o-mini',
        ],
        'anthropic' => [
            // Current
            'claude-opus-4-8', 'claude-sonnet-5', 'claude-haiku-4-5-20251001',
            'claude-fable-5', 'claude-haiku-4-5',
            // Previous-gen, still fully supported
            'claude-sonnet-4-6', 'claude-opus-4-7',
        ],
        'gemini' => [
            // Gemini 3.x — current
            'gemini-3.5-flash', 'gemini-3.1-pro-preview', 'gemini-3.1-flash-lite',
            // Gemini 2.5 — still maintained
            'gemini-2.5-pro', 'gemini-2.5-flash', 'gemini-2.5-flash-lite',
        ],
        'mistral' => [
            // Aliases — resolve to current generation automatically
            'mistral-large-latest', 'mistral-medium-latest', 'mistral-small-latest',
            'codestral-latest',
            // Specific dated builds
            'ministral-3-14b-25-12', 'ministral-3-8b-25-12',
            'devstral-2-25-12', 'magistral-medium-1-2-25-09',
        ],
    ];

    /**
     * The credential fields each provider's edit form renders.
     */
    private const FIELD_SCHEMA = [
        'openai' => [
            'api_key' => [
                'label' => 'API key',
                'type' => 'password',
                'hint' => 'Find at platform.openai.com/api-keys',
                'required' => true,
            ],
            'organization' => [
                'label' => 'Organization ID',
                'type' => 'text',
                'hint' => 'Optional. e.g. org-xxxxxxxxxxxx',
            ],
            'max_tokens' => [
                'label' => 'Max tokens per request',
                'type' => 'text',
                'placeholder' => 'e.g. 4096',
                'hint' => 'Optional ceiling — a single AI request can never burn more than this many output tokens. Leave blank for the model default.',
            ],
        ],
        'anthropic' => [
            'api_key' => [
                'label' => 'API key',
                'type' => 'password',
                'hint' => 'Find at console.anthropic.com/settings/keys',
                'required' => true,
            ],
            'max_tokens' => [
                'label' => 'Max tokens per request',
                'type' => 'text',
                'placeholder' => 'e.g. 4096',
                'hint' => 'Optional ceiling — a single AI request can never burn more than this many output tokens. Leave blank for the model default.',
            ],
        ],
        'gemini' => [
            'api_key' => [
                'label' => 'API key',
                'type' => 'password',
                'hint' => 'Generate at aistudio.google.com/app/apikey',
                'required' => true,
            ],
            'project_id' => [
                'label' => 'Project ID',
                'type' => 'text',
                'hint' => 'Optional. For Vertex AI billing.',
            ],
            'max_tokens' => [
                'label' => 'Max tokens per request',
                'type' => 'text',
                'placeholder' => 'e.g. 4096',
                'hint' => 'Optional ceiling — a single AI request can never burn more than this many output tokens. Leave blank for the model default.',
            ],
        ],
        'mistral' => [
            'api_key' => [
                'label' => 'API key',
                'type' => 'password',
                'hint' => 'Generate at console.mistral.ai/api-keys',
                'required' => true,
            ],
            'max_tokens' => [
                'label' => 'Max tokens per request',
                'type' => 'text',
                'placeholder' => 'e.g. 4096',
                'hint' => 'Optional ceiling — a single AI request can never burn more than this many output tokens. Leave blank for the model default.',
            ],
        ],
    ];

    public function index()
    {
        $providers = AdminAiKey::orderBy('sort_order')->get()->map(function (AdminAiKey $k) {
            $k->fields_schema        = self::FIELD_SCHEMA[$k->provider] ?? [];
            $k->extra_config_decoded = $k->extra_config_array;
            $k->model_choices        = self::MODELS[$k->provider] ?? [];
            return $k;
        });

        $stats = [
            'total'  => $providers->count(),
            'active' => $providers->where('is_active', true)->count(),
            'ready'  => $providers->filter(fn ($p) => $p->is_active && !empty($p->api_key))->count(),
            'no_key' => $providers->filter(fn ($p) => empty($p->api_key))->count(),
        ];

        return view('admin.api-keys.index', compact('providers', 'stats'));
    }

    public function update(Request $request, int $id)
    {
        $row    = AdminAiKey::findOrFail($id);
        $fields = self::FIELD_SCHEMA[$row->provider] ?? [];

        $data = $request->validate([
            'default_model' => ['nullable', 'string', 'max:80'],
            'api_key'       => ['nullable', 'string', 'max:1024'],
            'sort_order'    => ['nullable', 'integer', 'min:0'],
            'extra'         => ['nullable', 'array'],
            'extra.*'       => ['nullable', 'string', 'max:500'],
        ]);

        // Only overwrite the key if a non-empty value was submitted, so the
        // "leave blank to keep" placeholder works.
        if (!empty($data['api_key'])) {
            $row->api_key = $data['api_key'];
        }

        $row->default_model = $data['default_model'] ?? $row->default_model;
        $row->sort_order    = $data['sort_order'] ?? $row->sort_order;

        // Merge extra_config — only overwrite a key when a non-empty value was
        // submitted (so blank fields don't wipe existing config).
        $existing = $row->extra_config_array;
        $incoming = $data['extra'] ?? [];
        foreach ($fields as $key => $spec) {
            if ($key === 'api_key') continue;
            if (array_key_exists($key, $incoming) && $incoming[$key] !== '') {
                $existing[$key] = $incoming[$key];
            }
        }
        $row->extra_config = json_encode($existing, JSON_UNESCAPED_UNICODE);

        // Saving with a usable key on the row auto-activates it.
        if (!empty($row->api_key)) {
            $row->is_active = true;
        }
        $row->save();

        return back()->with('success', $row->name . ($row->is_active ? ' saved + activated.' : ' settings saved.'));
    }

    public function toggle(int $id)
    {
        $row = AdminAiKey::findOrFail($id);

        if (!$row->is_active && empty($row->api_key)) {
            return back()->with('error', 'Add an API key before activating.');
        }
        $row->update(['is_active' => !$row->is_active]);

        return back()->with('success', $row->is_active ? 'Activated.' : 'Deactivated.');
    }

    public static function fieldSchemaFor(string $provider): array
    {
        return self::FIELD_SCHEMA[$provider] ?? [];
    }
}
