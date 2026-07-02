<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;

/**
 * Encode/decode department lists for customer-care issue tables.
 * Stored as JSON array, e.g. ["Dispatch","QC"] — legacy single string values are still read.
 */
class CustomerCareDepartments
{
    /**
     * User-facing labels for stored department values. Keys are the exact
     * strings persisted in the `department` JSON column; values are unchanged.
     */
    private const DISPLAY_LABELS = [
        'Carrier' => 'Carrier Claims',
        'Carrier Issue' => 'Carrier Scan Issue',
        // Legacy / import aliases still seen in older rows.
        'Carrier and Claim' => 'Carrier Claims',
        'Carriers Claims' => 'Carrier Claims',
    ];

    /**
     * Map display labels / legacy aliases to the canonical value stored in DB
     * and used in department filters (option value= attributes).
     */
    private const CANONICAL_ALIASES = [
        'carrier' => 'Carrier',
        'carrier and claim' => 'Carrier',
        'carriers claims' => 'Carrier',
        'carrier claims' => 'Carrier',
        'carrier claim' => 'Carrier',
        'carrier issue' => 'Carrier Issue',
        'carrier scan issue' => 'Carrier Issue',
        'carrier scan issues' => 'Carrier Issue',
        'carrier claim issues' => 'Carrier Issue',
    ];

    public static function canonicalDepartment(string $value): string
    {
        $t = trim($value);
        if ($t === '') {
            return '';
        }
        $lower = strtolower($t);

        return self::CANONICAL_ALIASES[$lower] ?? $t;
    }

    public static function displayLabel(string $value): string
    {
        $t = trim($value);

        return self::DISPLAY_LABELS[$t] ?? $t;
    }

    /**
     * @return list<string>
     */
    public static function displayLabelsList(?string $raw): array
    {
        return array_map(
            [self::class, 'displayLabel'],
            self::decode($raw)
        );
    }

    /**
     * @return list<string>
     */
    public static function decode(?string $raw): array
    {
        if ($raw === null) {
            return [];
        }
        $t = trim((string) $raw);
        if ($t === '') {
            return [];
        }
        if ($t[0] === '[') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return self::normalizeStringList($decoded);
            }
        }

        return self::normalizeStringList([$t]);
    }

    /**
     * @param  list<string|mixed>  $departments
     */
    public static function encode(array $departments): string
    {
        $list = self::normalizeStringList($departments);
        sort($list);

        return json_encode($list, JSON_UNESCAPED_UNICODE);
    }

    public static function label(?string $raw): string
    {
        $list = self::displayLabelsList($raw);

        return implode(', ', $list);
    }

    /**
     * CSV / paste: "Dispatch|QC", "Dispatch, QC", or JSON array string.
     *
     * @return list<string>
     */
    public static function parseFromImportCell(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }
        if ($raw[0] === '[') {
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? self::normalizeStringList($decoded) : [];
        }
        $parts = preg_split('/[|,]/', $raw) ?: [];

        return self::normalizeStringList($parts);
    }

    /**
     * @param  list<string|mixed>  $items
     * @return list<string>
     */
    public static function normalizeStringList(array $items): array
    {
        $out = [];
        foreach ($items as $item) {
            $s = self::canonicalDepartment(trim((string) $item));
            if ($s !== '') {
                $out[] = $s;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * Strings to try inside JSON_CONTAINS for case / formatting variants (MySQL JSON string compare is case-sensitive).
     *
     * @return list<string>
     */
    public static function departmentJsonMatchStrings(string $department): array
    {
        $t = trim($department);
        if ($t === '') {
            return [];
        }
        $low = strtolower($t);

        return self::normalizeStringList([
            $t,
            $low,
            ucfirst($low),
            strtoupper($low),
            ucwords($low),
        ]);
    }

    /**
     * Match rows where $department is one of the stored departments (JSON array or legacy plain string).
     */
    public static function applyWhereDepartmentMatches(Builder $query, string $column, string $department): void
    {
        $department = trim($department);
        if ($department === '') {
            return;
        }
        $lowerNeedle = strtolower($department);
        // Match the quoted element inside the JSON-array string, e.g. ["Chargeback"].
        // Uses LOWER(col) LIKE instead of JSON_CONTAINS/CAST(.. AS JSON) so it works
        // on both MySQL and MariaDB (MariaDB does not support CAST(.. AS JSON)).
        $likeToken = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], '"'.$lowerNeedle.'"').'%';
        $query->where(function ($q) use ($column, $department, $lowerNeedle, $likeToken) {
            $q->whereRaw('LOWER(`'.$column.'`) LIKE ?', [$likeToken])
                ->orWhere($column, $department)
                ->orWhereRaw(
                    '(LEFT(TRIM(`'.$column.'`), 1) <> ?) AND LOWER(TRIM(`'.$column.'`)) = ?',
                    ['[', $lowerNeedle]
                );
        });
    }
}
