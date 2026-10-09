<?php

namespace App\Support;

/**
 * Locates real macOS desktop-agent artifacts (DMG or ZIP).
 * A universal build covers Apple Silicon and Intel. Arch-specific files are used only when no universal build exists.
 */
class AttendanceMacBuilds
{
    public const ARCHS = ['universal', 'arm64', 'x64'];

    /** Electron distributions are tens of megabytes. Smaller files are not offered as a download. */
    public const MIN_BYTES = 5 * 1024 * 1024;

    /**
     * @param  list<string|null>  $configuredPaths
     * @return array<string, array{arch:string, format:string, path:string, filename:string, bytes:int, label:string, detail:string, format_label:string, size_label:string}>
     */
    public static function discover(?string $publicDir = null, ?string $distDir = null, array $configuredPaths = [], int $minBytes = self::MIN_BYTES): array
    {
        $found = [];

        foreach ($configuredPaths as $path) {
            if (! is_string($path) || $path === '' || ! is_file($path)) {
                continue;
            }
            $meta = self::classify(basename($path)) ?? self::classifyConfigured($path);
            self::consider($found, $path, $meta, $minBytes);
        }

        foreach ([$publicDir, $distDir] as $dir) {
            if (! is_string($dir) || $dir === '' || ! is_dir($dir)) {
                continue;
            }
            $entries = scandir($dir) ?: [];
            foreach ($entries as $name) {
                if ($name === '.' || $name === '..') {
                    continue;
                }
                $full = $dir.DIRECTORY_SEPARATOR.$name;
                if (! is_file($full)) {
                    continue;
                }
                self::consider($found, $full, self::classify($name), $minBytes);
            }
        }

        return $found;
    }

    /**
     * @return array<string, array{arch:string, format:string, path:string, filename:string, bytes:int, label:string, detail:string, format_label:string, size_label:string}>
     */
    public static function located(): array
    {
        return self::discover(
            public_path('downloads'),
            base_path('attendance-agent/dist'),
            self::configuredPaths(),
        );
    }

    /**
     * @return list<string>
     */
    public static function configuredPaths(): array
    {
        $paths = [
            config('attendance.agent_mac_installer_path'),
            config('attendance.agent_mac_arm64_path'),
            config('attendance.agent_mac_x64_path'),
        ];

        return array_values(array_filter($paths, fn ($path) => is_string($path) && $path !== ''));
    }

    /**
     * Universal covers both chips, so separate architecture downloads stay hidden when it is present.
     *
     * @param  array<string, array<string, mixed>>  $found
     * @return list<array<string, mixed>>
     */
    public static function forDisplay(array $found): array
    {
        if (isset($found['universal'])) {
            return [$found['universal']];
        }

        $list = [];
        foreach (['arm64', 'x64'] as $arch) {
            if (isset($found[$arch])) {
                $list[] = $found[$arch];
            }
        }

        return $list;
    }

    /**
     * @return array{arch:string, format:string}|null
     */
    public static function classify(string $basename): ?array
    {
        $name = strtolower($basename);
        if (! preg_match('/\.(dmg|zip)$/', $name, $ext)) {
            return null;
        }
        $format = $ext[1];

        if (preg_match('/^5core-attendance-mac(?:-(universal|arm64|x64))?\.(dmg|zip)$/', $name, $hit)) {
            $arch = isset($hit[1]) && $hit[1] !== '' ? $hit[1] : 'universal';

            return ['arch' => $arch, 'format' => $format];
        }

        if (! str_contains($name, 'attendance')) {
            return null;
        }
        if (str_contains($name, 'setup-cn') || str_contains($name, '-cn.')) {
            return null;
        }

        if ($format === 'dmg') {
            return ['arch' => self::archFromName($name) ?? 'universal', 'format' => 'dmg'];
        }

        $arch = self::archFromName($name);
        if ($arch === null && ! str_contains($name, 'mac') && ! str_contains($name, 'darwin')) {
            return null;
        }

        return ['arch' => $arch ?? 'universal', 'format' => 'zip'];
    }

