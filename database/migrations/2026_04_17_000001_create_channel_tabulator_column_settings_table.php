<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Shared Tabulator column show/hide state per page (channel), JSON visibility map.
     */
    public function up(): void
    {
        if (Schema::hasTable('channel_tabulator_column_settings')) {
            return;
        }

        Schema::create('channel_tabulator_column_settings', function (Blueprint $table) {
            $table->id();
            $table->string('channel_name', 160)->unique();
            $table->json('visibility')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_tabulator_column_settings');
    }
};
