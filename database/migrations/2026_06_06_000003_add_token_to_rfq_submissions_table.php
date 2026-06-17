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
        Schema::table('rfq_submissions', function (Blueprint $table) {
            if (!Schema::hasColumn('rfq_submissions', 'token')) {
                $table->string('token')->nullable()->index()->after('rfq_form_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rfq_submissions', function (Blueprint $table) {
            if (Schema::hasColumn('rfq_submissions', 'token')) {
                $table->dropColumn('token');
            }
        });
    }
};
