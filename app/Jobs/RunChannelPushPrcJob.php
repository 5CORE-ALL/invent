<?php

namespace App\Jobs;

use App\Services\Support\ChannelPushPrcJobStore;
use App\Services\Support\ChannelPushPrcRunner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunChannelPushPrcJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 14400;

    public int $tries = 1;

    public int $uniqueFor = 14400;

    public function __construct(
        public readonly string $channel = 'ebay1'
    ) {
        $this->onQueue($this->channel.'-push-prc');
    }

    public function uniqueId(): string
    {
        return $this->channel.'-push-prc';
    }

    public function handle(): void
    {
        $store = ChannelPushPrcJobStore::for($this->channel);
        $state = $store->load();
        if (! $store->isActive($state)) {
            return;
        }

        Log::build([
            'driver' => 'single',
            'path' => storage_path('logs/'.$this->channel.'-push-prc.log'),
            'level' => 'debug',
        ])->info('Queue worker started Channel Push Prc', [
            'channel' => $this->channel,
            'job_id' => $state['id'] ?? null,
            'total' => $state['total'] ?? 0,
        ]);

        ChannelPushPrcRunner::for($this->channel)->run();
    }

    public function failed(\Throwable $exception): void
    {
        $store = ChannelPushPrcJobStore::for($this->channel);
        $store->markFailed($exception->getMessage());
        $store->appendMessage('Queue worker failed: '.$exception->getMessage(), false);
    }
}
