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
        Schema::table('user_salaries', function (Blueprint $table) {
            $table->string('bank_1', 100)->nullable()->after('adv_inc_other');
            $table->string('bank_2', 100)->nullable()->after('bank_1');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_salaries', function (Blueprint $table) {
            $table->dropColumn(['bank_1', 'bank_2']);
        });
    }
};
