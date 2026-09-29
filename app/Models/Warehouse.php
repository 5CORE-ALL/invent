<?php

namespace App\Models;

use App\Models\Wms\Zone;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;

class Warehouse extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = ['name', 'code', 'group', 'location', 'status'];

    public function zones(): HasMany
    {
        return $this->hasMany(Zone::class, 'warehouse_id');
    }

    /**
     * The single warehouse used for inventory transactions.
     * Prefers the row named Main Warehouse, then Main Godown, then Main.
     */
    public static function main(): ?self
    {
        return static::query()
            ->where(function ($q) {
                $q->whereRaw("LOWER(REPLACE(TRIM(name), ' ', '')) = ?", ['mainwarehouse'])
                    ->orWhereRaw("LOWER(REPLACE(TRIM(name), ' ', '')) = ?", ['maingodown'])
                    ->orWhereRaw('LOWER(TRIM(name)) = ?', ['main']);
            })
            ->orderByRaw("CASE
                WHEN LOWER(REPLACE(TRIM(name), ' ', '')) = 'mainwarehouse' THEN 0
                WHEN LOWER(REPLACE(TRIM(name), ' ', '')) = 'maingodown' THEN 1
                ELSE 2 END")
            ->first();
    }

    public static function mainId(): ?int
    {
        $id = static::main()?->id;

        return $id ? (int) $id : null;
    }

    /**
     * @return Collection<int, self>
     */
    public static function inventoryOptions(): Collection
    {
        $main = static::main();
        if ($main === null) {
            return static::query()->select('id', 'name')->orderBy('name')->get();
        }

        return static::query()->select('id', 'name')->whereKey($main->id)->get();
    }

    public static function forceOnRequest(Request $request, string $field = 'warehouse_id'): void
    {
        $id = static::mainId();
        if ($id) {
            $request->merge([$field => $id]);
        }
    }
}
