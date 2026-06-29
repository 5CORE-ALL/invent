<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mip_pre_checklists')) {
            return;
        }

        Schema::create('mip_pre_checklists', function (Blueprint $table) {
            $table->id();
            $table->string('source_table', 32);
            $table->unsignedBigInteger('source_id');
            $table->string('sku')->nullable()->index();
            $table->json('items');
            $table->string('status', 20)->nullable(); // updated | escalated
            $table->text('escalation_note')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('escalated_by')->nullable();
            $table->timestamp('escalated_at')->nullable();
            $table->timestamps();

            $table->unique(['source_table', 'source_id'], 'mip_pre_checklists_source_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mip_pre_checklists');
    }
};
