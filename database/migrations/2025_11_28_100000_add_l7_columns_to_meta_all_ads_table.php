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
        if (! Schema::hasTable('meta_all_ads')) {
            return;
        }

        if (! Schema::hasColumn('meta_all_ads', 'imp_l7')) {
            Schema::table('meta_all_ads', function (Blueprint $table) {
                $table->bigInteger('imp_l7')->default(0)->after('imp_l30');
            });
        }
        if (! Schema::hasColumn('meta_all_ads', 'spent_l7')) {
            Schema::table('meta_all_ads', function (Blueprint $table) {
                $table->decimal('spent_l7', 15, 2)->default(0)->after('spent_l30');
            });
        }
        if (! Schema::hasColumn('meta_all_ads', 'clicks_l7')) {
            Schema::table('meta_all_ads', function (Blueprint $table) {
                $table->integer('clicks_l7')->default(0)->after('clicks_l30');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('meta_all_ads')) {
            return;
        }

        $columns = ['imp_l7', 'spent_l7', 'clicks_l7'];
        $toDrop = array_values(array_filter($columns, fn (string $col) => Schema::hasColumn('meta_all_ads', $col)));
        if ($toDrop === []) {
            return;
        }

        Schema::table('meta_all_ads', function (Blueprint $table) use ($toDrop) {
            $table->dropColumn($toDrop);
        });
    }
};
