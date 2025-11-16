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
        Schema::create('container_plannings', function (Blueprint $table) {
            $table->id();
            $table->string('container_number');
            $table->string('po_number'); 
            $table->unsignedBigInteger('supplier_id'); 
            $table->string('area')->nullable();
            $table->string('packing_list_link')->nullable();
            $table->decimal('invoice_value', 15, 2)->nullable();
            $table->decimal('paid', 15, 2)->nullable();
            $table->decimal('balance', 15, 2)->nullable();
            $table->enum('pay_term', ['EXW', 'FOB'])->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('container_plannings');
    }
};
