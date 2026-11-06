<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class ExcelImport extends Model
{
    /** eBay account => label. Each account has its own Listing Quality Report upload. */
    public const ACCOUNTS = ['ebay1' => 'eBay 1', 'ebay2' => 'eBay 2', 'ebay3' => 'eBay 3'];

    public static function hasAccountColumn(): bool
    {
        static $has = null;

        return $has ??= Schema::hasColumn('excel_imports', 'account');
    }

    protected $fillable = [
        'original_filename',
        'stored_filename',
        'total_worksheets',
        'uploaded_by',
        'account',
    ];

    protected $casts = [
        'total_worksheets' => 'integer',
        'uploaded_by' => 'integer',
    ];

    public function worksheets(): HasMany
    {
        return $this->hasMany(ExcelWorksheet::class)->orderBy('worksheet_index');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** Imports of one eBay account. Before the account column exists, everything is eBay 1. */
    public function scopeForAccount(Builder $query, string $account = 'ebay1'): Builder
    {
        if (! self::hasAccountColumn()) {
            return $account === 'ebay1' ? $query : $query->whereRaw('1 = 0');
        }

        return $query->where('account', $account);
    }

    public function scopeOwnedBy(Builder $query, int $userId): Builder
    {
        return $query->where('uploaded_by', $userId);
    }
}
