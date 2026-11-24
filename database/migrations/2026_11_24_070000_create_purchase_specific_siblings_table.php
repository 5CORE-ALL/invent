<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('purchase_specific_siblings')) {
            return;
        }

        Schema::create('purchase_specific_siblings', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 191)->unique();
            $table->string('name', 120)->index();
            $table->string('updated_by', 191)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_specific_siblings');
    }
};
