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

        if (! Schema::hasColumn('meta_all_ads', 'l_page')) {
            Schema::table('meta_all_ads', function (Blueprint $table) {
                $table->string('l_page', 255)->nullable()->after('group_id');
            });
        }
        if (! Schema::hasColumn('meta_all_ads', 'purpose')) {
            Schema::table('meta_all_ads', function (Blueprint $table) {
                $table->string('purpose', 255)->nullable()->after('l_page');
            });
        }
        if (! Schema::hasColumn('meta_all_ads', 'audience')) {
            Schema::table('meta_all_ads', function (Blueprint $table) {
                $table->string('audience', 255)->nullable()->after('purpose');
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

        $columns = ['l_page', 'purpose', 'audience'];
        $toDrop = array_values(array_filter($columns, fn (string $col) => Schema::hasColumn('meta_all_ads', $col)));
        if ($toDrop === []) {
            return;
        }

        Schema::table('meta_all_ads', function (Blueprint $table) use ($toDrop) {
            $table->dropColumn($toDrop);
        });
    }
};
