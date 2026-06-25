<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Description Master persistence gaps: macy_metrics lacked description_master, and reverb_metrics
     * did not exist at all (so per-marketplace description saves for Macy's/Reverb failed).
     */
    public function up(): void
    {
        if (Schema::hasTable('macy_metrics') && ! Schema::hasColumn('macy_metrics', 'description_master')) {
            Schema::table('macy_metrics', function (Blueprint $table) {
                $table->text('description_master')->nullable();
            });
        }

        if (! Schema::hasTable('reverb_metrics')) {
            Schema::create('reverb_metrics', function (Blueprint $table) {
                $table->id();
                $table->string('sku')->index();
                $table->text('bullet_points')->nullable();
                $table->text('description_master')->nullable();
                $table->timestamps();
            });
        } else {
            if (! Schema::hasColumn('reverb_metrics', 'description_master')) {
                Schema::table('reverb_metrics', function (Blueprint $table) {
                    $table->text('description_master')->nullable();
                });
            }
        }
    }

    public function down(): void
    {
        // Non-destructive: leave columns/table in place.
    }
};
