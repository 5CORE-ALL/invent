<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('refund_edit_history')) {
            return;
        }

        Schema::create('refund_edit_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('inventory_id');
            $table->string('sku')->index();
            $table->string('field', 32);
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamp('updated_at');
            $table->index(['inventory_id', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refund_edit_history');
    }
};
