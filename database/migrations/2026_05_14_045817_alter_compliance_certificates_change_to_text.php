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
        Schema::table('compliance_certificates', function (Blueprint $table) {
            // Change boolean columns to text to store channel names
            $table->text('fcc')->nullable()->change();
            $table->text('gcc')->nullable()->change();
            $table->text('ul')->nullable()->change();
            $table->text('battery')->nullable()->change();
        });
    }


    public function down(): void
    {
        Schema::table('compliance_certificates', function (Blueprint $table) {
            // Revert back to boolean
            $table->boolean('fcc')->default(0)->change();
            $table->boolean('gcc')->default(0)->change();
            $table->boolean('ul')->default(0)->change();
            $table->boolean('battery')->default(0)->change();
        });
    }
};
