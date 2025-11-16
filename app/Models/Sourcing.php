<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sourcing extends Model
{
    use HasFactory;

    protected $table = 'sourcings';

    protected $fillable = [
        'target_item',
        'target_link1',
        'target_link2',
        'product_description',
        'rfq_form',
        'rfq_report',
        'status',
    ];
}
