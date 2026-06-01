<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mfrg_progress_po')) {
            return;
        }

        Schema::create('mfrg_progress_po', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('mfrg_progress_id')->unique();
            $table->string('sku', 128)->nullable()->index();
            $table->string('po_number', 100)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mfrg_progress_po');
    }
};
