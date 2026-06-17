<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('customer_care_issue_dropdown_options')) {
            return;
        }

        Schema::create('customer_care_issue_dropdown_options', function (Blueprint $table) {
            $table->id();
            $table->string('module_key', 80)->index();
            $table->string('field_type', 50)->index();
            $table->string('option_value', 255);
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->timestamps();
            $table->unique(['module_key', 'field_type', 'option_value'], 'cc_issue_dropdown_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_care_issue_dropdown_options');
    }
};
