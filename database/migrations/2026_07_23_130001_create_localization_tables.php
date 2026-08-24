<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Currencies + Languages — the managed lists the General settings page picks
 * from, matching WaDesk's /admin/currencies and /admin/languages. Seeded so the
 * pickers are populated on a fresh install.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('currencies')) {
            Schema::create('currencies', function (Blueprint $t) {
                $t->id();
                $t->string('code', 8)->unique();
                $t->string('name', 80);
                $t->string('symbol', 8)->nullable();
                $t->decimal('exchange_rate', 16, 6)->default(1);
                $t->boolean('is_active')->default(true);
                $t->boolean('is_default')->default(false);
                $t->timestamps();
            });

            $now = now();
            $rows = [
                ['USD', 'US Dollar', '$'], ['EUR', 'Euro', '€'], ['GBP', 'British Pound', '£'],
                ['INR', 'Indian Rupee', '₹'], ['AUD', 'Australian Dollar', 'A$'], ['CAD', 'Canadian Dollar', 'C$'],
                ['BRL', 'Brazilian Real', 'R$'], ['AED', 'UAE Dirham', 'د.إ'], ['IDR', 'Indonesian Rupiah', 'Rp'],
                ['SGD', 'Singapore Dollar', 'S$'], ['ZAR', 'South African Rand', 'R'], ['NGN', 'Nigerian Naira', '₦'],
                ['PHP', 'Philippine Peso', '₱'], ['JPY', 'Japanese Yen', '¥'], ['TRY', 'Turkish Lira', '₺'],
            ];
            foreach ($rows as $i => [$code, $name, $sym]) {
                DB::table('currencies')->insert([
                    'code' => $code, 'name' => $name, 'symbol' => $sym,
                    'exchange_rate' => 1, 'is_active' => true, 'is_default' => $code === 'USD',
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }

        if (! Schema::hasTable('languages')) {
            Schema::create('languages', function (Blueprint $t) {
                $t->id();
                $t->string('code', 12)->unique();
                $t->string('name', 80);
                $t->string('native_name', 80)->nullable();
                $t->boolean('is_rtl')->default(false);
                $t->boolean('is_active')->default(true);
                $t->boolean('is_default')->default(false);
                $t->timestamps();
            });

            $now = now();
            $rows = [
                ['en', 'English', 'English', false], ['es', 'Spanish', 'Español', false],
                ['pt', 'Portuguese', 'Português', false], ['fr', 'French', 'Français', false],
                ['de', 'German', 'Deutsch', false], ['it', 'Italian', 'Italiano', false],
                ['ar', 'Arabic', 'العربية', true], ['hi', 'Hindi', 'हिन्दी', false],
                ['id', 'Indonesian', 'Bahasa Indonesia', false], ['ms', 'Malay', 'Bahasa Melayu', false],
                ['tr', 'Turkish', 'Türkçe', false], ['ur', 'Urdu', 'اردو', true],
            ];
            foreach ($rows as [$code, $name, $native, $rtl]) {
                DB::table('languages')->insert([
                    'code' => $code, 'name' => $name, 'native_name' => $native, 'is_rtl' => $rtl,
                    'is_active' => true, 'is_default' => $code === 'en',
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('currencies');
        Schema::dropIfExists('languages');
    }
};
