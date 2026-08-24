<?php

namespace App\Http\Controllers\Instagram;

use App\Http\Controllers\Controller;
use App\Models\AdminAiKey;
use App\Models\InstagramAccount;
use App\Models\InstagramMessage;
use App\Models\UserAiKey;
use App\Services\AiAgentService;
use App\Services\Instagram\InstagramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * AI composer tools for the IgDesk post composer.
 *
 * Five JSON endpoints backing the "AI composer tools" card on
 * /instagram/composer. All are web+auth+composer-gated (see routes/instagram.php)
 * and same-origin CSRF-protected. Every one degrades gracefully to
 * { ok:false, error } when no AI key is configured — the UI surfaces the
 * "Add an AI key in Admin → API keys" hint rather than throwing.
 *
 * TEXT tools (caption / repurpose / review) route through AiAgentService, which
 * resolves the key user-BYOK → admin and picks the correct provider payload.
 * The model comes from the user's own default_model (openai) → the admin
 * openai default_model → a sensible fallback; the provider is inferred from the
 * model id so Claude / Gemini / Mistral keys work too.
 *
 * IMAGE generation calls OpenAI's Images API directly (gpt-image-1, falling
 * back to dall-e-3), stores the bytes on the same disk the composer uploads to,
 * and returns a public URL the composer attaches as the post media.
 *
 * BEST TIME is pure heuristic (no AI, no key needed): it reads the account's
 * real inbound-DM activity by hour/weekday to suggest when the audience is
 * active, and clearly falls back to general best practices when there's no data.
 */
class ComposerAiController extends Controller
{
    public function __construct(private AiAgentService $ai)
    {
    }

    private function wsId(): int
    {
        return (int) (Auth::user()?->current_workspace_id ?? 0);
    }

    /**
     * Plan enforcement — the AI composer tools are the `ai` entitlement
     * (access_instagram_ai). Returns a 403 JSON the front-end shows as an
     * upgrade prompt when the current plan doesn't include AI, or null when
     * allowed. Every AI endpoint calls this first.
     */
    private function ensureAi(): ?JsonResponse
    {
        if (! \App\Services\Instagram\InstagramGate::allows('ai', Auth::user()?->current_workspace)) {
            return response()->json([
                'ok'     => false,
                'locked' => true,
                'error'  => __('AI tools are not included in your plan. Upgrade to use the AI composer.'),
            ], 403);
        }

        return null;
    }

    /* ===================================================================
     |  Key / model resolution
     * =================================================================== */

    /**
     * The text model to use: the user's own openai default_model (BYOK) →
     * the admin openai default_model → a sensible fallback. The provider is
     * inferred from the model id, so a Claude/Gemini/Mistral default still
     * routes to the right API.
     */
    private function textModel(): string
    {
        $uid = (int) (Auth::id() ?? 0);
        if ($uid > 0) {
            $row = UserAiKey::where('user_id', $uid)->where('provider', 'openai')->first();
            if ($row && !empty($row->default_model)) {
                return $row->default_model;
            }
        }
        $admin = AdminAiKey::activeFor('openai');

        return ($admin && !empty($admin->default_model)) ? $admin->default_model : 'gpt-4o-mini';
    }

    /** True when a usable key exists for the provider (user BYOK or admin). */
    private function hasKeyFor(string $provider): bool
    {
        $uid = (int) (Auth::id() ?? 0);
        if ($uid > 0 && !empty(UserAiKey::forUser($uid, $provider))) {
            return true;
        }
        $admin = AdminAiKey::activeFor($provider);

        return $admin && !empty($admin->api_key);
    }

    /** The raw OpenAI key (user BYOK → admin) for the direct Images API call. */
    private function openAiKey(): ?string
    {
        $uid = (int) (Auth::id() ?? 0);
        if ($uid > 0) {
            $k = UserAiKey::forUser($uid, 'openai');
            if (!empty($k)) {
                return $k;
            }
        }
        $admin = AdminAiKey::activeFor('openai');

        return ($admin && !empty($admin->api_key)) ? $admin->api_key : null;
    }

    private const NO_KEY = 'No AI key configured. Add one in Admin → API keys (or your own key under Account → AI keys).';

    /**
     * Run a text generation through AiAgentService, returning the reply or a
     * JSON error response ready to send. $model/$provider resolved once here.
     */
    private function generate(string $system, string $user, int $maxTokens, float $temp = 0.7): array
    {
        $model    = $this->textModel();
        $provider = InstagramService::providerForModel($model);

        if (!$this->hasKeyFor($provider)) {
            return ['ok' => false, 'error' => self::NO_KEY];
        }

        $text = $this->ai->callProvider($provider, $model, 0, $system, $user, $maxTokens, $temp);
        if ($text === null || trim($text) === '') {
            return ['ok' => false, 'error' => 'The AI service did not return a result. Check the API key and try again.'];
        }

        return ['ok' => true, 'text' => trim($text)];
    }

