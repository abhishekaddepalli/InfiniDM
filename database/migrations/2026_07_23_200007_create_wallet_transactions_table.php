<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wallet ledger — one row per balance change (ported from WaDesk). A future
 * WalletService is the intended writer; `balance_after` is denormalised so a
 * wallet history page can paint running totals without re-summing the ledger.
 *
 * `kind` separates two parallel balances: 'credit' (usage credits) and
 * 'currency' (paid top-ups in minor units). Only the table + model are ported
 * in this billing-foundation phase — no credit send-path is wired.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('wallet_transactions')) {
            return;
        }
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('kind', 16);                  // 'credit' | 'currency'
            $table->string('type', 16);                  // 'earn' | 'spend' | 'refund' | 'admin_adjust' | 'topup'
            $table->bigInteger('amount');                // signed
            $table->bigInteger('balance_after');
            $table->string('source', 64)->nullable();
            $table->string('subject_type', 64)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('description', 191)->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
            $table->index(['kind', 'source']);
            $table->index(['subject_type', 'subject_id']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
