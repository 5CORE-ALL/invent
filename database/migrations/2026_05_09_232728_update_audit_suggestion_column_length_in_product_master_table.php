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
        Schema::table('product_master', function (Blueprint $table) {
            // Check if column exists before modifying
            if (Schema::hasColumn('product_master', 'audit_suggestion')) {
                $table->text('audit_suggestion')->nullable()->change();
            } else {
                // If column doesn't exist, create it as text
                $table->text('audit_suggestion')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_master', function (Blueprint $table) {
            // Revert back to varchar(100) if needed
            if (Schema::hasColumn('product_master', 'audit_suggestion')) {
                $table->string('audit_suggestion', 100)->nullable()->change();
            }
        });
    }
};
