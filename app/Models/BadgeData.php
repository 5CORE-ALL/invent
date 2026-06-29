<?php

namespace App\Models;

use App\Support\Badges\BadgeCalculatorRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class BadgeData extends Model
{
    protected $table = 'badges_data';

    public $timestamps = false;

    protected $fillable = [
        'page_name',
        'data',
        'updated_at',
    ];

    protected $casts = [
        'data' => 'array',
        'updated_at' => 'datetime',
    ];

    public static function saveForPage(string $pageName, array $data): self
    {
        return self::updateOrCreate(
            ['page_name' => $pageName],
            ['data' => $data, 'updated_at' => now()]
        );
    }

    public static function forPage(string $pageName): ?self
    {
        return self::query()->where('page_name', $pageName)->first();
    }

    public static function dataForPage(string $pageName, array $defaults = []): array
    {
        return array_merge($defaults, self::forPage($pageName)?->data ?? []);
    }

    /**
     * @param  list<string>  $pageNames
     * @return array<string, array<string, mixed>>
     */
    public static function dataForPages(array $pageNames): array
    {
        $rows = self::query()
            ->whereIn('page_name', $pageNames)
            ->get()
            ->keyBy('page_name');

        $result = [];
        foreach ($pageNames as $pageName) {
            $result[$pageName] = $rows->get($pageName)?->data ?? [];
        }

        return $result;
    }

    /**
     * @return array{page_name: string, data: array<string, mixed>}
     */
    public static function saveForCalculator(string $calculatorClass): array
    {
        $calculatorClass::syncBeforeCalculate();
        $data = $calculatorClass::calculate();
        self::saveForPage($calculatorClass::pageName(), $data);

        return [
            'page_name' => $calculatorClass::pageName(),
            'data' => $data,
        ];
    }

    /**
     * @return Collection<int, array{page_name: string, data: array<string, mixed>}>
     */
    public static function saveAllRegistered(): Collection
    {
        return collect(BadgeCalculatorRegistry::all())
            ->map(fn (string $calculatorClass) => self::saveForCalculator($calculatorClass));
    }
}
