<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Pricing FAQ — admin-editable accordion shown on /instagram/plans. Ported from
 * WaDesk so the plans page stops hard-coding its FAQ. Seeds the four defaults the
 * blade used to inline, so the live page looks identical until the admin edits them.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pricing_faqs')) return;

        Schema::create('pricing_faqs', function (Blueprint $t) {
            $t->id();
            $t->string('question');
            $t->text('answer');
            $t->unsignedInteger('sort_order')->default(0);
            $t->boolean('is_active')->default(true);
            $t->string('placement', 20)->default('pricing'); // pricing | home | both
            $t->timestamps();
        });

        $now  = now();
        $rows = [
            ['Can I change plans later?', 'Yes. Move up to a bigger plan whenever your automations or audience grow — the change applies right away.'],
            ['What does "Unlimited" mean?', 'Where a limit shows "Unlimited", that feature has no fixed cap on your plan.'],
            ['How is a plan paid for?', 'Pick a plan, choose a payment method at checkout, and pay once for the period. There is no auto-renewal — you buy each period yourself.'],
            ['Which currency am I charged in?', 'Each plan is priced and charged in its own currency, shown on the card and again at checkout before you pay.'],
        ];
        foreach ($rows as $i => [$q, $a]) {
            DB::table('pricing_faqs')->insert([
                'question' => $q, 'answer' => $a, 'sort_order' => $i + 1,
                'is_active' => true, 'placement' => 'pricing',
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_faqs');
    }
};
