<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('purchase_page_info_notes')) {
            return;
        }

        Schema::create('purchase_page_info_notes', function (Blueprint $table) {
            $table->id();
            $table->string('page_key', 64)->unique();
            $table->longText('html_content')->nullable();
            $table->string('updated_by', 191)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_page_info_notes');
    }
};
