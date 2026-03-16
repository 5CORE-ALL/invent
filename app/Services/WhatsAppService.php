<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    protected bool $enabled;
    protected string $provider;
    protected ?string $apiKey;
    protected ?string $source;
    protected ?string $srcName;
    protected ?string $appId;
    protected ?string $accessToken;
    protected string $apiBase;
    protected string $waApiBase;
    protected string $templateApiBase;

    public function __construct()
    {
        $config = config('services.whatsapp', []);
        $this->enabled = (bool) ($config['enabled'] ?? false);
        $this->provider = $config['provider'] ?? 'gupshup';
        $gupshup = $config['gupshup'] ?? [];
        $this->apiKey = $gupshup['api_key'] ?? null;
        $this->source = $gupshup['source'] ?? null;
        $this->srcName = $gupshup['src_name'] ?? '';
        $this->appId = $gupshup['app_id'] ?? null;
        $this->accessToken = $gupshup['access_token'] ?? null;
        $this->apiBase = rtrim($gupshup['api_base'] ?? 'https://partner.gupshup.io/partner/app', '/');
        $this->waApiBase = rtrim($gupshup['wa_api_base'] ?? 'https://api.gupshup.io/sm/api/v1', '/');
        $this->templateApiBase = rtrim($gupshup['template_api_base'] ?? 'https://api.gupshup.io/wa/api/v1', '/');
    }

    /**
     * Clean phone to digits only (with optional leading + / country code).
     * E.g. "+1 (555) 123-4567" -> "15551234567"
     */
    public static function cleanPhone(?string $phone): ?string
    {
        if ($phone === null || $phone === '') {
            return null;
        }
        $digits = preg_replace('/\D/', '', $phone);
        return $digits !== '' ? $digits : null;
    }

    /** Use Gupshup wa/sm API (api_key + source) instead of Partner v3. */
    public function usesWaApi(): bool
    {
        return $this->apiKey !== null && $this->apiKey !== '' && $this->source !== null && $this->source !== '';
    }

    /**
     * Check if WhatsApp is configured and enabled.
     */
    public function isEnabled(): bool
    {
        if (!$this->enabled) {
            return false;
        }
        if ($this->usesWaApi()) {
            return true;
        }
        return $this->appId && $this->accessToken && $this->source;
    }

    /**
     * Send a free-form (session) text message.
     * Within 24h of user's last reply: use this. After 24h: use template if provider requires.
     *
     * @param string $to Recipient phone (digits only, with country code)
     * @param string $text Message body
     * @return bool Success
     */
    public function sendText(string $to, string $text): bool
    {
        $clean = self::cleanPhone($to);
        if (!$clean) {
            Log::warning('WhatsAppService: empty or invalid recipient phone', ['to' => $to]);
            return false;
        }

        if (!$this->isEnabled()) {
            Log::warning('WhatsAppService: disabled or not configured — set WHATSAPP_ENABLED=true and GUPSHUP_API_KEY+GUPSHUP_SOURCE (or Partner v3).');
            return false;
        }

        if ($this->provider === 'gupshup') {
            if ($this->usesWaApi()) {
                return $this->sendGupshupWaApi($clean, $text);
            }
            return $this->sendGupshupPartnerV3($clean, $text);
        }

        Log::warning('WhatsAppService: unknown provider', ['provider' => $this->provider]);
        return false;
    }

    /**
     * Send via Gupshup wa/api/v1 (api_key + source). Form-urlencoded.
     */
    protected function sendGupshupWaApi(string $to, string $text): bool
    {
        $url = $this->waApiBase . '/msg';
        $source = self::cleanPhone($this->source) ?: $this->source;
        $message = json_encode(['type' => 'text', 'text' => $text]);

        $params = [
            'channel' => 'whatsapp',
            'source' => $source,
            'destination' => $to,
            'message' => $message,
        ];
        if ($this->srcName !== null && $this->srcName !== '') {
            $params['src.name'] = $this->srcName;
        }

        try {
            $response = Http::withHeaders(['apikey' => $this->apiKey])
                ->asForm()
                ->timeout(15)
                ->post($url, $params);

            $status = $response->status();
            $body = $response->body();

            if ($response->successful()) {
                Log::info('WhatsAppService: message sent (sm/wa API)', ['to' => $to, 'response' => $body]);
                return true;
            }

            Log::warning('WhatsAppService: Gupshup API error', [
                'to' => $to,
                'url' => $url,
                'status' => $status,
                'body' => $body,
            ]);
            return false;
        } catch (\Throwable $e) {
            Log::error('WhatsAppService: send failed', ['to' => $to, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return false;
        }
    }

    /**
     * Send text and return raw result for debugging. Does not throw.
     */
    public function sendTextWithDebug(string $to, string $text): array
    {
        $clean = self::cleanPhone($to);
        if (!$clean) {
            return ['ok' => false, 'error' => 'invalid_phone', 'detail' => 'empty or invalid recipient'];
        }
        if (!$this->isEnabled()) {
            return ['ok' => false, 'error' => 'disabled', 'detail' => 'WhatsApp not enabled or missing api_key/source'];
        }
        if (!$this->usesWaApi()) {
            return ['ok' => false, 'error' => 'wa_api_only', 'detail' => 'whatsapp:test-send uses sm/wa API only. Configure GUPSHUP_API_KEY + GUPSHUP_SOURCE.'];
        }

        $url = $this->waApiBase . '/msg';
        $source = self::cleanPhone($this->source) ?: $this->source;
        $message = json_encode(['type' => 'text', 'text' => $text]);
        $params = [
            'channel' => 'whatsapp',
            'source' => $source,
            'destination' => $clean,
            'message' => $message,
        ];
        if ($this->srcName !== null && $this->srcName !== '') {
            $params['src.name'] = $this->srcName;
        }

        try {
            $response = Http::withHeaders(['apikey' => $this->apiKey])
                ->asForm()
                ->timeout(15)
                ->post($url, $params);

            $status = $response->status();
            $body = $response->body();
            return [
                'ok' => $response->successful(),
                'status' => $status,
                'body' => $body,
                'url' => $url,
                'source' => $source,
                'destination' => $clean,
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'error' => $e->getMessage(),
                'status' => 0,
                'body' => '',
            ];
        }
    }

    /**
     * Send via Gupshup Partner API v3 (session / text message).
     */
    protected function sendGupshupPartnerV3(string $to, string $text): bool
    {
        $url = "{$this->apiBase}/{$this->appId}/v3/message";
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'text',
            'text' => ['body' => $text],
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Content-Type' => 'application/json',
            ])->timeout(15)->post($url, $payload);

            if ($response->successful()) {
                Log::info('WhatsAppService: message sent (Partner v3)', ['to' => $to]);
                return true;
            }

            Log::warning('WhatsAppService: Gupshup Partner API error', [
                'to' => $to,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return false;
        } catch (\Throwable $e) {
            Log::error('WhatsAppService: send failed (Partner v3)', ['to' => $to, 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Send template message (use when outside 24h session window).
     * Template must be approved; placeholders replaced by provider format.
     *
     * @param string $to Recipient phone (digits only)
     * @param string $templateIdOrName Template ID (Gupshup) or name
     * @param array<string, string> $params Placeholder key => value
     * @return bool Success
     */
    public function sendTemplate(string $to, string $templateIdOrName, array $params): bool
    {
        $clean = self::cleanPhone($to);
        if (!$clean) {
            Log::warning('WhatsAppService: empty or invalid recipient for template', ['to' => $to]);
            return false;
        }

        if (!$this->isEnabled()) {
            return false;
        }

        if ($this->provider === 'gupshup') {
            if ($this->usesWaApi()) {
                return $this->sendGupshupWaApiTemplate($clean, $templateIdOrName, $params);
            }
            return $this->sendGupshupPartnerV3Template($clean, $templateIdOrName, $params);
        }

        return false;
    }

    /**
     * Gupshup template API: POST to wa/api/v1/template/msg.
     * Works outside 24h window. Params = ordered list matching template placeholders.
     */
    protected function sendGupshupWaApiTemplate(string $to, string $templateId, array $params): bool
    {
        $url = $this->templateApiBase . '/template/msg';
        $source = self::cleanPhone($this->source) ?: $this->source;
        $paramsList = array_values(array_map(fn ($v) => (string) $v, $params));
        $template = json_encode(['id' => $templateId, 'params' => $paramsList]);
        $form = [
            'channel' => 'whatsapp',
            'source' => $source,
            'destination' => $to,
            'template' => $template,
        ];
        if ($this->srcName !== null && $this->srcName !== '') {
            $form['src.name'] = $this->srcName;
        }

        try {
            $response = Http::withHeaders(['apikey' => $this->apiKey])
                ->asForm()
                ->timeout(15)
                ->post($url, $form);

            if ($response->successful()) {
                Log::info('WhatsAppService: template sent (template/msg)', ['to' => $to, 'template' => $templateId]);
                return true;
            }
            Log::warning('WhatsAppService: Gupshup template API error', [
                'to' => $to,
                'template' => $templateId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return false;
        } catch (\Throwable $e) {
            Log::error('WhatsAppService: template send failed', [
                'to' => $to,
                'template' => $templateId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Gupshup Partner v3 template send.
     */
    protected function sendGupshupPartnerV3Template(string $to, string $templateName, array $params): bool
    {
        $url = "{$this->apiBase}/{$this->appId}/v3/message";
        $components = [];
        if (!empty($params)) {
            $components[] = [
                'type' => 'body',
                'parameters' => array_map(fn ($v) => ['type' => 'text', 'text' => (string) $v], array_values($params)),
            ];
        }
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => ['code' => 'en'],
                'components' => $components,
            ],
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Content-Type' => 'application/json',
            ])->timeout(15)->post($url, $payload);

            if ($response->successful()) {
                Log::info('WhatsAppService: template sent (Partner v3)', ['to' => $to, 'template' => $templateName]);
                return true;
            }
            Log::warning('WhatsAppService: Gupshup Partner template API error', [
                'to' => $to,
                'template' => $templateName,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return false;
        } catch (\Throwable $e) {
            Log::error('WhatsAppService: template send failed (Partner v3)', [
                'to' => $to,
                'template' => $templateName,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
