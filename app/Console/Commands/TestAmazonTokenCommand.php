<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AmazonSpApiService;
use Illuminate\Support\Facades\Cache;

class TestAmazonTokenCommand extends Command
{
    protected $signature = 'amazon:test-token {--force-refresh} {--clear-cache}';
    protected $description = 'Test Amazon SP-API token management and show token age';

    public function handle()
    {
        $this->info('=== Amazon SP-API Token Test ===');
        $this->newLine();
        
        // Option: Clear cache
        if ($this->option('clear-cache')) {
            Cache::forget('amazon_spapi_access_token');
            Cache::forget('amazon_spapi_access_token_data');
            $this->info('✓ Token cache cleared');
            $this->newLine();
        }
        
        // Check current token status
        $this->info('Current Token Status:');
        $tokenData = Cache::get('amazon_spapi_access_token_data');
        
        if ($tokenData && is_array($tokenData)) {
            $token = $tokenData['token'] ?? null;
            $timestamp = $tokenData['timestamp'] ?? null;
            
            if ($token && $timestamp) {
                $age = now()->diffInMinutes($timestamp);
                $this->line("  Token exists: YES");
                $this->line("  Token age: {$age} minutes");
                $this->line("  Created at: {$timestamp}");
                
                if ($age < 40) {
                    $this->line("  Status: ✅ FRESH (safe to use)");
                } else if ($age < 55) {
                    $this->line("  Status: ⚠️  AGING (will auto-refresh on next use)");
                } else {
                    $this->line("  Status: ❌ EXPIRED (will force refresh)");
                }
            } else {
                $this->line("  Token exists: YES (but missing metadata)");
                $this->line("  Status: ⚠️  NO METADATA (will force refresh)");
            }
        } else {
            $this->line("  Token exists: NO");
            $this->line("  Status: 🔄 Will request new token");
        }
        
        $this->newLine();
        
        // Get token (optionally force refresh)
        $forceRefresh = $this->option('force-refresh');
        
        if ($forceRefresh) {
            $this->info('Forcing token refresh...');
        } else {
            $this->info('Getting token (will use cache if fresh)...');
        }
        
        try {
            $service = new AmazonSpApiService();
            $token = $service->getAccessToken($forceRefresh);
            
            if ($token) {
                $this->info('✓ Token obtained successfully');
                $this->line('  Token: ' . substr($token, 0, 20) . '...' . substr($token, -10));
                
                // Show new token age
                $newTokenData = Cache::get('amazon_spapi_access_token_data');
                if ($newTokenData && isset($newTokenData['timestamp'])) {
                    $newAge = now()->diffInMinutes($newTokenData['timestamp']);
                    $this->line("  New token age: {$newAge} minutes");
                }
            } else {
                $this->error('✗ Failed to obtain token');
            }
        } catch (\Exception $e) {
            $this->error('✗ Exception: ' . $e->getMessage());
        }
        
        $this->newLine();
        $this->info('=== Test Complete ===');
        
        return 0;
    }
}


