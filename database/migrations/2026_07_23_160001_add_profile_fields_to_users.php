<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Profile fields the WaDesk-style Add/Edit user form carries. */
return new class extends Migration
{
    private array $cols = ['mobile', 'gender', 'address', 'country', 'state', 'city', 'zip', 'notes'];

    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }
        Schema::table('users', function (Blueprint $t) {
            if (! Schema::hasColumn('users', 'mobile'))  $t->string('mobile', 40)->nullable();
            if (! Schema::hasColumn('users', 'gender'))  $t->string('gender', 8)->nullable();
            if (! Schema::hasColumn('users', 'address')) $t->text('address')->nullable();
            if (! Schema::hasColumn('users', 'country')) $t->string('country', 80)->nullable();
            if (! Schema::hasColumn('users', 'state'))   $t->string('state', 80)->nullable();
            if (! Schema::hasColumn('users', 'city'))    $t->string('city', 80)->nullable();
            if (! Schema::hasColumn('users', 'zip'))     $t->string('zip', 20)->nullable();
            if (! Schema::hasColumn('users', 'notes'))   $t->text('notes')->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }
        foreach ($this->cols as $c) {
            if (Schema::hasColumn('users', $c)) {
                Schema::table('users', fn (Blueprint $t) => $t->dropColumn($c));
            }
        }
    }
};
