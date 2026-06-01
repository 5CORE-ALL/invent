<?php

namespace App\Services;

use App\Models\ImageMarketplaceMap;
use App\Models\Marketplace;
use App\Services\Marketplaces\MarketplaceInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

/**
 * Runs one image_marketplace_map through the marketplace service (Reverb, etc.).
 * Used synchronously from {@see \App\Http\Controllers\SkuImageController::pushImages} and from
 * {@see \App\Console\Commands\PushSkuImagesToReverbCommand}; optionally from {@see \App\Jobs\PushImageJob}.
 */
class SkuImageMarketplacePushProcessor
{
    public function processMapById(int $imageMarketplaceMapId): void
    {
        $map = ImageMarketplaceMap::query()
            ->with(['skuImage.product', 'marketplace'])
            ->find($imageMarketplaceMapId);

        if (! $map) {
            return;
        }

        if (! $map->skuImage || ! $map->marketplace) {
            $map->update([
                'status' => ImageMarketplaceMap::STATUS_FAILED,
                'response' => ['error' => 'Missing image or marketplace.'],
            ]);

            return;
        }

        if (! $map->marketplace->status) {
            $map->update([
                'status' => ImageMarketplaceMap::STATUS_FAILED,
                'response' => ['error' => 'Marketplace is disabled.'],
            ]);

            return;
        }

        $service = $this->resolveService($map->marketplace);
        if (! $service) {
            $code = (string) $map->marketplace->code;
            $class = 'App\\Services\\Marketplaces\\'.Str::studly($code).'Service';
            Log::warning('SkuImageMarketplacePushProcessor: no marketplace service class', [
                'map_id' => $map->id,
                'marketplace_code' => $code,
                'expected_class' => $class,
            ]);
            $map->update([
                'status' => ImageMarketplaceMap::STATUS_FAILED,
                'response' => [
                    'error' => 'No service for marketplace code "'.$code.'". Expected class '.$class.' to exist.',
                ],
            ]);

            return;
        }

        try {
            $result = $service->uploadImage($map->skuImage);
            if (! ($result['success'] ?? false)) {
                $msg = (string) ($result['message'] ?? 'Upload failed');
                Log::warning('SkuImageMarketplacePushProcessor: marketplace reported failure', [
                    'map_id' => $map->id,
                    'marketplace_code' => $map->marketplace->code,
                    'sku_image_id' => $map->sku_image_id,
                    'message' => $msg,
                ]);
                $map->update([
                    'status' => ImageMarketplaceMap::STATUS_FAILED,
                    'response' => [
                        'message' => $msg,
                        'data' => $result['data'] ?? null,
                    ],
                ]);

                return;
            }
            $map->update([
                'status' => ImageMarketplaceMap::STATUS_SENT,
                'response' => [
                    'data' => $result['data'] ?? [],
                    'message' => $result['message'] ?? '',
                ],
                'sent_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::warning('SkuImageMarketplacePushProcessor failed', [
                'map_id' => $map->id,
                'message' => $e->getMessage(),
            ]);
            $map->update([
                'status' => ImageMarketplaceMap::STATUS_FAILED,
                'response' => [
                    'error' => $e->getMessage(),
                ],
            ]);
        }
    }

    private function resolveService(Marketplace $marketplace): ?MarketplaceInterface
    {
        $class = 'App\\Services\\Marketplaces\\'.Str::studly($marketplace->code).'Service';
        if (! class_exists($class)) {
            return null;
        }
        $instance = app($class);
        if (! $instance instanceof MarketplaceInterface) {
            throw new InvalidArgumentException("{$class} must implement MarketplaceInterface");
        }

        return $instance;
    }
}
