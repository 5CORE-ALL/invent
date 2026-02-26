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
        Schema::create('serp_api_raw_responses', function (Blueprint $table) {
            $table->id();
            $table->string('search_query')->index();
            $table->unsignedSmallInteger('page')->default(1);
            $table->string('marketplace', 50)->nullable();
            $table->json('request_params')->nullable();
            $table->unsignedSmallInteger('http_status');
            $table->longText('raw_body');
            $table->boolean('success')->default(false);
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
        Schema::dropIfExists('serp_api_raw_responses');
    }
};
