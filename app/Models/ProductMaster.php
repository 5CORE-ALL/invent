<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductMaster extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'product_master';

    protected $fillable = [
        'parent',
        'is_spare_part',
        'min_stock_level',
        'reorder_level',
        'max_stock_level',
        'lead_time_days',
        'parent_id',
        'sku',
        'barcode',
        'group_id',
        'category_id',
        'group',
        'category',
        'Values',
        'remark',
        'sales',
        'views',
        'deleted_by',
        'title150',
        'title100',
        'title80',
        'title60',
        'amazon_last_sync',
        'amazon_sync_status',
        'amazon_sync_error',
        'bullet1',
        'bullet2',
        'bullet3',
        'bullet4',
        'bullet5',
        'product_description',
        'description_1500',
        'description_1000',
        'description_800',
        'description_600',
        'feature1',
        'feature2',
        'feature3',
        'feature4',
        'main_image',
        'main_image_brand',
        'image1',
        'image2',
        'image3',
        'image4',
        'image5',
        'image6',
        'image7',
        'image8',
        'image9',
        'image10',
        'image11',
        'image12',
        'video_product_overview',
        'video_product_overview_status',
        'video_unboxing',
        'video_unboxing_status',
        'video_how_to',
        'video_how_to_status',
        'video_setup',
        'video_setup_status',
        'video_troubleshooting',
        'video_troubleshooting_status',
        'video_brand_story',
        'video_brand_story_status',
        'video_product_benefits',
        'video_product_benefits_status',
    ];

    public function setTemuShipAttribute($value)
    {
        $values = $this->Values ?? [];
        $values['ship'] = $value;
        $this->attributes['Values'] = json_encode($values);
    }

    public function getTemuShipAttribute()
    {
        return $this->Values['ship'] ?? null;
    }

    protected $casts = [
        'Values' => 'array',
        'sales' => 'array',
        'views' => 'array',
        'is_spare_part' => 'boolean',
        'min_stock_level' => 'integer',
        'reorder_level' => 'integer',
        'max_stock_level' => 'integer',
        'lead_time_days' => 'integer',
    ];

    protected $guarded = ['group_id'];

    /**
     * Get the group that the product belongs to
     */
    public function productGroup()
    {
        return $this->belongsTo(ProductGroup::class, 'group_id');
    }

    /**
     * Get the category that the product belongs to
     */
    public function productCategory()
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function parentPart()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function childParts()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function sparePartDetail()
    {
        return $this->hasOne(SparePartDetail::class, 'product_master_id');
    }

    public function scopeSpareParts($query)
    {
        return $query->where('is_spare_part', true);
    }

    public function stockMovements()
    {
        return $this->hasMany(\App\Models\Wms\StockMovement::class, 'product_id');
    }
}

