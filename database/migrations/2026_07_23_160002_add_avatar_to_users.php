<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Uploaded avatar path for the admin user form. */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'avatar')) {
            Schema::table('users', fn (Blueprint $t) => $t->string('avatar')->nullable());
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'avatar')) {
            Schema::table('users', fn (Blueprint $t) => $t->dropColumn('avatar'));
        }
    }
};
