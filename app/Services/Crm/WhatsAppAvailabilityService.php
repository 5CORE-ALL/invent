<?php

namespace App\Services\Crm;

use App\Models\Crm\ShopifyCustomer;
use App\Services\WhatsAppService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class WhatsAppAvailabilityService
{
    protected ?bool $gupshupContactsSupported = null;

    public function __construct(protected WhatsAppService $whatsAppService) {}

    public function normalizePhone(?string $phone): ?string
    {
        return WhatsAppService::cleanPhone($phone);
    }

    /**
     * @return array{available: bool|null, phone: string|null}
     */
    public function inspect(?string $phone): array
    {
        $clean = $this->normalizePhone($phone);
        if ($clean === null) {
            return ['available' => null, 'phone' => null];
        }

        if (! $this->isPlausiblePhone($clean)) {
            return ['available' => false, 'phone' => $clean];
        }

        $fromApi = $this->checkViaGupshup($clean);
        if ($fromApi !== null) {
            return ['available' => $fromApi, 'phone' => $clean];
        }

        return ['available' => true, 'phone' => $clean];
    }

    /**
     * @param  array<int, int|string>  $ids
     * @return array<int, array{id: int, whatsapp: bool|null}>
     */
    public function checkCustomers(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $customers = ShopifyCustomer::query()->whereIn('id', $ids)->get();
        $results = [];

        foreach ($customers as $customer) {
            $results[] = [
                'id' => (int) $customer->id,
                'whatsapp' => $this->checkAndStore($customer),
            ];
        }

        return $results;
    }

    public function checkAndStore(ShopifyCustomer $customer): ?bool
    {
        $inspected = $this->inspect($customer->phone);

        if ($this->hasCacheColumns()) {
            $customer->forceFill([
                'whatsapp_available' => $inspected['available'],
                'whatsapp_phone' => $inspected['phone'],
                'whatsapp_checked_at' => now(),
            ])->save();
        }

        return $inspected['available'];
    }

    public function statusFromCache(ShopifyCustomer $customer): ?bool
    {
        return $this->cacheState($customer)['whatsapp'];
    }

    /**
     * @return array{whatsapp: bool|null, checked: bool}
     */
    public function cacheState(ShopifyCustomer $customer): array
    {
        $current = $this->normalizePhone($customer->phone);
        if ($current === null) {
            return ['whatsapp' => null, 'checked' => true];
        }

        if (! $this->hasCacheColumns()) {
            return ['whatsapp' => null, 'checked' => false];
        }

        $checkedAt = $customer->whatsapp_checked_at;
        $fresh = $checkedAt && $checkedAt->gte(now()->subDays(30)) && $customer->whatsapp_phone === $current;
        if (! $fresh) {
            return ['whatsapp' => null, 'checked' => false];
        }

        $available = $customer->whatsapp_available;
        if ($available === null) {
            return ['whatsapp' => null, 'checked' => true];
        }

        return ['whatsapp' => (bool) $available, 'checked' => true];
    }

    protected function isPlausiblePhone(string $digits): bool
    {
        $len = strlen($digits);

        return $len >= 8 && $len <= 15 && ! preg_match('/^0+$/', $digits);
    }

    protected function checkViaGupshup(string $digits): ?bool
    {
        if ($this->gupshupContactsSupported === false) {
            return null;
        }

        if (! $this->whatsAppService->isEnabled() || ! $this->whatsAppService->usesWaApi()) {
            $this->gupshupContactsSupported = false;

            return null;
        }

        $apiKey = (string) config('services.whatsapp.gupshup.api_key');
        $base = rtrim((string) config('services.whatsapp.gupshup.template_api_base', 'https://api.gupshup.io/wa/api/v1'), '/');

        try {
            $response = Http::withHeaders(['apikey' => $apiKey])
                ->acceptJson()
                ->timeout(8)
                ->post($base.'/contacts', [
                    'blocking' => 'wait',
                    'force_check' => true,
                    'contacts' => ['+'.$digits],
                ]);

            if ($response->status() === 404 || $response->status() === 405) {
                $this->gupshupContactsSupported = false;

                return null;
            }

            if (! $response->successful()) {
                $this->gupshupContactsSupported = false;

                return null;
            }

            $this->gupshupContactsSupported = true;
            $status = data_get($response->json(), 'contacts.0.status');
            if ($status === 'valid') {
                return true;
            }
            if ($status === 'invalid') {
                return false;
            }
        } catch (\Throwable $e) {
            Log::debug('WhatsApp availability check skipped', ['error' => $e->getMessage()]);
            $this->gupshupContactsSupported = false;
        }

        return null;
    }

    protected function hasCacheColumns(): bool
    {
        static $hasColumns = null;
        if ($hasColumns !== null) {
            return $hasColumns;
        }

        $hasColumns = Schema::hasTable('shopify_customers')
            && Schema::hasColumn('shopify_customers', 'whatsapp_available')
            && Schema::hasColumn('shopify_customers', 'whatsapp_phone')
            && Schema::hasColumn('shopify_customers', 'whatsapp_checked_at');

        return $hasColumns;
    }
}
