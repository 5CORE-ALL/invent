<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Mirrors the structure of `doba_data_view` so the "Doba without ship"
     * (pickup / prepaid label) page can persist its own SPRICE / SPFT / SROI /
     * S_SELF_PICK / PUSH_STATUS values independently of the regular Doba page.
     */
    public function up(): void
    {
        if (!Schema::hasTable('doba_withoutship_data_view')) {
            Schema::create('doba_withoutship_data_view', function (Blueprint $table) {
                $table->id();
                $table->string('sku')->unique();
                $table->json('value')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('doba_withoutship_data_view');
    }
};
