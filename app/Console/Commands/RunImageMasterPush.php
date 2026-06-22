<?php

namespace App\Console\Commands;

use App\Jobs\RunImageMasterPushJob;
use App\Services\Support\ImageMasterPushJobStore;
use App\Services\Support\ImageMasterPushRunner;
use Illuminate\Console\Command;

class RunImageMasterPush extends Command
{
    protected $signature = 'image-master:push-run {--sync : Run in this process instead of dispatching to the queue}';

    protected $description = 'Run the Image Master marketplace push worker (queued by default)';

    public function handle(ImageMasterPushRunner $runner, ImageMasterPushJobStore $store): int
    {
        if (! $this->option('sync')) {
            $state = $store->load();
            if (! $store->isActive($state)) {
                $this->warn('No active Image Master push job. Start one from Image Master first.');

                return self::FAILURE;
            }

            RunImageMasterPushJob::dispatch();
            $this->info('Dispatched RunImageMasterPushJob to the queue.');

            return self::SUCCESS;
        }

        $state = $store->load();
        if (! $store->isActive($state)) {
            $this->warn('No active Image Master push job.');

            return self::FAILURE;
        }

        $this->info('Running Image Master push synchronously...');

        return $runner->run() === 0 ? self::SUCCESS : self::FAILURE;
    }
}
