<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('shipping_report_issues')) {
            return;
        }

        Schema::create('shipping_report_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipping_report_line_id')->constrained('shipping_report_lines')->cascadeOnDelete();
            $table->string('order_number', 191);
            $table->string('sku', 191);
            $table->text('reason')->nullable();
            $table->timestamps();
        });

        Schema::table('shipping_followups', function (Blueprint $table) {
            $table->foreignId('shipping_report_issue_id')
                ->nullable()
                ->after('shipping_report_line_id')
                ->constrained('shipping_report_issues')
                ->nullOnDelete();
        });

        Schema::table('shipping_followup_archives', function (Blueprint $table) {
            $table->unsignedBigInteger('shipping_report_issue_id')->nullable();
        });

        $lines = DB::table('shipping_report_lines')
            ->where('is_cleared', 0)
            ->where(function ($q) {
                $q->whereNotNull('order_number')->orWhereNotNull('sku')->orWhereNotNull('reason');
            })
            ->get();

        foreach ($lines as $line) {
            $issueId = DB::table('shipping_report_issues')->insertGetId([
                'shipping_report_line_id' => $line->id,
                'order_number' => $line->order_number ?? '',
                'sku' => $line->sku ?? '',
                'reason' => $line->reason,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('shipping_followups')
                ->where('shipping_report_line_id', $line->id)
                ->whereNull('shipping_report_issue_id')
                ->update(['shipping_report_issue_id' => $issueId]);

            DB::table('shipping_report_lines')->where('id', $line->id)->update([
                'order_number' => null,
                'sku' => null,
                'reason' => null,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('shipping_followup_archives', function (Blueprint $table) {
            $table->dropColumn('shipping_report_issue_id');
        });

        Schema::table('shipping_followups', function (Blueprint $table) {
            $table->dropForeign(['shipping_report_issue_id']);
            $table->dropColumn('shipping_report_issue_id');
        });

        Schema::dropIfExists('shipping_report_issues');
    }
};
