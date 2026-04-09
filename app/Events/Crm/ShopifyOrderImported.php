<?php

namespace App\Events\Crm;

use App\Models\Crm\ShopifyOrder;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ShopifyOrderImported
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public ShopifyOrder $order,
        public bool $wasRecentlyCreated
    ) {
    }
}
