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
        if (! Schema::hasTable('channel_master')) {
            return;
        }

        if (! Schema::hasColumn('channel_master', 'base')) {
            Schema::table('channel_master', function (Blueprint $table) {
                $table->decimal('base', 10, 2)->nullable()->default(0)->after('channel_percentage');
            });
        }
        if (! Schema::hasColumn('channel_master', 'target')) {
            Schema::table('channel_master', function (Blueprint $table) {
                $table->decimal('target', 10, 2)->nullable()->default(0)->after('base');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('channel_master')) {
            return;
        }

        $columns = ['base', 'target'];
        $toDrop = array_values(array_filter($columns, fn (string $col) => Schema::hasColumn('channel_master', $col)));
        if ($toDrop === []) {
            return;
        }

        Schema::table('channel_master', function (Blueprint $table) use ($toDrop) {
            $table->dropColumn($toDrop);
        });
    }
};
