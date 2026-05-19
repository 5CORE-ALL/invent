<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('shipping_scope_links')) {
            return;
        }

        Schema::create('shipping_scope_links', function (Blueprint $table) {
            $table->id();
            $table->string('definition_scope', 96)->unique();
            $table->string('m_link', 2048)->nullable();
            $table->string('h_link', 2048)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipping_scope_links');
    }
};
