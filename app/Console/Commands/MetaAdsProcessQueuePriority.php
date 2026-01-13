<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;

class MetaAdsProcessQueuePriority extends Command
{
    protected $signature = 'meta-ads:process-priority 
                            {--limit=100 : Total number of jobs to process}';

    protected $description = 'Process Meta Ads jobs in priority order (Accounts -> Campaigns -> AdSets -> Ads -> Insights)';

    public function handle()
    {
        $limit = (int) $this->option('limit');
        $queueName = 'default';
        
        $this->info("Processing Meta Ads jobs in priority order...");
        
        // Priority order
        $priorities = [
            'SyncMetaAdAccountsJob' => 'Accounts',
            'SyncMetaCampaignsJob' => 'Campaigns',
            'SyncMetaAdSetsJob' => 'Ad Sets',
            'SyncMetaAdsJob' => 'Ads',
            'SyncMetaInsightsDailyJob' => 'Insights',
        ];
        
        $processed = 0;
        $totalProcessed = 0;
        
        foreach ($priorities as $jobPattern => $jobName) {
            if ($totalProcessed >= $limit) {
                break;
            }
            
            $this->line("Processing {$jobName} jobs...");
            
            // Find jobs of this type
            $jobs = DB::select("
                SELECT id, payload 
                FROM jobs 
                WHERE queue = ? AND payload LIKE ?
                ORDER BY id ASC
                LIMIT ?
            ", [$queueName, "%{$jobPattern}%", $limit - $totalProcessed]);
            
            $jobCount = count($jobs);
            
            if ($jobCount == 0) {
                $this->line("  No {$jobName} jobs found.");
                continue;
            }
            
            $this->line("  Found {$jobCount} {$jobName} jobs.");
            
            foreach ($jobs as $jobRecord) {
                if ($totalProcessed >= $limit) {
                    break;
                }
                
                try {
                    $payload = json_decode($jobRecord->payload, true);
                    $jobClass = $payload['displayName'] ?? 'Unknown';
                    
                    // Move job to front
                    $minId = DB::table('jobs')->min('id') ?? 1;
                    $newId = $minId - 1;
                    
                    // Delete and re-insert at front
                    $job = DB::table('jobs')->where('id', $jobRecord->id)->first();
                    if ($job) {
                        DB::table('jobs')->where('id', $jobRecord->id)->delete();
                        DB::table('jobs')->insert([
                            'id' => $newId,
                            'queue' => $job->queue,
                            'payload' => $job->payload,
                            'attempts' => 0,
                            'reserved_at' => null,
                            'available_at' => $job->available_at ?? time(),
                            'created_at' => $job->created_at,
                        ]);
                        
                        // Process the job
                        Artisan::call('queue:work', [
                            'connection' => 'database',
                            '--queue' => $queueName,
                            '--once' => true,
                            '--tries' => 3,
                            '--timeout' => 600,
                        ]);
                        
                        $processed++;
                        $totalProcessed++;
                        
                        if ($processed % 10 == 0) {
                            $this->info("  ✓ Processed {$processed} {$jobName} jobs");
                        }
                        
                        usleep(200000); // 0.2 second
                    }
                } catch (\Exception $e) {
                    $this->error("  Failed: " . $e->getMessage());
                }
            }
            
            $this->info("  Completed {$jobName}: {$processed} jobs processed");
            $processed = 0; // Reset for next priority
        }
        
        $this->info("Total processed: {$totalProcessed} Meta Ads jobs");
        
        return 0;
    }
}

