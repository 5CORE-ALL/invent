<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReverbViewData extends Model
{
    use HasFactory;

    protected $table = 'reverb_view_data';

    protected $fillable = [
        'sku',
        'parent',
        'values',
    ];

    protected $casts = [
        'values' => 'array',
    ];
}
