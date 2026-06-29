<?php

namespace App\Models;

use App\Support\Badges\BadgeDataCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamMemberKpi extends Model
{
    protected $table = 'team_member_kpis';

    protected $fillable = [
        'user_id',
        'email',
        'kpi_1_label',
        'kpi_1_value',
        'kpi_2_label',
        'kpi_2_value',
        'kpi_3_label',
        'kpi_3_value',
        'kpi_4_label',
        'kpi_4_value',
        'kpi_5_label',
        'kpi_5_value',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function forUser(User $user): self
    {
        return self::firstOrCreate(
            ['user_id' => $user->id],
            ['email' => $user->email]
        );
    }

    public function nextFreeSlot(): ?int
    {
        for ($k = 1; $k <= 5; $k++) {
            if (! BadgeDataCatalog::parseKey($this->{"kpi_{$k}_value"})) {
                return $k;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function assignedKeys(): array
    {
        $keys = [];

        for ($k = 1; $k <= 5; $k++) {
            $value = $this->{"kpi_{$k}_value"};
            if ($value) {
                $keys[] = $value;
            }
        }

        return $keys;
    }

    public function clearSlot(int $slot): void
    {
        if ($slot < 1 || $slot > 5) {
            return;
        }

        $this->{"kpi_{$slot}_label"} = null;
        $this->{"kpi_{$slot}_value"} = null;
    }
}
