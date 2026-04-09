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
        if (Schema::hasTable('temu_lmp')) {
            return;
        }

        Schema::create('temu_lmp', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->index();
            $table->decimal('lmp', 12, 2)->nullable();
            $table->text('lmp_link')->nullable();
            $table->decimal('lmp_2', 12, 2)->nullable();
            $table->text('lmp_link_2')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('temu_lmp');
    }
};
