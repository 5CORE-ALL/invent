<?php

namespace App\Jobs;

class SyncSocialMediaPosts extends SyncSocialMediaAccount
{
    public function __construct(int $accountId)
    {
        parent::__construct($accountId, 'posts');
    }
}
