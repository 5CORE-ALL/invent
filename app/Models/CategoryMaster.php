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

    public const FIELDS = ['category_ebay', 'category_amz', 'category_temu'];

    public const ID_FIELDS = ['category_ebay_id', 'category_amz_id', 'category_temu_id'];

    protected $table = 'category_masters';

    protected $fillable = [
        'sku',
        'parent',
        'category_ebay',
        'category_ebay_id',
        'category_amz',
        'category_amz_id',
        'category_temu',
        'category_temu_id',
        'updated_by',
    ];
}
