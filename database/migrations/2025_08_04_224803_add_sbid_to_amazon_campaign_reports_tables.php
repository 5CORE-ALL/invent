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
        foreach (['amazon_sb_campaign_reports', 'amazon_sd_campaign_reports', 'amazon_sp_campaign_reports'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                if (! Schema::hasColumn($tableName, 'note')) {
                    $table->text('note')->nullable()->after('id');
                }
                if (! Schema::hasColumn($tableName, 'sbid')) {
                    $table->string('sbid')->nullable()->after('note');
                }
                if (! Schema::hasColumn($tableName, 'yes_sbid')) {
                    $table->string('yes_sbid')->nullable()->after('sbid');
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['amazon_sb_campaign_reports', 'amazon_sd_campaign_reports', 'amazon_sp_campaign_reports'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            $cols = array_values(array_filter(
                ['note', 'sbid', 'yes_sbid'],
                fn (string $col): bool => Schema::hasColumn($tableName, $col)
            ));

            if ($cols === []) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($cols): void {
                $table->dropColumn($cols);
            });
        }
    }
};
