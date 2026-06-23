<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user check state for one designation_mgr_checkpoints row.
 *
 * Upserted from the CL Mgr modal. Combined with weightages to compute
 * each manager's own CL Mgr score (before juniors are added).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_mgr_checkpoint_progress')) {
            return;
        }

        Schema::create('user_mgr_checkpoint_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('users', 'id', 'umcp_user_id_fk')
                ->cascadeOnDelete();
            // Explicit short FK names — MySQL caps identifiers at 64 chars
            // and the auto-generated names on this combination exceed that.
            $table->unsignedBigInteger('designation_mgr_checkpoint_id');
            $table->foreign('designation_mgr_checkpoint_id', 'umcp_dmcp_id_fk')
                ->references('id')->on('designation_mgr_checkpoints')
                ->onDelete('cascade');
            $table->boolean('checked')->default(false);
            $table->timestamp('checked_at')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'designation_mgr_checkpoint_id'], 'umcp_user_cp_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_mgr_checkpoint_progress');
    }
};