    /* ===================================================================
     |  1. AI Caption — write a fresh caption from the topic/notes
     * =================================================================== */
    public function caption(Request $request): JsonResponse
    {
        if ($r = $this->ensureAi()) return $r;

        $data = $request->validate([
            'notes'      => 'nullable|string|max:2000',
            'media_type' => 'nullable|string|max:20',
            'tone'       => 'nullable|string|max:40',
        ]);

        $notes = trim((string) ($data['notes'] ?? ''));
        $type  = (string) ($data['media_type'] ?? 'post');
        $tone  = trim((string) ($data['tone'] ?? ''));

        $system = 'You are an expert Instagram copywriter. Write a single scroll-stopping caption for a '
            . $type . '. Keep it punchy and native to Instagram: a strong hook on the first line, a short body, '
            . 'one clear call-to-action, and 3-6 relevant hashtags on the final line. Use tasteful line breaks. '
            . 'Do NOT wrap the caption in quotes and do NOT add any commentary — output only the caption text.';
        if ($tone !== '') {
            $system .= ' Tone: ' . $tone . '.';
        }

        $user = $notes !== ''
            ? "Topic / notes for the post:\n" . $notes
            : 'Write an engaging general caption suitable for a lifestyle brand post. Invent a plausible, upbeat topic.';

        return response()->json($this->generate($system, $user, 320, 0.8));
    }

    /* ===================================================================
     |  2. Repurpose — rewrite the current caption
     * =================================================================== */
    public function repurpose(Request $request): JsonResponse
    {
        if ($r = $this->ensureAi()) return $r;

        $data = $request->validate([
            'caption' => 'required|string|max:2200',
            'style'   => 'nullable|string|max:40',
        ]);

        $caption = trim((string) $data['caption']);
        if ($caption === '') {
            return response()->json(['ok' => false, 'error' => 'Write a caption first, then Repurpose rewrites it.']);
        }

        $style = trim((string) ($data['style'] ?? ''));
        $ask   = $style !== ''
            ? 'Rewrite it in this style: ' . $style . '.'
            : 'Rewrite it: keep the core message but make it fresh — tighter, a different angle or tone, '
                . 'and a strong hook. Keep it Instagram-native with a CTA and 3-6 hashtags.';

        $system = 'You are an expert Instagram copywriter repurposing an existing caption. ' . $ask
            . ' Output ONLY the rewritten caption text — no quotes, no commentary.';

        return response()->json($this->generate($system, "Original caption:\n" . $caption, 320, 0.85));
    }

    /* ===================================================================
     |  3. Review — critique the caption (does NOT overwrite it)
     * =================================================================== */
    public function review(Request $request): JsonResponse
    {
        if ($r = $this->ensureAi()) return $r;

        $data = $request->validate([
            'caption' => 'required|string|max:2200',
        ]);

        $caption = trim((string) $data['caption']);
        if ($caption === '') {
            return response()->json(['ok' => false, 'error' => 'Write a caption first, then Review gives feedback.']);
        }

        $system = 'You are an Instagram growth strategist reviewing a draft caption. Give concise, actionable '
            . 'feedback in 3-5 short bullet points covering: the hook, length/readability, call-to-action, '
            . 'and hashtag choice (count + relevance). End with one quick "Try this" suggestion. '
            . 'Use plain hyphen bullets, no markdown headers. Be direct and specific.';

        return response()->json($this->generate($system, "Caption to review:\n" . $caption, 380, 0.5));
    }

    /* ===================================================================
     |  4. Best time — heuristic from real inbound-DM activity (no AI)
     * =================================================================== */
    public function bestTime(Request $request): JsonResponse
    {
        if ($r = $this->ensureAi()) return $r;

        $data = $request->validate([
            'instagram_account_id' => 'nullable|integer',
        ]);

        $wsId = $this->wsId();
        $q = InstagramMessage::where('workspace_id', $wsId)->where('direction', 'in');

        // Scope to the selected account when one is chosen and owned by this
        // workspace — otherwise fold in every connected account's activity.
        if (!empty($data['instagram_account_id'])) {
            $owns = InstagramAccount::forWorkspace($wsId)
                ->where('id', (int) $data['instagram_account_id'])->exists();
            if ($owns) {
                $q->where('instagram_account_id', (int) $data['instagram_account_id']);
            }
        }

        // Aggregate inbound message counts by hour-of-day over the last 90 days.
        $rows = (clone $q)->where('created_at', '>=', now()->subDays(90))
            ->selectRaw('HOUR(created_at) as h, COUNT(*) as c')
            ->groupBy('h')->pluck('c', 'h')->all();

        $total = array_sum($rows);

        if ($total < 20) {
            // Not enough real signal — say so and return general best-practice
            // windows (widely cited engagement peaks), clearly labelled.
            return response()->json([
                'ok'      => true,
                'basis'   => 'general',
                'note'    => 'Based on general Instagram best practices — not enough of your own audience data yet.',
                'windows' => [
                    ['label' => 'Weekdays', 'time' => '11:00 – 13:00', 'why' => 'Lunch-break scrolling'],
                    ['label' => 'Weekdays', 'time' => '19:00 – 21:00', 'why' => 'Evening wind-down'],
                    ['label' => 'Weekends', 'time' => '10:00 – 12:00', 'why' => 'Late-morning browsing'],
                ],
            ]);
        }

        // Rank hours by inbound volume and turn the top 3 into ~2h windows.
        arsort($rows);
        $topHours = array_slice(array_keys($rows), 0, 3, true);
        $windows  = [];
        foreach ($topHours as $h) {
            $h    = (int) $h;
            $end  = ($h + 2) % 24;
            $pct  = $total > 0 ? round(($rows[$h] / $total) * 100) : 0;
            $windows[] = [
                'label' => 'Your audience',
                'time'  => sprintf('%02d:00 – %02d:00', $h, $end),
                'why'   => $pct . '% of inbound DMs land around here',
            ];
        }

        return response()->json([
            'ok'      => true,
            'basis'   => 'audience',
            'note'    => 'Suggested from when your audience actually messages you (last 90 days of inbound DMs). A signal, not a guarantee.',
            'windows' => $windows,
        ]);
    }

