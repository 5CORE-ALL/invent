<?php

namespace App\Models\Wms;

use App\Models\Inventory;
use App\Models\ProductMaster;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    public const TYPE_GRN = 'GRN';

    public const TYPE_PUTAWAY = 'PUTAWAY';

    public const TYPE_PICK = 'PICK';

    public const TYPE_PACK = 'PACK';

    public const TYPE_DISPATCH = 'DISPATCH';

    public const TYPE_ADJUSTMENT = 'ADJUSTMENT';

    /** Spare parts module (also stored in `stock_movements.type`) */
    public const TYPE_ISSUE = 'issue';

    public const TYPE_PURCHASE = 'purchase';

    public const TYPE_SPARE_ADJUSTMENT = 'adjustment';

    protected $table = 'stock_movements';

    protected $fillable = [
        'product_id',
        'sku',
        'from_bin_id',
        'to_bin_id',
        'qty',
        'type',
        'user_id',
        'inventory_id',
        'note',
        'reference_type',
        'reference_id',
    ];

    protected $casts = [
        'qty' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(ProductMaster::class, 'product_id');
    }

    public function fromBin(): BelongsTo
    {
        return $this->belongsTo(Bin::class, 'from_bin_id');
    }

    public function toBin(): BelongsTo
    {
        return $this->belongsTo(Bin::class, 'to_bin_id');
    }

    /** Row in `inventories` this movement primarily refers to (meaning varies by type). */
    public function inventory(): BelongsTo
    {
        return $this->belongsTo(Inventory::class, 'inventory_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function types(): array
    {
        return [
            self::TYPE_GRN,
            self::TYPE_PUTAWAY,
            self::TYPE_PICK,
            self::TYPE_PACK,
            self::TYPE_DISPATCH,
            self::TYPE_ADJUSTMENT,
            self::TYPE_ISSUE,
            self::TYPE_PURCHASE,
            self::TYPE_SPARE_ADJUSTMENT,
        ];
    }
}
