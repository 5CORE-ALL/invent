<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;

/**
 * 64-bit difference hash (dHash) of a screenshot. Two screenshots of the same unchanged screen
 * hash to (nearly) the same value even when the taskbar clock ticks, so a long run of
 * near-identical hashes means nothing on screen moved.
 */
class ScreenshotHash
{
    private static ?bool $columnExists = null;

    public static function columnExists(): bool
    {
        return self::$columnExists ??= Schema::hasColumn('attendance_screenshots', 'phash');
    }

    /**
     * @return string|null 16 hex chars, or null when the image cannot be decoded
     */
    public static function fromBinary(string $binary): ?string
    {
        if ($binary === '' || ! function_exists('imagecreatefromstring')) {
            return null;
        }

        $src = @imagecreatefromstring($binary);
        if (! $src) {
            return null;
        }

        $small = imagecreatetruecolor(9, 8);
        imagecopyresampled($small, $src, 0, 0, 0, 0, 9, 8, imagesx($src), imagesy($src));

        $lum = [];
        for ($y = 0; $y < 8; $y++) {
            for ($x = 0; $x < 9; $x++) {
                $rgb = imagecolorat($small, $x, $y);
                $lum[$y][$x] = (0.299 * (($rgb >> 16) & 0xFF)) + (0.587 * (($rgb >> 8) & 0xFF)) + (0.114 * ($rgb & 0xFF));
            }
        }

        $bits = '';
        for ($y = 0; $y < 8; $y++) {
            for ($x = 0; $x < 8; $x++) {
                $bits .= $lum[$y][$x] > $lum[$y][$x + 1] ? '1' : '0';
            }
        }

        return sprintf('%08x%08x', bindec(substr($bits, 0, 32)), bindec(substr($bits, 32, 32)));
    }

    public static function fromFile(string $path): ?string
    {
        $binary = @file_get_contents($path);

        return $binary === false ? null : self::fromBinary($binary);
    }

    /**
     * Number of differing bits between two hashes (0 = identical, 64 = opposite).
     */
    public static function distance(string $a, string $b): int
    {
        if (strlen($a) !== 16 || strlen($b) !== 16) {
            return 64;
        }

        $bits = 0;
        foreach ([0, 8] as $offset) {
            $xor = hexdec(substr($a, $offset, 8)) ^ hexdec(substr($b, $offset, 8));
            while ($xor) {
                $bits += $xor & 1;
                $xor >>= 1;
            }
        }

        return $bits;
    }
}
