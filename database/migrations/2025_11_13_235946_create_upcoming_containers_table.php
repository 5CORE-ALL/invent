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
        Schema::create('upcoming_containers', function (Blueprint $table) {
            $table->id();
            
            $table->unsignedBigInteger('supplier_id')->nullable(); 
            
            $table->string('container_number')->nullable();
            $table->string('area')->nullable();
            $table->string('order_link')->nullable();
            $table->decimal('invoice_value', 15, 2)->nullable();
            $table->decimal('paid', 15, 2)->nullable();
            $table->decimal('balance', 15, 2)->nullable();
            $table->string('payment_terms')->nullable();

            $table->softDeletes(); 
            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('upcoming_containers');
    }
};
