<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Channels marked NR on /lmp-missing-data are left out of the LMP M. total.
     */
    public function up(): void
    {
        if (Schema::hasTable('lmp_missing_channel_nr')) {
            return;
        }

        Schema::create('lmp_missing_channel_nr', function (Blueprint $table) {
            $table->id();
            $table->string('channel_key', 64)->unique();
            $table->boolean('nr')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lmp_missing_channel_nr');
    }
};
