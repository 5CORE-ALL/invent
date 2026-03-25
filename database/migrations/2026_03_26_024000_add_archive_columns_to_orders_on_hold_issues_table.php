<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders_on_hold_issues', function (Blueprint $table) {
            $table->boolean('is_archived')->default(false)->after('created_by_user_id');
            $table->timestamp('archived_at')->nullable()->after('is_archived');
            $table->string('archived_by')->nullable()->after('archived_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders_on_hold_issues', function (Blueprint $table) {
            $table->dropColumn(['is_archived', 'archived_at', 'archived_by']);
        });
    }
};
