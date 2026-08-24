<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hide languages we ship no translation file for. The original seed activated
 * Malay (ms) but there is no lang/ms.json, so the header dropdown offered a
 * language that just fell back to English. Deactivate anything outside the 21
 * shipped codes so the dropdown only lists languages that actually translate.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('languages')) return;

        $shipped = ['en','ar','bn','de','es','fr','he','hi','id','it','ja','ko','nl','pl','pt','ru','th','tr','ur','vi','zh-CN'];

        DB::table('languages')->whereNotIn('code', $shipped)->update(['is_active' => false]);
    }

    public function down(): void
    {
        // Non-destructive.
    }
};
