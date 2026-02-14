<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\EbayMetric; // Add this at the top

class EbayDataUpdateController extends Controller
{
    public function updatePrice(Request $request)
    {
        try {
             $request->validate([
                'item_id' => 'required',
                'price' => 'required|numeric|min:0.01',
            ]);

            $token = $this->generateEbayToken();

            $headers = [
                'X-EBAY-API-COMPATIBILITY-LEVEL' => '967',
                'X-EBAY-API-DEV-NAME' => config('services.ebay.dev_id'),
                'X-EBAY-API-APP-NAME' => config('services.ebay.app_id'),
                'X-EBAY-API-CERT-NAME' => config('services.ebay.cert_id'),
                'X-EBAY-API-CALL-NAME' => 'ReviseFixedPriceItem',
                'X-EBAY-API-SITEID' => '0',
                'Content-Type' => 'text/xml',
            ];

            $xmlBody = '<?xml version="1.0" encoding="utf-8"?>
            <ReviseFixedPriceItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">
            <RequesterCredentials>
                <eBayAuthToken>' . $token . '</eBayAuthToken>
            </RequesterCredentials>
            <Item>
                <ItemID>' . $request->item_id . '</ItemID>
                <StartPrice>' . number_format($request->price, 2) . '</StartPrice>
            </Item>
            </ReviseFixedPriceItemRequest>';

            $endpoint = 'https://api.ebay.com/ws/api.dll';

            $response = Http::withHeaders($headers)->withBody($xmlBody, 'text/xml')->post($endpoint);

            $xml = simplexml_load_string($response->body());

            if ((string) $xml->Ack === 'Success' || (string) $xml->Ack === 'Warning') {
                // Update ebay_price in EbayMetric table
                $ebayMetric = EbayMetric::where('item_id', $request->item_id)->first();
                if ($ebayMetric) {
                    $ebayMetric->ebay_price = $request->price;
                    $ebayMetric->save();
                }
                return response()->json([
                    'message' => 'Updated successfully',
                    'ack' => (string) $xml->Ack,
                    'price' => $request->price
                ]);
            } else {
                Log::error('eBay price update failed', [
                    'item_id' => $request->item_id,
                    'price' => $request->price,
                    'response' => $response->body()
                ]);
                return response()->json([
                    'message' => 'Failed to update',
                    'error' => (string) $xml->Errors->LongMessage
                ], 422);
            }

        } catch (\Exception $e) {
            Log::error('eBay price update exception: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    private function generateEbayToken(): ?string
    {
        $clientId = config('services.ebay.app_id');
        $clientSecret = config('services.ebay.cert_id');

        $scope = implode(' ', [
            'https://api.ebay.com/oauth/api_scope',
            'https://api.ebay.com/oauth/api_scope/sell.account',
            'https://api.ebay.com/oauth/api_scope/sell.inventory',
            'https://api.ebay.com/oauth/api_scope/sell.account',
            'https://api.ebay.com/oauth/api_scope/sell.fulfillment',
            'https://api.ebay.com/oauth/api_scope/sell.analytics.readonly',
            'https://api.ebay.com/oauth/api_scope/sell.stores',
            'https://api.ebay.com/oauth/api_scope/sell.finances',
            'https://api.ebay.com/oauth/api_scope/sell.marketing',
        ]);

        try {
            $response = Http::asForm()
                ->withBasicAuth($clientId, $clientSecret)
                ->post('https://api.ebay.com/identity/v1/oauth2/token', [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => config('services.ebay.refresh_token'),
                    'scope' => $scope,
                ]);

            if ($response->successful()) {
                Log::error('eBay token', ['response' => 'Token generated!']);
                return $response->json()['access_token'];
            }

            Log::error('eBay token refresh error', ['response' => $response->json()]);
        } catch (\Exception $e) {
            Log::error('eBay token refresh exception: ' . $e->getMessage());
        }

        return null;
    }
}
