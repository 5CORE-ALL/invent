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
        Schema::create('amazon_channel_summary_data', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 50)->default('amazon'); // amazon, ebay, walmart, etc.
            $table->date('snapshot_date'); // Snapshot date
            $table->json('summary_data'); // All metrics in JSON (flexible!)
            $table->text('notes')->nullable(); // Optional notes
            $table->timestamps();
            
            // Unique: One snapshot per channel per day
            $table->unique(['channel', 'snapshot_date']);
            
            // Indexes for faster queries
            $table->index('channel');
            $table->index('snapshot_date');
            $table->index(['channel', 'snapshot_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('amazon_channel_summary_data');
    }
};
