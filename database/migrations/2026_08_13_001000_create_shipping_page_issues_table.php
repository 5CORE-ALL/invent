<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('shipping_page_issues')) {
            return;
        }

        Schema::create('shipping_page_issues', function (Blueprint $table) {
            $table->id();
            $table->date('o_date')->nullable()->index();
            $table->string('o_number', 100)->nullable()->index();
            $table->string('channel')->nullable()->index();
            $table->string('sku')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_page_issues');
    }
};
