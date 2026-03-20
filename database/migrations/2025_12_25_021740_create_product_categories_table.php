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
        if (!Schema::hasTable('product_categories')) {
            Schema::create('product_categories', function (Blueprint $table) {
                $table->id();
                $table->string('category_name', 191)->unique();
                $table->string('code', 191)->nullable()->unique();
                $table->text('description')->nullable();
                $table->string('status')->default('active');
                $table->timestamps();
                $table->softDeletes();
            });
        } else {
            // Table exists, add missing columns if needed
            Schema::table('product_categories', function (Blueprint $table) {
                if (!Schema::hasColumn('product_categories', 'category_name')) {
                    $table->string('category_name', 191)->unique()->after('id');
                }
                if (!Schema::hasColumn('product_categories', 'code')) {
                    $table->string('code', 191)->nullable()->unique()->after('category_name');
                }
                if (!Schema::hasColumn('product_categories', 'description')) {
                    $table->text('description')->nullable()->after('code');
                }
                if (!Schema::hasColumn('product_categories', 'status')) {
                    $table->string('status')->default('active')->after('description');
                }
                if (!Schema::hasColumn('product_categories', 'deleted_at')) {
                    $table->softDeletes();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_categories');
    }
};
