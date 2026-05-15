<?php

namespace App\Http\Controllers\PurchaseMaster;

use App\Http\Controllers\Controller;
use App\Models\ProductMaster;
use App\Models\ShopifySku;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class HeroImageController extends Controller
{
    public function heroImagesMaster()
    {
        return view('hero-images-master');
    }

    public function getHeroImagesMasterData(Request $request)
    {
        // Fetch all products from the database ordered by parent and SKU
        $products = ProductMaster::orderBy('parent', 'asc')
            ->orderByRaw("CASE WHEN sku LIKE 'PARENT %' THEN 1 ELSE 0 END")
            ->orderBy('sku', 'asc')
            ->get();

        // Fetch all shopify SKUs and normalize keys by replacing non-breaking spaces
        $shopifySkus = ShopifySku::all()->keyBy(function ($item) {
            // Normalize SKU: replace non-breaking spaces (\u00a0) with regular spaces
            return str_replace("\u{00a0}", ' ', $item->sku);
        });

        // Fetch amazon data view with buyer and seller links
        $amazonDataViews = \App\Models\AmazonDataView::all()->keyBy(function ($item) {
            return str_replace("\u{00a0}", ' ', $item->sku);
        });

        // Fetch junglescout data for LQS (Listing Quality Score)
        $junglescoutData = \DB::table('junglescout_product_data')
            ->get()
            ->keyBy(function ($item) {
                return str_replace("\u{00a0}", ' ', $item->sku);
            });

        // Prepare data in the same format as A+ Images Master
        $result = [];
        foreach ($products as $product) {
            $row = [
                'id' => $product->id,
                'Parent' => $product->parent,
                'SKU' => $product->sku,
            ];

            // Merge the Values array (if not null)
            if (is_array($product->Values)) {
                $row = array_merge($row, $product->Values);
            } elseif (is_string($product->Values)) {
                $values = json_decode($product->Values, true);
                if (is_array($values)) {
                    $row = array_merge($row, $values);
                }
            }

            // Add Shopify inv and quantity if available
            // Normalize the product SKU for lookup
            $normalizedSku = str_replace("\u{00a0}", ' ', $product->sku);

            if (isset($shopifySkus[$normalizedSku])) {
                $shopifyData = $shopifySkus[$normalizedSku];
                $row['shopify_inv'] = $shopifyData->inv !== null ? (float) $shopifyData->inv : 0;
                $row['shopify_quantity'] = $shopifyData->quantity !== null ? (float) $shopifyData->quantity : 0;

                // Ovl30 is shopify_quantity (same as product-master page)
                $row['ovl30'] = $row['shopify_quantity'];

                // Calculate Dil (Days in Inventory) = (shopify_quantity / shopify_inv) * 100
                $inv = $row['shopify_inv'];
                $ovl30 = $row['shopify_quantity'];
                $dil = ($inv > 0) ? ($ovl30 / $inv) * 100 : 0;
                $row['dil'] = round($dil, 2);

                $shopifyImage = $shopifyData->image_src ?? null;
            } else {
                $row['shopify_inv'] = 0;
                $row['shopify_quantity'] = 0;
                $row['ovl30'] = 0;
                $row['dil'] = 0;
                $shopifyImage = null;
            }

            $shopifyImage = $shopifySkus[$normalizedSku]->image_src ?? null;
            // image_path is inside $row (from Values JSON)
            $localImage = isset($row['image_path']) && $row['image_path'] ? $row['image_path'] : null;
            if ($shopifyImage) {
                $row['image_path'] = $shopifyImage; // Use Shopify URL
            } elseif ($localImage) {
                $row['image_path'] = '/'.ltrim($localImage, '/'); // Use local path, ensure leading slash
            } else {
                $row['image_path'] = null;
            }

            // Add Amazon buyer and seller links from amazon_data_view
            if (isset($amazonDataViews[$normalizedSku])) {
                $amazonData = $amazonDataViews[$normalizedSku];
                $amazonValue = is_array($amazonData->value) ? $amazonData->value : json_decode($amazonData->value, true);
                $row['buyer_link'] = $amazonValue['buyer_link'] ?? null;
                $row['seller_link'] = $amazonValue['seller_link'] ?? null;
            } else {
                $row['buyer_link'] = null;
                $row['seller_link'] = null;
            }

            // Add Junglescout LQS data
            if (isset($junglescoutData[$normalizedSku])) {
                $jsData = $junglescoutData[$normalizedSku];
                $jsDataValue = is_array($jsData->data) ? $jsData->data : json_decode($jsData->data, true);
                $row['listing_quality_score'] = $jsDataValue['listing_quality_score'] ?? null;
                $row['lqs'] = $jsDataValue['listing_quality_score'] ?? null;
            } else {
                $row['listing_quality_score'] = null;
                $row['lqs'] = null;
            }

            // Surface DB link, hero image, AI hero-image analysis, and push history
            // from Values JSON. (DB is shared with A+ Images Master.)
            $row['db'] = $row['db'] ?? ($row['DB'] ?? null);
            $row['hero_image'] = $row['hero_image'] ?? null;
            $row['hero_analysis'] = $row['hero_analysis'] ?? null;
            $row['hero_analysis_at'] = $row['hero_analysis_at'] ?? null;
            $row['hero_pushed_at'] = $row['hero_pushed_at'] ?? null;
            $row['hero_pushed_to'] = $row['hero_pushed_to'] ?? null;
            $row['hero_push_status'] = $row['hero_push_status'] ?? null; // success|failed
            $row['hero_push_history'] = $row['hero_push_history'] ?? [];

            $result[] = $row;
        }

        return response()->json([
            'message' => 'Data loaded from database',
            'data' => $result,
            'status' => 200,
        ]);
    }

    /**
     * Update the DB link stored in product_masters.Values for a given SKU.
     * The DB column is shared with the A+ Images Master view.
     */
    public function updateDBLink(Request $request)
    {
        try {
            $validated = $request->validate([
                'sku' => 'required|string',
                'db' => 'nullable|string|url',
            ]);

            $product = $this->findProductBySku($validated['sku']);

            if (! $product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found',
                ], 404);
            }

            $values = $this->getValuesArray($product);
            $values['db'] = $validated['db'];
            $values['DB'] = $validated['db'];

            $product->Values = $values;
            $product->save();

            return response()->json([
                'success' => true,
                'message' => 'DB link updated successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Hero Update DB Link Error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update DB link: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Upload (or replace) the hero image for a SKU. Stores the file under
     * storage/app/public/hero_images and saves the relative path in
     * product_masters.Values.hero_image.
     */
    public function uploadHeroImage(Request $request)
    {
        try {
            $validated = $request->validate([
                'sku' => 'required|string',
                'image_file' => 'required|file|mimes:jpeg,jpg,png,gif,bmp,webp,svg|max:10240',
            ]);

            $product = $this->findProductBySku($validated['sku']);

            if (! $product) {
                Log::error('Hero upload — product not found for SKU: '.$validated['sku']);

                return response()->json([
                    'success' => false,
                    'message' => 'Product not found for SKU: '.$validated['sku'],
                ], 404);
            }

            $values = $this->getValuesArray($product);

            $imageFile = $request->file('image_file');
            $safeSku = preg_replace('/[^A-Za-z0-9_\-]/', '_', $validated['sku']);
            $imageName = 'hero_'.$safeSku.'_'.time().'.'.$imageFile->getClientOriginalExtension();

            $directory = 'hero_images';
            if (! Storage::disk('public')->exists($directory)) {
                Storage::disk('public')->makeDirectory($directory);
            }

            $imagePath = $imageFile->storeAs($directory, $imageName, 'public');

            $values['hero_image'] = $imagePath;
            $product->Values = $values;
            $product->save();

            return response()->json([
                'success' => true,
                'message' => 'Hero image uploaded successfully',
                'image_path' => $imagePath,
            ]);
        } catch (\Exception $e) {
            Log::error('Hero Image Upload Error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to upload hero image: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Run an AI vision analysis on the SKU's hero image and persist the
     * structured JSON result in product_masters.Values.hero_analysis.
     *
     * Uses Anthropic Claude (Vision) via the same auth/header pattern the
     * rest of the app uses for Claude calls. Reads the API key from the
     * `services.anthropic.key` config (which falls back from
     * ANTHROPIC_API_KEY → CLAUDE_API_KEY in .env). Model is overridable via
     * env ANTHROPIC_HERO_VISION_MODEL.
     */
    public function analyzeHeroImage(Request $request)
    {
        try {
            $validated = $request->validate([
                'sku' => 'required|string',
                'force' => 'sometimes|boolean',
            ]);

            $product = $this->findProductBySku($validated['sku']);

            if (! $product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found for SKU: '.$validated['sku'],
                ], 404);
            }

            $values = $this->getValuesArray($product);

            $heroImage = $values['hero_image'] ?? null;
            if (! is_string($heroImage) || trim($heroImage) === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'No hero image uploaded for this SKU. Upload a hero image first.',
                ], 422);
            }

            // Resolve image bytes + MIME — supports both stored-on-disk paths and external URLs.
            [$bytes, $mime, $resolveErr] = $this->loadImageBytes($heroImage);
            if ($resolveErr !== null) {
                return response()->json(['success' => false, 'message' => $resolveErr], 422);
            }

            $apiKey = config('services.anthropic.key');
            if (! is_string($apiKey) || $apiKey === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Claude API key is not configured (set ANTHROPIC_API_KEY or CLAUDE_API_KEY in .env).',
                ], 500);
            }

            $primaryModel = (string) config('services.anthropic.hero_vision_model', 'claude-sonnet-4-20250514');
            $apiVersion = (string) config('services.anthropic.version', '2023-06-01');

            // Claude vision only accepts a fixed set of media types.
            $mime = $this->normalizeImageMime($mime);

            $b64 = base64_encode($bytes);
            $prompt = $this->heroAnalysisPrompt();

            // Try the configured model first; on "invalid model" errors fall back to
            // Haiku, which every Anthropic key has access to and which also supports vision.
            $candidateModels = array_values(array_unique(array_filter([
                $primaryModel,
                'claude-3-haiku-20240307',
            ])));

            $response = null;
            $usedModel = null;
            $lastError = null;

            foreach ($candidateModels as $candidate) {
                $response = Http::withHeaders([
                    'x-api-key' => $apiKey,
                    'anthropic-version' => $apiVersion,
                    'content-type' => 'application/json',
                ])
                    ->timeout(120)
                    ->post('https://api.anthropic.com/v1/messages', [
                        'model' => $candidate,
                        'max_tokens' => 1500,
                        'messages' => [
                            [
                                'role' => 'user',
                                'content' => [
                                    [
                                        'type' => 'image',
                                        'source' => [
                                            'type' => 'base64',
                                            'media_type' => $mime,
                                            'data' => $b64,
                                        ],
                                    ],
                                    [
                                        'type' => 'text',
                                        'text' => $prompt,
                                    ],
                                ],
                            ],
                        ],
                    ]);

                if ($response->successful()) {
                    $usedModel = $candidate;
                    break;
                }

                $bodyJson = $response->json();
                $lastError = data_get($bodyJson, 'error.message', $response->body());
                $errorType = (string) data_get($bodyJson, 'error.type', '');

                Log::warning('Hero image Claude analysis attempt failed', [
                    'sku' => $validated['sku'],
                    'model' => $candidate,
                    'status' => $response->status(),
                    'error_type' => $errorType,
                    'error' => $bodyJson['error'] ?? $response->body(),
                ]);

                // Only fall through to the next model if the failure looks model-specific.
                $msgLower = is_string($lastError) ? strtolower($lastError) : '';
                $isModelIssue = $errorType === 'not_found_error'
                    || str_contains($msgLower, 'model')
                    || str_contains($msgLower, 'permission')
                    || $response->status() === 404;

                if (! $isModelIssue) {
                    break; // network / auth / rate-limit — no point retrying with another model
                }
            }

            if (! $response || ! $response->successful()) {
                $hint = '';
                if (is_string($lastError) && str_contains(strtolower($lastError), 'model')) {
                    $hint = ' (set ANTHROPIC_HERO_VISION_MODEL in .env to a model your key supports, e.g. claude-3-haiku-20240307)';
                }

                return response()->json([
                    'success' => false,
                    'message' => 'AI service error: '.(is_string($lastError) ? $lastError : 'Request failed').$hint,
                    'tried_models' => $candidateModels,
                ], 502);
            }

            $model = $usedModel ?: $primaryModel;

            $text = (string) data_get($response->json(), 'content.0.text', '');
            if (trim($text) === '') {
                return response()->json(['success' => false, 'message' => 'Empty AI response.'], 502);
            }

            $cleaned = $this->stripJsonFences(trim($text));
            $analysis = json_decode($cleaned, true);

            if (! is_array($analysis)) {
                // Claude sometimes adds a short preamble before the JSON object —
                // try to extract the first {...} block as a fallback.
                if (preg_match('/\{.*\}/s', $cleaned, $m)) {
                    $analysis = json_decode($m[0], true);
                }
            }

            if (! is_array($analysis)) {
                Log::warning('Hero image Claude analysis returned non-JSON', ['raw' => $text]);

                return response()->json([
                    'success' => false,
                    'message' => 'AI returned non-JSON response.',
                    'raw' => $text,
                ], 502);
            }

            // Persist alongside the rest of the product's Values blob.
            $values['hero_analysis'] = $analysis;
            $values['hero_analysis_at'] = now()->toIso8601String();
            $values['hero_analysis_model'] = $model;

            $product->Values = $values;
            $product->save();

            return response()->json([
                'success' => true,
                'message' => 'Hero image analyzed successfully',
                'analysis' => $analysis,
                'analyzed_at' => $values['hero_analysis_at'],
                'model' => $model,
            ]);
        } catch (\Exception $e) {
            Log::error('Hero Image Analysis Error: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to analyze hero image: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Push the SKU's hero image to the configured marketplace as the main
     * product image. Currently supports Amazon (via AmazonSpApiService).
     *
     * Approval rules (any of the following counts as approved unless ?force=1):
     *   - Values.status_toggle === 'green', OR
     *   - Values.hero_analysis.pass_fail === 'PASS'
     *
     * Each push attempt is appended to Values.hero_push_history (keeping
     * the last 20) for an audit trail.
     */
    public function pushHeroImage(Request $request)
    {
        try {
            $validated = $request->validate([
                'sku' => 'required|string',
                'site' => 'sometimes|string|in:amazon',
                'force' => 'sometimes|boolean',
            ]);

            $site = strtolower($validated['site'] ?? 'amazon');
            $force = (bool) ($validated['force'] ?? false);

            $product = $this->findProductBySku($validated['sku']);
            if (! $product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Product not found for SKU: '.$validated['sku'],
                ], 404);
            }

            $values = $this->getValuesArray($product);

            $heroImage = $values['hero_image'] ?? null;
            if (! is_string($heroImage) || trim($heroImage) === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'No hero image uploaded for this SKU. Upload one first.',
                ], 422);
            }

            // Approval gate (skippable with ?force=1 from the UI's "push anyway" path).
            if (! $force) {
                $statusGreen = ($values['status_toggle'] ?? null) === 'green';
                $aiPass = (string) data_get($values, 'hero_analysis.pass_fail', '') === 'PASS';
                if (! $statusGreen && ! $aiPass) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Hero image is not approved. Either toggle status to green or run AI analysis with PASS, then try again. Use force=true to override.',
                        'requires_force' => true,
                    ], 422);
                }
            }

            // Build a publicly reachable HTTPS URL for Amazon.
            [$publicUrl, $urlErr] = $this->buildPublicHeroUrl($heroImage);
            if ($urlErr !== null) {
                return response()->json(['success' => false, 'message' => $urlErr], 422);
            }

            $result = ['success' => false, 'message' => 'Unsupported site.'];
            $pushedTo = $site;

            if ($site === 'amazon') {
                $result = $this->pushHeroToAmazon($validated['sku'], $publicUrl);
            }

            // Record the attempt regardless of outcome — useful for auditing.
            $entry = [
                'site' => $site,
                'image_url' => $publicUrl,
                'success' => (bool) ($result['success'] ?? false),
                'message' => (string) ($result['message'] ?? ''),
                'pushed_at' => now()->toIso8601String(),
                'user_id' => auth()->id(),
                'user_name' => auth()->user()->name ?? null,
            ];

            $history = is_array($values['hero_push_history'] ?? null) ? $values['hero_push_history'] : [];
            array_unshift($history, $entry);
            $values['hero_push_history'] = array_slice($history, 0, 20);

            if (! empty($result['success'])) {
                $values['hero_pushed_at'] = $entry['pushed_at'];
                $values['hero_pushed_to'] = $pushedTo;
                $values['hero_push_status'] = 'success';
            } else {
                $values['hero_push_status'] = 'failed';
            }

            $product->Values = $values;
            $product->save();

            return response()->json([
                'success' => (bool) ($result['success'] ?? false),
                'message' => $result['message'] ?? '',
                'site' => $site,
                'image_url' => $publicUrl,
                'pushed_at' => $values['hero_pushed_at'] ?? null,
            ], $result['success'] ? 200 : 502);
        } catch (\Exception $e) {
            Log::error('Hero Image Push Error: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to push hero image: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Push the hero image to Amazon as the main product image.
     *
     * Mirrors the simple/direct PATCH pattern used by Title Master
     * (ProductMasterController::updateTitlesToAmazon): LWA token → direct
     * Listings Items 2021-08-01 PATCH on /attributes/main_product_image_locator
     * with productType=PRODUCT. Only patches main image — other_product_image_*
     * positions are untouched.
     *
     * @return array{success: bool, message: string}
     */
    private function pushHeroToAmazon(string $sku, string $publicUrl): array
    {
        // Hero push uses its OWN dedicated SP-API credential block
        // (services.amazon_sp_b2 — backed by SPAPIB2_* env vars) so rotating
        // creds for hero push doesn't disturb Title Master or any other
        // feature that uses the shared services.amazon_sp.* config.
        // Each SPAPIB2_* env value falls back to its SPAPI_* counterpart.
        $clientId = config('services.amazon_sp_b2.client_id');
        $clientSecret = config('services.amazon_sp_b2.client_secret');
        $refreshToken = config('services.amazon_sp_b2.refresh_token');
        $sellerId = config('services.amazon_sp_b2.seller_id');
        $marketplaceId = (string) config('services.amazon_sp_b2.marketplace_id', 'ATVPDKIKX0DER');
        $endpoint = (string) config('services.amazon_sp_b2.endpoint', 'https://sellingpartnerapi-na.amazon.com');

        if (! $clientId || ! $clientSecret || ! $refreshToken || ! $sellerId) {
            return [
                'success' => false,
                'message' => 'Hero push SP-API credentials not configured properly in .env. '.
                    'Set SPAPIB2_CLIENT_ID, SPAPIB2_CLIENT_SECRET, SPAPIB2_REFRESH_TOKEN, AMAZONB2_SELLER_ID '.
                    '(or leave them empty to fall back to SPAPI_* / AMAZON_SELLER_ID).',
            ];
        }

        [$accessToken, $tokenError] = $this->getAmazonAccessToken($clientId, $clientSecret, $refreshToken);
        if (! $accessToken) {
            return [
                'success' => false,
                'message' => 'Amazon LWA token failed: '.($tokenError ?: 'unknown error').
                    '. Verify SPAPIB2_CLIENT_ID, SPAPIB2_CLIENT_SECRET, and SPAPIB2_REFRESH_TOKEN '.
                    '(or their SPAPI_* fallbacks) all belong to the same SP-API app.',
            ];
        }

        $url = $endpoint.'/listings/2021-08-01/items/'.$sellerId.'/'.rawurlencode($sku).'?marketplaceIds='.$marketplaceId;

        $payload = [
            'productType' => 'PRODUCT',
            'patches' => [
                [
                    'op' => 'replace',
                    'path' => '/attributes/main_product_image_locator',
                    'value' => [
                        [
                            'marketplace_id' => $marketplaceId,
                            'media_location' => $publicUrl,
                        ],
                    ],
                ],
            ],
        ];

        try {
            $response = Http::withHeaders([
                'x-amz-access-token' => $accessToken,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])
                ->timeout(60)
                ->patch($url, $payload);

            $status = $response->status();
            $body = $response->json() ?? [];
            $hasErrors = ! empty($body['errors']);

            if (($status === 200 || $status === 202) && ! $hasErrors) {
                Log::info('Hero image pushed to Amazon', [
                    'sku' => $sku,
                    'image_url' => $publicUrl,
                    'status' => $status,
                ]);

                return [
                    'success' => true,
                    'message' => 'Hero image pushed to Amazon as main product image.',
                ];
            }

            $errorMsg = $hasErrors
                ? ($body['errors'][0]['message'] ?? json_encode($body['errors']))
                : ($response->body() ?: 'Amazon returned status '.$status);

            Log::warning('Hero push to Amazon failed', [
                'sku' => $sku,
                'status' => $status,
                'errors' => $body['errors'] ?? null,
                'body_preview' => substr((string) $response->body(), 0, 500),
            ]);

            return [
                'success' => false,
                'message' => 'Amazon error ('.$status.'): '.substr((string) $errorMsg, 0, 400),
            ];
        } catch (\Throwable $e) {
            Log::error('Hero push to Amazon exception', [
                'sku' => $sku,
                'url' => $publicUrl,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Amazon push failed: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Exchange the SP-API refresh token for a short-lived LWA access token.
     * Mirrors ProductMasterController::getAmazonAccessToken but additionally
     * returns Amazon's error message so the UI can surface why auth failed
     * (most common causes: invalid_client, invalid_grant, mismatched app).
     *
     * @return array{0: ?string, 1: ?string} [accessToken, errorMessage]
     */
    private function getAmazonAccessToken(string $clientId, string $clientSecret, string $refreshToken): array
    {
        // Trim any whitespace/newlines that often sneak in when copy-pasting
        // long refresh tokens from Amazon Seller Central.
        $clientId = trim($clientId);
        $clientSecret = trim($clientSecret);
        $refreshToken = trim($refreshToken);

        if ($clientId === '' || $clientSecret === '' || $refreshToken === '') {
            return [null, 'one or more SP-API credentials are empty after trimming'];
        }

        try {
            $response = Http::asForm()
                ->timeout(30)
                ->withHeaders(['Accept' => 'application/json'])
                ->post('https://api.amazon.com/auth/o2/token', [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $refreshToken,
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                ]);

            if ($response->successful()) {
                $token = $response->json('access_token');
                if (is_string($token) && $token !== '') {
                    return [$token, null];
                }

                return [null, 'Amazon returned 200 but no access_token in body: '.substr((string) $response->body(), 0, 300)];
            }

            $body = $response->json() ?? [];
            $err = (string) ($body['error'] ?? 'http_'.$response->status());
            $desc = (string) ($body['error_description'] ?? '');
            $human = $desc !== '' ? ($err.' — '.$desc) : $err;

            Log::error('Hero push: failed to get Amazon LWA token', [
                'status' => $response->status(),
                'amazon_error' => $err,
                'amazon_error_description' => $desc,
                // Log only the first/last 6 chars of secrets so we can spot
                // copy-paste / whitespace issues without leaking the values.
                'client_id_fingerprint' => $this->fingerprint($clientId),
                'client_secret_fingerprint' => $this->fingerprint($clientSecret),
                'refresh_token_fingerprint' => $this->fingerprint($refreshToken),
                'body_preview' => substr((string) $response->body(), 0, 500),
            ]);

            return [null, $human];
        } catch (\Throwable $e) {
            Log::error('Hero push: LWA token exception', ['error' => $e->getMessage()]);

            return [null, 'network/exception: '.$e->getMessage()];
        }
    }

    /**
     * Short, non-leaking fingerprint of a secret for log debugging
     * (lets us tell e.g. "the .env still has the old refresh token").
     */
    private function fingerprint(string $value): string
    {
        $len = strlen($value);
        if ($len === 0) {
            return 'empty';
        }
        if ($len <= 12) {
            return 'len='.$len;
        }

        return substr($value, 0, 6).'…'.substr($value, -6).' (len='.$len.')';
    }

    /**
     * Build the publicly reachable HTTPS URL for a hero image stored on the
     * public disk (Amazon SP-API requires HTTPS).
     *
     * URL host resolution (first match wins):
     *   1. HERO_PUBLIC_BASE_URL env override (services.hero_images.public_base_url)
     *   2. REVERB_SKU_IMAGE_PUBLIC_BASE_URL (already wired in this app)
     *   3. APP_URL
     *   4. The default URL Storage::disk('public')->url() returns
     *
     * @return array{0: ?string, 1: ?string} [url, errorMessage]
     */
    private function buildPublicHeroUrl(string $heroImage): array
    {
        $heroImage = trim($heroImage);

        if (preg_match('#^https?://#i', $heroImage)) {
            // Already an absolute URL — Amazon needs HTTPS specifically.
            if (! preg_match('#^https://#i', $heroImage)) {
                return [null, 'Hero image URL must be HTTPS for Amazon push: '.$heroImage];
            }

            return [$heroImage, null];
        }

        $rel = ltrim(preg_replace('#^/?storage/#', '', $heroImage), '/');

        // Prefer an explicit public base URL when configured (lets local dev
        // push without touching APP_URL — point this at a tunnel or the
        // production host that already serves the same uploads).
        $base = (string) config('services.hero_images.public_base_url', '');
        if ($base === '') {
            $base = (string) config('app.url', '');
        }

        if ($base !== '') {
            $publicUrl = rtrim($base, '/').'/storage/'.ltrim($rel, '/');
        } else {
            $publicUrl = Storage::disk('public')->url($rel);
        }

        if (! preg_match('#^https://#i', $publicUrl)) {
            return [
                null,
                'Hero image is not served over HTTPS ('.($publicUrl ?: 'empty URL').'). '.
                'Amazon requires public HTTPS image URLs. Either set APP_URL to your https:// site URL '.
                'in .env, or add HERO_PUBLIC_BASE_URL=https://your-public-host (e.g. an ngrok tunnel '.
                'or your production domain), then run: php artisan config:clear',
            ];
        }

        return [$publicUrl, null];
    }

    /**
     * Claude Vision only accepts image/jpeg, image/png, image/gif, image/webp.
     * Coerce anything else (and bare "jpg") to a supported value.
     */
    private function normalizeImageMime(string $mime): string
    {
        $mime = strtolower(trim($mime));
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

        if (in_array($mime, $allowed, true)) {
            return $mime;
        }
        if ($mime === 'image/jpg' || $mime === 'image/pjpeg') {
            return 'image/jpeg';
        }

        return 'image/jpeg';
    }

    /**
     * Resolve a hero image reference (storage-relative path or absolute URL)
     * into raw bytes + MIME type for the OpenAI request.
     *
     * @return array{0: ?string, 1: string, 2: ?string} [bytes, mime, errorMessage]
     */
    private function loadImageBytes(string $heroImage): array
    {
        $heroImage = trim($heroImage);

        if (preg_match('#^https?://#i', $heroImage)) {
            try {
                $resp = Http::timeout(30)->get($heroImage);
                if (! $resp->successful()) {
                    return [null, '', 'Could not fetch hero image URL (HTTP '.$resp->status().').'];
                }
                $mime = $resp->header('Content-Type') ?: 'image/jpeg';
                if (! str_starts_with($mime, 'image/')) {
                    $mime = 'image/jpeg';
                }

                return [$resp->body(), $mime, null];
            } catch (\Throwable $e) {
                return [null, '', 'Could not fetch hero image URL: '.$e->getMessage()];
            }
        }

        // Stored on the public disk (e.g. "hero_images/hero_SKU_123.png")
        $rel = ltrim(preg_replace('#^/?storage/#', '', $heroImage), '/');

        if (! Storage::disk('public')->exists($rel)) {
            return [null, '', 'Hero image file is missing on the server: '.$rel];
        }

        $bytes = Storage::disk('public')->get($rel);
        $mime = Storage::disk('public')->mimeType($rel) ?: 'image/jpeg';
        if (! str_starts_with((string) $mime, 'image/')) {
            return [null, '', 'Hero image is not a valid image file.'];
        }

        return [$bytes, $mime, null];
    }

    /**
     * Strip ```json fences that some models add despite response_format=json_object.
     */
    private function stripJsonFences(string $text): string
    {
        $text = preg_replace('/^```(?:json)?\s*/i', '', $text) ?? $text;
        $text = preg_replace('/\s*```$/', '', $text) ?? $text;

        return trim($text);
    }

    /**
     * Hero image analysis prompt. Returns a strict JSON schema so the frontend
     * can render score badges and structured checklists predictably.
     */
    private function heroAnalysisPrompt(): string
    {
        return <<<'PROMPT'
You are an expert e-commerce hero image auditor for marketplaces (Amazon, Walmart, eBay, Shopify).

Analyze the provided product hero image for marketplace compliance, conversion optimization, and visual quality. Evaluate ALL of the following:

- Pure white background (#FFFFFF)
- Product centered properly
- Product fills 80–90% of frame
- Image sharpness and clarity, resolution quality
- Lighting and shadow quality
- No blur or pixelation
- No watermark, logo, or text overlays
- No unnecessary props/accessories
- Correct cropping
- Product visibility on mobile
- Contrast, brightness, color accuracy
- Background contamination
- Edge cleanliness / cutout quality
- Symmetry and alignment
- Professional appearance
- Marketplace compliance (Amazon/Walmart/eBay)
- Luxury / premium feel
- Click-through-rate potential & conversion optimization
- Whether the image looks trustworthy
- Whether the image stands out in search results
- Whether the image could reduce returns due to misleading presentation

Also identify: missing angles or hidden product parts, areas appearing fake or AI-generated, reflection issues, packaging visibility issues, and image composition problems.

Respond with ONLY valid JSON (no markdown, no commentary) in this EXACT shape:

{
  "overall_score": 0,
  "marketplace_compliance": "PASS|FAIL",
  "ctr_score": 0,
  "conversion_score": 0,
  "pass_fail": "PASS|FAIL",
  "critical_issues": ["string"],
  "improvements": ["string"],
  "detailed_checks": {
    "background": "string",
    "sharpness": "string",
    "cropping": "string",
    "lighting": "string",
    "mobile_visibility": "string",
    "professionalism": "string"
  },
  "fake_or_ai_flags": ["string"],
  "missing_angles": ["string"],
  "final_verdict": "string"
}

Scoring rules:
- overall_score, ctr_score, conversion_score are integers 0..10.
- pass_fail = "PASS" if overall_score >= 7 AND no critical_issues, else "FAIL".
- marketplace_compliance = "PASS" only if Amazon/Walmart/eBay main image rules are met (pure white background, no overlays/text/watermarks, product fills frame appropriately, single product).
- Keep arrays concise (max 6 items each), each item one short sentence.
- final_verdict: 1-2 sentence summary.
PROMPT;
    }

    /**
     * Find a ProductMaster row by SKU, tolerating non-breaking spaces that can
     * sneak in from spreadsheet imports.
     */
    private function findProductBySku(string $sku): ?ProductMaster
    {
        $normalizedSku = str_replace("\u{00a0}", ' ', $sku);

        $product = ProductMaster::where('sku', $normalizedSku)->first();

        if (! $product) {
            $product = ProductMaster::where('sku', $sku)->first();
        }

        return $product;
    }

    /**
     * Decode the Values JSON column into an array regardless of how Eloquent
     * cast it.
     */
    private function getValuesArray(ProductMaster $product): array
    {
        $values = is_array($product->Values)
            ? $product->Values
            : json_decode((string) $product->Values, true);

        return is_array($values) ? $values : [];
    }
}
