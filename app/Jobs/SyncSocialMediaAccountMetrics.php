<?php

namespace App\Jobs;

class SyncSocialMediaAccountMetrics extends SyncSocialMediaAccount
{
    public function __construct(int $accountId)
    {
        parent::__construct($accountId, 'account_metrics');
    }
}
