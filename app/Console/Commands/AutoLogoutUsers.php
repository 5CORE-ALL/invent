<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class AutoLogoutUsers extends Command
{
    protected $signature = 'users:auto-logout';
    protected $description = 'Automatically logout all users by clearing their sessions and tokens';

    public function handle()
    {
        $this->info('Starting auto logout process...');
        
        try {
            $totalCleared = 0;
            
            // Method 1: Clear session files
            $sessionsCleared = $this->clearSessionFiles();
            $totalCleared += $sessionsCleared;
            
            // Method 2: Clear database sessions if table exists
            $dbSessionsCleared = $this->clearDatabaseSessions();
            $totalCleared += $dbSessionsCleared;
            
            // Method 3: Clear remember_me tokens
            $tokensCleared = $this->clearRememberTokens();
            $totalCleared += $tokensCleared;
            
            // Method 4: Clear API tokens if table exists
            $apiTokensCleared = $this->clearApiTokens();
            $totalCleared += $apiTokensCleared;
            
            // Method 5: Clear application cache
            $this->info('Clearing application cache...');
            Artisan::call('cache:clear');
            
            // Method 6: Clear auth cache
            try {
                Artisan::call('auth:clear-resets');
                $this->info('Cleared password reset tokens.');
            } catch (\Exception $e) {
                // Command might not exist in older Laravel versions
            }
            
            // Log the action
            Log::info('Auto logout executed', [
                'sessions_cleared' => $sessionsCleared,
                'db_sessions_cleared' => $dbSessionsCleared,
                'remember_tokens_cleared' => $tokensCleared,
                'api_tokens_cleared' => $apiTokensCleared,
                'total_cleared' => $totalCleared,
                'timestamp' => Carbon::now()->toDateTimeString()
            ]);
            
            $this->info("✓ Successfully logged out all users. Total items cleared: {$totalCleared}");
            
            return 0;
        } catch (\Exception $e) {
            $this->error('Failed to logout users: ' . $e->getMessage());
            Log::error('Auto logout failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'timestamp' => Carbon::now()->toDateTimeString()
            ]);
            
            return 1;
        }
    }
    
    /**
     * Clear file-based sessions
     */
    private function clearSessionFiles()
    {
        $cleared = 0;
        
        try {
            $sessionPath = storage_path('framework/sessions');
            
            if (File::exists($sessionPath)) {
                $files = File::files($sessionPath);
                $cleared = count($files);
                
                foreach ($files as $file) {
                    File::delete($file);
                }
                
                $this->info("Cleared {$cleared} session file(s) from {$sessionPath}");
            } else {
                $this->warn("Session path does not exist: {$sessionPath}");
            }
        } catch (\Exception $e) {
            $this->warn("Could not clear session files: " . $e->getMessage());
        }
        
        return $cleared;
    }
    
    /**
     * Clear database sessions if table exists
     */
    private function clearDatabaseSessions()
    {
        $cleared = 0;
        
        try {
            if (Schema::hasTable('sessions')) {
                $cleared = DB::table('sessions')->delete();
                $this->info("Cleared {$cleared} session(s) from database table.");
            }
        } catch (\Exception $e) {
            $this->warn("Could not clear database sessions: " . $e->getMessage());
        }
        
        return $cleared;
    }
    
    /**
     * Clear remember_me tokens from users table
     */
    private function clearRememberTokens()
    {
        $cleared = 0;
        
        try {
            if (Schema::hasTable('users') && Schema::hasColumn('users', 'remember_token')) {
                $cleared = DB::table('users')
                    ->whereNotNull('remember_token')
                    ->update(['remember_token' => null]);
                    
                $this->info("Cleared {$cleared} remember token(s).");
            }
        } catch (\Exception $e) {
            $this->warn("Could not clear remember tokens: " . $e->getMessage());
        }
        
        return $cleared;
    }
    
    /**
     * Clear API tokens if personal_access_tokens table exists
     */
    private function clearApiTokens()
    {
        $cleared = 0;
        
        try {
            // Check for Laravel Sanctum tokens
            if (Schema::hasTable('personal_access_tokens')) {
                $cleared = DB::table('personal_access_tokens')->delete();
                $this->info("Cleared {$cleared} API token(s).");
            }
        } catch (\Exception $e) {
            $this->warn("Could not clear API tokens: " . $e->getMessage());
        }
        
        return $cleared;
    }
}
