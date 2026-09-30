<?php

namespace App\Console\Commands;

use App\Http\Controllers\MarketPlace\ListingManagerController;
use App\Models\ListingManagerChannelDraft;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Runs a Listing Manager draft publish outside the web request. Spawned detached by
 * ListingManagerController::publishDraft() for channels whose marketplace import polling
 * (Mirakl P41/P42) would otherwise exceed the gateway timeout.
 */
class ListingManagerPublishDraft extends Command
{
    protected $signature = 'listing-manager:publish-draft {id : listing_manager_channel_drafts.id}';

    protected $description = 'Publish a Listing Manager draft to its marketplace (background worker for slow channels)';

    public function handle(): int
    {
        @set_time_limit(0);
        @ini_set('memory_limit', '1024M');
        $id = (int) $this->argument('id');

        $this->line('['.now()->toDateTimeString().'] publish-draft '.$id.' start');

        // Exceptions are handled in runQueuedDraftPublish(); this covers fatals (memory, timeouts)
        // that would otherwise leave the draft at "Publishing…" forever.
        register_shutdown_function(static function () use ($id): void {
            $error = error_get_last();
            if (! is_array($error) || ! in_array($error['type'] ?? 0, [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR], true)) {
                return;
            }
            try {
                $draft = ListingManagerChannelDraft::query()->find($id);
                if ($draft && $draft->status === 'queued') {
                    $draft->status = 'failed';
                    $draft->notes = trim((string) $draft->notes."\nPublish failed: background process crashed — "
                        .mb_substr((string) ($error['message'] ?? 'fatal error'), 0, 400));
                    $draft->save();
                }
                Cache::forget(ListingManagerController::backgroundPublishLockKey($id));
            } catch (\Throwable) {
                // nothing else we can do from a shutdown handler
            }
        });

        $outcome = app(ListingManagerController::class)->runQueuedDraftPublish($id);
        $success = (bool) ($outcome['body']['success'] ?? false);
        $message = (string) ($outcome['body']['message'] ?? '');

        Log::info('ListingManager background publish finished', [
            'draft_id' => $id,
            'success' => $success,
            'status' => $outcome['status'],
            'message' => $message,
        ]);
        $this->line('['.now()->toDateTimeString().'] '.($success ? 'OK: ' : 'FAILED: ').$message);

        return $success ? self::SUCCESS : self::FAILURE;
    }
}
