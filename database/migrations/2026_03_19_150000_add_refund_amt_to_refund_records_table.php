<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = 'refund_records';

        if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'refund_amt')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($tableName) {
            if (Schema::hasColumn($tableName, 'qty')) {
                $table->decimal('refund_amt', 12, 2)->default(0)->after('qty');
            } else {
                $table->decimal('refund_amt', 12, 2)->default(0);
            }
        });
    }

    public function down(): void
    {
        $tableName = 'refund_records';

        if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'refund_amt')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) {
            $table->dropColumn('refund_amt');
        });
    }
};
