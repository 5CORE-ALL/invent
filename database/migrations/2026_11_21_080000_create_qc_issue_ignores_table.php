<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('qc_issue_ignores')) {
            Schema::create('qc_issue_ignores', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('issue_id')->unique(); // qc_and_packing_issues.id, marked "Ignore - Not supplier fault"
                $table->string('ignored_by')->nullable();
                $table->unsignedBigInteger('ignored_by_user_id')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('qc_issue_ignores');
    }
};
