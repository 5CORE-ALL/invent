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
        Schema::create('supplier_ledgers', function (Blueprint $table) {
            $table->id();
            $table->integer('supplier_id');
            $table->string('pm_image')->nullable();          
            $table->string('purchase_link')->nullable();
            $table->decimal('dr', 15, 2)->default(0);        
            $table->decimal('cr', 15, 2)->default(0);       
            $table->decimal('balance', 15, 2)->default(0); 
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_ledgers');
    }
};
