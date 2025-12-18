<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ADVMastersDailyData extends Model
{
    use HasFactory;

    protected $table = 'adv_masters_daily_datas';
    protected $primaryKey = 'adv_masters_daily_data_id';  
    public $timestamps = false;

    protected $fillable = [
        'date',
        'channel',
        'spent',
        'clicks',
        'ad_sales',
        'ad_sold',
        'missing_ads',
        'l30_sales',
        'cpc',
        'cvr',
        'acos',
        'tacos',
        'gpft',
        'tpft'
    ];












    
}
