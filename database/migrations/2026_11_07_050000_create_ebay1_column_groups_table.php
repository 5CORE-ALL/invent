<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ebay1 Data: named headers (Basics, Common, Others, ...) that group columns for showing and hiding.
     * A column may sit in several headers.
     */
    public function up(): void
    {
        if (Schema::hasTable('ebay1_column_groups')) {
            return;
        }

        Schema::create('ebay1_column_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->longText('columns')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedBigInteger('saved_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ebay1_column_groups');
    }
};
