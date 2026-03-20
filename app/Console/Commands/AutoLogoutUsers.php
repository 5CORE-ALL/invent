<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AutoLogoutUsers extends Command
{
    protected $signature = 'users:auto-logout';
    protected $description = 'Automatically logout all users by setting logined = 0';

    public function handle()
    {
        $this->info('Starting auto logout process...');
        
        try {
            // Check if logined column exists
            if (!DB::getSchemaBuilder()->hasColumn('users', 'logined')) {
                $this->error('Error: "logined" column does not exist in users table.');
                $this->info('Please run: php artisan migrate to add the column.');
                return 1;
            }
            
            // Set logined = 0 for all users who are currently logged in (logined = 1)
            $loggedOutCount = DB::table('users')
                ->where('logined', 1)
                ->update(['logined' => 0]);
            
            // Clear all session files to force logout
            $sessionPath = storage_path('framework/sessions');
            $sessionsCleared = 0;
            
            if (File::exists($sessionPath)) {
                $files = File::files($sessionPath);
                $sessionsCleared = count($files);
                
                foreach ($files as $file) {
                    File::delete($file);
                }
                
                $this->info("Cleared {$sessionsCleared} session file(s).");
            }
            
            // Log the action
            Log::info('Auto logout executed', [
                'users_logged_out' => $loggedOutCount,
                'sessions_cleared' => $sessionsCleared,
                'timestamp' => Carbon::now()->toDateTimeString()
            ]);
            
            $this->info("✓ Successfully logged out {$loggedOutCount} user(s).");
            
            return 0;
        } catch (\Exception $e) {
            $this->error('Failed to logout users: ' . $e->getMessage());
            Log::error('Auto logout failed', [
                'error' => $e->getMessage(),
                'timestamp' => Carbon::now()->toDateTimeString()
            ]);
            
            return 1;
        }
    }
}
