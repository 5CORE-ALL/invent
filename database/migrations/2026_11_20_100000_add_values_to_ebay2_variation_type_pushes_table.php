<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ebay2_variation_type_pushes') || Schema::hasColumn('ebay2_variation_type_pushes', 'current_values')) {
            return;
        }

        Schema::table('ebay2_variation_type_pushes', function (Blueprint $table) {
            // JSON {"Color": ["Black", "Red"]}: the values eBay lists under each variation aspect (GetItem).
            $table->longText('current_values')->nullable();
            // JSON [{"aspect": "Color", "old": "Black", "new": "Jet Black"}]: edits waiting to be pushed.
            $table->longText('value_edits')->nullable();
            // Last value push result: success | failed.
            $table->string('values_status', 16)->nullable();
            $table->text('values_message')->nullable();
            $table->timestamp('values_pushed_at')->nullable();
            $table->string('values_pushed_by', 191)->nullable();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('ebay2_variation_type_pushes')) {
            return;
        }
        Schema::table('ebay2_variation_type_pushes', function (Blueprint $table) {
            foreach (['current_values', 'value_edits', 'values_status', 'values_message', 'values_pushed_at', 'values_pushed_by'] as $col) {
                if (Schema::hasColumn('ebay2_variation_type_pushes', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
