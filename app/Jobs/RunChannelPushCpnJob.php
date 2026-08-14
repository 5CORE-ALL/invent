<?php

namespace App\Jobs;

use App\Services\Support\ChannelPushCpnJobStore;
use App\Services\Support\ChannelPushCpnRunner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunChannelPushCpnJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 14400;

    public int $tries = 1;

    public int $uniqueFor = 14400;

    public function __construct(
        public readonly string $channel = 'ebay2'
    ) {
        $this->onQueue($this->channel.'-push-cpn');
    }

    public function uniqueId(): string
    {
        return $this->channel.'-push-cpn';
    }

    public function handle(): void
    {
        $store = ChannelPushCpnJobStore::for($this->channel);
        $state = $store->load();
        if (! $store->isActive($state)) {
            return;
        }

        Log::build([
            'driver' => 'single',
            'path' => storage_path('logs/'.$this->channel.'-push-cpn.log'),
            'level' => 'debug',
        ])->info('Queue worker started Channel Push CPN%', [
            'channel' => $this->channel,
            'job_id' => $state['id'] ?? null,
            'total' => $state['total'] ?? 0,
        ]);

        ChannelPushCpnRunner::for($this->channel)->run();
    }

    public function failed(\Throwable $exception): void
    {
        $store = ChannelPushCpnJobStore::for($this->channel);
        $store->markFailed($exception->getMessage());
        $store->appendMessage('Queue worker failed: '.$exception->getMessage(), false);
    }
}
