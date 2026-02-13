<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryImportBatch extends Model
{
    protected $fillable = [
        'filename',
        'filepath',
        'total_rows',
        'processed_rows',
        'successful_rows',
        'failed_rows',
        'status',
        'error_message',
        'started_at',
        'completed_at',
        'created_by',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function errors()
    {
        return $this->hasMany(InventoryImportError::class, 'batch_id');
    }

    public function logs()
    {
        return $this->hasMany(InventoryLog::class, 'batch_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function markAsProcessing()
    {
        $this->update([
            'status' => 'processing',
            'started_at' => now(),
        ]);
    }

    public function markAsCompleted()
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    public function markAsFailed($errorMessage)
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
            'completed_at' => now(),
        ]);
    }

    public function incrementProcessed($successful = true)
    {
        $this->increment('processed_rows');
        if ($successful) {
            $this->increment('successful_rows');
        } else {
            $this->increment('failed_rows');
        }
    }
}
