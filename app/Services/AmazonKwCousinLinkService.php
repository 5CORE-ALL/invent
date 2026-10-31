<?php

namespace App\Services;

use App\Models\AmazonKwCousinLink;

/**
 * KW cousins: campaigns whose keywords are kept in sync on the Keyword Targeting page.
 * Same mesh/grouping logic as {@see AmazonCampaignLinkService}, backed by its own table so
 * cousin groups stay independent of the Campaign Link and negative-keyword groups.
 */
class AmazonKwCousinLinkService extends AmazonCampaignLinkService
{
    protected string $modelClass = AmazonKwCousinLink::class;
}
