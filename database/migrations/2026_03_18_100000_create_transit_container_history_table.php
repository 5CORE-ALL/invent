<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transit_container_history', function (Blueprint $table) {
            $table->id();
            $table->string('action_type', 50); // row_created, row_updated, row_moved, row_deleted, purchase_added, tab_added, push_inventory, push_arrived
            $table->unsignedBigInteger('transit_container_detail_id')->nullable();
            $table->string('from_tab')->nullable();
            $table->string('to_tab')->nullable();
            $table->string('our_sku')->nullable();
            $table->text('details')->nullable(); // JSON or text summary
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['action_type', 'created_at']);
            $table->index(['our_sku', 'created_at']);
            $table->index(['to_tab', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transit_container_history');
    }
};
