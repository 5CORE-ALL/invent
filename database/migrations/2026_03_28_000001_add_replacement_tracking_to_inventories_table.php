<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('inventories') || Schema::hasColumn('inventories', 'replacement_tracking')) {
            return;
        }

        Schema::table('inventories', function (Blueprint $table) {
            if (Schema::hasColumn('inventories', 'comment')) {
                $table->string('replacement_tracking', 22)->nullable()->after('comment');
            } else {
                $table->string('replacement_tracking', 22)->nullable();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('inventories') || !Schema::hasColumn('inventories', 'replacement_tracking')) {
            return;
        }

        Schema::table('inventories', function (Blueprint $table) {
            $table->dropColumn('replacement_tracking');
        });
    }
};
