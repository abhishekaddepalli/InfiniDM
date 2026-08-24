<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Delete chat" flag. Distinct from archived_at: a deleted thread must vanish
 * from BOTH the main list AND the Archived view (archive only moves it aside).
 * Named hidden_at, not deleted_at, so it never trips Laravel SoftDeletes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instagram_contacts', function (Blueprint $table) {
            if (!Schema::hasColumn('instagram_contacts', 'hidden_at')) {
                $table->timestamp('hidden_at')->nullable()->after('archived_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('instagram_contacts', function (Blueprint $table) {
            if (Schema::hasColumn('instagram_contacts', 'hidden_at')) $table->dropColumn('hidden_at');
        });
    }
};
