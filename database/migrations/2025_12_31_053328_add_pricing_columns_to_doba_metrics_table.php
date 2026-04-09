<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('doba_metrics')) {
            return;
        }

        if (! Schema::hasColumn('doba_metrics', 'self_pick_price')) {
            Schema::table('doba_metrics', function (Blueprint $table) {
                $table->decimal('self_pick_price', 10, 2)->nullable()->after('anticipated_income');
            });
        }
        if (! Schema::hasColumn('doba_metrics', 'msrp')) {
            Schema::table('doba_metrics', function (Blueprint $table) {
                $table->decimal('msrp', 10, 2)->nullable()->after('self_pick_price');
            });
        }
        if (! Schema::hasColumn('doba_metrics', 'map')) {
            Schema::table('doba_metrics', function (Blueprint $table) {
                $table->decimal('map', 10, 2)->nullable()->after('msrp');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('doba_metrics')) {
            return;
        }

        $columns = ['self_pick_price', 'msrp', 'map'];
        $toDrop = array_values(array_filter($columns, fn (string $col) => Schema::hasColumn('doba_metrics', $col)));
        if ($toDrop === []) {
            return;
        }

        Schema::table('doba_metrics', function (Blueprint $table) use ($toDrop) {
            $table->dropColumn($toDrop);
        });
    }
};
