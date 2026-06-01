<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('resource_departments')) {
            Schema::create('resource_departments', function (Blueprint $table) {
                $table->id();
                $table->string('slug', 64)->unique();
                $table->string('name', 128);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('resource_tags')) {
            Schema::create('resource_tags', function (Blueprint $table) {
                $table->id();
                $table->string('slug', 64);
                $table->string('tag_name', 128);
                $table->foreignId('department_id')->nullable()->constrained('resource_departments')->nullOnDelete();
                $table->timestamps();
                $table->unique(['slug', 'department_id']);
            });
        }

        if (! Schema::hasTable('resources_master')) {
            Schema::create('resources_master', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category', 64)->index();
            $table->string('file_type', 32)->nullable()->index();
            $table->string('mime_type', 128)->nullable();
            $table->string('file_path')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('original_filename')->nullable();
            $table->string('external_link')->nullable();
            $table->string('thumbnail_path')->nullable();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->string('status', 32)->default('active')->index();
            $table->string('version', 16)->default('1.0');
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->unsignedInteger('watch_count')->default(0);
            $table->unsignedInteger('download_count')->default(0);
            $table->json('checklist_schema')->nullable();
            $table->boolean('allow_completed_upload')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        }

        if (! Schema::hasTable('resource_department_map')) {
            Schema::create('resource_department_map', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resource_id')->constrained('resources_master')->cascadeOnDelete();
            $table->foreignId('department_id')->constrained('resource_departments')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['resource_id', 'department_id']);
        });
        }

        if (! Schema::hasTable('resource_tag_map')) {
            Schema::create('resource_tag_map', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resource_id')->constrained('resources_master')->cascadeOnDelete();
            $table->foreignId('resource_tag_id')->constrained('resource_tags')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['resource_id', 'resource_tag_id']);
        });
        }

        if (! Schema::hasTable('resource_access_logs')) {
            Schema::create('resource_access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resource_id')->constrained('resources_master')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('action', 32);
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
        }

        if (! Schema::hasTable('resource_audit_logs')) {
            Schema::create('resource_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resource_id')->nullable()->constrained('resources_master')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('action', 32);
            $table->json('meta')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
        }

        if (! Schema::hasColumn('users', 'resource_department_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('resource_department_id')->nullable()->constrained('resource_departments')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['resource_department_id']);
            $table->dropColumn('resource_department_id');
        });

        Schema::dropIfExists('resource_audit_logs');
        Schema::dropIfExists('resource_access_logs');
        Schema::dropIfExists('resource_tag_map');
        Schema::dropIfExists('resource_department_map');
        Schema::dropIfExists('resources_master');
        Schema::dropIfExists('resource_tags');
        Schema::dropIfExists('resource_departments');
    }
};
