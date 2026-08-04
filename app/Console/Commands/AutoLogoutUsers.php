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
    protected $description = 'Automatically logout all users by setting logined = 0 (skips stay_logged_in users)';

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

            $hasStayLoggedIn = DB::getSchemaBuilder()->hasColumn('users', 'stay_logged_in');
            $exemptIds = [];

            if ($hasStayLoggedIn) {
                $exemptIds = DB::table('users')
                    ->where('stay_logged_in', 1)
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->all();
            }
            
            // Set logined = 0 for logged-in users, except those marked stay_logged_in
            $query = DB::table('users')->where('logined', 1);
            if ($hasStayLoggedIn) {
                $query->where(function ($q) {
                    $q->where('stay_logged_in', 0)
                        ->orWhereNull('stay_logged_in');
                });
            }
            $loggedOutCount = $query->update(['logined' => 0]);
            
            // Clear session files, but keep sessions for stay_logged_in users
            $sessionPath = storage_path('framework/sessions');
            $sessionsCleared = 0;
            $sessionsKept = 0;
            
            if (File::exists($sessionPath)) {
                $files = File::files($sessionPath);
                
                foreach ($files as $file) {
                    if (! empty($exemptIds) && $this->sessionBelongsToExemptUser($file->getPathname(), $exemptIds)) {
                        $sessionsKept++;
                        continue;
                    }

                    File::delete($file);
                    $sessionsCleared++;
                }
                
                $this->info("Cleared {$sessionsCleared} session file(s)" . ($sessionsKept ? ", kept {$sessionsKept} for stay-logged-in users." : "."));
            }
            
            // Log the action
            Log::info('Auto logout executed', [
                'users_logged_out' => $loggedOutCount,
                'sessions_cleared' => $sessionsCleared,
                'sessions_kept' => $sessionsKept,
                'stay_logged_in_exempt' => count($exemptIds),
                'timestamp' => Carbon::now()->toDateTimeString()
            ]);
            
            $this->info("✓ Successfully logged out {$loggedOutCount} user(s).");
            if (! empty($exemptIds)) {
                $this->info('Skipped ' . count($exemptIds) . ' stay-logged-in user(s).');
            }
            
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

    /**
     * Detect whether a Laravel file session belongs to an exempt user.
     */
    protected function sessionBelongsToExemptUser(string $path, array $exemptIds): bool
    {
        $content = @file_get_contents($path);
        if ($content === false || $content === '') {
            return false;
        }

        foreach ($exemptIds as $id) {
            // Auth guard payload looks like: login_web_<hash>|i:<id>;
            if (preg_match('/login_web_[a-f0-9]+\|i:' . preg_quote((string) $id, '/') . ';/', $content)) {
                return true;
            }
        }

        return false;
    }
}
