<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_issues_summary', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 100)->index();
            $table->unsignedBigInteger('supplier_id')->nullable()->index();
            $table->unsignedInteger('total_reviews')->default(0);
            $table->unsignedInteger('negative_reviews')->default(0);
            $table->unsignedInteger('positive_reviews')->default(0);
            $table->unsignedInteger('neutral_reviews')->default(0);
            $table->decimal('negative_rate', 5, 2)->default(0);
            $table->unsignedInteger('issue_quality')->default(0);
            $table->unsignedInteger('issue_packaging')->default(0);
            $table->unsignedInteger('issue_shipping')->default(0);
            $table->unsignedInteger('issue_service')->default(0);
            $table->unsignedInteger('issue_wrong_item')->default(0);
            $table->unsignedInteger('issue_missing_parts')->default(0);
            $table->unsignedInteger('issue_other')->default(0);
            $table->decimal('avg_rating', 3, 2)->default(0);
            $table->timestamp('updated_at')->nullable();

            $table->unique('sku');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_issues_summary');
    }
};
