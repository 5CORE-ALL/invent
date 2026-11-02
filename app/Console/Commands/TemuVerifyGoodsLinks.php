<?php

namespace App\Console\Commands;

use App\Services\TemuApiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Finds SKUs the app shows as listed under a Temu 1 goods id that Temu does not actually hold under that goods
 * (the false success of "add to existing parent"). Read-only unless --fix is given; --fix clears goods_id / sku_id
 * on those rows so they show as Missing again and can be listed properly.
 */
class TemuVerifyGoodsLinks extends Command
{
    protected $signature = 'temu:verify-goods-links {goods_id : Parent goods id to check} {--fix : Unlink SKUs Temu does not hold under this goods}';

    protected $description = 'Compare temu_metrics SKUs linked to a goods id with the SKUs Temu really holds under it';

    public function handle(TemuApiService $temu): int
    {
        $goodsId = trim((string) $this->argument('goods_id'));
        if (! Schema::hasTable('temu_metrics')) {
            $this->error('temu_metrics table missing.');

            return self::FAILURE;
        }

        $remote = [];
        foreach ([2, 3] as $searchType) {
            for ($page = 1; $page <= 10; $page++) {
                $res = $temu->callOpenApi('bg.local.goods.sku.list.query', [
                    'searchText' => $goodsId,
                    'skuSearchType' => $searchType,
                    'pageNo' => $page,
                    'pageSize' => 100,
                ], 45);
                if (! ($res['success'] ?? false)) {
                    $this->warn('Temu list query (type '.$searchType.') failed: '.json_encode($res['message'] ?? $res['errorMsg'] ?? $res));
                    break;
                }
                $rows = (array) ($res['result']['skuList'] ?? []);
                foreach ($rows as $row) {
                    if (is_array($row) && (string) ($row['goodsId'] ?? '') === $goodsId) {
                        $sn = strtoupper(trim((string) ($row['skuSn'] ?? $row['outSkuSn'] ?? $row['extCode'] ?? '')));
                        if ($sn !== '') {
                            $remote[$sn] = (string) ($row['skuId'] ?? '');
                        }
                    }
                }
                if (count($rows) < 100) {
                    break;
                }
            }
        }
        $this->info('Temu holds '.count($remote).' SKU(s) under goods '.$goodsId.'.');
        if ($remote === []) {
            $this->error('Temu returned no SKUs for this goods; nothing is changed (cannot tell real from false).');

            return self::FAILURE;
        }

        $local = DB::table('temu_metrics')->where('goods_id', $goodsId)->get(['sku', 'goods_id', 'sku_id']);
        $false = [];
        foreach ($local as $row) {
            $sn = strtoupper(trim((string) $row->sku));
            if (! isset($remote[$sn])) {
                $false[] = $row->sku;
            }
        }
        $this->line('App links '.count($local).' SKU(s) to this goods; '.count($false).' are NOT on Temu under it:');
        foreach ($false as $sku) {
            $this->line('  - '.$sku);
        }

        if ($false !== [] && $this->option('fix')) {
            DB::table('temu_metrics')->where('goods_id', $goodsId)->whereIn('sku', $false)->update(['goods_id' => null, 'sku_id' => null]);
            $this->info('Unlinked '.count($false).' SKU(s); they show as Missing again.');
        } elseif ($false !== []) {
            $this->comment('Run again with --fix to unlink them.');
        }

        return self::SUCCESS;
    }
}
