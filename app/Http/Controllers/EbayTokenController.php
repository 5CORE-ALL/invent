<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EbayTokenController extends Controller
{
    private $ruNames = [
        'ebay1' => 'Amarjit_Kalra-AmarjitK-Produc-wmgogtl',
        'ebay2' => 'Pro_Light_Sound-ProLight-5CoreI-zhnuqwpvs',
        'ebay3' => 'Kaneer_Kaur_Kal-KaneerKa-5CoreI-yoyoa',
    ];

    private $baseUrl = 'https://auth.ebay.com/oauth2/authorize';
    private $tokenUrl = 'https://api.ebay.com/identity/v1/oauth2/token';

    private function getEnvKeys($account)
    {
        $keys = [
            'ebay1' => ['app_id' => 'EBAY_APP_ID', 'cert_id' => 'EBAY_CERT_ID', 'refresh_token' => 'EBAY_REFRESH_TOKEN'],
            'ebay2' => ['app_id' => 'EBAY2_APP_ID', 'cert_id' => 'EBAY2_CERT_ID', 'refresh_token' => 'EBAY2_REFRESH_TOKEN'],
            'ebay3' => ['app_id' => 'EBAY_3_APP_ID', 'cert_id' => 'EBAY_3_CERT_ID', 'refresh_token' => 'EBAY_3_REFRESH_TOKEN'],
        ];

        return $keys[$account] ?? null;
    }

    public function generate(Request $request)
    {
        $account = $request->get('account', 'ebay1'); // Default to ebay1
        
        if (!in_array($account, ['ebay1', 'ebay2', 'ebay3'])) {
            $account = 'ebay1';
        }

        $envKeys = $this->getEnvKeys($account);
        $clientId = env($envKeys['app_id']);
        $clientSecret = env($envKeys['cert_id']);

        if (!$clientId || !$clientSecret) {
            return view('ebay-token-generator', [
                'error' => "Missing {$envKeys['app_id']} or {$envKeys['cert_id']} in .env file",
                'selectedAccount' => $account,
            ]);
        }

        $scopes = [
            'https://api.ebay.com/oauth/api_scope/sell.account',
            'https://api.ebay.com/oauth/api_scope/sell.inventory',
            'https://api.ebay.com/oauth/api_scope/sell.fulfillment',
            'https://api.ebay.com/oauth/api_scope/sell.analytics.readonly',
            'https://api.ebay.com/oauth/api_scope/sell.stores',
            'https://api.ebay.com/oauth/api_scope/sell.finances',
            'https://api.ebay.com/oauth/api_scope/sell.marketing',
            'https://api.ebay.com/oauth/api_scope/sell.marketing.readonly',
        ];

        $scopeString = implode(' ', $scopes);
        $ruName = $this->ruNames[$account];

        // Add state parameter to identify which account when callback is received
        $state = base64_encode($account);

        $authUrl = $this->baseUrl . '?' . http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $ruName,
            'response_type' => 'code',
            'scope' => $scopeString,
            'state' => $state, // To identify account in callback
        ]);

        Log::info("eBay{$account} Authorization URL Generated", [
            'account' => $account,
            'ruName' => $ruName,
            'clientId' => substr($clientId, 0, 10) . '...',
        ]);

        return view('ebay-token-generator', [
            'authUrl' => $authUrl,
            'ruName' => $ruName,
            'clientId' => substr($clientId, 0, 10) . '...',
            'selectedAccount' => $account,
            'debugUrl' => $authUrl,
        ]);
    }

    public function callback(Request $request)
    {
        // Get account from state parameter (from eBay redirect), request parameter, or default to ebay1
        $state = $request->get('state');
        if ($state) {
            $account = base64_decode($state);
            if (!in_array($account, ['ebay1', 'ebay2', 'ebay3'])) {
                $account = null;
            }
        }
        
        // Fallback to request parameter or default
        $account = $account ?? $request->get('account') ?? $request->post('account') ?? 'ebay1';
        
        if (!in_array($account, ['ebay1', 'ebay2', 'ebay3'])) {
            $account = 'ebay1';
        }

        // Support both GET (from eBay redirect) and POST (from form submission)
        $code = $request->get('code') ?? $request->post('code');
        $error = $request->get('error');

        if ($error) {
            return view('ebay-token-result', [
                'error' => 'Authorization failed: ' . $error,
                'errorDescription' => $request->get('error_description', 'Unknown error'),
                'account' => $account,
            ]);
        }

        if (!$code) {
            return view('ebay-token-result', [
                'error' => 'No authorization code received from eBay',
                'account' => $account,
            ]);
        }

        $envKeys = $this->getEnvKeys($account);
        $clientId = env($envKeys['app_id']);
        $clientSecret = env($envKeys['cert_id']);

        if (!$clientId || !$clientSecret) {
            return view('ebay-token-result', [
                'error' => "Missing {$envKeys['app_id']} or {$envKeys['cert_id']} in .env file",
                'account' => $account,
            ]);
        }

        $ruName = $this->ruNames[$account];

        try {
            $response = Http::asForm()
                ->withBasicAuth($clientId, $clientSecret)
                ->post($this->tokenUrl, [
                    'grant_type' => 'authorization_code',
                    'code' => $code,
                    'redirect_uri' => $ruName,
                ]);

            if ($response->successful()) {
                $data = $response->json();

                if (isset($data['refresh_token'])) {
                    Log::info("eBay{$account} Refresh Token Generated Successfully", [
                        'account' => $account,
                        'ruName' => $ruName,
                        'token_length' => strlen($data['refresh_token']),
                    ]);

                    return view('ebay-token-result', [
                        'success' => true,
                        'refreshToken' => $data['refresh_token'],
                        'accessToken' => $data['access_token'] ?? null,
                        'expiresIn' => $data['expires_in'] ?? null,
                        'account' => $account,
                        'envKey' => $envKeys['refresh_token'],
                    ]);
                } else {
                    return view('ebay-token-result', [
                        'error' => 'Response did not contain refresh_token',
                        'response' => $data,
                        'account' => $account,
                    ]);
                }
            } else {
                $errorBody = $response->body();
                Log::error("eBay{$account} Refresh Token Generation Failed", [
                    'account' => $account,
                    'status' => $response->status(),
                    'response' => $errorBody,
                    'redirect_uri' => $ruName,
                ]);

                return view('ebay-token-result', [
                    'error' => 'Failed to exchange authorization code',
                    'status' => $response->status(),
                    'response' => $errorBody,
                    'account' => $account,
                ]);
            }
        } catch (\Exception $e) {
            Log::error("eBay{$account} Refresh Token Generation Exception", [
                'account' => $account,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return view('ebay-token-result', [
                'error' => 'Exception: ' . $e->getMessage(),
                'account' => $account,
            ]);
        }
    }
}
