<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['amazon_metrics', 'shopify_metrics', 'shopify_pls_metrics'] as $tableName) {
            if (! Schema::hasTable($tableName)) {
                Schema::create($tableName, function (Blueprint $table): void {
                    $table->id();
                    $table->string('sku')->nullable()->index();
                    $table->text('bullet_points')->nullable();
                    $table->text('description_master')->nullable();
                    $table->longText('image_urls')->nullable();
                    $table->longText('image_master_json')->nullable();
                    $table->timestamps();
                });

                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                if (! Schema::hasColumn($tableName, 'sku')) {
                    $table->string('sku')->nullable()->index();
                }
                if (! Schema::hasColumn($tableName, 'image_urls')) {
                    $table->longText('image_urls')->nullable();
                }
                if (! Schema::hasColumn($tableName, 'image_master_json')) {
                    $table->longText('image_master_json')->nullable();
                }
                if (! Schema::hasColumn($tableName, 'bullet_points')) {
                    $table->text('bullet_points')->nullable();
                }
                if (! Schema::hasColumn($tableName, 'description_master')) {
                    $table->text('description_master')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        // Intentionally no-op: these tables/columns are shared by multiple marketplace masters.
    }
};
