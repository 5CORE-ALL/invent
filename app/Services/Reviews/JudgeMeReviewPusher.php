<?php

namespace App\Services\Reviews;

use App\Models\MarketplaceReview;
use App\Models\ShopifySku;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Posts stored marketplace reviews to the Shopify store through Judge.me.
 *
 * Shopify itself has no reviews API, so the reviews go to the review app (Judge.me).
 * Needs JUDGEME_API_TOKEN (the private API token from Judge.me settings) in .env.
 */
class JudgeMeReviewPusher
{
    public function isConfigured(): bool
    {
        return $this->token() !== '' && $this->shopDomain() !== '';
    }

    public function configurationHint(): string
    {
        if ($this->token() === '') {
            return 'JUDGEME_API_TOKEN is not set in .env (Judge.me → Settings → Integrations → Review API, the private token).';
        }
        if ($this->shopDomain() === '') {
            return 'Shopify store domain is not set (SHOPIFY_STORE_URL or JUDGEME_SHOP_DOMAIN).';
        }

        return '';
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function push(MarketplaceReview $review): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'message' => $this->configurationHint()];
        }
        if ($review->rating === null || $review->rating < 1) {
            return $this->mark($review, MarketplaceReview::SHOPIFY_SKIPPED, 'No star rating');
        }
        if ($review->shopify_status === MarketplaceReview::SHOPIFY_PUSHED) {
            return ['success' => true, 'message' => 'Already pushed'];
        }

        $productId = $this->shopifyProductId((string) $review->sku);
        if ($productId === null) {
            return $this->mark($review, MarketplaceReview::SHOPIFY_FAILED, 'This SKU has no Shopify product (not in shopify_skus)');
        }

        $name = trim((string) $review->reviewer_name) !== '' ? (string) $review->reviewer_name : 'Verified Buyer';
        $payload = [
            'shop_domain' => $this->shopDomain(),
            'platform' => 'shopify',
            'api_token' => $this->token(),
            'id' => $productId,
            'name' => $name,
            'email' => $this->emailFor($review),
            'rating' => (int) $review->rating,
            'title' => (string) ($review->title ?? ''),
            'body' => (string) ($review->body ?? $review->title ?? ''),
        ];
        if (is_array($review->images) && $review->images !== []) {
            $payload['picture_urls'] = array_slice($review->images, 0, 5);
        }

        try {
            $res = Http::asJson()->acceptJson()->timeout(30)->post('https://judge.me/api/v1/reviews', $payload);
        } catch (\Throwable $e) {
            return $this->mark($review, MarketplaceReview::SHOPIFY_FAILED, 'Judge.me request failed: '.$e->getMessage(), $productId);
        }

        if ($res->successful()) {
            $review->forceFill([
                'shopify_status' => MarketplaceReview::SHOPIFY_PUSHED,
                'shopify_product_id' => $productId,
                'shopify_pushed_at' => now(),
                'shopify_error' => null,
                'shopify_attempts' => (int) $review->shopify_attempts + 1,
            ])->save();

            return ['success' => true, 'message' => 'Pushed to Shopify (Judge.me)'];
        }

        $message = 'Judge.me HTTP '.$res->status().': '.mb_substr(trim(preg_replace('/\s+/', ' ', (string) $res->body()) ?? ''), 0, 300);
        Log::warning('Judge.me review push failed', ['review_id' => $review->id, 'status' => $res->status(), 'body' => mb_substr((string) $res->body(), 0, 500)]);

        return $this->mark($review, MarketplaceReview::SHOPIFY_FAILED, $message, $productId);
    }

    /**
     * Shopify product id for a SKU (cached).
     */
    public function shopifyProductId(string $sku): ?string
    {
        $variantId = ShopifySku::firstForProductSku($sku)?->variant_id;
        if (! $variantId) {
            return null;
        }

        return Cache::remember('reviews:shopify_product:'.$variantId, now()->addDays(7), function () use ($variantId) {
            $domain = preg_replace('#^https?://#', '', rtrim((string) (config('services.shopify.store_url') ?: ''), '/'));
            $token = config('services.shopify.access_token') ?: config('services.shopify.password');
            if (! $domain || ! $token) {
                return null;
            }
            try {
                $res = Http::withHeaders(['X-Shopify-Access-Token' => $token])
                    ->timeout(30)
                    ->get("https://{$domain}/admin/api/".config('services.shopify.api_version', '2025-01')."/variants/{$variantId}.json");
            } catch (\Throwable) {
                return null;
            }
            $productId = $res->successful() ? $res->json('variant.product_id') : null;

            return $productId ? (string) $productId : null;
        });
    }

    /**
     * @return array{success: bool, message: string}
     */
    protected function mark(MarketplaceReview $review, string $status, string $message, ?string $productId = null): array
    {
        $review->forceFill([
            'shopify_status' => $status,
            'shopify_error' => mb_substr($message, 0, 500),
            'shopify_product_id' => $productId ?? $review->shopify_product_id,
            'shopify_attempts' => (int) $review->shopify_attempts + 1,
        ])->save();

        return ['success' => false, 'message' => $message];
    }

    protected function emailFor(MarketplaceReview $review): string
    {
        $configured = trim((string) config('services.judgeme.reviewer_email', ''));
        if ($configured !== '') {
            return $configured;
        }

        return 'reviews+mp'.$review->id.'@'.$this->shopDomain();
    }

    protected function token(): string
    {
        return trim((string) config('services.judgeme.api_token', ''));
    }

    protected function shopDomain(): string
    {
        $domain = (string) (config('services.judgeme.shop_domain') ?: config('services.shopify.store_url') ?: '');

        return trim(rtrim((string) preg_replace('#^https?://#', '', $domain), '/'));
    }
}
