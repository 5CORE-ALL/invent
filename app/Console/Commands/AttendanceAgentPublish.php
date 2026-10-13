<?php

namespace App\Console\Commands;

use App\Support\AttendanceAgentRelease;
use Illuminate\Console\Command;
use ZipArchive;

/**
 * Publishing used to be a manual copy of a binary into public/downloads, so the portal's
 * hardcoded agent_version drifted ahead of the installer it actually served and employees
 * were asked to update to a build that did not exist. This records what was published.
 */
class AttendanceAgentPublish extends Command
{
    protected $signature = 'attendance:agent-publish
        {--release= : Version being published (defaults to attendance-agent/package.json)}
        {--windows= : Path to the Windows installer (defaults to attendance-agent/dist)}
        {--mac-dmg= : Path to the Mac DMG (defaults to attendance-agent/dist)}
        {--mac-zip= : Path to the Mac ZIP (defaults to attendance-agent/dist)}
        {--force : Publish even when an artifact reports a different version}';

    protected $description = 'Copy built desktop agent installers into public/downloads and record the published version';

    /** Destination names must match what AttendanceMonitorController and AttendanceMacBuilds look for. */
    private const TARGETS = [
        'windows' => ['option' => 'windows', 'source' => '5Core-Attendance-Setup.exe', 'dest' => '5core-attendance-setup.exe'],
        'mac_dmg' => ['option' => 'mac-dmg', 'source' => '5Core-Attendance-Mac.dmg', 'dest' => '5core-attendance-mac.dmg'],
        'mac_zip' => ['option' => 'mac-zip', 'source' => '5Core-Attendance-Mac.zip', 'dest' => '5core-attendance-mac.zip'],
    ];

    /** electron-updater reads these; they point at the artifact by its build-time name. */
    private const FEEDS = [
        'windows' => ['file' => 'latest.yml', 'artifact' => '5Core-Attendance-Setup.exe', 'published' => '5core-attendance-setup.exe'],
        'mac' => ['file' => 'latest-mac.yml', 'artifact' => '5Core-Attendance-Mac.zip', 'published' => '5core-attendance-mac.zip'],
    ];

    public function handle(): int
    {
        $version = (string) ($this->option('release') ?: $this->packageVersion());
        if ($version === '') {
            $this->error('Could not determine the version. Pass --release=1.4.8.');

            return self::FAILURE;
        }

        if (version_compare($version, '0.0.1', '<')) {
            $this->error("Version '{$version}' is not a valid version number.");

            return self::FAILURE;
        }

        $dist = base_path('attendance-agent/dist');
        $published = [];
        $rows = [];

        // Validate every artifact before copying any, so a mismatch cannot leave
        // public/downloads holding half of one release and half of another.
        $found = [];
        foreach (self::TARGETS as $key => $target) {
            $path = (string) ($this->option($target['option']) ?: $dist.DIRECTORY_SEPARATOR.$target['source']);
            if (! is_file($path)) {
                $rows[] = [$key, 'missing', '—', '—'];

                continue;
            }
            if (! $this->versionMatches($path, $version)) {
                return self::FAILURE;
            }
            $found[$key] = $path;
        }

        foreach ($found as $key => $path) {
            $target = self::TARGETS[$key];
            $destination = public_path('downloads/'.$target['dest']);
            if (! is_dir(dirname($destination))) {
                mkdir(dirname($destination), 0755, true);
            }
            if (! copy($path, $destination)) {
                $this->error("Could not copy {$path} to {$destination}.");

                return self::FAILURE;
            }
            chmod($destination, 0644);

            $bytes = (int) filesize($destination);
            $platform = $key === 'windows' ? 'windows' : 'mac';

            // The DMG is the primary Mac artifact; the ZIP only backs auto-update.
            if (! isset($published[$platform]) || $key !== 'mac_zip') {
                $published[$platform] = [
                    'version' => $version,
                    'filename' => $target['dest'],
                    'bytes' => $bytes,
                    'sha256' => hash_file('sha256', $destination),
                    'published_at' => now()->toIso8601String(),
                ];
            }

            $rows[] = [$key, 'published', $version, $this->humanBytes($bytes)];
        }

        if ($published === []) {
            $this->error('No installers found. Build first, or pass --windows= / --mac-dmg=.');
            $this->line("Looked in: {$dist}");

            return self::FAILURE;
        }

        foreach (array_keys($published) as $platform) {
            $rows[] = ['feed ('.$platform.')', $this->publishFeed($platform, $dist) ? 'published' : 'missing', $version, '—'];
        }

        // Keep the platform we did not publish this run, so a Mac-only publish does not
        // silently retract the Windows release.
        $published = array_replace(AttendanceAgentRelease::manifest(), $published);
        $manifestPath = AttendanceAgentRelease::writeManifest($published);

        $this->table(['artifact', 'status', 'version', 'size'], $rows);
        $this->info('Wrote '.$manifestPath);

        foreach (AttendanceAgentRelease::PLATFORMS as $platform) {
            $live = AttendanceAgentRelease::publishedVersion($platform);
            $this->line($live === null
                ? "  {$platform}: nothing published — agents keep using the configured version"
                : "  {$platform}: portal now advertises v{$live}");
        }

        $configured = AttendanceAgentRelease::configuredVersion();
        if (version_compare($version, $configured, '>')) {
            $this->warn("config/attendance.php still says agent_version '{$configured}'. Bump it to '{$version}'.");
        }

        return self::SUCCESS;
    }

