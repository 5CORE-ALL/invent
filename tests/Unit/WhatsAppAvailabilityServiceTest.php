<?php

namespace Tests\Unit;

use App\Services\Crm\WhatsAppAvailabilityService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppAvailabilityServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['services.whatsapp.enabled' => false]);
    }

    public function test_inspect_empty_phone_is_unknown(): void
    {
        $service = app(WhatsAppAvailabilityService::class);

        $this->assertSame(['available' => null, 'phone' => null], $service->inspect(null));
        $this->assertSame(['available' => null, 'phone' => null], $service->inspect(''));
        $this->assertSame(['available' => null, 'phone' => null], $service->inspect('---'));
    }

    public function test_inspect_short_phone_is_unavailable(): void
    {
        $service = app(WhatsAppAvailabilityService::class);

        $this->assertSame(['available' => false, 'phone' => '123'], $service->inspect('123'));
    }

    public function test_inspect_plausible_phone_is_available_when_provider_disabled(): void
    {
        $service = app(WhatsAppAvailabilityService::class);

        $this->assertSame(['available' => true, 'phone' => '17876671861'], $service->inspect('+17876671861'));
    }

    public function test_inspect_uses_gupshup_contact_status_when_configured(): void
    {
        config([
            'services.whatsapp.enabled' => true,
            'services.whatsapp.gupshup.api_key' => 'test-key',
            'services.whatsapp.gupshup.source' => '12345',
        ]);
        Http::fake([
            'https://api.gupshup.io/wa/api/v1/contacts' => Http::response([
                'contacts' => [['status' => 'invalid']],
            ], 200),
        ]);

        $service = app(WhatsAppAvailabilityService::class);

        $this->assertSame(['available' => false, 'phone' => '17876671861'], $service->inspect('+1 787 667 1861'));
    }
}
