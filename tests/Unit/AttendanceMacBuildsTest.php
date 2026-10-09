<?php

namespace Tests\Unit;

use App\Support\AttendanceMacBuilds;
use PHPUnit\Framework\TestCase;

class AttendanceMacBuildsTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/attendance-mac-'.bin2hex(random_bytes(4));
        mkdir($this->dir, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir.'/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->dir);
        parent::tearDown();
    }

    public function test_ignores_windows_installers_and_tiny_placeholders(): void
    {
        $this->put('5core-attendance-setup.exe', 64);
        $this->put('5core-attendance-mac.dmg', 32);
        $this->put('notes.zip', 8000);

        $found = AttendanceMacBuilds::discover($this->dir, null, [], 64);

        $this->assertSame([], $found);
    }

    public function test_universal_dmg_is_preferred_over_zip(): void
    {
        $this->put('5core-attendance-mac.zip', 200);
        $dmg = $this->put('5Core-Attendance-Mac.dmg', 200);

        $found = AttendanceMacBuilds::discover($this->dir, null, [], 64);

        $this->assertArrayHasKey('universal', $found);
        $this->assertSame($dmg, $found['universal']['path']);
        $this->assertSame('dmg', $found['universal']['format']);
        $this->assertSame('application/x-apple-diskimage', AttendanceMacBuilds::mime('dmg'));
        $this->assertSame('5Core-Attendance-Mac.dmg', $found['universal']['filename']);
        $this->assertSame([$found['universal']], AttendanceMacBuilds::forDisplay($found));
    }

    public function test_separate_architectures_are_offered_when_no_universal_build_exists(): void
    {
        $this->put('5Core-Attendance-Mac-arm64.dmg', 120);
        $this->put('5core-attendance-mac-x64.zip', 120);

        $found = AttendanceMacBuilds::discover($this->dir, null, [], 64);
        $display = AttendanceMacBuilds::forDisplay($found);

        $this->assertSame(['arm64', 'x64'], array_keys($found));
        $this->assertCount(2, $display);
        $this->assertSame('Download for Mac (Apple Silicon)', $display[0]['label']);
        $this->assertSame('Apple Silicon (M1, M2, M3, M4)', $display[0]['detail']);
        $this->assertSame('5Core-Attendance-Mac-Intel.zip', $display[1]['filename']);
        $this->assertSame('application/zip', AttendanceMacBuilds::mime('zip'));
    }

    public function test_configured_path_is_accepted_even_with_a_custom_filename(): void
    {
        $path = $this->put('CompanyMacBuild.dmg', 90);

        $found = AttendanceMacBuilds::discover(null, null, [$path], 64);

        $this->assertSame($path, $found['universal']['path']);
        $this->assertStringContainsString('Apple Silicon (M1–M4) and Intel', $found['universal']['detail']);
    }

    public function test_newer_dmg_replaces_an_older_one_for_the_same_architecture(): void
    {
        $older = $this->put('5core-attendance-mac.dmg', 80);
        touch($older, time() - 100);
        $dist = $this->dir.'-dist';
        mkdir($dist);
        $newer = $dist.'/5Core-Attendance-Mac.dmg';
        file_put_contents($newer, str_repeat('M', 80));
        touch($newer, time());

        try {
            $found = AttendanceMacBuilds::discover($this->dir, $dist, [], 64);
            $this->assertSame($newer, $found['universal']['path']);
        } finally {
            @unlink($newer);
            @rmdir($dist);
        }
    }

    private function put(string $name, int $bytes): string
    {
        $path = $this->dir.'/'.$name;
        file_put_contents($path, str_repeat('A', $bytes));

        return $path;
    }
}
