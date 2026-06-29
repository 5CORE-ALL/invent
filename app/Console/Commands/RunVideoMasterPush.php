<?php

namespace App\Console\Commands;

use App\Jobs\RunVideoMasterPushJob;
use App\Services\Support\VideoMasterPushJobStore;
use App\Services\Support\VideoMasterPushRunner;
use Illuminate\Console\Command;

class RunVideoMasterPush extends Command
{
    protected $signature = 'video-master:push-run {--sync : Run in this process instead of dispatching to the queue}';

    protected $description = 'Run the Video Master marketplace push worker (queued by default)';

    public function handle(VideoMasterPushRunner $runner, VideoMasterPushJobStore $store): int
    {
        if (! $this->option('sync')) {
            $state = $store->load();
            if (! $store->isActive($state)) {
                $this->warn('No active Video Master push job. Start one from Video Master first.');

                return self::FAILURE;
            }

            RunVideoMasterPushJob::dispatch();
            $this->info('Dispatched RunVideoMasterPushJob to the queue.');

            return self::SUCCESS;
        }

        $state = $store->load();
        if (! $store->isActive($state)) {
            $this->warn('No active Video Master push job.');

            return self::FAILURE;
        }

        $this->info('Running Video Master push synchronously...');

        return $runner->run() === 0 ? self::SUCCESS : self::FAILURE;
    }
}
