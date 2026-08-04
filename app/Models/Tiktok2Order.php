<?php

namespace App\Models;

use Illuminate\Support\Facades\Schema;

/**
 * TikTok Shop 2 API order lines — same shape as TiktokOrder, separate shop.
 */
class Tiktok2Order extends TiktokOrder
{
    protected $table = 'tiktok2_orders';

    public static function tableReady(): bool
    {
        return Schema::hasTable('tiktok2_orders');
    }
}
