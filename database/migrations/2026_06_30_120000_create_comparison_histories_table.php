<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('comparison_histories')) {
            return;
        }

        Schema::create('comparison_histories', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->index();
            $table->string('parent')->nullable()->index();
            $table->string('field', 64);
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->text('changes')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamp('updated_at')->useCurrent();
            $table->index(['sku', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comparison_histories');
    }
};
