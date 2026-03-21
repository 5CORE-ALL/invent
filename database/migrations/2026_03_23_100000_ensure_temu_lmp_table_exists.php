<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Safety migration: create temu_lmp if missing (e.g. migration never ran or DB was restored without this table).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('temu_lmp')) {
            return;
        }

        Schema::create('temu_lmp', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->index();
            $table->decimal('lmp', 12, 2)->nullable();
            $table->text('lmp_link')->nullable();
            $table->decimal('lmp_2', 12, 2)->nullable();
            $table->text('lmp_link_2')->nullable();
            $table->json('lmp_entries')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // Do not drop: table may have been created by the original migrations.
    }
};
