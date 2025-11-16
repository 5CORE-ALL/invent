<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('channel_movement_analysis', function (Blueprint $table) {
            $table->id();
            $table->string('channel_name'); // e.g., "Amazon"
            $table->string('month'); // e.g., "Jul 2025"
            $table->decimal('system_data', 12, 2)->nullable(); // auto-calculated sales
            $table->decimal('site_amount', 12, 2)->nullable();  // manual
            $table->decimal('receipt_amount', 12, 2)->nullable(); // manual
            $table->decimal('expenses_percent', 6, 2)->nullable(); // = receipt / site
            $table->decimal('ours_percentage', 6, 2)->nullable(); // manual
            $table->timestamps();

            $table->unique(['channel_name', 'month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('channel_movement_analysis');
    }
};
