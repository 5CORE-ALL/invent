<?php

namespace App\Support\Ace;

use App\Models\ChannelMaster;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Data providers for ATE (Auto Task Event) rules: for each supported page,
 * the list of columns a rule can test and the rows (as plain arrays) to test them on.
 *
 * Each row always has `id` (the row's primary key) and `label` (what to show in logs),
 * plus one entry per column key.
 */
class AcePages
{
    public const PAGES = [
        'users' => 'Users',
        'active_channels' => 'Active Channels',
    ];

    /**
     * @return list<array{key: string, label: string, type: string}>  type: text | number | yesno
     */
    public static function columns(string $page, ?string $source = null, ?int $asUserId = null): array
    {
        if ($source !== null && self::isDynamicKey($page)) {
            return self::sourceColumns($source, $asUserId);
        }

        if ($page === 'users') {
            return [
                ['key' => 'name', 'label' => 'Name', 'type' => 'text'],
                ['key' => 'email', 'label' => 'Email', 'type' => 'text'],
                ['key' => 'phone', 'label' => 'Phone', 'type' => 'text'],
                ['key' => 'designation', 'label' => 'Designation', 'type' => 'text'],
                ['key' => 'org_level', 'label' => 'Role (director / mgr / exec)', 'type' => 'text'],
                ['key' => 'date_of_joining', 'label' => 'Date of Joining', 'type' => 'text'],
                ['key' => 'tenure_days', 'label' => 'Days since Joining', 'type' => 'number'],
                ['key' => 'docs_count', 'label' => 'Docs Count', 'type' => 'number'],
                ['key' => 'docs_ok', 'label' => 'Docs (directors exempt)', 'type' => 'yesno'],
                ['key' => 'has_resume', 'label' => 'Has Resume', 'type' => 'yesno'],
                ['key' => 'has_phone', 'label' => 'Has Phone', 'type' => 'yesno'],
            ];
        }

        if ($page === 'active_channels') {
            $cols = [
                ['key' => 'channel', 'label' => 'Channel', 'type' => 'text'],
                ['key' => 'alias', 'label' => 'Alias', 'type' => 'text'],
                ['key' => 'notes_remarks', 'label' => 'Notes and Remarks', 'type' => 'text'],
            ];
            if (Schema::hasTable('promotion_columns')) {
                foreach (DB::table('promotion_columns')->orderBy('position')->orderBy('id')->get(['id', 'name']) as $c) {
                    $cols[] = ['key' => 'col_'.$c->id, 'label' => (string) $c->name, 'type' => 'text'];
                }
            }

            return $cols;
        }

        return [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function rows(string $page, ?string $source = null, ?int $asUserId = null): array
    {
        if ($source !== null && self::isDynamicKey($page)) {
            return self::sourceRows($source, $asUserId);
        }

        return match ($page) {
            'users' => self::userRows(),
            'active_channels' => self::channelRows(),
            default => [],
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function userRows(): array
    {
        $out = [];
        $users = User::query()->active()->with('docs')->orderBy('name')->get();
        foreach ($users as $u) {
            $joined = $u->date_of_joining;
            $docs = $u->docs ? $u->docs->count() : 0;
            $isDirector = method_exists($u, 'isDirector') && $u->isDirector();
            $out[] = [
                'id' => (int) $u->id,
                'label' => (string) $u->name,
                'user_id' => (int) $u->id,
                'name' => (string) $u->name,
                'email' => (string) ($u->email ?? ''),
                'phone' => (string) ($u->phone ?? ''),
                'designation' => (string) ($u->designation ?? ''),
                'org_level' => strtolower((string) ($u->org_level ?? '')),
                'date_of_joining' => $joined ? $joined->format('Y-m-d') : '',
                'tenure_days' => $joined ? (int) $joined->diffInDays(now()) : '',
                'docs_count' => $docs,
                'docs_ok' => ($docs > 0 || $isDirector) ? 'Yes' : 'No',
                'has_resume' => ! empty($u->resume_path) ? 'Yes' : 'No',
                'has_phone' => trim((string) ($u->phone ?? '')) !== '' ? 'Yes' : 'No',
            ];
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function channelRows(): array
    {
        if (! Schema::hasTable('channel_master')) {
            return [];
        }

        $hasAlias = Schema::hasColumn('channel_master', 'alias');
        $cols = ['id', 'channel'];
        if ($hasAlias) {
            $cols[] = 'alias';
        }

        $channels = ChannelMaster::query()
            ->whereRaw('LOWER(TRIM(status)) = ?', ['active'])
            ->whereNotNull('channel')
            ->where('channel', '!=', '')
            ->orderBy('channel')
            ->get($cols);

        $extra = collect();
        if (Schema::hasTable('promotion_channel_data')) {
            $extra = DB::table('promotion_channel_data')
                ->whereIn('channel_master_id', $channels->pluck('id'))
                ->get()
                ->keyBy('channel_master_id');
        }

        $out = [];
        foreach ($channels as $c) {
            $stored = $extra->get($c->id);
            $values = $stored && $stored->values ? (json_decode($stored->values, true) ?: []) : [];
            $row = [
                'id' => (int) $c->id,
                'label' => trim((string) $c->channel),
                'channel' => trim((string) $c->channel),
                'alias' => $hasAlias ? (string) ($c->alias ?? '') : '',
                'notes_remarks' => (string) ($stored->notes_remarks ?? ''),
            ];
            foreach ($values as $columnId => $v) {
                $row['col_'.$columnId] = (string) $v;
            }
            $out[] = $row;
        }

        return $out;
    }
    // ------------------------------------------------------------------
    // Dynamic pages (analytics / active-channels tables): rows come from the page's own
    // JSON data endpoint, read in-process as the rule owner.
    // ------------------------------------------------------------------

    public static function isStatic(string $page): bool
    {
        return isset(self::PAGES[$page]);
    }

    public static function isDynamicKey(string $page): bool
    {
        return (bool) preg_match('/^p_[a-f0-9]{12}$/', $page);
    }

    /** Stable page key for a request path, e.g. "amazon-tabulator-view" -> "p_1a2b3c4d5e6f". */
    public static function pageKey(string $path): string
    {
        return 'p_'.substr(sha1('/'.trim($path, '/')), 0, 12);
    }

    /** The row's primary key / display label (dynamic rows keep theirs in __id / __label). */
    public static function rowId(array $row): string
    {
        return (string) ($row['__id'] ?? $row['id'] ?? '');
    }

    public static function rowLabel(array $row): string
    {
        return (string) ($row['__label'] ?? $row['label'] ?? self::rowId($row));
    }

    /**
     * A data source must be a relative same-app GET path that looks like a data feed and does not
     * look like an action. It is only ever read as the rule's owner, so it exposes nothing new.
     */
    public static function validSource(?string $path): bool
    {
        $path = (string) $path;
        if ($path === '' || strlen($path) > 1000 || $path[0] !== '/' || str_starts_with($path, '//')
            || str_contains($path, '..') || str_contains($path, '\\') || preg_match('/[\x00-\x1f\s]/', $path)) {
            return false;
        }
        $bare = (string) parse_url($path, PHP_URL_PATH);
        if (! in_array($bare, self::configuredSources(), true)) {
            if (! preg_match('/(data|json|rows|list|feed)/i', $bare)) {
                return false;
            }
            if (preg_match('/(delete|destroy|remove|push|apply|sync|update|save|import|export|clear|reset|run|send|cron|dispatch|upload|store|create|download|token|logout|login)/i', $bare)) {
                return false;
            }
        }
        try {
            app('router')->getRoutes()->match(Request::create($path, 'GET'));
        } catch (\Throwable $e) {
            return false;
        }

        return true;
    }

    /** Data paths declared in config/ace.php (trusted without the name heuristics). @return list<string> */
    private static function configuredSources(): array
    {
        $out = [];
        foreach ((array) config('ace.pages', []) as $cfg) {
            if (! empty($cfg['url'])) {
                $out[] = (string) parse_url((string) $cfg['url'], PHP_URL_PATH);
            } elseif (! empty($cfg['route'])) {
                try {
                    $out[] = (string) parse_url(route($cfg['route'], [], false), PHP_URL_PATH);
                } catch (\Throwable $e) {
                    // unknown route name
                }
            }
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function sourceRows(string $source, ?int $asUserId = null): array
    {
        if (! self::validSource($source)) {
            throw new \RuntimeException('This page\'s data source cannot be used by Auto Task Events.');
        }

        $user = $asUserId ? User::query()->find($asUserId) : Auth::user();
        if (! $user) {
            throw new \RuntimeException('The rule owner no longer exists.');
        }

        $guard = Auth::guard();
        $previous = $guard->user();
        $originalRequest = app('request');
        try {
            Auth::setUser($user);
            $req = Request::create($source, 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
            $req->setUserResolver(fn () => $user);
            $response = app('router')->dispatch($req);
            $status = $response->getStatusCode();
            $body = method_exists($response, 'getContent') ? (string) $response->getContent() : '';
        } finally {
            app()->instance('request', $originalRequest);
            if ($previous) {
                Auth::setUser($previous);
            } elseif (method_exists($guard, 'forgetUser')) {
                $guard->forgetUser();
            }
        }

        if ($status !== 200) {
            throw new \RuntimeException('The page data returned HTTP '.$status.'.');
        }
        $json = json_decode($body, true);
        if (! is_array($json)) {
            throw new \RuntimeException('The page data is not JSON.');
        }
        $list = $json;
        if (! array_is_list($json)) {
            $list = null;
            foreach (['data', 'rows', 'items', 'result'] as $k) {
                if (isset($json[$k]) && is_array($json[$k])) {
                    $list = $json[$k];
                    break;
                }
            }
        }
        if (! is_array($list)) {
            throw new \RuntimeException('Could not find the table rows in the page data.');
        }

        return self::normalizeRows(array_values($list));
    }

    /**
     * Flatten each row to scalar columns and give it __id / __label.
     *
     * @param  list<mixed>  $list
     * @return list<array<string, mixed>>
     */
    private static function normalizeRows(array $list): array
    {
        $idKey = null;
        $out = [];
        $seen = [];
        foreach ($list as $raw) {
            if (! is_array($raw)) {
                continue;
            }
            $row = [];
            foreach ($raw as $k => $v) {
                if (is_bool($v)) {
                    $row[(string) $k] = $v ? 'Yes' : 'No';
                } elseif (is_scalar($v)) {
                    $row[(string) $k] = $v;
                } elseif ($v === null) {
                    $row[(string) $k] = '';
                }
            }
            if ($row === []) {
                continue;
            }
            if ($idKey === null) {
                $idKey = '';
                foreach (['(Child) sku', 'sku', 'SKU', 'Sku', 'id', 'ID', 'channel', 'Channel', 'Parent', 'parent'] as $cand) {
                    if (array_key_exists($cand, $row)) {
                        $idKey = $cand;
                        break;
                    }
                }
                $idKey = $idKey !== '' ? $idKey : (string) array_key_first($row);
            }
            $id = trim((string) ($row[$idKey] ?? ''));
            if ($id === '' || isset($seen[$id])) {
                continue; // no usable / duplicate key: cannot be tracked one-per-row
            }
            $seen[$id] = true;
            // keep subject keys short enough for the runs table
            $row['__id'] = strlen($id) > 48 ? substr($id, 0, 40).'~'.dechex(crc32($id)) : $id;
            // what people recognise the row by (e.g. the channel name rather than its numeric id)
            $label = $id;
            foreach (['(Child) sku', 'sku', 'SKU', 'Sku', 'channel', 'Channel', 'name', 'Name', 'title', 'Title', 'Parent', 'parent'] as $cand) {
                if (isset($row[$cand]) && trim((string) $row[$cand]) !== '') {
                    $label = trim((string) $row[$cand]);
                    break;
                }
            }
            $row['__label'] = mb_substr($label, 0, 200);
            $out[] = $row;
        }

        return $out;
    }

    /**
     * @return list<array{key: string, label: string, type: string}>
     */
    private static function sourceColumns(string $source, ?int $asUserId): array
    {
        $rows = self::sourceRows($source, $asUserId);
        $cols = [];
        foreach (array_slice($rows, 0, 300) as $r) {
            foreach ($r as $k => $v) {
                if (str_starts_with((string) $k, '__')) {
                    continue;
                }
                if (! isset($cols[$k])) {
                    $cols[$k] = ['key' => (string) $k, 'label' => (string) $k, 'type' => is_numeric($v) && $v !== '' ? 'number' : 'text'];
                }
            }
        }

        return array_values($cols);
    }
}
