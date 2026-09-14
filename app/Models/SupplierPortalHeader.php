<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class SupplierPortalHeader extends Model
{
    protected $table = 'supplier_portal_headers';

    protected $fillable = [
        'category',
        'title',
        'instructions',
        'sort_order',
    ];

    /**
     * @return array<string, \Illuminate\Support\Collection<int, self>>
     */
    public static function groupedByCategory(): array
    {
        $out = [];
        foreach (array_keys(SupplierPortalAsset::CATEGORIES) as $key) {
            $out[$key] = collect();
        }

        if (! Schema::hasTable('supplier_portal_headers')) {
            return $out;
        }

        $rows = static::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        foreach ($rows as $row) {
            $key = SupplierPortalAsset::resolveCategoryKey((string) $row->category)
                ?? (string) $row->category;
            if (! isset($out[$key])) {
                continue;
            }
            $out[$key]->push($row);
        }

        return $out;
    }
}
