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
        if (! Schema::hasTable('product_master')) {
            return;
        }

        if (! Schema::hasColumn('product_master', 'amazon_last_sync')) {
            Schema::table('product_master', function (Blueprint $table) {
                $table->timestamp('amazon_last_sync')->nullable()->after('title60');
            });
        }
        if (! Schema::hasColumn('product_master', 'amazon_sync_status')) {
            Schema::table('product_master', function (Blueprint $table) {
                $table->string('amazon_sync_status', 50)->nullable()->after('amazon_last_sync');
            });
        }
        if (! Schema::hasColumn('product_master', 'amazon_sync_error')) {
            Schema::table('product_master', function (Blueprint $table) {
                $table->text('amazon_sync_error')->nullable()->after('amazon_sync_status');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('product_master')) {
            return;
        }

        $columns = ['amazon_last_sync', 'amazon_sync_status', 'amazon_sync_error'];
        $toDrop = array_values(array_filter($columns, fn (string $col) => Schema::hasColumn('product_master', $col)));
        if ($toDrop === []) {
            return;
        }

        Schema::table('product_master', function (Blueprint $table) use ($toDrop) {
            $table->dropColumn($toDrop);
        });
    }
};
