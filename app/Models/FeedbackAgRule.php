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
        'field', 'operator', 'value', 'conditions', 'match_mode', 'message', 'allow_reply', 'is_active', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'conditions' => 'array',
        'allow_reply' => 'boolean',
        'is_active' => 'boolean',
    ];

    public static function tableReady(): bool
    {
        return Schema::hasTable(self::TABLE);
    }

    /** Multiple-condition columns exist (migration 2026_11_10_000200 has run). */
    public static function conditionsReady(): bool
    {
        return Schema::hasColumn(self::TABLE, 'conditions') && Schema::hasColumn(self::TABLE, 'match_mode');
    }

    /**
     * The rule's conditions (falls back to the single field/operator/value of older rules).
     *
     * @return list<array{field: string, operator: string, value: string}>
     */
    public function conditionList(): array
    {
        $list = is_array($this->conditions) ? $this->conditions : [];
        if ($list === []) {
            $list = [['field' => $this->field, 'operator' => $this->operator, 'value' => $this->value]];
        }

        return array_values($list);
    }

    /** @param  array{status: string, avg: ?float, reviews: int, ratings: int}  $m */
    public function matches(array $m): bool
    {
        $any = $this->match_mode === 'any';
        foreach ($this->conditionList() as $c) {
            $ok = self::conditionMatches($c['field'] ?? '', $c['operator'] ?? '=', (string) ($c['value'] ?? ''), $m);
            if ($any && $ok) {
                return true;
            }
            if (! $any && ! $ok) {
                return false;
            }
        }

        return ! $any;
    }

    /** @param  array{status: string, avg: ?float, reviews: int, ratings: int}  $m */
    public static function conditionMatches(string $field, string $operator, string $value, array $m): bool
    {
        if ($field === 'status') {
            $is = $m['status'] === $value;

            return $operator === '!=' ? ! $is : $is;
        }

        $actual = $m[$field] ?? null;
        if ($actual === null || ! is_numeric($value)) {
            return false;
        }
        $actual = (float) $actual;
        $target = (float) $value;

        return match ($operator) {
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
