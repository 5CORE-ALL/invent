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
        if (Schema::hasTable('cp_histories')) {
            return;
        }

        Schema::create('cp_histories', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->index();
            $table->decimal('old_cp', 12, 2)->nullable();
            $table->decimal('new_cp', 12, 2);
            $table->boolean('is_increase')->default(false);
            $table->text('reason')->nullable(); // mandatory reason when CP increases
            $table->string('changed_by')->nullable(); // email of the user who edited
            $table->boolean('approved')->default(false);
            $table->string('approved_by')->nullable(); // approver email
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cp_histories');
    }
};
