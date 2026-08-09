<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserDashboardPreference extends Model
{
    protected $table = 'user_dashboard_preferences';

    protected $fillable = [
        'user_id',
        'hidden_items',
        'custom_links',
        'custom_kpis',
    ];

    protected $casts = [
        'hidden_items' => 'array',
        'custom_links' => 'array',
        'custom_kpis' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function forUser(int $userId): self
    {
        return self::query()->firstOrCreate(
            ['user_id' => $userId],
            [
                'hidden_items' => [],
                'custom_links' => [],
                'custom_kpis' => [],
            ]
        );
    }

    /**
     * @return array{hidden_items: list<string>, custom_links: array<string, list<array{label:string,url:string}>>, custom_kpis: array<string, list<array{key:string,label?:string}>>}
     */
    public function asPayload(): array
    {
        return [
            'hidden_items' => array_values(array_filter(array_map('strval', $this->hidden_items ?? []))),
            'custom_links' => is_array($this->custom_links) ? $this->custom_links : [],
            'custom_kpis' => is_array($this->custom_kpis) ? $this->custom_kpis : [],
        ];
    }
}
