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
        if (! Schema::hasTable('container_plannings') || Schema::hasColumn('container_plannings', 'currency')) {
            return;
        }

        $after = Schema::hasColumn('container_plannings', 'packing_list_link');

        Schema::table('container_plannings', function (Blueprint $table) use ($after): void {
            if ($after) {
                $table->string('currency', 10)->default('USD')->after('packing_list_link');
            } else {
                $table->string('currency', 10)->default('USD');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('container_plannings') || ! Schema::hasColumn('container_plannings', 'currency')) {
            return;
        }

        Schema::table('container_plannings', function (Blueprint $table) {
            $table->dropColumn('currency');
        });
    }
};
