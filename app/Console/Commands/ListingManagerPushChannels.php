<?php

namespace App\Console\Commands;

use App\Http\Controllers\MarketPlace\ListingManagerController;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Runs a Listing Manager "Push to Marketplaces" content update outside the web request.
 * Spawned detached by ListingManagerController::pushProductToMarketplaces(); the job payload
 * and its outcome live in the cache under the token so the modal can poll for results.
 */
class ListingManagerPushChannels extends Command
{
    protected $signature = 'listing-manager:push-channels {token : cache token of the queued push job}';

    protected $description = 'Push Listing Manager product content to marketplaces (background worker for slow channels)';

    public function handle(): int
    {
        @set_time_limit(0);
        @ini_set('memory_limit', '1024M');
        $token = (string) $this->argument('token');

        $this->line('['.now()->toDateTimeString().'] push-channels '.$token.' start');

        register_shutdown_function(static function () use ($token): void {
            $error = error_get_last();
            if (! is_array($error) || ! in_array($error['type'] ?? 0, [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR], true)) {
                return;
            }
            try {
                ListingManagerController::failPushJob(
                    $token,
                    'Background process crashed — '.mb_substr((string) ($error['message'] ?? 'fatal error'), 0, 400)
                );
            } catch (\Throwable) {
                // nothing else we can do from a shutdown handler
            }
        });

        $job = app(ListingManagerController::class)->runPushJob($token);
        $success = (bool) ($job['success'] ?? false);
        $message = (string) ($job['message'] ?? '');

        Log::info('ListingManager background push finished', [
            'token' => $token,
            'sku' => $job['sku'] ?? null,
            'status' => $job['status'] ?? null,
            'success' => $success,
            'message' => $message,
        ]);
        $this->line('['.now()->toDateTimeString().'] '.($success ? 'OK: ' : 'FAILED: ').$message);

        return $success ? self::SUCCESS : self::FAILURE;
    }
}
