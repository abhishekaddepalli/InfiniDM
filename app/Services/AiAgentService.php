<?php

namespace App\Services;

use App\Models\AdminAiKey;
use App\Models\UserAiKey;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Multi-provider LLM engine for the standalone IgDesk build.
 *
 * A faithful port of WaDesk's AiAgentService provider router — the OpenAI /
 * Anthropic / Gemini / Mistral request payloads are kept EXACTLY as WaDesk's so
 * they stay correct (per-model token-field quirks, vision blocks, JSON mode).
 *
 * The WaDesk original also drove the team-inbox AI agent + voice assistant
 * (respondIfAssigned / generateReply / handoff), which depend on WaDesk-only
 * models (AiAgent, Conversation, InboxMessage, Workspace, InboxDispatcher,
 * WalletService, AiTokenMeter). None of those exist in IgDesk, so this port
 * keeps only what IgDesk actually calls: callProvider(). The
 * App\Services\Instagram\IgFlowRunner AI node and AiChatService both route
 * through here.
 *
 * KEY RESOLUTION (adapted for a workspace-less app):
 *   1. The signed-in user's own BYOK key for the provider (UserAiKey, by
 *      user_id) — only available on the request paths where a user is
 *      authenticated (e.g. an in-app test call).
 *   2. Else the platform key (AdminAiKey::activeFor). The flow runtime fires
 *      from Meta's webhook with no authenticated user, so it always resolves to
 *      the admin key.
 */
