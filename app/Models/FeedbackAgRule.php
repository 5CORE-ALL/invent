<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * AG-MSG rule for the Feedback team table: when a person's row matches
 * "<field> <operator> <value>", the message is sent to them from president@5core.com.
 */
class FeedbackAgRule extends Model
{
    public const TABLE = 'feedback_ag_rules';

    public const FIELDS = ['status', 'avg', 'reviews', 'ratings'];

    public const OPERATORS = ['=', '!=', '<', '<=', '>', '>='];

    protected $table = self::TABLE;

    protected $fillable = [
        'field', 'operator', 'value', 'message', 'allow_reply', 'is_active', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'allow_reply' => 'boolean',
        'is_active' => 'boolean',
    ];

    public static function tableReady(): bool
    {
        return Schema::hasTable(self::TABLE);
    }

    /** @param  array{status: string, avg: ?float, reviews: int, ratings: int}  $m */
    public function matches(array $m): bool
    {
        if ($this->field === 'status') {
            $is = $m['status'] === $this->value;

            return $this->operator === '!=' ? ! $is : $is;
        }

        $actual = $m[$this->field] ?? null;
        if ($actual === null || ! is_numeric($this->value)) {
            return false;
        }
        $actual = (float) $actual;
        $target = (float) $this->value;

        return match ($this->operator) {
            '=' => abs($actual - $target) < 0.005,
            '!=' => abs($actual - $target) >= 0.005,
            '<' => $actual < $target,
            '<=' => $actual <= $target,
            '>' => $actual > $target,
            '>=' => $actual >= $target,
            default => false,
        };
    }
}
