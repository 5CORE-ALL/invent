<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('to_order_pre_checklists')) {
            return;
        }

        Schema::create('to_order_pre_checklists', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('to_order_analysis_id')->nullable();
            $table->string('sku')->index();
            $table->json('items');
            $table->string('status', 32)->nullable(); // clear_to_load | escalated
            $table->text('escalation_note')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('escalated_by')->nullable();
            $table->timestamp('escalated_at')->nullable();
            $table->timestamps();

            $table->unique('sku', 'to_order_pre_checklists_sku_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('to_order_pre_checklists');
    }
};
