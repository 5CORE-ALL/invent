<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_incentives') || Schema::hasColumn('user_incentives', 'additional_condition')) {
            return;
        }

        Schema::table('user_incentives', function (Blueprint $table) {
            $table->text('additional_condition')->nullable()->after('body');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('user_incentives') || ! Schema::hasColumn('user_incentives', 'additional_condition')) {
            return;
        }

        Schema::table('user_incentives', function (Blueprint $table) {
            $table->dropColumn('additional_condition');
        });
    }
};
