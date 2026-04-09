<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('refund_records')) {
            return;
        }

        Schema::create('refund_records', function (Blueprint $table) {
            $table->id();
            $table->string('sku');
            $table->unsignedInteger('qty');
            $table->string('reason');
            $table->string('comment', 255)->nullable();
            $table->string('created_by')->nullable();
            $table->boolean('is_archived')->default(false);
            $table->timestamps();
            $table->index(['sku', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refund_records');
    }
};
