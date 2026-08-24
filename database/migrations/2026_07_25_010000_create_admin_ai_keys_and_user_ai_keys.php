<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * AI provider keys for the standalone Instaflow build.
 *
 * Two tables:
 *   admin_ai_keys  — platform-owned global keys (one row per provider). Used as
 *                    the fallback for every AI feature (the flow "AI" node).
 *   user_ai_keys   — per-user BYOK. Instaflow has NO workspaces (every row is
 *                    workspace_id = 0; ownership is by user_id), so a customer's
 *                    own key is scoped to their user_id, not a workspace.
 *
 * `api_key` is stored encrypted at rest (Crypt mutators on the models). The
 * column is TEXT because an encrypted blob is far longer than the raw key.
 *
 * Idempotent: guarded with Schema::hasTable, and the admin seed only inserts a
 * provider row when it is missing — re-running never duplicates or overwrites an
 * admin-entered key.
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('admin_ai_keys')) {
            Schema::create('admin_ai_keys', function (Blueprint $table) {
                $table->id();
                // openai | anthropic | gemini | mistral
                $table->string('provider', 32)->unique();
                $table->string('name', 80);
                $table->text('api_key')->nullable();               // encrypted at rest via cast/mutator
                $table->string('default_model', 80)->nullable();
                $table->string('extra_config', 500)->nullable();   // JSON: max_tokens, organization, etc.
                $table->boolean('is_active')->default(false);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('user_ai_keys')) {
            Schema::create('user_ai_keys', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->string('provider', 32);
                $table->text('api_key')->nullable();               // encrypted at rest via mutator
                $table->string('default_model', 80)->nullable();
                $table->timestamps();

                $table->unique(['user_id', 'provider']);
            });
        }

        // Pre-seed one row per provider so Admin → AI keys lists every provider
        // on a fresh deploy (inactive + blank key until the operator pastes one).
        $providers = [
            ['provider' => 'openai',    'name' => 'OpenAI',           'default_model' => 'gpt-5.4-mini',         'sort_order' => 1],
            ['provider' => 'anthropic', 'name' => 'Anthropic Claude', 'default_model' => 'claude-opus-4-8',      'sort_order' => 2],
            ['provider' => 'gemini',    'name' => 'Google Gemini',    'default_model' => 'gemini-3.5-flash',     'sort_order' => 3],
            ['provider' => 'mistral',   'name' => 'Mistral',          'default_model' => 'mistral-large-latest', 'sort_order' => 4],
        ];

        foreach ($providers as $p) {
            if (DB::table('admin_ai_keys')->where('provider', $p['provider'])->exists()) {
                continue; // never touch an existing row — preserve any admin-entered key
            }
            DB::table('admin_ai_keys')->insert([
                'provider'      => $p['provider'],
                'name'          => $p['name'],
                'api_key'       => null,   // blank until admin pastes the real key
                'default_model' => $p['default_model'],
                'extra_config'  => json_encode([]),
                'is_active'     => false,
                'sort_order'    => $p['sort_order'],
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_ai_keys');
        Schema::dropIfExists('admin_ai_keys');
    }
};
