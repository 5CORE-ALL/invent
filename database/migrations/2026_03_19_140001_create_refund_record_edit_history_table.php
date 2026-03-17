<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refund_record_edit_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('refund_record_id')->constrained('refund_records')->cascadeOnDelete();
            $table->string('sku')->index();
            $table->string('field', 32);
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamp('updated_at');
            $table->index(['refund_record_id', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refund_record_edit_history');
    }
};
