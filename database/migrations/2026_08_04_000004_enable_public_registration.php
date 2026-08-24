<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Setting;

/**
 * Public registration is on by default. A prior admin-settings save wrote
 * allow_registration = 0, which hid the "Create an account" link on the login
 * page. Flip it back on.
 */
return new class extends Migration
{
    public function up(): void
    {
        try {
            Setting::set('allow_registration', 1, 'int');
        } catch (\Throwable $e) {
        }
    }

    public function down(): void
    {
    }
};
