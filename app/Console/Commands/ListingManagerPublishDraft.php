<?php

namespace App\Console\Commands;

use App\Http\Controllers\MarketPlace\ListingManagerController;
use Illuminate\Console\Command;
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
        $id = (int) $this->argument('id');

        $outcome = app(ListingManagerController::class)->runQueuedDraftPublish($id);
        $success = (bool) ($outcome['body']['success'] ?? false);
        $message = (string) ($outcome['body']['message'] ?? '');

        Log::info('ListingManager background publish finished', [
            'draft_id' => $id,
            'success' => $success,
            'status' => $outcome['status'],
            'message' => $message,
        ]);
        $this->line(($success ? 'OK: ' : 'FAILED: ').$message);

        return $success ? self::SUCCESS : self::FAILURE;
    }
}
