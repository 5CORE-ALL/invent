<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExcelWorksheet extends Model
{
    protected $fillable = [
        'excel_import_id',
        'worksheet_name',
        'worksheet_index',
        'total_rows',
        'headers',
    ];

    protected $casts = [
        'excel_import_id' => 'integer',
        'worksheet_index' => 'integer',
        'total_rows' => 'integer',
        'headers' => 'array',
    ];

    public function import(): BelongsTo
    {
        return $this->belongsTo(ExcelImport::class, 'excel_import_id');
    }

    public function rows(): HasMany
    {
        return $this->hasMany(ExcelWorksheetRow::class);
    }

    public function scopeForAccount(Builder $query, string $account = 'ebay1'): Builder
    {
        return $query->whereHas('import', function (Builder $import) use ($account) {
            $import->forAccount($account);
        });
    }

    public function scopeOwnedBy(Builder $query, int $userId): Builder
    {
        return $query->whereHas('import', function (Builder $import) use ($userId) {
            $import->where('uploaded_by', $userId);
        });
    }
}
