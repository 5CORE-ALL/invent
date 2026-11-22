<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tables of the Amz Data page: a copy of each Ebay1 Data table (same columns and indexes), starting empty,
     * and the list of fields Amazon defines for each product type.
     * The page also creates them the first time it is opened, so deploying without migrate works too.
     */
    private const TABLES = [
        'item_specifics', 'extra_columns', 'column_sources', 'column_groups',
        'settings', 'pending_specifics', 'cell_styles', 'missing_history',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $name) {
            $from = 'ebay1_'.$name;
            $to = 'amz1_'.$name;
            if (Schema::hasTable($from) && ! Schema::hasTable($to)) {
                DB::statement('CREATE TABLE `'.$to.'` LIKE `'.$from.'`');
            }
        }
        if (! Schema::hasTable('amz1_type_columns')) {
            Schema::create('amz1_type_columns', function (Blueprint $table) {
                $table->id();
                $table->string('product_type', 120)->unique();
                $table->longText('columns')->nullable();
                $table->string('error', 500)->nullable();
                $table->timestamp('fetched_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $name) {
            Schema::dropIfExists('amz1_'.$name);
        }
        Schema::dropIfExists('amz1_type_columns');
    }
};
