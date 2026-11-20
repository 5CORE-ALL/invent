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
        'address_2',
        'city',
        'province',
        'country',
        'account_number',
        'acc_type',
        'currency',
        'file_image',
        'snippet_image',
    ];

    protected $appends = ['file_image_url', 'snippet_image_url'];

    public function getFileImageUrlAttribute(): ?string
    {
        return $this->file_image ? asset('storage/' . $this->file_image) : null;
    }

    public function getSnippetImageUrlAttribute(): ?string
    {
        return $this->snippet_image ? asset('storage/' . $this->snippet_image) : null;
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(SupplierBankAccountHistory::class);
    }
}