    public static function downloadFilename(string $arch, string $format): string
    {
        $ext = $format === 'zip' ? 'zip' : 'dmg';

        return match ($arch) {
            'arm64' => "5Core-Attendance-Mac-AppleSilicon.{$ext}",
            'x64' => "5Core-Attendance-Mac-Intel.{$ext}",
            default => "5Core-Attendance-Mac.{$ext}",
        };
    }

    public static function label(string $arch): string
    {
        return match ($arch) {
            'arm64' => 'Download for Mac (Apple Silicon)',
            'x64' => 'Download for Mac (Intel)',
            default => 'Download for Mac',
        };
    }

    public static function detail(string $arch): string
    {
        return match ($arch) {
            'arm64' => 'Apple Silicon (M1, M2, M3, M4)',
            'x64' => 'Intel Mac',
            default => 'Apple Silicon (M1–M4) and Intel',
        };
    }

    public static function mime(string $format): string
    {
        return $format === 'zip' ? 'application/zip' : 'application/x-apple-diskimage';
    }

    public static function sizeLabel(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1).' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1).' KB';
        }

        return $bytes.' bytes';
    }

    /**
     * @param  array<string, array<string, mixed>>  $found
     * @param  array{arch:string, format:string}|null  $meta
     */
    private static function consider(array &$found, string $path, ?array $meta, int $minBytes): void
    {
        if ($meta === null || ! in_array($meta['arch'], self::ARCHS, true)) {
            return;
        }
        if (! in_array($meta['format'], ['dmg', 'zip'], true)) {
            return;
        }

        $bytes = filesize($path);
        if ($bytes === false || $bytes < $minBytes) {
            return;
        }

        $arch = $meta['arch'];
        $candidate = [
            'arch' => $arch,
            'format' => $meta['format'],
            'path' => $path,
            'filename' => self::downloadFilename($arch, $meta['format']),
            'bytes' => (int) $bytes,
            'label' => self::label($arch),
            'detail' => self::detail($arch),
            'format_label' => strtoupper($meta['format']),
            'size_label' => self::sizeLabel((int) $bytes),
        ];

        if (! isset($found[$arch]) || self::prefer($candidate, $found[$arch])) {
            $found[$arch] = $candidate;
        }
    }

    /**
     * @param  array<string, mixed>  $candidate
     * @param  array<string, mixed>  $current
     */
    private static function prefer(array $candidate, array $current): bool
    {
        $rank = ['dmg' => 2, 'zip' => 1];
        $candidateRank = $rank[$candidate['format']] ?? 0;
        $currentRank = $rank[$current['format']] ?? 0;
        if ($candidateRank !== $currentRank) {
            return $candidateRank > $currentRank;
        }

        $candidateMtime = @filemtime((string) $candidate['path']) ?: 0;
        $currentMtime = @filemtime((string) $current['path']) ?: 0;

        return $candidateMtime >= $currentMtime;
    }

    private static function archFromName(string $name): ?string
    {
        if (str_contains($name, 'universal')) {
            return 'universal';
        }
        if (str_contains($name, 'arm64') || str_contains($name, 'aarch64') || str_contains($name, 'applesilicon')) {
            return 'arm64';
        }
        if (str_contains($name, 'x64') || str_contains($name, 'x86_64') || str_contains($name, 'intel')) {
            return 'x64';
        }

        return null;
    }

    /**
     * Configured paths may use any filename. Directory scans stay strict so unrelated archives are ignored.
     *
     * @return array{arch:string, format:string}|null
     */
    private static function classifyConfigured(string $path): ?array
    {
        $format = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));
        if (! in_array($format, ['dmg', 'zip'], true)) {
            return null;
        }

        return [
            'arch' => self::archFromName(strtolower(basename($path))) ?? 'universal',
            'format' => $format,
        ];
    }
}
