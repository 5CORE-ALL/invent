<?php

namespace App\Jobs;

class SyncSocialMediaPostMetrics extends SyncSocialMediaAccount
{
    public function __construct(int $accountId)
    {
        parent::__construct($accountId, 'post_metrics');
    }
}
