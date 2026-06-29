<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('comparison_data')) {
            return;
        }

        Schema::create('comparison_data', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique();
            $table->string('parent')->nullable();
            $table->json('sheet_data');
            $table->text('google_sheet_url')->nullable();
            $table->string('google_sheet_tab')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comparison_data');
    }
};
