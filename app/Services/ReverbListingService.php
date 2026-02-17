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

        $url = "https://api.reverb.com/api/listings/{$listingId}";
        $response = Http::withoutVerifying()
            ->withHeaders([
                'Authorization' => 'Bearer ' . $this->token,
                'Accept' => 'application/hal+json',
                'Accept-Version' => '3.0',
                'Content-Type' => 'application/hal+json',
            ])
            ->timeout(30)
            ->put($url, [
                'inventory' => max(0, $quantity),
            ]);

        if ($response->successful()) {
            return true;
        }

        Log::warning('ReverbListingService: failed to update listing inventory', [
            'listing_id' => $listingId,
            'status' => $response->status(),
            'body' => $response->body(),
        ]);
        return false;
    }
}
