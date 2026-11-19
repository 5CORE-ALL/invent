<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ebay2_variation_type_pushes')) {
            return;
        }

        Schema::create('ebay2_variation_type_pushes', function (Blueprint $table) {
            $table->id();
            $table->string('item_id', 40)->unique();
            $table->string('parent_sku', 191)->nullable();
            // Variation aspect name(s) read from eBay with GetItem, e.g. "Color" or "Color / Size".
            $table->string('current_type', 191)->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->text('import_error')->nullable();
            // What the user typed in the New Type cell (kept so an edit survives a reload).
            $table->string('new_type', 65)->nullable();
            // Last push result: success | failed.
            $table->string('push_status', 16)->nullable();
            $table->text('push_message')->nullable();
            $table->timestamp('pushed_at')->nullable();
            $table->string('pushed_by', 191)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ebay2_variation_type_pushes');
    }
};
