<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table) {
                $table->id();
                $table->string('name', 80)->unique();
                $table->string('description', 255)->nullable();
                // Permission keys granted to this role, e.g. ["inbox.view","flows.manage"].
                $table->json('permissions')->nullable();
                // System roles (Administrator / Member) can't be deleted or renamed.
                $table->boolean('is_system')->default(false);
                $table->timestamps();
            });
        }

        if (Schema::hasTable('users') && !Schema::hasColumn('users', 'role_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedBigInteger('role_id')->nullable()->after('is_admin')->index();
            });
        }

        // Seed the two protected default roles (idempotent).
        $now = now();
        if (DB::table('roles')->where('name', 'Administrator')->doesntExist()) {
            DB::table('roles')->insert([
                'name'        => 'Administrator',
                'description' => 'Full access to every feature.',
                'permissions' => json_encode(['*']),
                'is_system'   => true,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
        }
        if (DB::table('roles')->where('name', 'Member')->doesntExist()) {
            DB::table('roles')->insert([
                'name'        => 'Member',
                'description' => 'Day-to-day access — inbox, automations, content.',
                'permissions' => json_encode([
                    'dashboard.view', 'inbox.view', 'inbox.reply',
                    'automations.view', 'flows.view', 'content.view',
                    'contacts.view', 'analytics.view',
                ]),
                'is_system'   => true,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'role_id')) {
            Schema::table('users', fn (Blueprint $t) => $t->dropColumn('role_id'));
        }
        Schema::dropIfExists('roles');
    }
};
