<?php

namespace App\Console\Commands;

use App\Services\ShopifyB2bPromotionService;
use Illuminate\Console\Command;

/**
 * Ends Shopify B2B percentage-off promotions (set on /promotions) whose end time has passed:
 * the original price is pushed back to business5core.com.
 */
class ShopifyB2bPromoExpireCommand extends Command
{
    protected $signature = 'shopify-b2b:promo-expire';

    protected $description = 'Restore the original price of Shopify B2B promotions whose end time has passed.';

    public function handle(ShopifyB2bPromotionService $service): int
    {
        $r = $service->expireDue();
        $this->info('Shopify B2B promo expire: removed='.$r['removed'].' failed='.$r['failed']);

        return self::SUCCESS;
    }
}
