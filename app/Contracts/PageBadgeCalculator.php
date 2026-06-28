<?php

namespace App\Contracts;

interface PageBadgeCalculator
{
    public static function pageName(): string;

    /**
     * @return array<string, int|float|string|null>
     */
    public static function calculate(): array;

    public static function syncBeforeCalculate(): void;
}
