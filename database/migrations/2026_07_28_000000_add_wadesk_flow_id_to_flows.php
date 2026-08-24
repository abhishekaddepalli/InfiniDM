<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WaDesk → Instaflow flow sync. When an operator authors an Instagram flow in
 * WaDesk's builder and saves it, WaDesk pushes the (format-identical) flow over
 * the bridge and Instaflow stores it here so its native runtime + keyword
 * matcher run it. `wadesk_flow_id` is the stable external key so a re-save
 * UPDATES the same Instaflow flow instead of creating a duplicate.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('flows')) return;
        Schema::table('flows', function (Blueprint $table) {
            if (! Schema::hasColumn('flows', 'wadesk_flow_id')) {
                $table->unsignedBigInteger('wadesk_flow_id')->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('flows') || ! Schema::hasColumn('flows', 'wadesk_flow_id')) return;
        Schema::table('flows', function (Blueprint $table) {
            $table->dropColumn('wadesk_flow_id');
        });
    }
};
