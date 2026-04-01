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
        $pairs = [
            'dispatch_issue_issues',
            'dispatch_issue_issue_histories',
        ];

        foreach ($pairs as $tbl) {
            Schema::table($tbl, function (Blueprint $table) use ($tbl) {
                if (!Schema::hasColumn($tbl, 'order_number')) {
                    $table->string('order_number')->nullable()->after('sku');
                }
                if (!Schema::hasColumn($tbl, 'refund_amount')) {
                    $table->decimal('refund_amount', 10, 2)->nullable()->after('order_number');
                }
                if (!Schema::hasColumn($tbl, 'total_loss')) {
                    $table->decimal('total_loss', 10, 2)->nullable()->after('refund_amount');
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['dispatch_issue_issues', 'dispatch_issue_issue_histories'] as $tbl) {
            Schema::table($tbl, function (Blueprint $table) use ($tbl) {
                $cols = array_filter(['order_number', 'refund_amount', 'total_loss'],
                    fn($c) => Schema::hasColumn($tbl, $c));
                if ($cols) $table->dropColumn(array_values($cols));
            });
        }
    }
};
