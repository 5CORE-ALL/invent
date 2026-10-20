<?php

namespace App\Console\Commands;

use App\Models\TemuDataView;
use App\Models\TemuMetric;
use App\Services\TemuApiService;
use App\Services\TemuPromotionApiService;
use Illuminate\Console\Command;

/**
 * One-off cleanup: take Temu 1 goods out of Temu promotion (lightning deal) activities.
 *
 * Dry run by default: lists every goods the Temu 1 account is enrolled with in an open activity
 * (ongoing / not started) and shows which ones the old /new-temuone Promotion switch enrolled.
 * --apply deactivates only those app-enrolled goods; add --all to also deactivate goods that were
 * enrolled by hand in Seller Central.
 */
class NewTemuonePromotionCleanupCommand extends Command
{
    protected $signature = 'newtemuone:promotion-cleanup
        {--apply : Really deactivate on Temu (default is a dry run, read-only)}
        {--all : With --apply, also deactivate goods this app did not enroll}
        {--activity= : Only this activity id}';

    protected $description = 'List (dry run) or remove (--apply) Temu 1 goods enrolled in Temu promotion activities.';

    public function handle(TemuApiService $temu): int
    {
        $apply = (bool) $this->option('apply');
        $all = (bool) $this->option('all');
        $promo = new TemuPromotionApiService($temu);

        $mine = $this->appEnrolledGoodsIds();
        $this->line('Goods the old Temu 1 promotion switch enrolled (from NTO_PM_PCT markers): '.count($mine));

        $activities = $this->openActivities($promo);
        if ($activities === null) {
            return self::FAILURE;
        }
        if ($activities === []) {
            $this->info('No ongoing / not-started promotion activities — nothing can be enrolled or removed.');

            return self::SUCCESS;
        }

        $rows = [];
        $targets = [];
        $failed = 0;
        foreach ($activities as $a) {
            $activityId = (string) $a['activityId'];
            $res = $promo->enrolledGoods($activityId);
            if (! $res['success']) {
                $this->error("Activity {$activityId}: could not list enrolled goods — {$res['message']}");
                $failed++;

                continue;
            }
            foreach ($res['goods'] as $goodsId => $g) {
                $isMine = isset($mine[(string) $goodsId]);
                $rows[] = [
                    $activityId,
                    (string) ($a['activityName'] ?? ''),
                    (string) $goodsId,
                    count($g['skus']).' sku(s): '.implode(', ', array_map(fn ($id, $p) => $id.'=$'.number_format($p, 2), array_keys($g['skus']), $g['skus'])),
                    $g['qty'],
                    $isMine ? 'yes' : 'no',
                ];
                if ($isMine || $all) {
                    $targets[] = [$activityId, (string) $goodsId];
                }
            }
        }

        if ($rows === []) {
            $this->info('The Temu 1 account has no goods enrolled in any open promotion activity.');

            return $failed > 0 ? self::FAILURE : self::SUCCESS;
        }

        $this->table(['Activity', 'Name', 'Goods ID', 'SKUs (skuId=promo price)', 'Qty', 'Enrolled by app'], $rows);
        $this->line('Enrolled goods: '.count($rows).' · would be removed: '.count($targets).($all ? ' (--all)' : ' (app-enrolled only)'));

        if (! $apply) {
            $this->warn('Dry run — nothing changed. Re-run with --apply to deactivate the goods listed as removable.');

            return $failed > 0 ? self::FAILURE : self::SUCCESS;
        }

        $ok = 0;
        $fail = 0;
        foreach ($targets as [$activityId, $goodsId]) {
            $res = $promo->deactivateGoods($activityId, $goodsId, 'nto-rm'.substr(md5($activityId.$goodsId), 0, 24));
            if ($res['success']) {
                $ok++;
                $this->line("OK   activity {$activityId} goods {$goodsId} draft ".($res['draft_id'] ?? '—'));
            } else {
                $fail++;
                $this->error("FAIL activity {$activityId} goods {$goodsId}: {$res['message']}");
            }
        }
        $this->info("Deactivation submitted: {$ok} ok, {$fail} failed. Temu applies it asynchronously — re-run without --apply to confirm the list is empty.");

        return ($fail > 0 || $failed > 0) ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return list<array<string, mixed>>|null null on API error
     */
    private function openActivities(TemuPromotionApiService $promo): ?array
    {
        $only = trim((string) $this->option('activity'));
        $out = [];
        $seen = [];
        foreach ([TemuPromotionApiService::STATUS_ONGOING, TemuPromotionApiService::STATUS_NOT_STARTED] as $status) {
            $res = $promo->queryActivities(TemuPromotionApiService::TYPE_LIGHTNING_DEAL, $status);
            if (! $res['success']) {
                $this->error('Could not list Temu promotion activities: '.$res['message']);

                return null;
            }
            foreach ($res['activities'] as $a) {
                $id = (string) ($a['activityId'] ?? '');
                if ($id === '' || isset($seen[$id]) || ($only !== '' && $only !== $id)) {
                    continue;
                }
                $seen[$id] = true;
                $out[] = $a;
            }
        }

        return $out;
    }

    /**
     * Goods ids of SKUs the old promotion switch applied (NTO_PM_PCT > 0 in temu_data_views).
     *
     * @return array<string, true>
     */
    private function appEnrolledGoodsIds(): array
    {
        $skus = [];
        TemuDataView::query()
            ->where('value', 'like', '%NTO_PM_PCT%')
            ->select(['id', 'sku', 'value'])
            ->chunkById(500, function ($rows) use (&$skus) {
                foreach ($rows as $row) {
                    $v = is_array($row->value) ? $row->value : (json_decode((string) $row->value, true) ?: []);
                    if ((float) ($v['NTO_PM_PCT'] ?? 0) > 0 && trim((string) $row->sku) !== '') {
                        $skus[strtoupper(trim((string) $row->sku))] = true;
                    }
                }
            });

        $goods = [];
        foreach (array_chunk(array_keys($skus), 500) as $chunk) {
            TemuMetric::query()
                ->whereIn(\DB::raw('UPPER(TRIM(sku))'), $chunk)
                ->whereNotNull('goods_id')
                ->pluck('goods_id')
                ->each(function ($gid) use (&$goods) {
                    $gid = trim((string) $gid);
                    if ($gid !== '') {
                        $goods[$gid] = true;
                    }
                });
        }

        return $goods;
    }
}
