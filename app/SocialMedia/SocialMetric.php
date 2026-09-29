<?php

namespace App\SocialMedia;

class SocialMetric
{
    public const AVAILABLE = 'available';

    public const NOT_AVAILABLE = 'not_available';

    public function __construct(
        public readonly string $name,
        public readonly ?float $value,
        public readonly string $availability,
    ) {
    }

    public static function available(string $name, float|int|string|null $value): self
    {
        if ($value === null || $value === '') {
            return self::missing($name);
        }

        return new self($name, (float) $value, self::AVAILABLE);
    }

    public static function missing(string $name): self
    {
        return new self($name, null, self::NOT_AVAILABLE);
    }

    public function isAvailable(): bool
    {
        return $this->availability === self::AVAILABLE && $this->value !== null;
    }

    /**
     * @return array{value: float|null, availability: string}
     */
    public function toArray(): array
    {
        return [
            'value' => $this->value,
            'availability' => $this->availability,
        ];
    }
}
