<?php

namespace App\Services\Crm;

use App\Models\Crm\Customer;
use App\Models\Crm\ShopifyCustomer;
use App\Models\GoogleMapsExtractorResult;
use App\Models\GoogleMapsExtractorSearch;
use Illuminate\Support\Facades\Schema;

class GoogleMapsLeadImportService
{
    public const SOURCE = 'Google';

    public const LOCAL_SHOPIFY_ID_BASE = 8_800_000_000_000;

    public function __construct(protected ShopifyCustomerClassifier $classifier) {}

    /**
     * @param  array<int, int>  $resultIds
     * @return array{created: int, updated: int, skipped: int, errors: array<int, string>}
     */
    public function import(GoogleMapsExtractorSearch $search, array $resultIds): array
    {
        $ids = [];
        foreach ($resultIds as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        $summary = [
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        if ($ids === []) {
            return $summary;
        }

        $results = $search->results()->whereIn('id', array_values($ids))->get();
        $tag = trim((string) $search->query);

        foreach ($results as $result) {
            try {
                $action = $this->importResult($result, $tag);
                $summary[$action]++;
            } catch (\Throwable $e) {
                $summary['skipped']++;
                $summary['errors'][] = trim((string) $result->name).': '.$e->getMessage();
            }
        }

        $summary['errors'] = array_slice($summary['errors'], 0, 20);

        return $summary;
    }

    /**
     * @return 'created'|'updated'
     */
    protected function importResult(GoogleMapsExtractorResult $result, string $tag): string
    {
        $name = trim((string) $result->name);
        if ($name === '') {
            throw new \RuntimeException('Lead is missing a name.');
        }

        $email = $this->nullableEmail($result->email);
        $phone = $this->nullableString($result->phone);
        $existing = $this->findExistingShopifyCustomer($result, $email, $phone);
        $crm = $this->resolveCrmCustomer($existing, $name, $email, $phone);
        $social = $this->socialFields($result);
        $address = $this->addressParts($result->address);
        $payload = $this->customerPayload($existing, $name, $email, $phone, $tag, $address, $result);

        if ($existing !== null) {
            $this->fillShopifyCustomer($existing, $crm, $name, $email, $phone, $payload, $social, false);
            $this->linkResult($result, $existing);

            return 'updated';
        }

        $shopifyId = $this->nextLocalShopifyCustomerId((int) $result->id);
        $record = new ShopifyCustomer;
        $record->incrementing = false;
        $record->id = $this->nextShopifyCustomersTableId($shopifyId);
        $this->fillShopifyCustomer($record, $crm, $name, $email, $phone, $payload, $social, true, $shopifyId);
        $this->linkResult($result, $record);

        return 'created';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array{website: ?string, facebook: ?string, instagram: ?string}  $social
     */
    protected function fillShopifyCustomer(
        ShopifyCustomer $record,
        Customer $crm,
        string $name,
        ?string $email,
        ?string $phone,
        array $payload,
        array $social,
        bool $creating,
        ?int $shopifyId = null
    ): void {
        $attributes = [
            'customer_id' => $crm->id,
            'email' => $email ?: $record->email,
            'first_name' => $creating ? $name : ($record->first_name ?: $name),
            'last_name' => $creating ? null : $record->last_name,
            'phone' => $phone ?: $record->phone,
            'raw_payload' => $payload,
            'sync_status' => $creating ? 'local' : ($record->sync_status ?: 'local'),
        ];

        if ($creating && $shopifyId !== null) {
            $attributes['shopify_customer_id'] = $shopifyId;
        }

        if (Schema::hasColumn('shopify_customers', 'website')) {
            $attributes['website'] = $social['website'] ?: $record->website;
            $attributes['facebook'] = $social['facebook'] ?: $record->facebook;
            $attributes['instagram'] = $social['instagram'] ?: $record->instagram;
        }

        $record->forceFill($attributes)->save();

        if (Schema::hasColumn('shopify_customers', 'classification_source')) {
            $fresh = $record->refresh();
            if (! $fresh->classification_overridden) {
                $this->classifier->classify($fresh);
            }
            $fresh->forceFill([
                'classification_source' => self::SOURCE,
                'classification_reason' => 'Imported from Google Maps',
                'classification_overridden' => true,
                'classified_at' => now(),
            ])->save();
        }
    }

    protected function findExistingShopifyCustomer(GoogleMapsExtractorResult $result, ?string $email, ?string $phone): ?ShopifyCustomer
    {
        if (Schema::hasColumn('google_maps_extractor_results', 'shopify_customer_id') && $result->shopify_customer_id) {
            $linked = ShopifyCustomer::query()
                ->where('shopify_customer_id', $result->shopify_customer_id)
                ->first();
            if ($linked !== null) {
                return $linked;
            }
        }

        if ($email !== null) {
            $byEmail = ShopifyCustomer::query()
                ->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])
                ->first();
            if ($byEmail !== null) {
                return $byEmail;
            }
        }

        $digits = $this->digitsOnly($phone);
        if ($digits !== '' && strlen($digits) >= 7) {
            return ShopifyCustomer::query()
                ->whereNotNull('phone')
                ->whereRaw(
                    "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, '+', ''), '-', ''), ' ', ''), '(', ''), ')', '') LIKE ?",
                    ['%'.$digits]
                )
                ->first();
        }

        return null;
    }

    protected function resolveCrmCustomer(?ShopifyCustomer $existing, string $name, ?string $email, ?string $phone): Customer
    {
        if ($existing?->customer_id) {
            $crm = Customer::query()->find($existing->customer_id);
            if ($crm !== null) {
                return $crm;
            }
        }

        if ($email !== null) {
            $crm = Customer::query()->whereRaw('LOWER(email) = ?', [mb_strtolower($email)])->first();
            if ($crm !== null) {
                return $crm;
            }
        }

        return Customer::query()->create([
            'company_id' => null,
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
        ]);
    }

    /**
     * @param  array{address1: ?string, province: ?string, zip: ?string}  $address
     * @return array<string, mixed>
     */
    protected function customerPayload(
        ?ShopifyCustomer $existing,
        string $name,
        ?string $email,
        ?string $phone,
        string $tag,
        array $address,
        GoogleMapsExtractorResult $result
    ): array {
        $payload = is_array($existing?->raw_payload) ? $existing->raw_payload : [];
        $tags = $this->classifier->tagsFromPayload($payload);
        if ($tag !== '' && ! $this->hasTag($tags, $tag)) {
            $tags[] = $tag;
        }

        $currentAddress = isset($payload['default_address']) && is_array($payload['default_address'])
            ? $payload['default_address']
            : [];

        $payload['tags'] = implode(', ', $tags);
        $payload['email'] = $email ?: ($payload['email'] ?? null);
        $payload['phone'] = $phone ?: ($payload['phone'] ?? null);
        $payload['first_name'] = $payload['first_name'] ?? $name;
        $payload['default_address'] = [
            'address1' => $address['address1'] ?: ($currentAddress['address1'] ?? null),
            'province' => $address['province'] ?: ($currentAddress['province'] ?? null),
            'zip' => $address['zip'] ?: ($currentAddress['zip'] ?? null),
        ];
        $payload['google_maps'] = [
            'result_id' => $result->id,
            'maps_url' => $result->maps_url,
            'category' => $result->category,
        ];

        return $payload;
    }

    /**
     * @return array{website: ?string, facebook: ?string, instagram: ?string}
     */
    protected function socialFields(GoogleMapsExtractorResult $result): array
    {
        $facebook = null;
        $instagram = null;
        foreach ((array) ($result->social_links ?? []) as $link) {
            $link = trim((string) $link);
            if ($link === '') {
                continue;
            }
            $host = mb_strtolower((string) parse_url($link, PHP_URL_HOST));
            if ($facebook === null && (str_contains($host, 'facebook.com') || str_contains($host, 'fb.com'))) {
                $facebook = $link;
            }
            if ($instagram === null && str_contains($host, 'instagram.com')) {
                $instagram = $link;
            }
        }

        return [
            'website' => $this->nullableString($result->website),
            'facebook' => $facebook,
            'instagram' => $instagram,
        ];
    }

    /**
     * @return array{address1: ?string, province: ?string, zip: ?string}
     */
    protected function addressParts(mixed $address): array
    {
        $address = $this->nullableString($address);
        $province = null;
        $zip = null;
        if ($address !== null && preg_match('/\b([A-Z]{2})\s+(\d{5})(?:-\d{4})?\b/', $address, $match)) {
            $province = $match[1];
            $zip = $match[2];
        }

        return [
            'address1' => $address,
            'province' => $province,
            'zip' => $zip,
        ];
    }

    protected function linkResult(GoogleMapsExtractorResult $result, ShopifyCustomer $customer): void
    {
        if (! Schema::hasColumn('google_maps_extractor_results', 'shopify_customer_id')) {
            return;
        }

        $result->forceFill([
            'shopify_customer_id' => $customer->shopify_customer_id,
        ])->save();
    }

    protected function nextLocalShopifyCustomerId(int $resultId): int
    {
        $candidate = self::LOCAL_SHOPIFY_ID_BASE + max(1, $resultId);
        while (ShopifyCustomer::query()->where('shopify_customer_id', $candidate)->exists()) {
            $candidate++;
        }

        return $candidate;
    }

    protected function nextShopifyCustomersTableId(int $preferred): int
    {
        if (! ShopifyCustomer::query()->whereKey($preferred)->exists()) {
            return $preferred;
        }

        return ((int) ShopifyCustomer::query()->max('id')) + 1;
    }

    /**
     * @param  array<int, string>  $tags
     */
    protected function hasTag(array $tags, string $tag): bool
    {
        $needle = mb_strtolower(trim($tag));
        foreach ($tags as $existing) {
            if (mb_strtolower(trim((string) $existing)) === $needle) {
                return true;
            }
        }

        return false;
    }

    protected function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    protected function nullableEmail(mixed $value): ?string
    {
        $email = $this->nullableString($value);
        if ($email === null || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return mb_strtolower($email);
    }

    protected function digitsOnly(?string $value): string
    {
        return preg_replace('/\D+/', '', (string) $value) ?? '';
    }
}
