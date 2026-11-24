<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ebay3_variation_type_pushes')) {
            return;
        }

        Schema::create('ebay3_variation_type_pushes', function (Blueprint $table) {
            $table->id();
            $table->string('item_id', 40)->unique();
            $table->string('parent_sku', 191)->nullable();
            $table->string('current_type', 191)->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->text('import_error')->nullable();
            $table->string('new_type', 65)->nullable();
            $table->string('push_status', 16)->nullable();
            $table->text('push_message')->nullable();
            $table->timestamp('pushed_at')->nullable();
            $table->string('pushed_by', 191)->nullable();
            $table->longText('current_values')->nullable();
            $table->longText('value_edits')->nullable();
            $table->longText('live_variations')->nullable();
            $table->string('values_status', 16)->nullable();
            $table->text('values_message')->nullable();
            $table->timestamp('values_pushed_at')->nullable();
            $table->string('values_pushed_by', 191)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ebay3_variation_type_pushes');
    }
};
