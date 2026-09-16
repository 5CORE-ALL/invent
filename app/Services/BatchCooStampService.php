<?php

namespace App\Services;

class BatchCooStampService
{
    /**
     * @var array<string, string>
     */
    public const PRESETS = [
        'china' => 'MADE IN CHINA',
        'india' => 'MADE IN INDIA',
        'usa' => 'MADE IN USA',
        'vietnam' => 'MADE IN VIETNAM',
        'mexico' => 'MADE IN MEXICO',
        'taiwan' => 'MADE IN TAIWAN',
        'korea' => 'MADE IN KOREA',
        'japan' => 'MADE IN JAPAN',
        'thailand' => 'MADE IN THAILAND',
        'indonesia' => 'MADE IN INDONESIA',
    ];

    /**
     * Normalize a country / preset / custom line into marketplace stamp text.
     */
    public function label(?string $countryOrPreset, ?string $custom = null): string
    {
        $custom = trim((string) $custom);
        if ($custom !== '') {
            return $this->normalizeLabel($custom);
        }

        $raw = trim((string) $countryOrPreset);
        if ($raw === '') {
            return self::PRESETS['china'];
        }

        $key = $this->presetKey($raw);
        if ($key !== null) {
            return self::PRESETS[$key];
        }

        return $this->normalizeLabel($raw);
    }

    public function presetKey(?string $value): ?string
    {
        $norm = strtolower(trim((string) $value));
        $norm = str_replace(['made in ', 'made-in-', 'country of origin', 'coo'], '', $norm);
        $norm = trim($norm, " \t:-");

        return match ($norm) {
            'cn', 'chn', 'china', 'prc', 'people\'s republic of china' => 'china',
            'in', 'ind', 'india' => 'india',
            'us', 'usa', 'united states', 'united states of america', 'america' => 'usa',
            'vn', 'vnm', 'vietnam', 'viet nam' => 'vietnam',
            'mx', 'mex', 'mexico' => 'mexico',
            'tw', 'twn', 'taiwan' => 'taiwan',
            'kr', 'kor', 'korea', 'south korea', 'republic of korea' => 'korea',
            'jp', 'jpn', 'japan' => 'japan',
            'th', 'tha', 'thailand' => 'thailand',
            'id', 'idn', 'indonesia' => 'indonesia',
            default => array_key_exists($norm, self::PRESETS) ? $norm : null,
        };
    }

