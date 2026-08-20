<?php

namespace App\Jobs;

use App\Services\Support\ChannelPushSpriceJobStore;
use App\Services\Support\ChannelPushSpriceRunner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunChannelPushSpriceJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 14400;

    public int $tries = 1;

    public int $uniqueFor = 14400;

    public function __construct(
        public readonly string $channel = 'ebay1'
    ) {
        $this->onQueue($this->channel.'-push-sprice');
    }

    public function uniqueId(): string
    {
        return $this->channel.'-push-sprice';
    }

    public function handle(): void
    {
        $store = ChannelPushSpriceJobStore::for($this->channel);
        $state = $store->load();
        if (! $store->isActive($state)) {
            return;
        }

        Log::build([
            'driver' => 'single',
            'path' => storage_path('logs/'.$this->channel.'-push-sprice.log'),
            'level' => 'debug',
        ])->info('Queue worker started S PRC push', [
            'channel' => $this->channel,
            'job_id' => $state['id'] ?? null,
            'total' => $state['total'] ?? 0,
        ]);

        ChannelPushSpriceRunner::for($this->channel)->run();
    }

    public function failed(\Throwable $exception): void
    {
        $store = ChannelPushSpriceJobStore::for($this->channel);
        $store->markFailed($exception->getMessage());
        $store->appendMessage('Queue worker failed: '.$exception->getMessage(), false);
    }
}
