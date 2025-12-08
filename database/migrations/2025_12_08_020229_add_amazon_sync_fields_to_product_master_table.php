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
        Schema::table('product_master', function (Blueprint $table) {
            $table->timestamp('amazon_last_sync')->nullable()->after('title60');
            $table->string('amazon_sync_status', 50)->nullable()->after('amazon_last_sync');
            $table->text('amazon_sync_error')->nullable()->after('amazon_sync_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_master', function (Blueprint $table) {
            $table->dropColumn(['amazon_last_sync', 'amazon_sync_status', 'amazon_sync_error']);
        });
    }
};