    /**
     * Stamp COO text onto the bottom of a product photo. Returns JPEG bytes.
     */
    public function stamp(string $imageBytes, string $label, ?string $secondLine = null): string
    {
        if (! function_exists('imagecreatefromstring')) {
            throw new \RuntimeException('GD is required to stamp Made in / COO text.');
        }

        $src = @imagecreatefromstring($imageBytes);
        if (! $src) {
            throw new \RuntimeException('Could not read the source image for the COO stamp.');
        }

        $label = $this->normalizeLabel($label);
        $secondLine = trim((string) $secondLine);
        if ($secondLine !== '') {
            $secondLine = $this->normalizeLabel($secondLine);
        }

        $w = imagesx($src);
        $h = imagesy($src);
        $size = max($w, $h, 800);
        $dst = imagecreatetruecolor($size, $size);
        imagealphablending($dst, true);
        $white = imagecolorallocate($dst, 255, 255, 255);
        imagefilledrectangle($dst, 0, 0, $size, $size, $white);

        $scale = min($size / max($w, 1), ($size * 0.82) / max($h, 1));
        $nw = (int) max(1, round($w * $scale));
        $nh = (int) max(1, round($h * $scale));
        $x = (int) (($size - $nw) / 2);
        $y = (int) max(8, (($size * 0.82) - $nh) / 2);
        imagecopyresampled($dst, $src, $x, $y, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($src);

        $barTop = (int) round($size * 0.84);
        $black = imagecolorallocate($dst, 17, 24, 39);
        $barBg = imagecolorallocate($dst, 255, 255, 255);
        imagefilledrectangle($dst, 0, $barTop, $size, $size, $barBg);
        imageline($dst, (int) round($size * 0.08), $barTop, (int) round($size * 0.92), $barTop, $black);

        $font = $this->fontPath();
        $drawn = false;
        if ($font !== null && function_exists('imagettftext')) {
            $drawn = $this->drawTtfLines($dst, $font, $size, $barTop, $label, $secondLine, $black);
        }
        if (! $drawn) {
            $this->drawBuiltinLines($dst, $size, $barTop, $label, $secondLine, $black);
        }

        ob_start();
        imagejpeg($dst, null, 92);
        $out = (string) ob_get_clean();
        imagedestroy($dst);

        if ($out === '') {
            throw new \RuntimeException('Could not encode the stamped COO image.');
        }

        return $out;
    }

    /**
     * @param  \GdImage|resource  $dst
     */
    private function drawTtfLines($dst, string $font, int $size, int $barTop, string $label, string $secondLine, int $color): bool
    {
        $barH = $size - $barTop;
        $maxWidth = (int) round($size * 0.88);
        $fontSize = max(18, (int) round($size * ($secondLine !== '' ? 0.045 : 0.055)));
        $box = @imagettfbbox($fontSize, 0, $font, $label);
        if (! is_array($box)) {
            return false;
        }

        while ($this->boxWidth($box) > $maxWidth && $fontSize > 14) {
            $fontSize--;
            $box = imagettfbbox($fontSize, 0, $font, $label);
            if (! is_array($box)) {
                return false;
            }
        }

        $textW = $this->boxWidth($box);
        $textH = $this->boxHeight($box);
        $x = (int) (($size - $textW) / 2);
        if ($secondLine === '') {
            $y = $barTop + (int) (($barH + $textH) / 2) - 4;
            imagettftext($dst, $fontSize, 0, $x, $y, $color, $font, $label);

            return true;
        }

        $subSize = max(12, (int) round($fontSize * 0.62));
        $subBox = imagettfbbox($subSize, 0, $font, $secondLine);
        if (! is_array($subBox)) {
            return false;
        }
        $subW = $this->boxWidth($subBox);
        $subH = $this->boxHeight($subBox);
        $gap = (int) max(6, round($size * 0.012));
        $block = $textH + $gap + $subH;
        $y1 = $barTop + (int) (($barH - $block) / 2) + $textH;
        $y2 = $y1 + $gap + $subH;
        imagettftext($dst, $fontSize, 0, $x, $y1, $color, $font, $label);
        imagettftext($dst, $subSize, 0, (int) (($size - $subW) / 2), $y2, $color, $font, $secondLine);

        return true;
    }

    /**
     * @param  \GdImage|resource  $dst
     */
    private function drawBuiltinLines($dst, int $size, int $barTop, string $label, string $secondLine, int $color): void
    {
        $font = 5;
        $charW = imagefontwidth($font);
        $charH = imagefontheight($font);
        $x = (int) max(8, ($size - ($charW * strlen($label))) / 2);
        $y = $barTop + (int) (($size - $barTop - $charH) / 2);
        if ($secondLine !== '') {
            $y = $barTop + (int) (($size - $barTop - ($charH * 2) - 6) / 2);
            imagestring($dst, $font, $x, $y, $label, $color);
            $x2 = (int) max(8, ($size - ($charW * strlen($secondLine))) / 2);
            imagestring($dst, $font, $x2, $y + $charH + 6, $secondLine, $color);

            return;
        }
        imagestring($dst, $font, $x, $y, $label, $color);
    }

    /**
     * @param  array<int, int>  $box
     */
    private function boxWidth(array $box): int
    {
        return (int) (max($box[0], $box[2], $box[4], $box[6]) - min($box[0], $box[2], $box[4], $box[6]));
    }

    /**
     * @param  array<int, int>  $box
     */
    private function boxHeight(array $box): int
    {
        return (int) (max($box[1], $box[3], $box[5], $box[7]) - min($box[1], $box[3], $box[5], $box[7]));
    }

    private function normalizeLabel(string $text): string
    {
        $text = strtoupper(trim(preg_replace('/\s+/', ' ', $text) ?? $text));
        if ($text !== '' && ! str_starts_with($text, 'MADE IN ') && ! str_contains($text, 'BATCH')) {
            if ($this->presetKey($text) !== null) {
                return $this->label($text);
            }
        }

        return $text;
    }

    private function fontPath(): ?string
    {
        $candidates = [
            public_path('fonts/DejaVuSans-Bold.ttf'),
            base_path('public/fonts/DejaVuSans-Bold.ttf'),
            '/System/Library/Fonts/Supplemental/Arial Bold.ttf',
            '/System/Library/Fonts/Supplemental/Arial.ttf',
            '/Library/Fonts/Arial Bold.ttf',
            '/Library/Fonts/Arial.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf',
            '/usr/share/fonts/truetype/liberation2/LiberationSans-Bold.ttf',
        ];

        foreach ($candidates as $path) {
            if (is_string($path) && $path !== '' && is_file($path)) {
                return $path;
            }
        }

        return null;
    }
}
