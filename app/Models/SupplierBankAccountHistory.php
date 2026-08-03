<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierBankAccountHistory extends Model
{
    protected $fillable = [
        'supplier_id',
        'supplier_bank_account_id',
        'user_id',
        'user_name',
        'action',
        'changes',
    ];

    protected $casts = [
        'changes' => 'array',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(SupplierBankAccount::class, 'supplier_bank_account_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
