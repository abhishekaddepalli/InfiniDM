<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Create `flows` ONLY when it does not already exist.
 *
 * This is what lets one ZIP serve both products:
 *
 *  - Installed into WaDesk, `flows` is already there (core owns it, and core's
 *    Flow model drives chat / call / instagram flows alike). This migration is
 *    a no-op and MUST NOT touch the table — altering it would break every
 *    existing WhatsApp and call flow.
 *  - Installed standalone, there is no WaDesk, so nothing has created `flows`
 *    yet and the Instagram flow builder has nowhere to save. This creates it.
 *
 * The columns mirror core's exactly, because the Node runtime
 * (instagramFlowService.js) reads the same shape in both products. A divergent
 * standalone schema would mean two runtimes to maintain.
 *
 * `workspace_id` is kept in standalone rather than stripped. Standalone is
 * still sold to businesses who want more than one brand per install, and
 * carrying the column costs nothing while removing it would fork every model
 * and query away from core.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('flows')) {
            return;   // WaDesk — core owns this table.
        }

        Schema::create('flows', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('workspace_id')->nullable()->index();

            $table->string('flow_name');
            $table->string('category')->nullable();

            // 'instagram' is the only value standalone ever writes, but the
            // column stays wide so a flow authored here opens unchanged if the
            // customer later moves onto WaDesk.
            $table->string('flow_type')->default('instagram')->index();
            $table->string('provider')->nullable()->index();

            // Encrypted at rest in core; same treatment here so the two are
            // byte-compatible and a DB copied between products still reads.
            $table->longText('flow_data')->nullable();
            $table->string('flow_file_path')->nullable();

            $table->string('trigger_kind')->nullable()->index();
            $table->unsignedBigInteger('trigger_value')->nullable();
            $table->text('trigger_keywords')->nullable();
            $table->unsignedBigInteger('trigger_device_id')->nullable();

            $table->boolean('is_published')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('published_at')->nullable();

            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // Deliberately NOT dropping the table.
        //
        // In WaDesk this migration never created it, so dropping would destroy
        // core data on an uninstall. There is no way at rollback time to know
        // which product created it, so the safe answer is always "leave it".
    }
};
