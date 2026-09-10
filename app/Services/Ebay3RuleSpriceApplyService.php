<?php

namespace App\Services;

/**
 * eBay 3 alias of {@see EbayRuleSpriceApplyService}.
 */
class Ebay3RuleSpriceApplyService extends EbayRuleSpriceApplyService
{
    public function __construct()
    {
        parent::__construct('ebay3');
    }
}
