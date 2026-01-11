<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\User;

class ForceLogoutUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:force-logout 
                            {email? : Email of user to logout (optional)}
                            {--all : Logout all users}
                            {--except-admin : Exclude admin users}
                            {--clear-sessions : Clear all sessions immediately}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Force logout specific user or all users (require Google login)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');
        $all = $this->option('all');
        $exceptAdmin = $this->option('except-admin');

        // Validate: Need either email or --all flag
        if (!$email && !$all) {
            $this->error('❌ Please provide either an email or use --all flag');
            $this->info('Examples:');
            $this->line('  php artisan users:force-logout user@example.com');
            $this->line('  php artisan users:force-logout --all');
            $this->line('  php artisan users:force-logout --all --except-admin');
            return Command::FAILURE;
        }

        // Logout specific user
        if ($email) {
            return $this->logoutSpecificUser($email);
        }

        // Logout all users
        if ($all) {
            return $this->logoutAllUsers($exceptAdmin);
        }

        return Command::SUCCESS;
    }

    /**
     * Logout specific user by email
     */
    private function logoutSpecificUser($email)
    {
        $user = User::where('email', $email)->first();

        if (!$user) {
            $this->error("❌ User not found: {$email}");
            return Command::FAILURE;
        }

        if ($user->require_google_login == 1) {
            $this->warn("⚠️  User {$email} is already marked for Google login only");
            $this->info("Status: Already logged out");
            return Command::SUCCESS;
        }

        // Show user info
        $this->info("👤 User Details:");
        $this->table(
            ['ID', 'Name', 'Email', 'Role', 'Last Activity'],
            [[
                $user->id,
                $user->name,
                $user->email,
                $user->role ?? 'N/A',
                $user->last_activity_at ? Carbon::parse($user->last_activity_at)->format('Y-m-d H:i:s') : 'Never'
            ]]
        );

        if (!$this->confirm("Force logout this user?", true)) {
            $this->info('❌ Operation cancelled');
            return Command::FAILURE;
        }

        // Update user
        $user->update([
            'require_google_login' => 1,
            'auto_logged_out_at' => Carbon::now(),
        ]);

        $this->info("✅ User {$email} has been logged out!");
        $this->line("They must now login with Google to access the system.");

        // Clear sessions if flag is set
        if ($this->option('clear-sessions')) {
            $this->clearAllSessions();
        }

        return Command::SUCCESS;
    }

    /**
     * Logout all users
     */
    private function logoutAllUsers($exceptAdmin)
    {
        $this->warn("⚠️  WARNING: This will logout ALL users!");
        
        if ($exceptAdmin) {
            $this->info("Admin users will be excluded");
        }

        // Count users
        $query = User::where('require_google_login', 0);
        
        if ($exceptAdmin) {
            $query->where('role', '!=', 'admin');
        }

        $count = $query->count();

        if ($count == 0) {
            $this->info('✅ No users to logout!');
            return Command::SUCCESS;
        }

        $this->warn("Total users to logout: {$count}");

        // Show sample users
        $sampleUsers = $query->limit(5)->get(['id', 'email', 'name', 'role']);
        
        $this->info("\nSample users (first 5):");
        $this->table(
            ['ID', 'Email', 'Name', 'Role'],
            $sampleUsers->map(fn($u) => [
                $u->id,
                $u->email,
                $u->name,
                $u->role ?? 'N/A'
            ])
        );

        if ($count > 5) {
            $this->line("... and " . ($count - 5) . " more users");
        }

        if (!$this->confirm("\nAre you sure you want to logout {$count} users?", false)) {
            $this->info('❌ Operation cancelled');
            return Command::FAILURE;
        }

        // Double confirmation for safety
        if (!$this->confirm("⚠️  FINAL CONFIRMATION: Logout {$count} users?", false)) {
            $this->info('❌ Operation cancelled');
            return Command::FAILURE;
        }

        // Update all users
        $query = User::where('require_google_login', 0);
        
        if ($exceptAdmin) {
            $query->where('role', '!=', 'admin');
        }

        $affected = $query->update([
            'require_google_login' => 1,
            'auto_logged_out_at' => Carbon::now(),
        ]);

        $this->info("✅ Successfully logged out {$affected} users!");
        $this->line("They must now login with Google to access the system.");

        // Clear sessions if flag is set
        if ($this->option('clear-sessions')) {
            $this->clearAllSessions();
        }

        return Command::SUCCESS;
    }

    /**
     * Clear all active sessions
     */
    private function clearAllSessions()
    {
        $this->info("\n🔄 Clearing all sessions...");
        
        try {
            // Clear session files
            $sessionPath = storage_path('framework/sessions');
            $files = glob($sessionPath . '/*');
            
            if ($files) {
                foreach ($files as $file) {
                    if (is_file($file)) {
                        unlink($file);
                    }
                }
                $this->info("✅ Cleared " . count($files) . " session files");
            } else {
                $this->info("ℹ️  No session files found");
            }
            
            // Clear cache
            $this->call('cache:clear');
            
            $this->info("✅ Sessions and cache cleared successfully!");
            $this->warn("⚠️  All users will be logged out immediately on next request");
            
        } catch (\Exception $e) {
            $this->error("❌ Error clearing sessions: " . $e->getMessage());
        }
    }
}
