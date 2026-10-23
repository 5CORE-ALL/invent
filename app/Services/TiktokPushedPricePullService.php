<?php

namespace App\Services;

use App\Models\TiktokShopDataView;
use App\Models\TiktokTwoShopDataView;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Push and pull together, same as AmazonPushedPricePullService.
 *
 * After a successful S PRC push the live TikTok price is read straight away (TiktokSprcDilAutoPushService::pushOne).
 * When TikTok still shows something else, the SKU is marked pending here and tiktok:pull-pushed-prices re-reads it every
 * minute (due 2 minutes after each attempt, up to 6 attempts) until the Price column shows the pushed price.
 * The state lives in the shop data view JSON (PRICE_PULL_*), like Amazon's amazon_data_view.
 */
class TiktokPushedPricePullService
{
    public const DELAY_MINUTES = 2;

    public const MAX_ATTEMPTS = 6;

    public function scheduleAfterPush(string $channel, string $sku): void
    {
        $view = $this->viewFor($channel, $sku);
        if ($view === null) {
            return;
        }
        $value = $this->decode($view->value);
        $value['PRICE_PULL_STATUS'] = 'pending';
        $value['PRICE_PULL_DUE_AT'] = now()->addMinutes(self::DELAY_MINUTES)->toDateTimeString();
        $value['PRICE_PULL_ATTEMPTS'] = 0;
        unset($value['PRICE_PULLED_AT'], $value['PRICE_PULLED_VALUE']);
        $view->value = $value;
        $view->save();
    }

    /**
     * @return array{due:int,pulled:int,retried:int,failed:int}
     */
    public function pullDue(string $channel, int $limit = 30, int $delayMs = 500): array
    {
        $channel = TiktokSprcDilAutoPushService::normalizeChannel($channel);
        $stats = ['due' => 0, 'pulled' => 0, 'retried' => 0, 'failed' => 0];
        $class = $this->viewClass($channel);

        $due = $class::query()
            ->where('value->PRICE_PULL_STATUS', 'pending')
            ->where('value->PRICE_PULL_DUE_AT', '<=', now()->toDateTimeString())
            ->orderBy('id')
            ->limit(max(1, $limit))
            ->get();
        $stats['due'] = $due->count();
        if ($due->isEmpty()) {
            return $stats;
        }

        $puller = app(ChannelPushedPricePullService::class);
        foreach ($due as $view) {
            $sku = trim((string) $view->sku);
            $value = $this->decode($view->value);
            $expected = isset($value['SPRICE_PUSHED_VALUE']) && is_numeric($value['SPRICE_PUSHED_VALUE'])
                ? round((float) $value['SPRICE_PUSHED_VALUE'], 2)
                : null;

            try {
                $res = $puller->pullSkus(
                    $channel,
                    [$sku],
                    $expected !== null ? [strtoupper($sku) => $expected] : []
                )[0] ?? null;
            } catch (Throwable $e) {
                Log::warning('[TiktokPushedPricePull] pull failed', ['channel' => $channel, 'sku' => $sku, 'error' => $e->getMessage()]);
                $res = null;
            }

            if ($res && ! empty($res['success'])) {
                $value['PRICE_PULL_STATUS'] = 'pulled';
                $value['PRICE_PULLED_AT'] = now()->toDateTimeString();
                if (isset($res['price']) && is_numeric($res['price'])) {
                    $value['PRICE_PULLED_VALUE'] = round((float) $res['price'], 2);
                }
                $stats['pulled']++;
            } else {
                $attempts = ((int) ($value['PRICE_PULL_ATTEMPTS'] ?? 0)) + 1;
                $value['PRICE_PULL_ATTEMPTS'] = $attempts;
                if ($attempts >= self::MAX_ATTEMPTS) {
                    $value['PRICE_PULL_STATUS'] = 'failed';
                    $stats['failed']++;
                    Log::warning('[TiktokPushedPricePull] live price never matched the push', [
                        'channel' => $channel,
                        'sku' => $sku,
                        'expected' => $expected,
                        'attempts' => $attempts,
                    ]);
                } else {
                    $value['PRICE_PULL_STATUS'] = 'pending';
                    $value['PRICE_PULL_DUE_AT'] = now()->addMinutes(self::DELAY_MINUTES)->toDateTimeString();
                    $stats['retried']++;
                }
            }
            $view->value = $value;
            $view->save();

            if ($delayMs > 0) {
                usleep($delayMs * 1000);
            }
        }

        return $stats;
    }

    /** @return class-string<TiktokShopDataView|TiktokTwoShopDataView> */
    private function viewClass(string $channel): string
    {
        return $channel === 'tiktok2' ? TiktokTwoShopDataView::class : TiktokShopDataView::class;
    }

    private function viewFor(string $channel, string $sku): TiktokShopDataView|TiktokTwoShopDataView|null
    {
        $class = $this->viewClass(TiktokSprcDilAutoPushService::normalizeChannel($channel));
        $key = strtoupper(str_replace("\u{00a0}", ' ', trim($sku)));
        if ($key === '') {
            return null;
        }
        foreach ($class::query()->whereRaw('UPPER(TRIM(REPLACE(sku, UNHEX(\'C2A0\'), \' \'))) = ?', [$key])->limit(1)->get() as $view) {
            return $view;
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function decode(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }
}
