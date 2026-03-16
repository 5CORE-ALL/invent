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
        Schema::create('arrived_containers', function (Blueprint $table) {
            $table->id();

            $table->string('tab_name')->nullable();
            $table->string('supplier_name')->nullable();
            $table->string('company_name')->nullable();
            $table->string('our_sku')->nullable();
            $table->string('parent')->nullable();

            $table->integer('no_of_units')->nullable();
            $table->integer('total_ctn')->nullable();
            $table->decimal('rate', 10, 2)->nullable();
            $table->string('unit')->nullable();

            $table->string('status')->nullable();
            $table->string('changes')->nullable();

            $table->string('package_size')->nullable();
            $table->string('product_size_link')->nullable();
            $table->string('comparison_link')->nullable();
            $table->string('order_link')->nullable();
            $table->string('image_src')->nullable();
            $table->string('photos')->nullable();
            $table->longText('specification')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('arrived_containers');
    }
};
