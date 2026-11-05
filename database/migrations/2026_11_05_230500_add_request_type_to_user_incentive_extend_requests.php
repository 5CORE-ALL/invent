<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_incentive_extend_requests')) {
            return;
        }

        if (! Schema::hasColumn('user_incentive_extend_requests', 'request_type')) {
            Schema::table('user_incentive_extend_requests', function (Blueprint $table) {
                $table->string('request_type', 20)->default('extend')->after('current_cutoff');
            });
        }

        DB::statement('ALTER TABLE `user_incentive_extend_requests` MODIFY `requested_cutoff` DATE NULL');
    }

    public function down(): void
    {
        if (! Schema::hasTable('user_incentive_extend_requests')) {
            return;
        }

        DB::statement("UPDATE `user_incentive_extend_requests` SET `requested_cutoff` = CURDATE() WHERE `requested_cutoff` IS NULL");
        DB::statement('ALTER TABLE `user_incentive_extend_requests` MODIFY `requested_cutoff` DATE NOT NULL');

        if (Schema::hasColumn('user_incentive_extend_requests', 'request_type')) {
            Schema::table('user_incentive_extend_requests', function (Blueprint $table) {
                $table->dropColumn('request_type');
            });
        }
    }
};
