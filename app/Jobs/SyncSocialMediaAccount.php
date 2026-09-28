<?php

namespace App\Jobs;

use App\Models\SocialMediaAccount;
use App\SocialMedia\SocialMediaSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncSocialMediaAccount implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 600;

    public function __construct(public int $accountId, public string $syncType = 'account')
    {
        $this->onQueue((string) config('social_media.sync_queue', 'social-media'));
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function uniqueId(): string
    {
        return (string) $this->accountId;
    }

    public function uniqueFor(): int
    {
        return 900;
    }

    public function handle(SocialMediaSyncService $sync): void
    {
        $account = SocialMediaAccount::query()->find($this->accountId);
        if (! $account || ! $account->sync_enabled || $account->status === 'disconnected') {
            return;
        }
        if ($account->tokenStatus() === 'expired') {
            $account->forceFill([
                'status' => 'needs_reconnect',
                'last_sync_status' => 'auth_error',
                'last_sync_error' => ucfirst($account->platform).' access token expired — reconnect the account.',
            ])->save();

            return;
        }

        $sync->sync($account, $this->syncType);
    }
}
