<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('badges_data')) {
            return;
        }

        Schema::create('badges_data', function (Blueprint $table) {
            $table->id();
            $table->string('page_name', 160)->unique();
            $table->json('data')->nullable();
            $table->timestamp('updated_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('badges_data');
    }
};
