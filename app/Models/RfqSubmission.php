<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RfqSubmission extends Model
{
    protected $fillable = [
        'rfq_form_id',
        'data'
    ];

    protected $casts = [
        'data' => 'array',
    ];

    public function form()
    {
        return $this->belongsTo(RfqForm::class, 'rfq_form_id');
    }
}
