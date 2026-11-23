<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A QR (quotation request) form sent to one supplier for one comparison SKU.
 * The supplier opens a personal link, fills the Critical specs, and the values land in
 * that supplier's column of the comparison sheet.
 */
class ComparisonQuoteRequest extends Model
{
    protected $table = 'comparison_quote_requests';

    protected $fillable = [
        'sku',
        'parent',
        'supplier_name',
        'supplier_key',
        'supplier_email',
        'token',
        'sent_by',
        'sent_at',
        'submitted_at',
        'submitted_values',
        'last_error',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'submitted_at' => 'datetime',
    ];
}
