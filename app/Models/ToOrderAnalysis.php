<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class ToOrderAnalysis extends Model
{
    use HasFactory, SoftDeletes;
    
    protected $table = 'to_order_analysis';

    protected $fillable = [
        'parent',
        'sku',
        'rfq_form_link',
        'date_apprvl',
        'mail_sent',
        'rfq_report',
        'stage',
        'nrl',
        'sheet_link',
        'supplier_name',
        'advance_date',
        'order_qty',
        'auth_user',
    ];
    
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
}
