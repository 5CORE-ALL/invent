<?php

namespace App\Services;

use App\Services\MarketplaceManager\MarketplaceLiveInventoryRules;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ReverbListingService
{
    protected string $token;

    public function __construct()
    {
        $this->token = (string) (ReverbApiService::getReverbBearerToken() ?: '');
    }

    /**
     * Update a Reverb listing's inventory by listing ID.
     *
     * Rules:
     * - Unlinked SKUs: never updated (caller skips).
     * - Unpublished / suspended / dead: never update.
     * - Draft: update inventory only — never publish / never force live.
     * - Linked + live OR sold/out_of_stock/ended: update from Shopify.
     * - Sold-family + Shopify qty >= 1: PUT inventory WITH publish=true (required by Reverb).
     * - Shopify 0 => marketplace 0.
     */
    public function updateListingInventory(string $listingId, int $quantity): bool
    {
        if (! $this->token) {
            Log::warning('ReverbListingService: no Reverb bearer token (OAuth or REVERB_TOKEN).');

            return false;
        }

        $listingId = trim((string) $listingId);
        if ($listingId === '') {
            Log::warning('ReverbListingService: empty listing ID.');

            return false;
        }

        $quantity = max(0, (int) $quantity);
        $state = $this->fetchListingStateSlug($listingId);

        if (MarketplaceLiveInventoryRules::reverbIsInactiveBlocked($state)) {
            Log::info('ReverbListingService: skipped inventory update on inactive listing', [
                'listing_id' => $listingId,
                'state' => $state,
                'requested_qty' => $quantity,
            ]);

            return true;
        }

        if (! MarketplaceLiveInventoryRules::reverbMayUpdateInventory($state)) {
            Log::info('ReverbListingService: skipped inventory update (state not eligible)', [
                'listing_id' => $listingId,
                'state' => $state,
                'requested_qty' => $quantity,
            ]);

            return true;
        }

        // Sold/ended/oos with stock: Reverb ignores inventory-only PUT — must publish.
        // Draft never publishes — inventory-only PUT keeps state=draft.
        $restockSold = $quantity > 0 && MarketplaceLiveInventoryRules::reverbMayPublishRestock($state);

        if (! $this->putListingInventory($listingId, $quantity, $restockSold)) {
            return false;
        }

        if ($restockSold) {
            $after = $this->fetchListingStateSlug($listingId);
            $inv = $this->fetchListingInventory($listingId);
            if ($after !== 'live' || (int) $inv < $quantity) {
                // One more explicit publish+inventory attempt
                $this->putListingInventory($listingId, $quantity, true);
                $after = $this->fetchListingStateSlug($listingId);
                $inv = $this->fetchListingInventory($listingId);
            }
            Log::info('ReverbListingService: sold restock result', [
                'listing_id' => $listingId,
                'from_state' => $state,
                'after_state' => $after,
                'after_inventory' => $inv,
                'requested_qty' => $quantity,
            ]);
        }

        return true;
    }

    protected function putListingInventory(string $listingId, int $quantity, bool $publishLive = false): bool
    {
        $url = 'https://api.reverb.com/api/listings/'.$listingId;
        $payload = ['inventory' => $quantity];
        if ($publishLive) {
            // Verified working on sold listings: invent without publish leaves state=sold inv=0.
            $payload['publish'] = true;
            $payload['state'] = ['slug' => 'live'];
        }

        $lastResponse = null;
        $maxAttempts = 3;
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $lastResponse = Http::withoutVerifying()
                ->withHeaders($this->headers())
                ->timeout(60)
                ->put($url, $payload);

            if ($lastResponse->successful()) {
                return true;
            }

            if ($attempt < $maxAttempts) {
                usleep(1000 * (int) pow(2, $attempt));
            }
        }

        Log::warning('ReverbListingService: failed to update listing inventory', [
            'listing_id' => $listingId,
            'publish_live' => $publishLive,
            'status' => $lastResponse ? $lastResponse->status() : null,
            'body' => $lastResponse ? $lastResponse->body() : null,
        ]);

        return false;
    }

    /**
     * @return array<string, string>
     */
    protected function headers(): array
    {
        return [
            'Authorization' => 'Bearer '.$this->token,
            'Accept' => 'application/hal+json',
            'Accept-Version' => '3.0',
            'Content-Type' => 'application/hal+json',
        ];
    }

    protected function fetchListingStateSlug(string $listingId): ?string
    {
        $item = $this->fetchListingJson($listingId);
        if ($item === null) {
            return null;
        }

        $state = $item['state'] ?? null;
        if (is_array($state)) {
            return strtolower(trim((string) ($state['slug'] ?? $state['description'] ?? ''))) ?: null;
        }
        if (is_string($state) && $state !== '') {
            return strtolower(trim($state));
        }

        return null;
    }

    protected function fetchListingInventory(string $listingId): ?int
    {
        $item = $this->fetchListingJson($listingId);
        if ($item === null) {
            return null;
        }

        if (array_key_exists('inventory', $item)) {
            return (int) $item['inventory'];
        }
        if (array_key_exists('quantity', $item)) {
            return (int) $item['quantity'];
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function fetchListingJson(string $listingId): ?array
    {
        try {
            $response = Http::withoutVerifying()
                ->withHeaders([
                    'Authorization' => 'Bearer '.$this->token,
                    'Accept' => 'application/hal+json',
                    'Accept-Version' => '3.0',
                ])
                ->timeout(30)
                ->get('https://api.reverb.com/api/listings/'.$listingId);

            if (! $response->successful()) {
                return null;
            }

            $item = $response->json();

            return is_array($item) ? $item : null;
        } catch (\Throwable $e) {
            Log::warning('ReverbListingService: failed to fetch listing', [
                'listing_id' => $listingId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
