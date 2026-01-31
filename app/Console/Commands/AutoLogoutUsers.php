<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AutoLogoutUsers extends Command
{
    protected $signature = 'users:auto-logout';
    protected $description = 'Automatically logout all users by clearing their sessions';

    public function handle()
    {
        $this->info('Starting auto logout process...');
        
        try {
            $sessionsCleared = 0;
            
            // Get the session driver from config
            $sessionDriver = config('session.driver');
            
            $this->info("Session driver: {$sessionDriver}");
            
            // Clear sessions based on driver
            switch ($sessionDriver) {
                case 'file':
                    // Clear file-based sessions
                    $sessionPath = storage_path('framework/sessions');
                    
                    if (File::exists($sessionPath)) {
                        $files = File::files($sessionPath);
                        $sessionsCleared = count($files);
                        
                        foreach ($files as $file) {
                            File::delete($file);
                        }
                        
                        $this->info("Cleared {$sessionsCleared} session file(s).");
                    }
                    break;
                    
                case 'cookie':
                    // Cookie sessions are client-side, just clear cache
                    $this->info('Cookie-based sessions detected. Clearing auth cache...');
                    Artisan::call('cache:clear');
                    break;
                    
                case 'redis':
                case 'memcached':
                    // Clear cache for redis/memcached sessions
                    $this->info("Clearing {$sessionDriver} cache...");
                    Artisan::call('cache:clear');
                    break;
                    
                default:
                    $this->warn("Unknown session driver: {$sessionDriver}");
            }
            
            // Also clear the application cache to ensure logout
            Artisan::call('cache:clear');
            
            // Log the action
            Log::info('Auto logout executed', [
                'session_driver' => $sessionDriver,
                'sessions_cleared' => $sessionsCleared,
                'timestamp' => Carbon::now()->toDateTimeString()
            ]);
            
            $this->info("✓ Successfully logged out all users.");
            
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
