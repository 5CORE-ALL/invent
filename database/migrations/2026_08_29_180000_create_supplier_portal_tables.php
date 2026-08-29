<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('supplier_portal_settings')) {
            Schema::create('supplier_portal_settings', function (Blueprint $table) {
                $table->id();
                $table->string('company_name')->default('5 Core');
                $table->string('hero_title')->default('Welcome to 5 Core Supplier Portal');
                $table->text('hero_subtitle')->nullable();
                $table->string('hero_image_path')->nullable();
                $table->text('announcement')->nullable();
                $table->string('contact_email')->nullable();
                $table->string('footer_tagline')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('supplier_portal_assets')) {
            Schema::create('supplier_portal_assets', function (Blueprint $table) {
                $table->id();
                $table->string('category', 40);
                $table->string('title');
                $table->string('file_name');
                $table->string('file_path');
                $table->string('mime', 120)->nullable();
                $table->unsignedBigInteger('file_size')->default(0);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
                $table->index(['category', 'sort_order']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_portal_assets');
        Schema::dropIfExists('supplier_portal_settings');
    }
};
