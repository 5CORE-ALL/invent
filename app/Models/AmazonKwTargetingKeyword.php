<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AmazonKwTargetingKeyword extends Model
{
    protected $table = 'amazon_kw_targeting_keywords';

    protected $fillable = [
        'campaign_id',
        'campaign_name',
        'ad_group_id',
        'keyword_id',
        'keyword',
        'keyword_key',
        'match_type',
        'state',
        'source',
    ];

    public static function normalizeKey(string $keyword): string
    {
        return mb_substr(mb_strtolower(preg_replace('/\s+/u', ' ', trim($keyword))), 0, 255);
    }
}
