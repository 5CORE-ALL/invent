<?php

namespace App\Models\Wms;

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
        ];
    }
}
