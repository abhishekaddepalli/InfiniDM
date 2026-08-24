<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Rebrand IgDesk -> InstaMagic. The brand shown across the app is dynamic:
 * brand_name()/site_name() read the `site_name` setting, and the payment
 * drivers read `app_name`. Point both at "InstaMagic" so the whole UI + payment
 * descriptors carry the new name. Admin can still override either in
 * /admin/settings/general afterwards.
 */
return new class extends Migration
{
    public function up(): void
    {
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('settings')) {
                \App\Models\Setting::set('site_name', 'InstaMagic');
                \App\Models\Setting::set('app_name', 'InstaMagic');
            }
        } catch (\Throwable $e) {
            // Best-effort; settings table may not exist during a fresh install.
        }
    }

    public function down(): void
    {
        // No-op: we don't want a rollback to silently restore the old brand.
    }
};