    /**
     * The feed names the artifact as electron-builder built it, but it is served under a
     * lowercase name. On a case-sensitive filesystem the agent would 404 on the download,
     * so the reference is rewritten to match. The bytes are unchanged, so the checksums
     * recorded in the feed still hold.
     */
    private function publishFeed(string $platform, string $dist): bool
    {
        $feed = self::FEEDS[$platform] ?? null;
        if ($feed === null) {
            return false;
        }

        $source = $dist.DIRECTORY_SEPARATOR.$feed['file'];
        if (! is_file($source)) {
            return false;
        }

        $yaml = str_replace($feed['artifact'], $feed['published'], (string) file_get_contents($source));
        $destination = public_path('downloads/'.$feed['file']);
        file_put_contents($destination, $yaml);
        chmod($destination, 0644);

        return true;
    }

    /**
     * Only the Mac ZIP exposes a readable version. Catching the mismatch here is the whole
     * point of the command: publishing 1.4.5 while claiming 1.4.8 is what caused the loop.
     */
    private function versionMatches(string $path, string $expected): bool
    {
        if (! str_ends_with(strtolower($path), '.zip')) {
            return true;
        }

        $embedded = $this->macZipVersion($path);
        if ($embedded === null) {
            $this->warn('Could not read a version out of '.basename($path).'; publishing it unchecked.');

            return true;
        }

        if ($embedded === $expected) {
            return true;
        }

        if ($this->option('force')) {
            $this->warn("{$path} contains v{$embedded}, not v{$expected} — publishing anyway (--force).");

            return true;
        }

        $this->error("{$path} contains v{$embedded} but you are publishing v{$expected}.");
        $this->line('Rebuild the app, or re-run with --release='.$embedded.' (or --force).');

        return false;
    }

    private function macZipVersion(string $path): ?string
    {
        if (! class_exists(ZipArchive::class)) {
            return null;
        }

        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            return null;
        }

        // Only the top-level bundle. The nested Electron helper apps carry their own
        // Info.plist with no CFBundleShortVersionString, which read as "unknown version"
        // and let a mislabelled build through.
        $plist = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if (preg_match('#^[^/]+\.app/Contents/Info\.plist$#', $name)) {
                $plist = (string) $zip->getFromIndex($i);
                break;
            }
        }
        $zip->close();

        if ($plist === null || $plist === '') {
            return null;
        }

        return preg_match(
            '#<key>CFBundleShortVersionString</key>\s*<string>([^<]+)</string>#',
            $plist,
            $hit
        ) ? trim($hit[1]) : null;
    }

    private function packageVersion(): string
    {
        $path = base_path('attendance-agent/package.json');
        if (! is_file($path)) {
            return '';
        }

        $json = json_decode((string) file_get_contents($path), true);

        return is_array($json) ? (string) ($json['version'] ?? '') : '';
    }

    private function humanBytes(int $bytes): string
    {
        return $bytes >= 1048576
            ? number_format($bytes / 1048576, 1).' MB'
            : number_format($bytes / 1024).' KB';
    }
}
