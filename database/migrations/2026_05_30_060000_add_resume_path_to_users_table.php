<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'resume_path')) {
                $table->string('resume_path')->nullable()->after('designation');
            }
            if (! Schema::hasColumn('users', 'resume_original_name')) {
                $table->string('resume_original_name')->nullable()->after('resume_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'resume_original_name')) {
                $table->dropColumn('resume_original_name');
            }
            if (Schema::hasColumn('users', 'resume_path')) {
                $table->dropColumn('resume_path');
            }
        });
    }
};
