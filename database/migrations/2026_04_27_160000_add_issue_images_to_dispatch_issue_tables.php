<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['dispatch_issue_issues', 'dispatch_issue_issue_histories'] as $tbl) {
            if (! Schema::hasTable($tbl)) {
                continue;
            }
            Schema::table($tbl, function (Blueprint $table) use ($tbl) {
                if (! Schema::hasColumn($tbl, 'image_1_path')) {
                    $table->string('image_1_path', 512)->nullable();
                }
                if (! Schema::hasColumn($tbl, 'image_2_path')) {
                    $table->string('image_2_path', 512)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['dispatch_issue_issues', 'dispatch_issue_issue_histories'] as $tbl) {
            if (! Schema::hasTable($tbl)) {
                continue;
            }
            Schema::table($tbl, function (Blueprint $table) use ($tbl) {
                $drop = [];
                if (Schema::hasColumn($tbl, 'image_1_path')) {
                    $drop[] = 'image_1_path';
                }
                if (Schema::hasColumn($tbl, 'image_2_path')) {
                    $drop[] = 'image_2_path';
                }
                if ($drop !== []) {
                    $table->dropColumn($drop);
                }
            });
        }
    }
};
