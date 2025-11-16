<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\ApiController;

class SyncSheet extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:sync-sheet';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $controller = new ApiController();

        // Call the method
        $response = $controller->syncInvAndL30ToSheet();

        // Log or output result
        $status = $response->getStatusCode();
        $data = $response->getData(true);

        if ($status === 200) {
            $this->info('Sync successful: ' . json_encode($data));
        } else {
            $this->error('Sync failed: ' . json_encode($data));
        }

    }
}
