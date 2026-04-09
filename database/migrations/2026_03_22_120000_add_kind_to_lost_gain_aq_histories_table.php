<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = 'lost_gain_aq_histories';

        if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, 'kind')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($tableName) {
            if (Schema::hasColumn($tableName, 'batch_uuid')) {
                $table->string('kind', 8)->default('aq')->after('batch_uuid')->index();
            } else {
                $table->string('kind', 8)->default('aq')->index();
            }
        });
    }

    public function down(): void
    {
        $tableName = 'lost_gain_aq_histories';

        if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'kind')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) {
            $table->dropColumn('kind');
        });
    }
};
