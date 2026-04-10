<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('to_order_review') && !Schema::hasColumn('to_order_review', 'reviews_note')) {
            Schema::table('to_order_review', function (Blueprint $table) {
                $table->text('reviews_note')->nullable()->after('supplier');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('to_order_review') && Schema::hasColumn('to_order_review', 'reviews_note')) {
            Schema::table('to_order_review', function (Blueprint $table) {
                $table->dropColumn('reviews_note');
            });
        }
    }
};
