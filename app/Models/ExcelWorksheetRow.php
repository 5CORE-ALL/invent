<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExcelWorksheetRow extends Model
{
    protected $fillable = [
        'excel_worksheet_id',
        'row_index',
        'data',
        'search_text',
    ];

    protected $casts = [
        'excel_worksheet_id' => 'integer',
        'row_index' => 'integer',
        'data' => 'array',
    ];

    public function worksheet(): BelongsTo
    {
        return $this->belongsTo(ExcelWorksheet::class, 'excel_worksheet_id');
    }
}
