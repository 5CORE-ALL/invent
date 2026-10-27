<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('meeting_participants') && ! Schema::hasColumn('meeting_participants', 'rsvp')) {
            Schema::table('meeting_participants', function (Blueprint $table) {
                // pending | accepted | declined  (invitee's answer to a meeting request)
                $table->string('rsvp', 12)->default('pending')->after('is_invited');
                $table->dateTime('rsvp_at')->nullable()->after('rsvp');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('meeting_participants', 'rsvp')) {
            Schema::table('meeting_participants', function (Blueprint $table) {
                $table->dropColumn(['rsvp', 'rsvp_at']);
            });
        }
    }
};
