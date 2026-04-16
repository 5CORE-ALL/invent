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

        return [$t];
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
        return implode(', ', self::decode($raw));
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
            $s = trim((string) $item);
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
        $query->where(function ($q) use ($column, $department, $lowerNeedle) {
            foreach (self::departmentJsonMatchStrings($department) as $variant) {
                $jsonFragment = json_encode($variant, JSON_UNESCAPED_UNICODE);
                $q->orWhereRaw(
                    '(JSON_VALID(`'.$column.'`) AND JSON_CONTAINS(CAST(`'.$column.'` AS JSON), CAST(? AS JSON), \'$\'))',
                    [$jsonFragment]
                );
            }
            $q->orWhere($column, $department)
                ->orWhereRaw(
                    '(NOT JSON_VALID(`'.$column.'`) OR LEFT(TRIM(`'.$column.'`), 1) <> ?) AND LOWER(TRIM(`'.$column.'`)) = ?',
                    ['[', $lowerNeedle]
                );
        });
    }
}
