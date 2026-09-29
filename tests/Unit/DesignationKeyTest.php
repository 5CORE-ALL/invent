<?php

namespace Tests\Unit;

use App\Support\DesignationKey;
use PHPUnit\Framework\TestCase;

class DesignationKeyTest extends TestCase
{
    public function test_encoded_ampersand_matches_the_plain_designation(): void
    {
        $plain = 'Social Media Content & Communications Executive';
        $encoded = 'Social Media Content &amp; Communications Executive';

        $this->assertSame($plain, DesignationKey::canonical($encoded));
        $this->assertSame($plain, DesignationKey::canonical($plain));
        $this->assertContains($encoded, DesignationKey::variants($plain));
        $this->assertContains($plain, DesignationKey::variants($encoded));
    }
}
