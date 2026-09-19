<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ScopeOfImprovement extends Model
{
    protected $table = 'scope_of_improvements';

    protected $fillable = [
        'user_id',
        'issue',
        'root_cause',
        'fixing_root_cause',
        's_by',
        'history',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'history' => 'array',
    ];

    protected static function booted(): void
    {
        // Restored dumps often drop AUTO_INCREMENT on id (SQLSTATE 1364).
        static::creating(function (self $model) {
            if ($model->getAttribute($model->getKeyName()) !== null) {
                return;
            }

            try {
                $col = DB::selectOne("SHOW COLUMNS FROM `{$model->getTable()}` WHERE Field = 'id'");
                $extra = strtolower((string) ($col->Extra ?? ''));
                if (str_contains($extra, 'auto_increment')) {
                    return;
                }
            } catch (\Throwable) {
                // fall through and assign manually
            }

            $model->id = ((int) (DB::table($model->getTable())->max('id') ?? 0)) + 1;
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
