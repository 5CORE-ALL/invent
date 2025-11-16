<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        if (!Schema::hasTable('to_order_review')) {
            Schema::create('to_order_review', function (Blueprint $table) {
                $table->id();
                $table->string('parent')->nullable();
                $table->string('sku')->nullable();
                $table->string('supplier')->nullable();
                $table->text('positive_review')->nullable();
                $table->text('negative_review')->nullable();
                $table->text('improvement')->nullable();
                $table->date('date_updated')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('to_order_review');
    }
};
