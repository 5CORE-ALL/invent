<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ComplianceCertificate extends Model
{
    use HasFactory;

    protected $fillable = [
        'sku',
        'inv',
        'fcc',
        'gcc',
        'ul',
        'battery',
        'certificate_available',
        'certificate_files',
        'status',
        'updated_by',
    ];

    protected $casts = [
        'certificate_available' => 'array',
    ];
}
