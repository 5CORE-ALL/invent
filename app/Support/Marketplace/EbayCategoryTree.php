<?php

namespace App\Support\Marketplace;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Every leaf category of the eBay US category tree (Taxonomy API, tree 0), kept in a file so the category picker
 * does not wait on eBay. The tree is fetched on first use and again after 30 days (or on request).
 * Only leaf categories are kept: eBay lists items in leaf categories only.
 */
class EbayCategoryTree
{
    private const FILE = 'ebay_cache/category_tree_0_leaves.json';
    private const MAX_AGE_DAYS = 30;

    /** @var array{fetched_at: string, categories: list<array{0: string, 1: string}>}|null */
    private static ?array $memo = null;
    /** @var list<string>|null lower case paths, same order as the categories */
    private static ?array $lower = null;
    public static string $error = '';

    private static function path(): string
    {
        return storage_path('app/'.self::FILE);
    }

    /**
     * @return array{fetched_at: string, categories: list<array{0: string, 1: string}>}
     */
    public static function load(bool $force = false): array
    {
        self::$error = '';
        if (! $force && self::$memo !== null) {
            return self::$memo;
        }
        $file = self::path();
        if (! $force && is_file($file) && (time() - (int) filemtime($file)) < self::MAX_AGE_DAYS * 86400) {
            $data = json_decode((string) file_get_contents($file), true);
            if (is_array($data) && ! empty($data['categories'])) {
                self::$lower = null;

                return self::$memo = $data;
            }
        }

        $fetched = self::fetch();
        if ($fetched !== null) {
            self::$lower = null;

            return self::$memo = $fetched;
        }

        // eBay did not answer: an older copy on disk is better than nothing.
        if (is_file($file)) {
            $data = json_decode((string) file_get_contents($file), true);
            if (is_array($data) && ! empty($data['categories'])) {
                self::$lower = null;

                return self::$memo = $data;
            }
        }

        return self::$memo = ['fetched_at' => '', 'categories' => []];
    }

    /**
     * @return array{fetched_at: string, categories: list<array{0: string, 1: string}>}|null
     */
    private static function fetch(): ?array
    {
        @set_time_limit(300);
        @ini_set('memory_limit', '1024M');
        $token = EbayCategoryAspects::appToken((string) config('services.ebay.app_id'), (string) config('services.ebay.cert_id'));
        if ($token === '') {
            self::$error = 'The eBay API keys are missing on this server, so the eBay categories cannot be fetched.';

            return null;
        }
        try {
            $res = Http::timeout(240)
                ->withToken($token)
                ->acceptJson()
                ->withHeaders(['Accept-Encoding' => 'gzip'])
                ->get('https://api.ebay.com/commerce/taxonomy/v1/category_tree/0');
            if (! $res->successful()) {
                self::$error = 'eBay answered '.$res->status().' when the category tree was requested.';
                Log::warning('eBay category tree failed', ['status' => $res->status(), 'body' => substr($res->body(), 0, 400)]);

                return null;
            }
            $root = $res->json('rootCategoryNode');
            if (! is_array($root)) {
                self::$error = 'eBay returned no category tree.';

                return null;
            }
            unset($res);

            $leaves = [];
            // Iterative walk: [node, parent path]
            $stack = [[$root, '']];
            while ($stack) {
                [$node, $prefix] = array_pop($stack);
                $id = (string) ($node['category']['categoryId'] ?? '');
                $name = trim((string) ($node['category']['categoryName'] ?? ''));
                $isRoot = $id === '0' || $id === '' || $name === 'Root';
                $path = $isRoot ? '' : ($prefix === '' ? $name : $prefix.' > '.$name);
                $children = $node['childCategoryTreeNodes'] ?? [];
                if (! $isRoot && (($node['leafCategoryTreeNode'] ?? false) || ! $children)) {
                    $leaves[] = [$id, $path];

                    continue;
                }
                foreach (array_reverse((array) $children) as $child) {
                    $stack[] = [$child, $path];
                }
            }
            if ($leaves === []) {
                self::$error = 'The eBay category tree was empty.';

                return null;
            }
            usort($leaves, static fn ($a, $b) => strcasecmp($a[1], $b[1]));
            $data = ['fetched_at' => now()->toDateTimeString(), 'categories' => $leaves];
            $dir = dirname(self::path());
            if (! is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            file_put_contents(self::path(), json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return $data;
        } catch (\Throwable $e) {
            self::$error = 'Could not fetch the eBay categories: '.$e->getMessage();
            Log::warning('eBay category tree error', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Leaf categories whose path (or id) contains every word of the query. Empty query lists the first ones.
     *
     * @return array{total: int, results: list<array{id: string, path: string}>, fetched_at: string}
     */
    public static function search(string $query, int $limit = 200, bool $force = false): array
    {
        $data = self::load($force);
        $all = $data['categories'];
        if (self::$lower === null || count(self::$lower) !== count($all)) {
            self::$lower = array_map(static fn ($c) => mb_strtolower($c[1]), $all);
        }
        $terms = array_values(array_filter(preg_split('/\s+/', mb_strtolower(trim($query))) ?: []));
        $hits = [];
        $total = 0;
        foreach ($all as $i => $cat) {
            $ok = true;
            foreach ($terms as $term) {
                if (! str_contains(self::$lower[$i], $term) && $cat[0] !== $term) {
                    $ok = false;
                    break;
                }
            }
            if (! $ok) {
                continue;
            }
            $total++;
            if (count($hits) < $limit * 4) {
                $hits[] = $i;
            }
        }
        // The category whose own name (last part) holds the words comes first.
        if ($terms !== []) {
            usort($hits, function ($a, $b) use ($all, $terms) {
                $score = function (int $i) use ($all, $terms): int {
                    $parts = explode(' > ', mb_strtolower($all[$i][1]));
                    $leaf = (string) end($parts);
                    $s = 0;
                    foreach ($terms as $term) {
                        if ($leaf === $term) {
                            $s += 3;
                        } elseif (str_contains($leaf, $term)) {
                            $s += 1;
                        }
                    }

                    return $s;
                };

                return $score($b) <=> $score($a) ?: $a <=> $b;
            });
        }
        $results = [];
        foreach (array_slice($hits, 0, $limit) as $i) {
            $results[] = ['id' => (string) $all[$i][0], 'path' => (string) $all[$i][1]];
        }

        return ['total' => $total, 'results' => $results, 'fetched_at' => (string) $data['fetched_at']];
    }

    public static function byId(string $id): ?array
    {
        foreach (self::load()['categories'] as $cat) {
            if ((string) $cat[0] === $id) {
                return ['id' => (string) $cat[0], 'path' => (string) $cat[1]];
            }
        }

        return null;
    }
}
