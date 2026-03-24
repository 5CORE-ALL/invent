<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spare_part_purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('po_number')->unique();
            // Align with legacy purchase_orders.supplier_id (no FK — avoids errno 150 on mixed id types / engines)
            $table->unsignedInteger('supplier_id')->index();
            $table->enum('status', ['draft', 'sent', 'partially_received', 'received', 'closed'])->default('draft')->index();
            $table->date('expected_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('spare_part_purchase_order_items', function (Blueprint $table) {
            $table->id();
            // Short column name keeps FK identifier under MySQL 64-char limit
            $table->foreignId('po_id')->constrained('spare_part_purchase_orders')->cascadeOnDelete();
            $table->foreignId('part_id')->constrained('product_master')->cascadeOnDelete();
            $table->unsignedInteger('qty_ordered')->default(0);
            $table->unsignedInteger('qty_received')->default(0);
            $table->decimal('unit_cost', 12, 2)->nullable();
            $table->timestamps();

            $table->index(['po_id', 'part_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spare_part_purchase_order_items');
        Schema::dropIfExists('spare_part_purchase_orders');
    }
};
