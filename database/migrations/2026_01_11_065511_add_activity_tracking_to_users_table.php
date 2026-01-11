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
        Schema::table('users', function (Blueprint $table) {
            // Track last activity for auto-logout after 6 hours
            $table->timestamp('last_activity_at')->nullable()->after('updated_at');
            
            // Flag to require only Google login (0 = normal login, 1 = Google only)
            $table->tinyInteger('require_google_login')->default(0)->after('last_activity_at');
            
            // When was user auto-logged out
            $table->timestamp('auto_logged_out_at')->nullable()->after('require_google_login');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['last_activity_at', 'require_google_login', 'auto_logged_out_at']);
        });
    }
};
