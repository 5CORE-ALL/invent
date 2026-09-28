<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('social_media_accounts')) {
            Schema::create('social_media_accounts', function (Blueprint $table) {
                $table->id();
                $table->string('platform', 32);
                $table->string('external_account_id');
                $table->string('account_name');
                $table->string('username')->nullable();
                $table->string('profile_url')->nullable();
                $table->text('access_token')->nullable();
                $table->text('refresh_token')->nullable();
                $table->timestamp('token_expires_at')->nullable();
                $table->string('status', 32)->default('connected');
                $table->boolean('sync_enabled')->default(true);
                $table->timestamp('last_synced_at')->nullable();
                $table->string('last_sync_status', 32)->nullable();
                $table->text('last_sync_error')->nullable();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->unique(['platform', 'external_account_id'], 'sm_accounts_platform_external_unique');
                $table->index(['sync_enabled', 'status']);
            });
        }

        if (! Schema::hasTable('social_media_posts')) {
            Schema::create('social_media_posts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('social_media_account_id')->constrained('social_media_accounts')->cascadeOnDelete();
                $table->string('external_post_id');
                $table->string('content_type', 32)->default('post');
                $table->text('title')->nullable();
                $table->timestamp('published_at')->nullable();
                $table->string('permalink')->nullable();
                $table->string('media_url')->nullable();
                $table->string('status', 32)->default('published');
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->unique(['social_media_account_id', 'external_post_id'], 'sm_posts_account_external_unique');
                $table->index(['published_at', 'content_type']);
            });
        }

        if (! Schema::hasTable('social_media_post_metrics')) {
            Schema::create('social_media_post_metrics', function (Blueprint $table) {
                $table->id();
                $table->foreignId('social_media_post_id')->constrained('social_media_posts')->cascadeOnDelete();
                $table->date('metric_date');
                $table->json('metrics');
                $table->json('raw_metrics')->nullable();
                $table->timestamps();
                $table->unique(['social_media_post_id', 'metric_date'], 'sm_post_metrics_unique');
            });
        }

        if (! Schema::hasTable('social_media_account_metrics')) {
            Schema::create('social_media_account_metrics', function (Blueprint $table) {
                $table->id();
                $table->foreignId('social_media_account_id')->constrained('social_media_accounts')->cascadeOnDelete();
                $table->date('metric_date');
                $table->json('metrics');
                $table->json('raw_metrics')->nullable();
                $table->timestamps();
                $table->unique(['social_media_account_id', 'metric_date'], 'sm_account_metrics_unique');
            });
        }

        if (! Schema::hasTable('social_media_normalized_metrics')) {
            Schema::create('social_media_normalized_metrics', function (Blueprint $table) {
                $table->id();
                $table->foreignId('social_media_account_id')->constrained('social_media_accounts')->cascadeOnDelete();
                $table->unsignedBigInteger('social_media_post_id')->default(0);
                $table->date('metric_date');
                $table->string('metric_name', 64);
                $table->decimal('metric_value', 20, 4)->nullable();
                $table->string('availability', 32);
                $table->timestamps();
                $table->unique(
                    ['social_media_account_id', 'social_media_post_id', 'metric_date', 'metric_name'],
                    'sm_normalized_unique'
                );
                $table->index(['metric_date', 'metric_name', 'availability'], 'sm_normalized_lookup');
            });
        }

        if (! Schema::hasTable('social_media_sync_logs')) {
            Schema::create('social_media_sync_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('social_media_account_id')->constrained('social_media_accounts')->cascadeOnDelete();
                $table->string('platform', 32);
                $table->string('sync_type', 32);
                $table->timestamp('started_at');
                $table->timestamp('completed_at')->nullable();
                $table->string('status', 32);
                $table->unsignedInteger('records_processed')->default(0);
                $table->text('error_message')->nullable();
                $table->string('reference')->nullable();
                $table->timestamps();
                $table->index(['social_media_account_id', 'started_at']);
            });
        }

        if (! Schema::hasTable('social_media_kpi_targets')) {
            Schema::create('social_media_kpi_targets', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('platform', 32)->nullable();
                $table->date('period_start');
                $table->date('period_end');
                $table->string('metric_name', 64);
                $table->decimal('target_value', 20, 4);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['period_start', 'period_end', 'metric_name'], 'sm_targets_period_metric');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('social_media_kpi_targets');
        Schema::dropIfExists('social_media_sync_logs');
        Schema::dropIfExists('social_media_normalized_metrics');
        Schema::dropIfExists('social_media_account_metrics');
        Schema::dropIfExists('social_media_post_metrics');
        Schema::dropIfExists('social_media_posts');
        Schema::dropIfExists('social_media_accounts');
    }
};
