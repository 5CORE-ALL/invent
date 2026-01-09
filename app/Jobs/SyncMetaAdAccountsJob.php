<?php

namespace App\Jobs;

use App\Models\MetaAdAccount;
use App\Services\MetaAdsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SyncMetaAdAccountsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 600;
    public $tries = 3;

    protected $userId;

    /**
     * Create a new job instance.
     */
    public function __construct(?int $userId = null)
    {
        $this->userId = $userId;
    }

    /**
     * Execute the job.
     */
    public function handle(MetaAdsService $metaAdsService): void
    {
        try {
            Log::info('SyncMetaAdAccountsJob: Starting sync', ['user_id' => $this->userId]);
            
            $accounts = $metaAdsService->fetchAdAccounts();
            $synced = 0;

            foreach ($accounts as $account) {
                $metaId = $account['id'] ?? null;
                if (!$metaId) continue;

                $metaUpdatedTime = null;
                if (isset($account['updated_time'])) {
                    try {
                        $metaUpdatedTime = Carbon::parse($account['updated_time']);
                    } catch (\Exception $e) {
                        // Invalid date
                    }
                }

                MetaAdAccount::updateOrCreate(
                    [
                        'meta_id' => $metaId,
                    ],
                    [
                        'user_id' => $this->userId,
                        'account_id' => $account['account_id'] ?? null,
                        'name' => $account['name'] ?? null,
                        'account_status' => $account['account_status'] ?? null,
                        'currency' => $account['currency'] ?? null,
                        'timezone_name' => $account['timezone_name'] ?? null,
                        'meta_updated_time' => $metaUpdatedTime,
                        'synced_at' => now(),
                        'raw_json' => $account,
                    ]
                );
                $synced++;
            }

            Log::info('SyncMetaAdAccountsJob: Completed', [
                'user_id' => $this->userId,
                'synced' => $synced,
            ]);
        } catch (\Exception $e) {
            Log::error('SyncMetaAdAccountsJob: Failed', [
                'user_id' => $this->userId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
