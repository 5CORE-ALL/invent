<?php

namespace App\Jobs;

use App\Models\ImageMarketplaceMap;
use App\Models\Marketplace;
use App\Services\Marketplaces\MarketplaceInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

class PushImageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $imageMarketplaceMapId) {}

    public function handle(): void
    {
        $map = ImageMarketplaceMap::query()
            ->with(['skuImage.product', 'marketplace'])
            ->find($this->imageMarketplaceMapId);

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
            $map->update([
                'status' => ImageMarketplaceMap::STATUS_FAILED,
                'response' => ['error' => 'No service for code: '.$map->marketplace->code],
            ]);

            return;
        }

        try {
            $result = $service->uploadImage($map->skuImage);
            if (! ($result['success'] ?? false)) {
                $map->update([
                    'status' => ImageMarketplaceMap::STATUS_FAILED,
                    'response' => [
                        'message' => $result['message'] ?? 'Upload failed',
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
            Log::warning('PushImageJob failed', [
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
