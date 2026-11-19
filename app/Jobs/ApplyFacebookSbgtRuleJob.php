<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Artisan;

/**
 * Runs right after the Sbgt rule of /facebook-all-ads-sheet is saved: pauses /
 * re-activates the Meta campaigns so they match the new rule.
 */
class ApplyFacebookSbgtRuleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /** One Meta call (plus a short pause) per changed campaign. */
    public int $timeout = 1800;

    public int $tries = 1;

    public function __construct(public ?int $userId = null)
    {
    }

    public function handle(): void
    {
        Artisan::call('facebook:apply-sbgt-rule', ['--user' => (int) ($this->userId ?? 0)]);
    }
}
