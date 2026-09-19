<?php

namespace Tests\Feature;

use Tests\TestCase;

class ChatPwaTest extends TestCase
{
    public function test_manifest_is_public_and_named_invent_chat(): void
    {
        $path = public_path('manifest.json');
        $this->assertFileExists($path);
        $manifest = json_decode((string) file_get_contents($path), true);
        $this->assertSame('Invent Chat', $manifest['name'] ?? null);
        $this->assertSame('/chat', $manifest['start_url'] ?? null);
        $this->assertSame('standalone', $manifest['display'] ?? null);
        $this->assertNotEmpty($manifest['icons'] ?? []);
        $shortcutUrls = array_map(static fn ($s) => $s['url'] ?? '', $manifest['shortcuts'] ?? []);
        $this->assertSame(['/chat'], $shortcutUrls);
        $this->assertStringNotContainsString('/incoming-view', json_encode($manifest));
        $this->assertStringNotContainsString('/wms/', json_encode($manifest));
    }

    public function test_service_worker_does_not_cache_private_chat(): void
    {
        $sw = (string) file_get_contents(public_path('sw.js'));
        $this->assertStringContainsString('/offline.html', $sw);
        $this->assertStringContainsString('/chat/sync', $sw);
        $this->assertStringContainsString('/chat/files', $sw);
        $this->assertStringContainsString('isPrivatePath', $sw);
        $this->assertStringNotContainsString('cache.put(request, copy); // chat', $sw);
    }

    public function test_offline_shell_has_no_private_data(): void
    {
        $html = (string) file_get_contents(public_path('offline.html'));
        $this->assertStringContainsString('Invent Chat', $html);
        $this->assertStringNotContainsString('csrf', strtolower($html));
        $this->assertStringNotContainsString('/chat/sync', $html);
    }

    public function test_standalone_app_boot_sends_inventory_to_chat(): void
    {
        $boot = (string) file_get_contents(resource_path('views/layouts/shared/invent-chat-pwa-boot.blade.php'));
        $this->assertStringContainsString("display-mode: standalone", $boot);
        $this->assertStringContainsString("location.replace('/chat')", $boot);
    }

    public function test_asset_links_are_public(): void
    {
        $res = $this->get('/.well-known/assetlinks.json');
        $res->assertOk();
        $this->assertStringContainsString('application/json', (string) $res->headers->get('Content-Type'));
        $json = (string) file_get_contents(public_path('.well-known/assetlinks.json'));
        $this->assertStringContainsString('com.fivecore.invent.chat', $json);
    }
}
