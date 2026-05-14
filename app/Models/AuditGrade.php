<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditGrade extends Model
{
    protected $table = 'audit_grades';

    protected $fillable = [
        'module',
        'grade',
        'min_score',
        'max_score',
        'color',
        'description',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'min_score' => 'decimal:2',
        'max_score' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    /**
     * Return the grade bands that should be used for a given module.
     * If the requested module has its own bands they take priority,
     * otherwise the global rows (module IS NULL) are returned.
     */
    public static function bandsForModule(?string $module = null)
    {
        if ($module !== null && $module !== '') {
            $rows = self::query()->active()
                ->where('module', $module)
                ->orderByDesc('min_score')
                ->get();
            if ($rows->isNotEmpty()) {
                return $rows;
            }
        }

        return self::query()->active()
            ->whereNull('module')
            ->orderByDesc('min_score')
            ->get();
    }

    /**
     * Resolve the matching grade band for a given total score (within a module
     * if supplied, otherwise the global default set). Falls back to the lowest
     * defined band ('F' typically) when nothing matches.
     */
    public static function forScore(float $score, ?string $module = null): ?self
    {
        $bands = self::bandsForModule($module);

        foreach ($bands as $band) {
            if ($score >= (float) $band->min_score && $score <= (float) $band->max_score) {
                return $band;
            }
        }

        return $bands->last();
    }
}
