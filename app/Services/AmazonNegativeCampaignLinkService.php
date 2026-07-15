<?php

namespace App\Services;

use App\Models\AmazonNegativeCampaignLink;

/**
 * Campaign linking for negative-keyword sharing. Identical mesh/grouping logic as
 * {@see AmazonCampaignLinkService}, backed by its own links table so negative-keyword
 * groups stay independent of the keyword-performance groups.
 */
class AmazonNegativeCampaignLinkService extends AmazonCampaignLinkService
{
    protected string $modelClass = AmazonNegativeCampaignLink::class;
}
