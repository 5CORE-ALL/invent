<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('requisitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->string('department')->nullable();
            $table->enum('status', ['draft', 'submitted', 'approved', 'issued', 'closed'])->default('draft')->index();
            $table->enum('priority', ['low', 'medium', 'high'])->default('medium');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('requisition_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requisition_id')->constrained('requisitions')->cascadeOnDelete();
            $table->foreignId('part_id')->constrained('product_master')->cascadeOnDelete();
            $table->unsignedInteger('quantity_requested')->default(0);
            $table->unsignedInteger('quantity_approved')->nullable();
            $table->unsignedInteger('quantity_issued')->default(0);
            $table->timestamps();

            $table->index(['requisition_id', 'part_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('requisition_items');
        Schema::dropIfExists('requisitions');
    }
};
