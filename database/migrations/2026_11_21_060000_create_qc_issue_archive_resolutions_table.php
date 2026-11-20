<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('qc_issue_archive_resolutions')) {
            Schema::create('qc_issue_archive_resolutions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('issue_id')->index(); // qc_and_packing_issues.id
                $table->boolean('corrective_action_taken');
                $table->text('details'); // results obtained (Yes) or reason for archiving (No)
                $table->string('archived_by')->nullable();
                $table->unsignedBigInteger('archived_by_user_id')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('qc_issue_archive_resolutions');
    }
};
