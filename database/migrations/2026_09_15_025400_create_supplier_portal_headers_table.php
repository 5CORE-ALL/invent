<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('supplier_portal_headers')) {
            return;
        }

        Schema::create('supplier_portal_headers', function (Blueprint $table) {
            $table->id();
            $table->string('category', 40);
            $table->string('title', 200);
            $table->text('instructions')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['category', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_portal_headers');
    }
};
