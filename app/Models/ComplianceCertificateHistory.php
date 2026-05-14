<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ComplianceCertificateHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'sku',
        'certificate_id',
        'action',
        'description',
        'changes',
        'files_uploaded',
        'updated_by',
    ];

    protected $casts = [
        'changes' => 'array',
    ];
}
