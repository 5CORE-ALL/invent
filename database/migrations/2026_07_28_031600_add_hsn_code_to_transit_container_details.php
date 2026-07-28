<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('transit_container_details')) {
            return;
        }

        Schema::table('transit_container_details', function (Blueprint $table) {
            if (! Schema::hasColumn('transit_container_details', 'hsn_code')) {
                $table->string('hsn_code', 32)->nullable()->after('company_name');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('transit_container_details')) {
            return;
        }

        Schema::table('transit_container_details', function (Blueprint $table) {
            if (Schema::hasColumn('transit_container_details', 'hsn_code')) {
                $table->dropColumn('hsn_code');
            }
        });
    }
};
