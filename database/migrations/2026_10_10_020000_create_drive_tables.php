<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drive_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('owner_id');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('type', 10); // folder | file
            $table->string('name', 255);
            $table->string('extension', 20)->nullable();
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('disk', 30)->nullable();
            $table->string('path', 500)->nullable();
            $table->string('color', 20)->nullable();
            $table->text('description')->nullable();
            $table->string('share_token', 64)->nullable()->unique();
            $table->string('link_access', 10)->default('none'); // none | view
            $table->timestamp('trashed_at')->nullable();
            $table->unsignedBigInteger('trashed_root_id')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index(['owner_id', 'parent_id', 'trashed_at']);
            $table->index('parent_id');
            $table->index('trashed_root_id');
        });

        Schema::create('drive_shares', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('item_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('email', 191);
            $table->string('role', 10)->default('viewer'); // viewer | editor
            $table->unsignedBigInteger('shared_by');
            $table->timestamps();

            $table->unique(['item_id', 'email']);
            $table->index('user_id');
            $table->index('email');
        });

        Schema::create('drive_stars', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('item_id');
            $table->timestamp('created_at')->nullable();

            $table->primary(['user_id', 'item_id']);
        });

        Schema::create('drive_item_versions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('item_id');
            $table->string('name', 255);
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('disk', 30);
            $table->string('path', 500);
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamps();

            $table->index('item_id');
        });

        Schema::create('drive_activities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('item_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('action', 40);
            $table->string('details', 500)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['item_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drive_activities');
        Schema::dropIfExists('drive_item_versions');
        Schema::dropIfExists('drive_stars');
        Schema::dropIfExists('drive_shares');
        Schema::dropIfExists('drive_items');
    }
};
