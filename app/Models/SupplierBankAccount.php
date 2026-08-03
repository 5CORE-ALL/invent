<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierBankAccount extends Model
{
    protected $fillable = [
        'supplier_id',
        'supplier_name',
        'nick_name',
        'company_name',
        'swift',
        'address',
        'city',
        'province',
        'country',
        'account_number',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(SupplierBankAccountHistory::class);
    }
}
