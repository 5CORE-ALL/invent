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
        Schema::create('amazon_fbm_targeting_check_histories', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->index();
            $table->enum('type', ['kw', 'pt']);
            $table->string('campaign')->nullable();
            $table->string('issue')->nullable();
            $table->text('remark')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('amazon_fbm_targeting_check_histories');
    }
};
