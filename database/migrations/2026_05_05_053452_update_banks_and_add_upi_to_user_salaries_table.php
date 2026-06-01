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
            // Change bank columns to TEXT to accept any length
            $table->text('bank_1')->nullable()->change();
            $table->text('bank_2')->nullable()->change();
            
            // Add UPI ID column after bank_2
            $table->text('upi_id')->nullable()->after('bank_2');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_salaries', function (Blueprint $table) {
            // Revert bank columns back to string(100)
            $table->string('bank_1', 100)->nullable()->change();
            $table->string('bank_2', 100)->nullable()->change();
            
            // Drop UPI ID column
            $table->dropColumn('upi_id');
        });
    }
};