    /* ===================================================================
     |  5. AI Image — generate via OpenAI Images API + store + attach
     * =================================================================== */
    public function image(Request $request): JsonResponse
    {
        if ($r = $this->ensureAi()) return $r;

        $data = $request->validate([
            'prompt' => 'required|string|max:1000',
            'size'   => 'nullable|in:1024x1024,1024x1536,1536x1024',
        ]);

        $prompt = trim((string) $data['prompt']);
        if ($prompt === '') {
            return response()->json(['ok' => false, 'error' => 'Describe the image you want to generate.']);
        }

        $key = $this->openAiKey();
        if (!$key) {
            return response()->json(['ok' => false, 'error' => 'Image generation needs an OpenAI key. Add one in Admin → API keys.']);
        }

        $size = (string) ($data['size'] ?? '1024x1024');

        // Try gpt-image-1 first (always returns b64_json). If the org can't use
        // it (403 / model access), fall back to dall-e-3 with b64_json output.
        $b64 = $this->tryOpenAiImage($key, 'gpt-image-1', $prompt, $size, false);
        if ($b64 === null) {
            // dall-e-3 only supports square + wide/tall 1792 sizes; map ours.
            $dalleSize = $size === '1024x1024' ? '1024x1024'
                : ($size === '1536x1024' ? '1792x1024' : '1024x1792');
            $b64 = $this->tryOpenAiImage($key, 'dall-e-3', $prompt, $dalleSize, true);
        }

        if ($b64 === null) {
            return response()->json(['ok' => false, 'error' => 'The image service refused the request. Check that the OpenAI key has image access and try again.']);
        }

        $bytes = base64_decode($b64, true);
        if ($bytes === false || $bytes === '') {
            return response()->json(['ok' => false, 'error' => 'The generated image was empty. Try a different prompt.']);
        }

        // Store on the same disk the composer uploads to, in the same dir, so
        // the published post can reference it exactly like an uploaded file.
        $disk = function_exists('media_disk') ? media_disk() : 'public';
        $path = 'instagram-media/ai-' . now()->format('Ymd') . '-' . bin2hex(random_bytes(6)) . '.png';

        try {
            \Illuminate\Support\Facades\Storage::disk($disk)->put($path, $bytes, 'public');
        } catch (\Throwable $e) {
            Log::error('[COMPOSER-AI] image store failed: ' . $e->getMessage());
            return response()->json(['ok' => false, 'error' => 'Could not save the generated image.']);
        }

        // Surface the generated image in the personal Files library (best-effort).
        \App\Models\UserFile::record([
            'user_id'       => (int) auth()->id(),
            'disk'          => $disk,
            'path'          => $path,
            'original_name' => basename($path),
            'mime'          => 'image/png',
            'size'          => strlen($bytes),
            'folder'        => 'AI images',
        ]);

        $url = function_exists('media_url') ? media_url($path) : asset('storage/' . $path);

        return response()->json(['ok' => true, 'url' => $url]);
    }

    /**
     * One OpenAI Images API attempt. Returns the base64 payload or null on any
     * non-2xx / missing-data response. $withFormat sends response_format
     * (required for dall-e-3 b64; gpt-image-1 rejects the field and always b64s).
     */
    private function tryOpenAiImage(string $key, string $model, string $prompt, string $size, bool $withFormat): ?string
    {
        $payload = [
            'model'  => $model,
            'prompt' => $prompt,
            'size'   => $size,
            'n'      => 1,
        ];
        if ($withFormat) {
            $payload['response_format'] = 'b64_json';
        }

        try {
            $res = Http::withToken($key)->timeout(120)
                ->post('https://api.openai.com/v1/images/generations', $payload);
        } catch (\Throwable $e) {
            Log::warning('[COMPOSER-AI] images threw', ['model' => $model, 'err' => $e->getMessage()]);
            return null;
        }

        if (!$res->ok()) {
            Log::warning('[COMPOSER-AI] images non-200', [
                'model'  => $model,
                'status' => $res->status(),
                'body'   => substr($res->body(), 0, 300),
            ]);
            return null;
        }

        $b64 = (string) ($res->json('data.0.b64_json') ?? '');

        return $b64 !== '' ? $b64 : null;
    }
}
