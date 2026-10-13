<?php

namespace App\Support;

/**
 * The agent version the portal advertises has to be the version the download link can
 * actually hand out, per platform. `attendance:agent-publish` writes this manifest next
 * to the installers; without it the portal offers an update the installer cannot deliver
 * and the employee is asked to update forever.
 */
class AttendanceAgentRelease
{
    public const MANIFEST_PATH = 'downloads/agent-release.json';

    public const PLATFORMS = ['windows', 'mac'];

    /** @var array<string, array<string, mixed>>|null */
    private static ?array $memo = null;

    public static function platformFor(?string $osName): string
    {
        $os = strtolower(trim((string) $osName));

        return $os === 'darwin' || str_starts_with($os, 'mac') ? 'mac' : 'windows';
    }

    /**
     * @return array<string, array{version: string, filename: string, bytes: int, sha256: string, published_at: string}>
     */
    public static function manifest(): array
    {
        if (self::$memo !== null) {
            return self::$memo;
        }

        $path = public_path(self::MANIFEST_PATH);
        if (! is_file($path)) {
            return self::$memo = [];
        }

        $raw = json_decode((string) file_get_contents($path), true);
        if (! is_array($raw)) {
            return self::$memo = [];
        }

        $entries = [];
        foreach (self::PLATFORMS as $platform) {
            $entry = $raw[$platform] ?? null;
            if (! is_array($entry)) {
                continue;
            }
            $version = (string) ($entry['version'] ?? '');
            if ($version === '') {
                continue;
            }
            $entries[$platform] = [
                'version' => $version,
                'filename' => (string) ($entry['filename'] ?? ''),
                'bytes' => (int) ($entry['bytes'] ?? 0),
                'sha256' => (string) ($entry['sha256'] ?? ''),
                'published_at' => (string) ($entry['published_at'] ?? ''),
            ];
        }

        return self::$memo = $entries;
    }

    public static function forget(): void
    {
        self::$memo = null;
    }

    public static function publishedVersion(string $platform): ?string
    {
        return self::manifest()[$platform]['version'] ?? null;
    }

    public static function configuredVersion(): string
    {
        return (string) config('attendance.agent_version', '1.0.0');
    }

    /**
     * What this OS can actually download, or null when nothing is published for it.
     * While no manifest exists at all the configured version still wins, so behaviour
     * is unchanged until the first `attendance:agent-publish` run.
     */
    public static function availableFor(?string $osName = null): ?string
    {
        $manifest = self::manifest();
        if ($manifest === []) {
            return self::configuredVersion();
        }

        if ($osName !== null && $osName !== '') {
            return $manifest[self::platformFor($osName)]['version'] ?? null;
        }

        // Unknown platform: never promise more than the lowest build we can serve.
        $versions = array_column($manifest, 'version');
        usort($versions, 'version_compare');

        return $versions === [] ? null : (string) reset($versions);
    }

    /**
     * Version to advertise. With nothing published for this platform the installed build
     * is already the newest obtainable one — claiming otherwise is what asked employees
     * to install an update that the download link could not produce.
     */
    public static function latestFor(?string $osName = null, ?string $installedVersion = null): string
    {
        $available = self::availableFor($osName);
        if ($available !== null) {
            return $available;
        }

        return $installedVersion !== null && $installedVersion !== ''
            ? $installedVersion
            : self::configuredVersion();
    }

    /** Live screen streaming did not exist before this build, so older agents can never show video. */
    public static function supportsLiveVideo(?string $version): bool
    {
        if ($version === null || $version === '') {
            return false;
        }

        return version_compare($version, (string) config('attendance.live_min_version', '1.4.0'), '>=');
    }

    /**
     * A build newer than what is published cannot be downloaded; the download page warns
     * about it instead of silently serving the stale installer.
     */
    public static function isStale(string $platform): bool
    {
        $published = self::publishedVersion($platform);

        return $published !== null && version_compare(self::configuredVersion(), $published, '>');
    }

    /**
     * @param  array<string, array<string, mixed>>  $entries
     */
    public static function writeManifest(array $entries): string
    {
        $path = public_path(self::MANIFEST_PATH);
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
        self::forget();

        return $path;
    }
}
