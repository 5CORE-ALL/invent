<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CategoryMaster extends Model
{
    /** Channel key => category name column. The ID column is the name column + "_id". */
    public const CHANNEL_FIELDS = [
        'ebay' => 'category_ebay',
        'amz' => 'category_amz',
        'temu' => 'category_temu',
    ];

    /** Editable category columns. category_5c is typed by hand: it has no channel to pull from or push to. */
    public const FIELDS = ['category_5c', 'category_ebay', 'category_amz', 'category_temu'];

    public const ID_FIELDS = ['category_5c_id', 'category_ebay_id', 'category_amz_id', 'category_temu_id'];

    protected $table = 'category_masters';

    protected $fillable = [
        'sku',
        'parent',
        'category_5c',
        'category_5c_id',
        'category_ebay',
        'category_ebay_id',
        'category_amz',
        'category_amz_id',
        'category_temu',
        'category_temu_id',
        'category_shopify',
        'category_shopify_id',
        'category_shopify_synced_at',
        'updated_by',
    ];

    protected $casts = [
        'category_shopify_synced_at' => 'datetime',
    ];
}
