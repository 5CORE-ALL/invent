<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Supplier extends Model
{
    use HasFactory;

    protected $fillable = [
        'type', 'category_id', 'name', 'company', 'sku', 'parent', 'phone', 'city',
        'email', 'whatsapp', 'wechat', 'alibaba', 'others', 'address', 'bank_details',
        'approval_status',
    ];

    public function ratings()
    {
        return $this->hasMany(SupplierRating::class);
    }

    public function remarkHistories()
    {
        return $this->hasMany(SupplierRemarkHistory::class)->orderByDesc('id');
    }

    public function latestRemark()
    {
        return $this->hasOne(SupplierRemarkHistory::class)->latestOfMany();
    }

    /**
     * Distinct non-empty supplier names, ordered by name — same catalog as /supplier.list with no type/category filters.
     */
    public static function distinctNamesForListPage(): Collection
    {
        return static::query()
            ->whereNotNull('name')
            ->where('name', '!=', '')
            ->orderBy('name')
            ->pluck('name')
            ->unique()
            ->values();
    }

    /**
     * One row per distinct name (lowest id first) for JSON dropdowns — aligned with {@see distinctNamesForListPage()}.
     *
     * @return Collection<int, self>
     */
    public static function distinctNameRowsForDropdownJson(): Collection
    {
        return static::query()
            ->whereNotNull('name')
            ->where('name', '!=', '')
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name'])
            ->unique('name')
            ->values();
    }

}
