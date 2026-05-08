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
        Schema::create('supplier_remarks', function (Blueprint $table) {
            $table->id();
            $table->string('supplier_name');
            $table->text('remark');
            $table->string('created_by')->nullable();
            $table->timestamps();
            
            $table->index('supplier_name');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_remarks');
    }
};
