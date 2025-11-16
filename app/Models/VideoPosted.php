<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VideoPosted extends Model
{
    use HasFactory;

    protected $table = 'video_posted_values';
    protected $fillable = ['sku','value',];
    protected $casts = [
        'value' => 'array',
    ];
}

class AssemblyVideo extends Model
{
    use HasFactory;

    protected $table = 'assembly_videos';
    protected $fillable = ['sku', 'value'];
    protected $casts = [
        'value' => 'array',
    ];
}

class ThreeDVideo extends Model
{
    use HasFactory;

    protected $table = 'three_d_videos';
    protected $fillable = ['sku', 'value'];
    protected $casts = [
        'value' => 'array',
    ];
}

class Video360 extends Model
{
    use HasFactory;

    protected $table = 'video_360s';
    protected $fillable = ['sku', 'value'];
    protected $casts = [
        'value' => 'array',
    ];
}

class BenefitVideo extends Model
{
    use HasFactory;

    protected $table = 'benefit_videos';
    protected $fillable = ['sku', 'value'];
    protected $casts = [
        'value' => 'array',
    ];
}

class DiyVideo extends Model
{
    use HasFactory;

    protected $table = 'diy_videos';
    protected $fillable = ['sku', 'value'];
    protected $casts = [
        'value' => 'array',
    ];
}

class ShoppableVideo extends Model
{
    use HasFactory;

    protected $table = 'shoppable_videos';
    protected $fillable = ['sku', 'value'];
    protected $casts = [
        'value' => 'array',
    ];
}
