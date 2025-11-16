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
        Schema::table('rfq_forms', function (Blueprint $table) {
            $table->string('dimension_inner')->after('fields');
            $table->string('product_dimension')->after('fields');
            $table->string('package_dimension')->after('fields');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rfq_forms', function (Blueprint $table) {
            //
        });
    }
};
