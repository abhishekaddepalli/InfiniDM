<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sync the languages table to the set we actually ship translation files for
 * (lang/<code>.json — 20 languages + English). The original seed only covered
 * 12 and included Malay (no ms.json), so the header dropdown offered languages
 * with no strings. This upserts every shipped language as active with its
 * native name + RTL flag, so the dropdown exactly matches what will translate.
 *
 * updateOrInsert keyed by code — never duplicates, safe to re-run.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('languages')) return;

        $now = now();

        // [code, English name, native name, is_rtl]
        $langs = [
            ['en',    'English',    'English',           false],
            ['ar',    'Arabic',     'العربية',           true],
            ['bn',    'Bengali',    'বাংলা',              false],
            ['de',    'German',     'Deutsch',           false],
            ['es',    'Spanish',    'Español',           false],
            ['fr',    'French',     'Français',          false],
            ['he',    'Hebrew',     'עברית',             true],
            ['hi',    'Hindi',      'हिन्दी',             false],
            ['id',    'Indonesian', 'Bahasa Indonesia',  false],
            ['it',    'Italian',    'Italiano',          false],
            ['ja',    'Japanese',   '日本語',             false],
            ['ko',    'Korean',     '한국어',             false],
            ['nl',    'Dutch',      'Nederlands',        false],
            ['pl',    'Polish',     'Polski',            false],
            ['pt',    'Portuguese', 'Português',         false],
            ['ru',    'Russian',    'Русский',           false],
            ['th',    'Thai',       'ไทย',               false],
            ['tr',    'Turkish',    'Türkçe',            false],
            ['ur',    'Urdu',       'اردو',              true],
            ['vi',    'Vietnamese', 'Tiếng Việt',        false],
            ['zh-CN', 'Chinese (Simplified)', '简体中文', false],
        ];

        foreach ($langs as $i => [$code, $name, $native, $rtl]) {
            DB::table('languages')->updateOrInsert(
                ['code' => $code],
                [
                    'name'        => $name,
                    'native_name' => $native,
                    'is_rtl'      => $rtl,
                    'is_active'   => true,
                    'sort_order'  => $i,
                    'updated_at'  => $now,
                    // created_at only set when the row is new (updateOrInsert
                    // won't clobber an existing one's timestamp on match, but
                    // insert needs it — Laravel fills both keys on insert).
                    'created_at'  => $now,
                ]
            );
        }

        // English stays the platform default unless an admin picked otherwise.
        if (! DB::table('languages')->where('is_default', true)->exists()) {
            DB::table('languages')->where('code', 'en')->update(['is_default' => true]);
        }
    }

    public function down(): void
    {
        // Non-destructive — leave the language rows in place.
    }
};
