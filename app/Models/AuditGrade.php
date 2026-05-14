<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditGrade extends Model
{
    protected $table = 'audit_grades';

    protected $fillable = [
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

    /**
     * Resolve the matching grade band for a given total score.
     * Always returns the lowest band ('F') if nothing matches.
     */
    public static function forScore(float $score): ?self
    {
        $bands = self::where('is_active', true)
            ->orderByDesc('min_score')
            ->get();

        foreach ($bands as $band) {
            if ($score >= (float) $band->min_score && $score <= (float) $band->max_score) {
                return $band;
            }
        }

        return $bands->last();
    }
}
