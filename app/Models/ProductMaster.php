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
        'sku',
        'group_id',
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
        'video_unboxing',
        'video_how_to',
        'video_setup',
        'video_troubleshooting',
        'video_brand_story',
        'video_product_benefits',
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
    ];
}
