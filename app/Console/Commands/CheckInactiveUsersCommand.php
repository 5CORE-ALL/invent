<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CheckInactiveUsersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:check-inactive 
                            {--hours=6 : Number of hours of inactivity before marking user}
                            {--dry-run : Show what would be done without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check and mark inactive users to require Google login only';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $hours = $this->option('hours');
        $dryRun = $this->option('dry-run');
        
        $this->info("🔍 Checking for users inactive for {$hours}+ hours...");
        
        // Get timestamp for X hours ago
        $inactiveTime = Carbon::now()->subHours($hours);
        
        // Find inactive users
        $inactiveUsers = DB::table('users')
            ->where('last_activity_at', '<', $inactiveTime)
            ->whereNotNull('last_activity_at')
            ->select('id', 'email', 'name', 'last_activity_at')
            ->get();
        
        if ($inactiveUsers->isEmpty()) {
            $this->info('✅ No inactive users found!');
            return Command::SUCCESS;
        }
        
        $this->warn("Found {$inactiveUsers->count()} inactive users:");
        
        // Display table of inactive users
        $tableData = [];
        foreach ($inactiveUsers as $user) {
            $lastActivity = Carbon::parse($user->last_activity_at);
            $hoursInactive = $lastActivity->diffInHours(Carbon::now());
            
            $tableData[] = [
                'ID' => $user->id,
                'Email' => $user->email,
                'Name' => $user->name,
                'Last Activity' => $lastActivity->format('Y-m-d H:i:s'),
                'Hours Inactive' => $hoursInactive,
            ];
        }
        
        $this->table(
            ['ID', 'Email', 'Name', 'Last Activity', 'Hours Inactive'],
            $tableData
        );
        
        if ($dryRun) {
            $this->warn('🔸 DRY RUN MODE - No changes made');
            $this->info('These users WOULD be auto-logged out on next request');
            return Command::SUCCESS;
        }
        
        // Confirm before proceeding
        if (!$this->confirm('Record auto-logout timestamp for these users?', true)) {
            $this->info('❌ Operation cancelled');
            return Command::FAILURE;
        }
        
        // Update users - just mark when they were auto-logged out
        $affectedRows = DB::table('users')
            ->where('last_activity_at', '<', $inactiveTime)
            ->whereNotNull('last_activity_at')
            ->update([
                'auto_logged_out_at' => Carbon::now(),
            ]);
        
        $this->info("✅ Successfully recorded auto-logout for {$affectedRows} users");
        $this->line('These users will be logged out on their next request.');
        
        return Command::SUCCESS;
    }
}

