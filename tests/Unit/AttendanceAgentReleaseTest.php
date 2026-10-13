<?php

namespace Tests\Unit;

use App\Support\AttendanceAgentRelease;
use Tests\TestCase;

class AttendanceAgentReleaseTest extends TestCase
{
    private string $manifestPath;

    protected function setUp(): void
    {
        parent::setUp();

        config(['attendance.agent_version' => '1.4.8', 'attendance.live_min_version' => '1.4.0']);
        $this->manifestPath = public_path(AttendanceAgentRelease::MANIFEST_PATH);
        $this->clearManifest();
    }

    protected function tearDown(): void
    {
        $this->clearManifest();

        parent::tearDown();
    }

    private function clearManifest(): void
    {
        if (is_file($this->manifestPath)) {
            unlink($this->manifestPath);
        }
        AttendanceAgentRelease::forget();
    }

    /** @param array<string, array<string, mixed>> $entries */
    private function publish(array $entries): void
    {
        AttendanceAgentRelease::writeManifest($entries);
    }

    public function test_falls_back_to_configured_version_before_anything_is_published(): void
    {
        $this->assertSame('1.4.8', AttendanceAgentRelease::latestFor('windows', '1.4.2'));
        $this->assertSame('1.4.8', AttendanceAgentRelease::latestFor('darwin', '1.4.2'));
    }

    public function test_advertises_the_published_build_not_the_configured_one(): void
    {
        $this->publish(['windows' => ['version' => '1.4.5']]);

        // The installer on disk is 1.4.5, so offering 1.4.8 would restart the update loop.
        $this->assertSame('1.4.5', AttendanceAgentRelease::latestFor('windows', '1.4.2'));
    }

    public function test_an_employee_on_the_published_build_is_not_asked_to_update_again(): void
    {
        $this->publish(['windows' => ['version' => '1.4.5']]);

        $latest = AttendanceAgentRelease::latestFor('windows', '1.4.5');

        $this->assertFalse(version_compare($latest, '1.4.5', '>'), 'A fully updated agent must not be offered another update.');
    }

    public function test_platform_with_nothing_published_is_never_offered_an_update(): void
    {
        $this->publish(['mac' => ['version' => '1.4.8']]);

        // Nothing is published for Windows, so 1.4.2 is already the newest obtainable build.
        $this->assertSame('1.4.2', AttendanceAgentRelease::latestFor('windows', '1.4.2'));
        $this->assertNull(AttendanceAgentRelease::availableFor('windows'));
    }

    public function test_platforms_do_not_borrow_each_others_versions(): void
    {
        $this->publish([
            'windows' => ['version' => '1.4.5'],
            'mac' => ['version' => '1.4.8'],
        ]);

        $this->assertSame('1.4.5', AttendanceAgentRelease::latestFor('windows', '1.4.5'));
        $this->assertSame('1.4.8', AttendanceAgentRelease::latestFor('Darwin', '1.4.8'));
    }

    public function test_unknown_platform_never_promises_more_than_the_lowest_published_build(): void
    {
        $this->publish([
            'windows' => ['version' => '1.4.5'],
            'mac' => ['version' => '1.4.8'],
        ]);

        $this->assertSame('1.4.5', AttendanceAgentRelease::latestFor(null));
    }

    public function test_stale_detection_flags_a_bumped_config_that_was_never_published(): void
    {
        $this->publish(['windows' => ['version' => '1.4.5']]);

        $this->assertTrue(AttendanceAgentRelease::isStale('windows'));

        $this->publish(['windows' => ['version' => '1.4.8']]);

        $this->assertFalse(AttendanceAgentRelease::isStale('windows'));
    }

    public function test_live_video_support_follows_the_minimum_streaming_build(): void
    {
        $this->assertFalse(AttendanceAgentRelease::supportsLiveVideo('1.3.9'));
        $this->assertTrue(AttendanceAgentRelease::supportsLiveVideo('1.4.0'));
        $this->assertTrue(AttendanceAgentRelease::supportsLiveVideo('1.4.8'));
        $this->assertFalse(AttendanceAgentRelease::supportsLiveVideo(null));
    }

    public function test_platform_detection_maps_reported_os_names(): void
    {
        $this->assertSame('mac', AttendanceAgentRelease::platformFor('darwin'));
        $this->assertSame('mac', AttendanceAgentRelease::platformFor('macOS'));
        $this->assertSame('windows', AttendanceAgentRelease::platformFor('win32'));
        $this->assertSame('windows', AttendanceAgentRelease::platformFor(null));
    }

    public function test_corrupt_manifest_does_not_take_the_portal_down(): void
    {
        file_put_contents($this->manifestPath, 'not json at all');
        AttendanceAgentRelease::forget();

        $this->assertSame([], AttendanceAgentRelease::manifest());
        $this->assertSame('1.4.8', AttendanceAgentRelease::latestFor('windows', '1.4.2'));
    }
}
