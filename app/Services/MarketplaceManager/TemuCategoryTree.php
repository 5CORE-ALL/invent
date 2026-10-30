<?php

namespace App\Services\MarketplaceManager;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Temu's category tree (id -> name / parent), downloaded once with bg.local.goods.cats.get and stored in
 * storage/app/temu_cache/{shop}_category_tree.json, so a category ID can be shown as "Electronics > Speakers > ...".
 *
 * The crawl is resumable: it keeps the list of parents still to fetch, so a time-limited run (queue job) continues
 * where the previous one stopped.
 */
class TemuCategoryTree
{
    /** @var array<string, array<string, mixed>> */
    private static array $memo = [];

    /**
     * @param  object  $api  TemuApiService | Temu2ApiService (needs callOpenApi)
     */
    public function __construct(private object $api, private string $shopKey) {}

    public static function file(string $shopKey): string
    {
        return 'temu_cache/'.$shopKey.'_category_tree.json';
    }

    /**
     * @return array{nodes: array<string, array{0: string, 1: string, 2: int}>, pending: list<string>, done: bool, error: string, updated_at: string}
     */
    public static function read(string $shopKey): array
    {
        if (isset(self::$memo[$shopKey])) {
            return self::$memo[$shopKey];
        }
        $state = ['nodes' => [], 'pending' => ['0'], 'done' => false, 'error' => '', 'updated_at' => ''];
        try {
            $disk = Storage::disk('local');
            if ($disk->exists(self::file($shopKey))) {
                $json = json_decode((string) $disk->get(self::file($shopKey)), true);
                if (is_array($json) && isset($json['nodes']) && is_array($json['nodes'])) {
                    $state = array_merge($state, $json);
                }
            }
        } catch (\Throwable $e) {
            // no tree yet
        }

        return self::$memo[$shopKey] = $state;
    }

    /** "A > B > C" for a category id, '' when the id is not (yet) in the tree. */
    public static function path(string $shopKey, string $id): string
    {
        $nodes = self::read($shopKey)['nodes'];
        $id = trim($id);
        $parts = [];
        $guard = 0;
        while ($id !== '' && $id !== '0' && isset($nodes[$id]) && $guard++ < 12) {
            array_unshift($parts, (string) $nodes[$id][0]);
            $id = (string) $nodes[$id][1];
        }

        return implode(' > ', $parts);
    }

    public static function isComplete(string $shopKey): bool
    {
        $s = self::read($shopKey);

        return ! empty($s['done']) && $s['nodes'] !== [];
    }

    public static function forget(string $shopKey): void
    {
        unset(self::$memo[$shopKey]);
    }

    /**
     * Fetch categories until the tree is complete or the time budget is used up.
     *
     * @return array{done: bool, nodes: int, pending: int, calls: int, error: string}
     */
    public function crawl(int $budgetSeconds = 100, bool $fresh = false, ?callable $progress = null): array
    {
        $state = $fresh
            ? ['nodes' => [], 'pending' => ['0'], 'done' => false, 'error' => '', 'updated_at' => '']
            : self::read($this->shopKey);
        if (! empty($state['done']) && ! $fresh) {
            return ['done' => true, 'nodes' => count($state['nodes']), 'pending' => 0, 'calls' => 0, 'error' => ''];
        }

        $nodes = $state['nodes'];
        $pending = array_values(array_map('strval', $state['pending'] ?: ['0']));
        $deadline = microtime(true) + max(10, $budgetSeconds);
        $calls = 0;
        $failures = 0;
        $error = '';

        while ($pending !== [] && microtime(true) < $deadline) {
            $parent = array_shift($pending);
            $res = $this->api->callOpenApi('bg.local.goods.cats.get', ['parentCatId' => (int) $parent], 30);
            $calls++;
            if (! ($res['success'] ?? false)) {
                $failures++;
                $error = trim((string) ($res['errorMsg'] ?? $res['message'] ?? 'Temu category call failed'));
                $pending[] = $parent; // try again later
                if ($failures >= 5 && $calls === $failures) {
                    break; // nothing has worked yet: the API is not available to this shop
                }
                if ($failures >= 25) {
                    break;
                }
                usleep(300000);
                continue;
            }
            $list = $res['result']['goodsCatsList'] ?? $res['result']['catList'] ?? [];
            foreach ((array) $list as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $cid = preg_replace('/\D+/', '', (string) ($row['catId'] ?? '')) ?? '';
                $name = trim((string) ($row['catName'] ?? ''));
                if ($cid === '' || $name === '') {
                    continue;
                }
                $leaf = ! empty($row['leaf']) ? 1 : 0;
                $nodes[$cid] = [$name, (string) ($row['parentId'] ?? $parent), $leaf];
                if (! $leaf) {
                    $pending[] = $cid;
                }
            }
            if ($calls % 25 === 0) {
                $this->save($nodes, $pending, false, '');
                if ($progress) {
                    $progress(count($nodes), count($pending), $calls);
                }
            }
            usleep(120000);
        }

        $done = $pending === [] && $nodes !== [];
        $this->save($nodes, $pending, $done, $done ? '' : $error);
        Log::info('TemuCategoryTree crawl', ['shop' => $this->shopKey, 'done' => $done, 'nodes' => count($nodes), 'pending' => count($pending), 'calls' => $calls, 'error' => $error]);

        return ['done' => $done, 'nodes' => count($nodes), 'pending' => count($pending), 'calls' => $calls, 'error' => $done ? '' : $error];
    }

    /**
     * @param  array<string, array{0: string, 1: string, 2: int}>  $nodes
     * @param  list<string>  $pending
     */
    private function save(array $nodes, array $pending, bool $done, string $error): void
    {
        try {
            Storage::disk('local')->put(self::file($this->shopKey), json_encode([
                'nodes' => $nodes,
                'pending' => array_values($pending),
                'done' => $done,
                'error' => $error,
                'updated_at' => now()->toIso8601String(),
            ], JSON_UNESCAPED_UNICODE));
        } catch (\Throwable $e) {
            Log::warning('TemuCategoryTree: could not save', ['error' => $e->getMessage()]);
        }
        self::forget($this->shopKey);
    }
}
