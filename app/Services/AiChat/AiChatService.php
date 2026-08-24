<?php

namespace App\Services\AiChat;

use App\Services\AiAgentService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Single-shot text-reply helper that wraps AiAgentService's provider router so
 * assistants don't duplicate the OpenAI / Anthropic / Gemini branching.
 *
 * Ported from WaDesk for the standalone IgDesk build. WaDesk's contextFor()
 * concatenated an assistant's `ai_training_sources` rows into a knowledge-base
 * string; IgDesk does not ship that table (the AI-training subsystem was
 * stripped), so contextFor() is made FAIL-SAFE here — it returns '' whenever the
 * table/model is absent and never throws. IgFlowRunner's AI node calls it and
 * simply skips the knowledge-base injection on an empty string.
 */
class AiChatService
{
    public function __construct(private AiAgentService $provider)
    {
    }

    /**
     * Concatenated training material for an assistant, hard-capped so we never
     * blow the context window. Returns '' when the training subsystem isn't
     * present in this build (IgDesk) — wrapped so a missing table/model can
     * never surface as an error to the flow runtime.
     *
     * The parameter is intentionally untyped: WaDesk passed an AiChatAssistant
     * model, which does not exist in IgDesk. Any object exposing
     * `workspace_id` / `id` works; a null/other value yields ''.
     */
    public function contextFor($assistant): string
    {
        try {
            if (! $assistant || ! Schema::hasTable('ai_training_sources')) {
                return '';
            }
            if (! class_exists(\App\Models\AiTrainingSource::class)) {
                return '';
            }

            $rows = \App\Models\AiTrainingSource::query()
                ->where('workspace_id', $assistant->workspace_id ?? 0)
                ->where(function ($q) use ($assistant) {
                    $q->whereNull('assistant_id')->orWhere('assistant_id', $assistant->id ?? 0);
                })
                ->where('status', 'ready')
                ->orderBy('id')
                ->get();

            $parts  = [];
            $budget = 12000;
            foreach ($rows as $r) {
                $text = trim((string) (method_exists($r, 'renderedText') ? $r->renderedText() : ($r->content ?? '')));
                if ($text === '') continue;
                $chunk = '[' . ($r->label ?? 'source') . "]\n" . $text;
                $parts[] = mb_substr($chunk, 0, max(500, $budget));
                $budget -= mb_strlen($chunk);
                if ($budget <= 0) break;
            }
            return implode("\n\n", $parts);
        } catch (\Throwable $e) {
            Log::info('[AI-CHAT] contextFor skipped: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Generate a reply for an assistant given the visitor's message. Kept for
     * API-compatibility with WaDesk callers; delegates to the provider router
     * with the assistant's persona + knowledge base. IgDesk's flow runtime
     * does not call this today (it drives the AI node directly), but any future
     * text-AI channel can.
     */
    public function reply($assistant, string $visitorMessage, string $transcript = ''): string
    {
        $system = $this->systemPrompt($assistant);
        $context = $this->contextFor($assistant);
        if ($context !== '') {
            $system .= "\n\n--- Knowledge base ---\n" . $context . "\n--- End knowledge base ---";
        }

        $user = ($transcript !== '' ? "Conversation so far:\n$transcript\n\n" : '')
              . "Visitor just said:\n" . trim($visitorMessage)
              . "\n\nReply briefly and helpfully. Plain text only, no role prefix.";

        $reply = $this->provider->callProvider(
            provider:     (string) ($assistant->ai_provider ?? 'openai'),
            model:        (string) ($assistant->ai_model ?? 'gpt-4o-mini'),
            workspaceId:  (int) ($assistant->workspace_id ?? 0),
            systemPrompt: $system,
            userPrompt:   $user,
            maxTokens:    (int) ($assistant->reply_max_tokens ?? 400),
            temperature:  (float) ($assistant->temperature ?? 0.7),
        );

        if (!$reply || trim($reply) === '') {
            return (string) ($assistant->fallback_message ?? '')
                ?: "Sorry, I couldn't generate a reply right now. A team member will follow up shortly.";
        }
        return trim($reply);
    }

    /** Compose the system prompt: persona + tone + language. */
    private function systemPrompt($assistant): string
    {
        $base = trim((string) ($assistant->system_prompt ?? '')) ?: 'You are a helpful assistant.';
        $tone = trim((string) ($assistant->tone ?? '')) ?: 'helpful';
        $lang = trim((string) ($assistant->language ?? '')) ?: 'en';

        $out  = $base . "\n";
        $out .= "Speak in a $tone tone. Default language: $lang. Match the visitor's language if different.\n";
        $out .= "Keep replies short — chat-style, not essay-style. No role prefixes like \"Assistant:\".";
        return $out;
    }
}
