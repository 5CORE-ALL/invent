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
        if (Schema::hasTable('amazon_search_raw_responses')) {
            return;
        }
        Schema::create('amazon_search_raw_responses', function (Blueprint $table) {
            $table->id();
            $table->string('search_query')->index();
            $table->string('marketplace', 50)->nullable();
            $table->unsignedSmallInteger('page')->nullable();
            $table->longText('raw_response');
            $table->unsignedSmallInteger('pages_count')->nullable();
            $table->timestamps();

            $table->index(['search_query', 'page']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('amazon_search_raw_responses');
    }
};