class AiAgentService
{
    /**
     * Direct LLM call. Resolves the API key (user BYOK → admin fallback),
     * routes to the provider, returns the reply text or null on any failure.
     *
     * Signature kept identical to WaDesk's so IgFlowRunner + AiChatService call
     * it unchanged. `$workspaceId` is always 0 in IgDesk (no workspaces); it
     * is accepted for signature-compatibility and otherwise unused.
     */
    public function callProvider(
        string $provider,
        string $model,
        int $workspaceId,
        string $systemPrompt,
        string $userPrompt,
        int $maxTokens = 512,
        float $temperature = 0.7,
        ?array $image = null,   // {mime, b64} — optional vision attachment
        bool $jsonMode = false, // force a strict JSON object reply (extraction)
    ): ?string {
        $provider = strtolower(trim($provider));
        $apiKey   = $this->resolveKey($provider);

        if (!$apiKey) {
            Log::warning("[AI-AGENT] No API key for provider={$provider}");
            return null;
        }

        // Platform hard ceiling on output tokens per request — admin sets it on
        // /admin/api-keys (extra_config.max_tokens) so a single AI call can
        // never burn more than this, regardless of what the node requested.
        // Blank/0 = no extra cap. Never block a send on the cap lookup.
        try {
            $adminRow = AdminAiKey::where('provider', $provider)->first();
            $adminCap = (int) ($adminRow?->extra_config_array['max_tokens'] ?? 0);
            if ($adminCap > 0 && $maxTokens > $adminCap) {
                $maxTokens = $adminCap;
            }
        } catch (\Throwable $e) { /* ignore */ }

        try {
            return match ($provider) {
                'openai'    => $this->callOpenAI($apiKey, $model, $systemPrompt, $userPrompt, $maxTokens, $temperature, $image, $jsonMode),
                'anthropic' => $this->callAnthropic($apiKey, $model, $systemPrompt, $userPrompt, $maxTokens, $temperature, $image, $jsonMode),
                'gemini'    => $this->callGemini($apiKey, $model, $systemPrompt, $userPrompt, $maxTokens, $temperature, $image, $jsonMode),
                'mistral'   => $this->callMistral($apiKey, $model, $systemPrompt, $userPrompt, $maxTokens, $temperature, $jsonMode),
                default     => null,
            };
        } catch (\Throwable $e) {
            Log::error("[AI-AGENT] provider={$provider} model={$model} error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * User BYOK (by user_id) first, else the platform admin key. Never throws.
     */
    private function resolveKey(string $provider): ?string
    {
        try {
            $uid = (int) (auth()->id() ?? 0);
            if ($uid > 0) {
                $userKey = UserAiKey::forUser($uid, $provider);
                if (!empty($userKey)) return $userKey;
            }
        } catch (\Throwable $e) { /* fall through to admin key */ }

        $admin = AdminAiKey::activeFor($provider);
        return ($admin && !empty($admin->api_key)) ? $admin->api_key : null;
    }

    /**
     * Mistral chat completions — OpenAI-compatible JSON API. Bearer auth, same
     * messages shape, supports response_format:json_object for structured
     * extraction. No vision (Mistral's image support differs).
     */
    private function callMistral(string $key, string $model, string $system, string $user, int $maxTokens, float $temp, bool $jsonMode = false): ?string
    {
        $payload = [
            'model'       => $model,
            'messages'    => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user',   'content' => $user],
            ],
            'max_tokens'  => $maxTokens,
            'temperature' => $temp,
        ];
        if ($jsonMode) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        $res = Http::withToken($key)
            ->acceptJson()
            ->timeout(30)
            ->post('https://api.mistral.ai/v1/chat/completions', $payload);
        if ($res->ok()) {
            return trim((string) ($res->json('choices.0.message.content') ?? '')) ?: null;
        }
        Log::warning('[AI-AGENT] Mistral non-200', ['status' => $res->status(), 'body' => substr($res->body(), 0, 300)]);
        return null;
    }

    private function callOpenAI(string $key, string $model, string $system, string $user, int $maxTokens, float $temp, ?array $image = null, bool $jsonMode = false): ?string
    {
        // Multimodal content array when an image is attached, else the plain
        // string the text path always sent (Chat Completions vision).
        $userContent = $image
            ? [
                ['type' => 'text',      'text' => $user],
                ['type' => 'image_url', 'image_url' => ['url' => 'data:' . $image['mime'] . ';base64,' . $image['b64']]],
              ]
            : $user;
        // OpenAI parameter compatibility: GPT-5+ and the o-series reasoning
        // models REJECT `max_tokens` in Chat Completions — they require
        // `max_completion_tokens`. The older gpt-4.x / gpt-4o line still takes
        // the classic `max_tokens`, so the token-limit field is chosen per model.
        $m   = strtolower($model);
        $new = (bool) preg_match('/^(gpt-5|gpt-6|o[1-9])/', $m);
        // GPT-5 / GPT-6 / o-series ALSO reject a CUSTOM `temperature` — they
        // accept ONLY the default (1). So omit `temperature` for the whole
        // `$new` family; only the classic gpt-4.x / gpt-4o line takes a custom one.

        $payload = [
            'model'    => $model,
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user',   'content' => $userContent],
            ],
        ];
        $payload[$new ? 'max_completion_tokens' : 'max_tokens'] = $maxTokens;
        if (!$new) {
            $payload['temperature'] = $temp;
        }
        if ($jsonMode) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        $res = Http::withToken($key)
            ->timeout($image ? 60 : 30)
            ->post('https://api.openai.com/v1/chat/completions', $payload);
        if ($res->ok()) {
            return trim((string) ($res->json('choices.0.message.content') ?? '')) ?: null;
        }
        Log::warning('[AI-AGENT] OpenAI non-200', ['status' => $res->status(), 'body' => substr($res->body(), 0, 300)]);
        return null;
    }

    private function callAnthropic(string $key, string $model, string $system, string $user, int $maxTokens, float $temp, ?array $image = null, bool $jsonMode = false): ?string
    {
        // Anthropic vision: content blocks array with a base64 image source.
        $content = $image
            ? [
                ['type' => 'text',  'text' => $user],
                ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $image['mime'], 'data' => $image['b64']]],
              ]
            : $user;
        $messages = [['role' => 'user', 'content' => $content]];
        // Structured-extraction mode — Anthropic has no json_object flag, and a
        // last-assistant-turn "{" prefill now 400s on current Claude models. So
        // instruct JSON-only via the system prompt (every model honours it) and
        // strip any stray code fence from the reply below.
        if ($jsonMode) {
            $system = trim($system)
                . "\n\nIMPORTANT: Respond with ONLY a single valid JSON object. "
                . "No prose, no explanation, no markdown code fences.";
        }
        $res = Http::withHeaders([
            'x-api-key'         => $key,
            'anthropic-version' => '2023-06-01',
        ])->timeout($image ? 60 : 30)->post('https://api.anthropic.com/v1/messages', [
            'model'      => $model,
            'max_tokens' => $maxTokens,
            'system'     => $system,
            'messages'   => $messages,
        ]);
        if ($res->ok()) {
            $text = trim((string) ($res->json('content.0.text') ?? ''));
            if ($jsonMode && $text !== '') {
                $text = trim(preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $text));
            }
            return $text ?: null;
        }
        Log::warning('[AI-AGENT] Anthropic non-200', [
            'status' => $res->status(),
            'model'  => $model,
            'body'   => mb_substr($res->body(), 0, 800),
        ]);
        return null;
    }

    private function callGemini(string $key, string $model, string $system, string $user, int $maxTokens, float $temp, ?array $image = null, bool $jsonMode = false): ?string
    {
        $prompt = $system . "\n\n" . $user;
        // Gemini vision: an extra inline_data part alongside the text part.
        $parts = [['text' => $prompt]];
        if ($image) {
            $parts[] = ['inline_data' => ['mime_type' => $image['mime'], 'data' => $image['b64']]];
        }
        $generationConfig = ['maxOutputTokens' => $maxTokens, 'temperature' => $temp];
        if ($jsonMode) {
            $generationConfig['responseMimeType'] = 'application/json';
        }
        $res = Http::timeout($image ? 60 : 30)->post(
            "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$key}",
            [
                'contents'         => [['parts' => $parts]],
                'generationConfig' => $generationConfig,
            ]
        );
        if ($res->ok()) {
            return trim((string) ($res->json('candidates.0.content.parts.0.text') ?? '')) ?: null;
        }
        Log::warning('[AI-AGENT] Gemini non-200', ['status' => $res->status()]);
        return null;
    }
}
