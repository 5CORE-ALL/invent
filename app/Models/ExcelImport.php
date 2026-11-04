<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExcelImport extends Model
{
    protected $fillable = [
        'original_filename',
        'stored_filename',
        'total_worksheets',
        'uploaded_by',
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

    public function scopeOwnedBy(Builder $query, int $userId): Builder
    {
        return $query->where('uploaded_by', $userId);
    }
}
