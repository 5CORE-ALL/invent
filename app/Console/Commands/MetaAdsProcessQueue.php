<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;

class MetaAdsProcessQueue extends Command
{
    protected $signature = 'meta-ads:process-queue 
                            {--limit=50 : Number of Meta Ads jobs to process}
                            {--queue=default : Queue name}';

    protected $description = 'Process only Meta Ads related jobs from the queue';

    public function handle()
    {
        $limit = (int) $this->option('limit');
        $queueName = $this->option('queue');
        
        $this->info("Processing Meta Ads jobs from queue: {$queueName}");
        $this->line("This will process jobs one by one, filtering for Meta Ads jobs only.");
        $this->line("It may take time if there are many non-Meta Ads jobs in queue.");
        
        // Meta Ads job patterns
        $metaAdsPatterns = [
            'SyncMetaAdAccountsJob',
            'SyncMetaCampaignsJob',
            'SyncMetaAdSetsJob',
            'SyncMetaAdsJob',
            'SyncMetaInsightsDailyJob',
        ];
        
        // First, move Meta Ads jobs to front by updating their ID to be lower
        // Get the minimum ID in jobs table
        $minId = DB::table('jobs')->min('id') ?? 1;
        
        // Get Meta Ads jobs
        $whereConditions = [];
        foreach ($metaAdsPatterns as $pattern) {
            $whereConditions[] = "payload LIKE '%{$pattern}%'";
        }
        $whereClause = implode(' OR ', $whereConditions);
        
        $metaAdsJobs = DB::select("
            SELECT id 
            FROM jobs 
            WHERE queue = ? AND ({$whereClause})
            ORDER BY id ASC
            LIMIT ?
        ", [$queueName, $limit]);
        
        if (count($metaAdsJobs) == 0) {
            $this->warn("No Meta Ads jobs found in queue.");
            return 0;
        }
        
        $this->info("Found " . count($metaAdsJobs) . " Meta Ads jobs. Moving to front and processing...");
        
        // Move Meta Ads jobs to front by creating new jobs with lower IDs
        // Then delete old ones
        $processed = 0;
        
        foreach ($metaAdsJobs as $index => $jobRecord) {
            try {
                // Get full job data
                $job = DB::table('jobs')->where('id', $jobRecord->id)->first();
                
                if (!$job) {
                    continue; // Already processed
                }
                
                $payload = json_decode($job->payload, true);
                $jobClass = $payload['displayName'] ?? 'Unknown';
                
                $this->line("Processing: {$jobClass} (ID: {$job->id})");
                
                // Delete the job from queue
                DB::table('jobs')->where('id', $job->id)->delete();
                
                // Re-insert at front with very low ID
                $newId = $minId - ($index + 1);
                DB::table('jobs')->insert([
                    'id' => $newId,
                    'queue' => $job->queue,
                    'payload' => $job->payload,
                    'attempts' => 0,
                    'reserved_at' => null,
                    'available_at' => $job->available_at ?? time(),
                    'created_at' => $job->created_at,
                ]);
                
                // Now process this job (it's at the front now)
                Artisan::call('queue:work', [
                    'connection' => 'database',
                    '--queue' => $queueName,
                    '--once' => true,
                    '--tries' => 3,
                    '--timeout' => 600,
                ]);
                
                $processed++;
                $this->info("✓ Processed {$processed}/" . count($metaAdsJobs));
                
                usleep(200000); // 0.2 second delay
                
            } catch (\Exception $e) {
                $this->error("Failed to process job ID {$jobRecord->id}: " . $e->getMessage());
            }
        }
        
        $this->info("Completed! Processed: {$processed} Meta Ads jobs");
        
        return 0;
    }
}
