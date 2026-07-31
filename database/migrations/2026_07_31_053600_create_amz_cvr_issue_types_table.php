<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('amz_cvr_issue_types')) {
            return;
        }

        Schema::create('amz_cvr_issue_types', function (Blueprint $table) {
            $table->id();
            $table->string('issue_key', 100)->unique();
            $table->string('label', 255);
            $table->unsignedBigInteger('assignee_user_id')->nullable();
            $table->string('assignee_email', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('amz_cvr_issue_types');
    }
};
