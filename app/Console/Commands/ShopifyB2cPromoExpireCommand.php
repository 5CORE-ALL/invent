<?php

namespace App\Console\Commands;

use App\Services\ShopifyB2cPromotionService;
use Illuminate\Console\Command;

/**
 * Ends Shopify B2C product-page promotions (set on /promotions) whose end time has passed:
 * the original price and compare-at price are put back on the variant.
 */
class ShopifyB2cPromoExpireCommand extends Command
{
    protected $signature = 'shopify-b2c:promo-expire';

    protected $description = 'Restore the original price of Shopify B2C promotions whose end time has passed.';

    public function handle(ShopifyB2cPromotionService $service): int
    {
        $r = $service->expireDue();
        $this->info('Shopify B2C promo expire: removed='.$r['removed'].' failed='.$r['failed']);

        return self::SUCCESS;
    }
}
