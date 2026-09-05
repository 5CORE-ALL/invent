<?php

namespace Tests\Unit;

use App\Support\AttendanceAccess;
use PHPUnit\Framework\TestCase;

class AttendanceAccessDirectorTest extends TestCase
{
    public function test_org_level_director_is_detected(): void
    {
        $this->assertTrue(AttendanceAccess::isDirectorProfile('Director'));
        $this->assertTrue(AttendanceAccess::isDirectorProfile('director', 'user', 'Executive'));
    }

    public function test_role_director_is_detected(): void
    {
        $this->assertTrue(AttendanceAccess::isDirectorProfile('exec', 'director'));
    }

    public function test_designation_with_director_word_is_detected(): void
    {
        $this->assertTrue(AttendanceAccess::isDirectorProfile(null, null, 'Director'));
        $this->assertTrue(AttendanceAccess::isDirectorProfile(null, null, 'Managing Director'));
    }

    public function test_non_directors_are_not_detected(): void
    {
        $this->assertFalse(AttendanceAccess::isDirectorProfile('exec', 'user', 'Executive'));
        $this->assertFalse(AttendanceAccess::isDirectorProfile('mgr', 'admin', 'Manager'));
        $this->assertFalse(AttendanceAccess::isDirectorProfile(null, null, 'Coordinator'));
        $this->assertFalse(AttendanceAccess::isDirectorProfile(null));
    }
}
