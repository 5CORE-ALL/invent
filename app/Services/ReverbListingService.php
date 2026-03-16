<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ReverbListingService
{
    protected string $token;

    public function __construct()
    {
        $this->token = (string) (config('services.reverb.token') ?: env('REVERB_TOKEN', ''));
    }

    /**
     * Update a Reverb listing's inventory by listing ID.
     * PUT https://api.reverb.com/api/listings/{id} with inventory field.
     */
    public function updateListingInventory(string $listingId, int $quantity): bool
    {
        if (! $this->token) {
            Log::warning('ReverbListingService: REVERB_TOKEN not set.');
            return false;
        }

        $listingId = trim((string) $listingId);
        if ($listingId === '') {
            Log::warning('ReverbListingService: empty listing ID.');
            return false;
        }

        $url = 'https://api.reverb.com/api/listings/' . $listingId;
        $payload = ['inventory' => max(0, (int) $quantity)];

        $lastResponse = null;
        $maxAttempts = 3;
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $lastResponse = Http::withoutVerifying()
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->token,
                    'Accept' => 'application/hal+json',
                    'Accept-Version' => '3.0',
                    'Content-Type' => 'application/hal+json',
                ])
                ->timeout(60)
                ->put($url, $payload);

            if ($lastResponse->successful()) {
                return true;
            }

            if ($attempt < $maxAttempts) {
                usleep(1000 * (int) pow(2, $attempt)); // 2s, 4s backoff
            }
        }

        Log::warning('ReverbListingService: failed to update listing inventory', [
            'listing_id' => $listingId,
            'status' => $lastResponse ? $lastResponse->status() : null,
            'body' => $lastResponse ? $lastResponse->body() : null,
        ]);
        return false;
    }
}
