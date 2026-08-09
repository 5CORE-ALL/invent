<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('badges_data_histories')) {
            return;
        }

        Schema::create('badges_data_histories', function (Blueprint $table) {
            $table->id();
            $table->string('page_name', 160);
            $table->string('field', 120);
            $table->date('snapshot_date');
            $table->double('value')->nullable();
            $table->timestamp('captured_at')->nullable();

            $table->unique(['page_name', 'field', 'snapshot_date'], 'badges_data_histories_page_field_date_uq');
            $table->index(['page_name', 'field', 'snapshot_date'], 'badges_data_histories_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('badges_data_histories');
    }
};
