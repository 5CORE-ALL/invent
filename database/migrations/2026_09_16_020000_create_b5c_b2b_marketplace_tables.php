<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('b5c_b2b_products')) {
            Schema::create('b5c_b2b_products', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('listing_id')->nullable()->index();
                $table->string('sku', 191)->index();
                $table->string('slug', 191)->nullable();
                $table->string('title', 500)->nullable();
                $table->integer('qty')->nullable();
                $table->decimal('price', 12, 2)->nullable();
                $table->decimal('special_price', 12, 2)->nullable();
                $table->boolean('in_stock')->nullable();
                $table->boolean('is_active')->nullable();
                $table->json('payload')->nullable();
                $table->timestamps();
                $table->unique('sku');
            });
        }

        if (! Schema::hasTable('b5c_b2b_orders')) {
            Schema::create('b5c_b2b_orders', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('store_order_id')->unique();
                $table->string('status', 64)->nullable()->index();
                $table->string('customer_email', 191)->nullable();
                $table->string('customer_name', 191)->nullable();
                $table->string('currency', 16)->nullable();
                $table->decimal('total', 12, 2)->nullable();
                $table->string('tracking_reference', 191)->nullable();
                $table->timestamp('ordered_at')->nullable()->index();
                $table->string('shopify_order_id', 64)->nullable()->index();
                $table->timestamp('shopify_imported_at')->nullable();
                $table->json('payload')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('channel_master')) {
            $exists = DB::table('channel_master')
                ->whereRaw('LOWER(TRIM(channel)) = ?', ['business 5 core (b2b)'])
                ->exists();
            if (! $exists) {
                $row = [
                    'id' => ((int) DB::table('channel_master')->max('id')) + 1,
                    'channel' => 'Business 5 Core (B2B)',
                    'alias' => 'B5C B2B',
                    'logo' => 'uploads/shopify.png',
                    'status' => 'Active',
                    'type' => 'API',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
                $row = array_filter(
                    $row,
                    static fn ($key) => Schema::hasColumn('channel_master', $key),
                    ARRAY_FILTER_USE_KEY
                );
                DB::table('channel_master')->insert($row);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('b5c_b2b_orders');
        Schema::dropIfExists('b5c_b2b_products');
    }
};
