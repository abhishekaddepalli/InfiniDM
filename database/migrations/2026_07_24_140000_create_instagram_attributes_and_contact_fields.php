<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WaDesk-style subscriber attributes for Instagram contacts.
 *
 * `instagram_attributes` holds the CUSTOM field definitions an operator creates
 * (key + label), scoped by owner. The three built-ins — name, email, phone —
 * are real columns on `instagram_contacts` (name already existed); everything
 * else a flow captures lands in the contact's `attributes` JSON bag. An Ask
 * question node can then persist its answer onto the contact, and every flow
 * pre-loads what we already know as {{name}}, {{email}}, {{custom_key}} …
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instagram_contacts', function (Blueprint $table) {
            if (!Schema::hasColumn('instagram_contacts', 'email')) {
                $table->string('email', 191)->nullable()->after('name');
            }
            if (!Schema::hasColumn('instagram_contacts', 'phone')) {
                $table->string('phone', 32)->nullable()->after('email');
            }
            if (!Schema::hasColumn('instagram_contacts', 'attributes')) {
                $table->text('attributes')->nullable()->after('phone'); // JSON map of custom_key => value
            }
        });

        if (!Schema::hasTable('instagram_attributes')) {
            Schema::create('instagram_attributes', function (Blueprint $table) {
                $table->id();
                // Instaflow scopes by user_id (workspace_id is 0); both are kept
                // so the same table works inside WaDesk (workspace) too.
                $table->unsignedBigInteger('user_id')->default(0)->index();
                $table->unsignedBigInteger('workspace_id')->default(0)->index();
                $table->string('key', 64);       // machine slug used in {{key}}
                $table->string('label', 120);    // human label shown in pickers
                $table->timestamps();

                $table->unique(['user_id', 'key']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('instagram_attributes');
        Schema::table('instagram_contacts', function (Blueprint $table) {
            foreach (['email', 'phone', 'attributes'] as $c) {
                if (Schema::hasColumn('instagram_contacts', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
