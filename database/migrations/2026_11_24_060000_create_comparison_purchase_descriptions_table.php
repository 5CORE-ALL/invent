<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('comparison_purchase_descriptions')) {
            return;
        }

        Schema::create('comparison_purchase_descriptions', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 191)->unique();
            $table->string('description', 180)->default('');
            $table->string('updated_by', 191)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comparison_purchase_descriptions');
    }
};
