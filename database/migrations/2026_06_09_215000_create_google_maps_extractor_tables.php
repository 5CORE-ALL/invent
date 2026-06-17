<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('google_maps_extractor_searches')) {
            Schema::create('google_maps_extractor_searches', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('query');
                $table->string('location')->nullable();
                $table->unsignedSmallInteger('result_limit')->default(10);
                $table->string('status', 30)->default('pending')->index();
                $table->unsignedInteger('results_count')->default(0);
                $table->text('error_message')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('google_maps_extractor_results')) {
            Schema::create('google_maps_extractor_results', function (Blueprint $table) {
                $table->id();
                $table->foreignId('search_id')->constrained('google_maps_extractor_searches')->cascadeOnDelete();
                $table->string('source', 50)->default('google_maps');
                $table->string('name');
                $table->string('phone')->nullable();
                $table->text('address')->nullable();
                $table->text('website')->nullable();
                $table->text('email')->nullable();
                $table->json('social_links')->nullable();
                $table->text('maps_url')->nullable();
                $table->string('category')->nullable();
                $table->decimal('rating', 4, 2)->nullable();
                $table->unsignedInteger('reviews_count')->nullable();
                $table->json('raw_payload')->nullable();
                $table->timestamps();

                $table->index(['search_id', 'source']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('google_maps_extractor_results');
        Schema::dropIfExists('google_maps_extractor_searches');
    }
};
