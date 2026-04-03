<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * AliExpress LMP sheet — same shape as temu_lmp (SKU, LMP, LMP Link, LMP 2, LMP Link 2, optional lmp_entries JSON).
     */
    public function up(): void
    {
        Schema::create('aliexpress_lmp_data_sheet', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->index();
            $table->decimal('lmp', 12, 2)->nullable();
            $table->text('lmp_link')->nullable();
            $table->decimal('lmp_2', 12, 2)->nullable();
            $table->text('lmp_link_2')->nullable();
            $table->json('lmp_entries')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aliexpress_lmp_data_sheet');
    }
};
