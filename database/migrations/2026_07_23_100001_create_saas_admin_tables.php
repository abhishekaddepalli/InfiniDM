<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * SaaS admin foundation for the standalone Instaflow edition.
 *
 * Three things at once, because they only make sense together:
 *   - users gain is_admin (the operator) + package_id (customers on a plan)
 *   - settings: a key/value store for admin-editable global config (site name,
 *     logo, Meta app credentials) — standalone has no SystemSetting model
 *   - packages: the SaaS plans an operator sells (features + limits)
 *
 * Every step is guarded so a re-run, or a run on a DB that already carries one
 * of these (a WaDesk-derived schema), is a no-op rather than an error.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $t) {
                if (! Schema::hasColumn('users', 'is_admin')) {
                    $t->boolean('is_admin')->default(false)->after('email');
                }
                if (! Schema::hasColumn('users', 'package_id')) {
                    $t->unsignedBigInteger('package_id')->nullable()->after('is_admin');
                }
                if (! Schema::hasColumn('users', 'trial_ends_at')) {
                    $t->timestamp('trial_ends_at')->nullable()->after('package_id');
                }
            });
        }

        if (! Schema::hasTable('settings')) {
            Schema::create('settings', function (Blueprint $t) {
                $t->id();
                $t->string('key', 96)->unique();
                $t->longText('value')->nullable();
                $t->string('type', 16)->default('string'); // string|bool|int|json
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('packages')) {
            Schema::create('packages', function (Blueprint $t) {
                $t->id();
                $t->string('name');
                $t->string('slug')->unique();
                $t->text('description')->nullable();
                $t->decimal('price', 10, 2)->default(0);
                $t->string('currency', 8)->default('USD');
                $t->string('interval', 12)->default('month'); // month|year|lifetime
                $t->unsignedInteger('trial_days')->default(0);

                // Limits. NULL = unlimited.
                $t->integer('max_accounts')->nullable();
                $t->integer('max_flows')->nullable();
                $t->integer('max_automations')->nullable();
                $t->integer('monthly_dms')->nullable();
                $t->integer('team_seats')->nullable();

                // Feature toggles as a JSON set of enabled keys.
                $t->json('features')->nullable();

                $t->boolean('is_active')->default(true);
                $t->boolean('is_default')->default(false); // assigned to new signups
                $t->boolean('is_featured')->default(false);
                $t->unsignedInteger('sort')->default(0);
                $t->timestamps();
            });

            // A starter Free plan so signups have something to land on and the
            // packages page is never empty on a fresh install.
            DB::table('packages')->insert([
                'name' => 'Free', 'slug' => 'free', 'description' => 'Get started — one account, core automations.',
                'price' => 0, 'currency' => 'USD', 'interval' => 'month', 'trial_days' => 0,
                'max_accounts' => 1, 'max_flows' => 3, 'max_automations' => 5, 'monthly_dms' => 500, 'team_seats' => 1,
                'features' => json_encode(['inbox', 'automations', 'flows', 'comments']),
                'is_active' => true, 'is_default' => true, 'is_featured' => false, 'sort' => 0,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Never drop the users columns on rollback — other data may reference
        // them. The two new tables are safe to drop.
        Schema::dropIfExists('packages');
        Schema::dropIfExists('settings');
    }
};
