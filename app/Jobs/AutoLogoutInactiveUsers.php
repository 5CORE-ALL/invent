<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AutoLogoutInactiveUsers implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     * Mark inactive users (6 hours+) to require Google login
     */
    public function handle(): void
    {
        try {
            // Get timestamp for 6 hours ago
            $sixHoursAgo = Carbon::now()->subHours(6);
            
            // Update users who are inactive for more than 6 hours
            $affectedRows = DB::table('users')
                ->where('last_activity_at', '<', $sixHoursAgo)
                ->where('require_google_login', 0)
                ->whereNotNull('last_activity_at')
                ->update([
                    'require_google_login' => 1,
                    'auto_logged_out_at' => Carbon::now(),
                ]);
            
            if ($affectedRows > 0) {
                Log::info("Auto logout job: {$affectedRows} users marked for Google login due to inactivity");
            }
        } catch (\Exception $e) {
            Log::error('Auto logout job failed: ' . $e->getMessage());
        }
    }
}

