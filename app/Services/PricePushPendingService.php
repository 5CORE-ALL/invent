<?php

namespace App\Services;

use App\Http\Controllers\MarketPlace\CvrMasterController;
use App\Models\PricePushPending;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

/**
 * Tracks every price push made from the /price-increase modals, retries the ones that did not go
 * through (hourly, see price-push:retry-pending) and e-mails an alert while something is still pending.
 */
class PricePushPendingService
{
    public const ALERT_EMAIL = 'software5@5core.com';
    public const MAX_ATTEMPTS = 72;     // hourly → about 3 days, then the row is marked "failed" (no more retries)
    public const REALERT_HOURS = 6;     // digest e-mail is repeated at most this often while something is pending
    /** Channels that are pushed by hand only — the hourly retry skips them. */
    public const MANUAL_ONLY_MARKETPLACES = ['temu', 'temu2'];
    private const EXTRA_KEYS = ['self_pick_price', 'goods_id', 'sku_id', 'product_id'];

    public const TASK_ASSIGNEE_NAME = 'Shreya';
    public const TASK_ASSIGNOR_EMAIL = 'president@5core.com';
    public const TASK_GROUP = 'Listing Not Found';

    /**
     * Rows that count for the red alert on /price-increase, its Hist-column counters and the alert e-mail.
     * AliExpress is left out, and so is every row whose SKU is "not listed" on the channel (that one goes to
     * the listing task for Shreya instead, see createListingTask()) and every row stopped by a daily API limit
     * (429 "Retry after N seconds" — retried automatically once the limit resets).
     */
    public static function isAlertable($row): bool
    {
        if (str_starts_with(self::key((string) $row->marketplace), 'aliexpress')) {
            return false;
        }

        $err = (string) $row->last_error;
        // "Daily API limit of 25,000 requests reached … Retry after N seconds": temporary, paused and retried automatically
        if (self::rateLimitSeconds($err) !== null || preg_match('/daily\s+api\s+limit/i', $err)) {
            return false;
        }

        // Marketplace is reviewing the listing / a previous change ("PUB_BIZCHECK_PRODUCT_IN_AUDITING … under review",
        // "modification record under review", "wait for Temu to review your last application"): clears by itself, the
        // hourly retry goes through once the review is done.
        if (preg_match('/in_auditing|under\s+review|wait\s+for\s+(?:the\s+)?(?:\w+\s+to\s+)?review/i', $err)) {
            return false;
        }

        // Amazon: "did not accept the price update (status: INVALID) … product type specified is invalid or not supported"
        // is a problem in the Amazon listing data itself, not something the hourly retry can fix.
        if (self::key((string) $row->marketplace) === 'amazon'
            && preg_match('/product\s+type\b[^.\n]{0,40}\binvalid|did\s+not\s+accept\s+the\s+price\s+update[^\n]{0,20}status:\s*invalid/i', $err)) {
            return false;
        }

        return ! self::isListingNotFound($err);
    }

    /** Outstanding rows that are shown / mailed as "NOT pushed yet" (see isAlertable()). */
    public static function alertableOutstanding(?array $skus = null)
    {
        $q = PricePushPending::outstanding();
        if ($skus) {
            $q->whereIn('sku', $skus);
        }

        return $q->orderByDesc('last_attempt_at')->get()->filter(fn ($r) => self::isAlertable($r))->values();
    }

    /** Marketplace answers that mean "this SKU has no listing there" (as opposed to a temporary API error). */
    public static function isListingNotFound(string $message): bool
    {
        return (bool) preg_match(
            '/(no\s+\w+(\s+\w+)?\s+skucode|not\s+listed|listing\s+not\s+found|no\s+\w+\s+listing|(goodsid|skuid|product\s*id|item)\s+not\s+found|can.t\s+find\s+this\s+item|not\s+found\s+for\s+sku|sku\s+not\s+found'
            // "SKU: X not found in Walmart API data", "Product not found", "Item ID not found", "Variant ID not found for SKU" …
            . '|\b(sku|product|item|listing|variant|offer|asin|goods)\b[^.\n]{0,40}\bnot\s+found'
            . '|not\s+found\s+(for|in|on)\s+(this\s+)?(sku|product|item|listing|walmart|shopify|prolight|ebay|amazon)'
            . '|(product|sku|variant|item)[\s_]*id\b[^.\n]{0,30}\bmissing\s+for\s+sku'
            . '|(product|listing|item)\s+(does\s+not|doesn.t)\s+exist'
            // Temu [150010188] "The mall and goods not match": the goods id is not in that Temu account = not listed there
            . '|mall\s+and\s+goods\s+not\s+match'
            // Amazon Listings API answered 404 for every spelling of the SKU = not in that Seller Central account
            . '|could\s+not\s+find\s+this\s+offer)/i',
            $message
        );
    }

    /** The listing-task assignee (active user "Shreya"). */
    private function listingTaskUser(): ?User
    {
        $key = strtolower(self::TASK_ASSIGNEE_NAME);
        $matches = User::active()
            ->where(function ($q) use ($key) {
                $q->whereRaw('LOWER(name) LIKE ?', [$key . '%'])->orWhereRaw('LOWER(email) LIKE ?', [$key . '%']);
            })
            ->orderBy('id')
            ->get();
        if ($matches->isEmpty()) {
            return null;
        }

        return $matches->first(fn ($u) => strtolower(trim((string) $u->name)) === $key) ?? $matches->first();
    }

    /**
     * One task per SKU + channel (not repeated for 7 days, even after it is closed — the hourly retry keeps failing
     * the same way until the listing exists). Assigned to Shreya; assignor = president@5core.com.
     */
    private function createListingTask(PricePushPending $row, string $message, ?string $by): void
    {
        try {
            if (! Schema::hasTable('tasks')) {
                return;
            }
            $marker = 'listing-nf-' . md5(strtoupper($row->sku) . '|' . $row->marketplace);
            $exists = Task::withTrashed()
                ->where('link8', $marker)
                ->where('created_at', '>=', now()->subDays(7))
                ->exists();
            if ($exists) {
                return;
            }

            $assignee = $this->listingTaskUser();
            if (! $assignee) {
                Log::warning('Listing-not-found task: user ' . self::TASK_ASSIGNEE_NAME . ' not found', ['sku' => $row->sku, 'channel' => $row->channel]);

                return;
            }
            $assignorEmail = self::TASK_ASSIGNOR_EMAIL;
            $channel = $row->channel ?: $row->marketplace;

            $start = now();
            $due = $start->copy()->addDays(2);
            $data = [
                'title' => 'Listing not found: ' . $row->sku . ' on ' . $channel,
                'description' => "The price push for SKU {$row->sku} on {$channel} (\${$row->price}) failed because the listing was not found.\n\n"
                    . 'Marketplace said: ' . mb_substr(trim($message), 0, 500) . "\n\n"
                    . "Please create / link the listing for this SKU on {$channel} (or sync the channel products), then push the price again from "
                    . url('/price-increase') . '. The push is retried automatically every hour until the listing exists.',
                'group' => self::TASK_GROUP,
                'priority' => 'normal',
                'assignor' => $assignorEmail,
                'assign_to' => $assignee->email ?: $assignee->name,
                'status' => 'Todo',
                'eta_time' => 30,
                'start_date' => $start,
                'completion_date' => $due,
                'due_date' => $due,
                'completion_day' => 0,
                'etc_done' => 0,
                'is_missed' => 0,
                'is_missed_track' => 0,
                'workspace' => 0,
                'order' => 0,
                'task_id' => '',
                'link1' => url('/price-increase'),
                'link2' => '', 'link3' => '', 'link4' => '', 'link5' => '', 'link6' => '', 'link7' => '',
                'link8' => $marker,
                'link9' => '',
                'is_data_from' => 0,
                'is_automate_task' => 0,
                'task_type' => 'manual',
                'rework_reason' => '',
                'delete_rating' => 0,
                'delete_feedback' => '',
                'split_tasks' => 0,
            ];
            if (Schema::hasColumn('tasks', 'is_corrective_action')) {
                $data['is_corrective_action'] = 0;
            }
            $task = Task::create($data);

            try {
                app(TaskWhatsAppNotificationService::class)->notifyNewTaskAssigned($task);
            } catch (\Throwable $e) {
                Log::warning('Listing-not-found task WhatsApp failed', ['task_id' => $task->id, 'error' => $e->getMessage()]);
            }
        } catch (\Throwable $e) {
            Log::warning('Listing-not-found task could not be created: ' . $e->getMessage(), ['sku' => $row->sku, 'channel' => $row->channel]);
        }
    }

    /**
     * "Action" column of the not-updated list: id => ['action' => 'done'|'awaited', 'note' => ...].
     * Listing not found → the task for Sneha (create / link the listing): Done once that task is Done / Archived, else Awaited.
     * Every other failure (API limit, not listed on a channel, timeout …) → Awaited: the hourly automatic retry is the action.
     */
    public function actionsFor($rows): array
    {
        $out = [];
        $markers = [];
        foreach ($rows as $r) {
            $manualOnly = in_array($r->marketplace, self::MANUAL_ONLY_MARKETPLACES, true);
            $out[$r->id] = ['action' => 'awaited', 'note' => $manualOnly
                ? 'Temu is pushed manually only — push it again by hand'
                : ($r->status === 'failed' ? 'Gave up — push it manually' : 'Automatic retry every hour')];
            if (self::isListingNotFound((string) $r->last_error)) {
                $markers[$r->id] = 'listing-nf-' . md5(strtoupper($r->sku) . '|' . $r->marketplace);
                $out[$r->id]['note'] = 'Waiting for the listing task (' . self::TASK_ASSIGNEE_NAME . ')';
            }
        }
        if ($markers) {
            try {
                if (Schema::hasTable('tasks')) {
                    $tasks = Task::withTrashed()->whereIn('link8', array_values($markers))->orderBy('created_at')->get(['link8', 'status', 'deleted_at']);
                    $byMarker = [];
                    foreach ($tasks as $t) {
                        $byMarker[$t->link8] = $t; // latest task wins
                    }
                    foreach ($markers as $id => $marker) {
                        $t = $byMarker[$marker] ?? null;
                        if ($t && in_array((string) $t->status, ['Done', 'Archived'], true)) {
                            $out[$id] = ['action' => 'done', 'note' => 'Listing task ' . $t->status . ' (' . self::TASK_ASSIGNEE_NAME . ') — the next retry will push it'];
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('PricePushPending actionsFor failed: ' . $e->getMessage());
            }
        }

        return $out;
    }

    public static function key(string $marketplace): string
    {
        return preg_replace('/\s+/', '', strtolower(trim($marketplace)));
    }

    public function available(): bool
    {
        try {
            return Schema::hasTable('price_push_pendings');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Call the real push exactly like the modal does and return [ok, message, body]. */
    public function runPush(array $payload, ?Request $original = null): array
    {
        try {
            // From the web the real request is passed through (logged-in user → "pushed by" in the history)
            $resp = app(CvrMasterController::class)->pushPriceToAmazon(
                $original ?: Request::create('/cvr-master-push-price', 'POST', $payload)
            );
            $body = method_exists($resp, 'getData') ? $resp->getData(true) : [];
            $body = is_array($body) ? $body : [];
            $ok = ! empty($body['success']);

            return [$ok, (string) ($body['message'] ?? ($ok ? 'Pushed' : 'Push failed')), $body, $resp];
        } catch (\Throwable $e) {
            return [false, $e->getMessage() ?: 'Push exception', ['success' => false], null];
        }
    }

    /** Store the outcome of one push attempt (success → pushed, failure → pending / failed). */
    public function record(string $sku, string $marketplace, ?string $channel, float $price, array $extras, bool $ok, string $message, ?string $by = null): ?PricePushPending
    {
        if (! $this->available()) {
            return null;
        }
        $sku = strtoupper(trim($sku));
        $mp = self::key($marketplace);
        if ($sku === '' || $mp === '' || ! ($price > 0)) {
            return null;
        }

        try {
            $row = PricePushPending::firstOrNew(['sku' => $sku, 'marketplace' => $mp]);
            $samePrice = $row->exists && abs((float) $row->price - $price) < 0.005;
            if (! $samePrice) {
                $row->attempts = 0;      // a new price is a new job
                $row->alerted_at = null;
                $row->pushed_at = null;  // an older push of a different price does not count for this one
            }
            $row->channel = $channel ?: ($row->channel ?: $marketplace);
            $row->price = $price;
            $row->extras = $extras ?: null;
            $row->last_attempt_at = now();
            if ($by) {
                $row->requested_by = $by;
            }
            // "Daily API limit … Retry after N seconds": the marketplace refused for a known time, not because the push is wrong.
            // That try does not count against MAX_ATTEMPTS (the hourly retry pauses that marketplace until the limit resets).
            $rateLimited = ! $ok && self::rateLimitSeconds($message) !== null;
            $row->attempts = $rateLimited ? max(1, (int) $row->attempts) : (int) $row->attempts + 1;
            if ($ok) {
                $row->status = 'pushed';
                $row->pushed_at = now();
                $row->last_error = null;
            } else {
                $row->status = $row->attempts >= self::MAX_ATTEMPTS ? 'failed' : 'pending';
                $row->last_error = mb_substr($message, 0, 1000);
            }
            $row->save();

            // The channel has no listing for this SKU: nothing a retry can fix → hand it to Sneha as a task
            if (! $ok && self::isListingNotFound($message)) {
                $this->createListingTask($row, $message, $by);
            }

            return $row;
        } catch (\Throwable $e) {
            Log::warning('PricePushPending record failed: ' . $e->getMessage());

            return null;
        }
    }

    /** Re-push one stored row. */
    public function retryRow(PricePushPending $row): bool
    {
        $payload = array_merge(
            (array) ($row->extras ?? []),
            ['sku' => $row->sku, 'price' => $row->price, 'marketplace' => $row->marketplace]
        );
        [$ok, $msg] = $this->runPush($payload);
        $this->record($row->sku, $row->marketplace, $row->channel, (float) $row->price, (array) ($row->extras ?? []), $ok, $msg);

        return $ok;
    }

    /** Retry every outstanding row (not "failed"), up to $limit. Returns [tried, ok]. */
    public function retryAll(int $limit = 500): array
    {
        if (! $this->available()) {
            return [0, 0];
        }
        // "Queued" rows belong to the sequential queue (drainQueue) — never pushed here, so two pushes never run in parallel
        // Temu / Temu 2 are never re-pushed automatically: they are pushed manually only
        // A marketplace that answered "Daily API limit … Retry after N seconds" is left alone until that time has passed,
        // so the hourly run neither burns the (already exhausted) quota nor uses up the rows' attempts.
        $blocked = $this->rateLimitedMarketplaces();
        $rows = PricePushPending::outstanding()->where('status', 'pending')
            ->whereNotIn('marketplace', array_merge(self::MANUAL_ONLY_MARKETPLACES, array_keys($blocked)))
            ->where(function ($q) {
                $q->whereNull('last_error')->orWhere('last_error', 'not like', PricePushPending::QUEUED_PREFIX . '%');
            })
            ->orderBy('last_attempt_at')->limit($limit)->get();
        $ok = 0;
        $tried = 0;
        foreach ($rows as $row) {
            if (isset($blocked[$row->marketplace])) {
                continue; // this marketplace hit its limit during this very run
            }
            $tried++;
            if ($this->retryRow($row)) {
                $ok++;
            } else {
                $fresh = $row->fresh();
                if ($fresh && self::rateLimitSeconds((string) $fresh->last_error) !== null) {
                    $blocked[$row->marketplace] = true;
                    Log::info('Price push retry: marketplace hit its API limit, skipping the rest of it until it resets', ['marketplace' => $row->marketplace]);
                }
            }
            usleep(400000); // be gentle with the marketplace APIs
        }

        return [$tried, $ok];
    }

    /**
     * Seconds a marketplace asked us to wait ("Daily API limit of 25,000 requests reached … Retry after 40703 seconds."), else null.
     * Capped at 24 h.
     */
    public static function rateLimitSeconds(string $message): ?int
    {
        if (preg_match('/limit[^\n]{0,200}?retry\s+after\s+(\d+)\s*(?:seconds?|secs?|s)\b/i', $message, $m)) {
            $s = (int) $m[1];

            return $s > 0 ? min($s, 86400) : null;
        }

        return null;
    }

    /**
     * Marketplaces whose outstanding rows say they are rate limited and whose "retry after" time has not passed yet.
     * Derived from the stored error + last attempt time, so it needs no extra state.
     *
     * @return array<string, \Illuminate\Support\Carbon>  marketplace => time the limit is over
     */
    public function rateLimitedMarketplaces(): array
    {
        $out = [];
        $rows = PricePushPending::outstanding()->where('last_error', 'like', '%retry after%')
            ->get(['marketplace', 'last_error', 'last_attempt_at']);
        foreach ($rows as $r) {
            $secs = self::rateLimitSeconds((string) $r->last_error);
            if ($secs === null || ! $r->last_attempt_at) {
                continue;
            }
            $until = $r->last_attempt_at->copy()->addSeconds($secs + 60);
            if ($until->isFuture() && (! isset($out[$r->marketplace]) || $until->gt($out[$r->marketplace]))) {
                $out[$r->marketplace] = $until;
            }
        }

        return $out;
    }

    private const QUEUE_LOCK_KEY = 'price_push_queue_worker';
    private const QUEUE_LOCK_SECONDS = 300; // renewed before every job; a dead worker frees the queue after this

    /**
     * SEQUENTIAL FIFO QUEUE of background pushes. The queue is the set of rows that are "Queued" (written by
     * POST /price-push-background). Only ONE worker drains it at any time (a cache lock that is renewed before every job),
     * oldest request first, one channel push after the other — never two pushes in parallel, whichever SKU / user queued them.
     * A worker that dies (php-fpm timeout, restart) lets the lock expire; price-push:run-queued (every minute) then takes the
     * queue over, so nothing is abandoned. Returns [pushed, ok, workerRan] (workerRan = false: another worker is already on it).
     */
    public function drainQueue(int $maxJobs = 1000): array
    {
        if (! $this->available()) {
            return [0, 0, false];
        }
        $token = bin2hex(random_bytes(8));
        if (! Cache::add(self::QUEUE_LOCK_KEY, $token, self::QUEUE_LOCK_SECONDS)) {
            return [0, 0, false]; // another worker is draining — it will also pick up what was just queued
        }
        $tried = 0;
        $ok = 0;
        $done = [];
        try {
            while ($tried < $maxJobs) {
                if (Cache::get(self::QUEUE_LOCK_KEY) !== $token) {
                    break; // lock lost (expired while a job ran long) — the next worker continues
                }
                Cache::put(self::QUEUE_LOCK_KEY, $token, self::QUEUE_LOCK_SECONDS);
                $row = PricePushPending::where('status', 'pending')
                    ->where('last_error', 'like', PricePushPending::QUEUED_PREFIX . '%')
                    ->when($done, fn ($q) => $q->whereNotIn('id', $done))
                    ->orderBy('last_attempt_at')
                    ->orderBy('id')
                    ->first();
                if (! $row) {
                    break;
                }
                $done[] = $row->id;
                if (! $this->claimRow((int) $row->id)) {
                    continue; // an express worker (runExpress) is pushing this row right now
                }
                $tried++;
                try {
                    if ($this->runQueuedRow($row)) {
                        $ok++;
                    }
                } finally {
                    $this->releaseRow((int) $row->id);
                }
            }
        } finally {
            if (Cache::get(self::QUEUE_LOCK_KEY) === $token) {
                Cache::forget(self::QUEUE_LOCK_KEY);
            }
        }

        return [$tried, $ok, true];
    }

    /**
     * Start `php artisan price-push:express <ids>` as a detached background process (survives the web request, a refresh or a
     * closed tab). Returns false when it could not be started, so the caller can fall back to working inside the request.
     */
    public function spawnExpressWorker(array $rowIds): bool
    {
        $rowIds = array_values(array_filter(array_map('intval', $rowIds)));
        if (! $rowIds || ! function_exists('exec')) {
            return false;
        }
        $php = (string) env('PRICE_PUSH_PHP', '');
        if ($php === '' || ! is_executable($php)) {
            $php = '';
            foreach (['/usr/bin/php8.3', '/usr/bin/php', '/usr/local/bin/php'] as $cand) {
                if (is_executable($cand)) {
                    $php = $cand;
                    break;
                }
            }
        }
        $artisan = base_path('artisan');
        if ($php === '' || ! is_file($artisan)) {
            return false;
        }
        $log = storage_path('logs/price-push-worker.log');
        $setsid = is_executable('/usr/bin/setsid') ? '/usr/bin/setsid ' : '';
        $cmd = 'cd ' . escapeshellarg(base_path()) . ' && ' . $setsid . 'nohup ' . escapeshellarg($php) . ' ' . escapeshellarg($artisan)
            . ' price-push:express ' . implode(' ', $rowIds) . ' >> ' . escapeshellarg($log) . ' 2>&1 < /dev/null &';
        try {
            @exec($cmd, $out, $code);

            return $code === 0;
        } catch (\Throwable $e) {
            Log::warning('Price push: could not start the express worker: ' . $e->getMessage());

            return false;
        }
    }

    private const ROW_CLAIM_SECONDS = 600;

    /** One worker per row: the FIFO worker and the express worker never push the same row twice. */
    private function claimRow(int $id): bool
    {
        return Cache::add('price_push_row_claim:' . $id, 1, self::ROW_CLAIM_SECONDS);
    }

    private function releaseRow(int $id): void
    {
        Cache::forget('price_push_row_claim:' . $id);
    }

    /**
     * EXPRESS push: the rows a user has just clicked are pushed at once by that click's own worker — they do not wait behind
     * the shared FIFO queue (other SKUs / retries). Amazon goes first, then the other channels in the order they were sent.
     * Rows are claimed one by one, so the FIFO worker (drainQueue) skips what is already being pushed here and the other
     * way round. Rows already pushed by someone else in the meantime are skipped. $jobs = [ ['sku' => .., 'marketplace' => ..], … ].
     * Returns [pushed, ok].
     */
    public function runExpress(array $jobs): array
    {
        if (! $this->available() || ! $jobs) {
            return [0, 0];
        }
        $order = [];
        foreach (array_values($jobs) as $i => $j) {
            $sku = strtoupper(trim((string) ($j['sku'] ?? '')));
            $mp = self::key((string) ($j['marketplace'] ?? ''));
            if ($sku === '' || $mp === '') {
                continue;
            }
            $order[$sku . '|' . $mp] = [$sku, $mp, $mp === 'amazon' ? 0 : 1, $i];
        }
        uasort($order, fn ($a, $b) => [$a[2], $a[3]] <=> [$b[2], $b[3]]);

        $tried = 0;
        $ok = 0;
        foreach ($order as [$sku, $mp]) {
            $row = PricePushPending::where('sku', $sku)->where('marketplace', $mp)->first();
            if (! $row || $row->status !== 'pending' || strpos((string) $row->last_error, PricePushPending::QUEUED_PREFIX) !== 0) {
                continue; // already pushed / handled by the FIFO worker
            }
            $id = (int) $row->id;
            if (! $this->claimRow($id)) {
                continue; // the FIFO worker has it
            }
            try {
                $fresh = $row->fresh();
                if (! $fresh || $fresh->status !== 'pending' || strpos((string) $fresh->last_error, PricePushPending::QUEUED_PREFIX) !== 0) {
                    continue;
                }
                $tried++;
                if ($this->runQueuedRow($fresh)) {
                    $ok++;
                }
            } finally {
                $this->releaseRow($id);
            }
        }

        return [$tried, $ok];
    }

    /** Push one queued row exactly like the web request would (same user in the push history), record + cache the result. */
    private function runQueuedRow(PricePushPending $row): bool
    {
        $extras = (array) ($row->extras ?? []);
        $payload = array_merge($extras, [
            'sku' => $row->sku,
            'price' => $row->price,
            'marketplace' => $row->marketplace,
            'channel' => (string) ($row->channel ?: $row->marketplace),
        ]);
        $body = [];
        try {
            $req = Request::create('/cvr-master-push-price', 'POST', $payload);
            if ($row->requested_by) {
                $user = User::where('name', $row->requested_by)->first();
                if ($user) {
                    $req->setUserResolver(fn () => $user); // "pushed by" in the push history
                }
            }
            [$ok, $message, $body] = $this->runPush($payload, $req);
        } catch (\Throwable $e) {
            $ok = false;
            $message = $e->getMessage() ?: 'Push exception';
        }
        $saved = $this->record($row->sku, $row->marketplace, $row->channel, (float) $row->price, $extras, $ok, (string) $message);
        Cache::put(
            'price_push_result:' . strtoupper($row->sku) . '|' . $row->marketplace,
            ['message' => (string) $message, 'saved_only' => ! empty($body['saved_only'])],
            now()->addMinutes(30)
        );
        if ($saved && ! $ok && $saved->alerted_at === null && self::isAlertable($saved)) {
            if ($this->sendAlert([$saved], 'A channel price could not be pushed. It will be retried every hour.')) {
                $saved->forceFill(['alerted_at' => now()])->save();
            }
        }

        return $ok;
    }

    /** E-mail software5@5core.com. $rows = outstanding rows to list. */
    public function sendAlert($rows, string $reason): bool
    {
        if (count($rows) === 0) {
            return false;
        }
        $lines = [];
        foreach ($rows as $r) {
            $lines[] = sprintf(
                '%s | %s | $%s | tries: %d | %s%s',
                $r->sku,
                $r->channel ?: $r->marketplace,
                number_format((float) $r->price, 2),
                (int) $r->attempts,
                $r->last_error ? mb_substr(preg_replace('/\s+/', ' ', (string) $r->last_error), 0, 160) : 'not pushed',
                $r->status === 'failed' ? ' | GAVE UP — push it manually' : ''
            );
        }
        $body = $reason . "\n\nSKU | Channel | Price | Tries | Last error\n" . implode("\n", $lines)
            . "\n\nThe pricing page shows a red alert while any of these is pending; the system retries every hour."
            . "\n" . url('/price-increase');
        try {
            Mail::raw($body, function ($m) use ($rows) {
                $m->to(self::ALERT_EMAIL)->subject('[Pricing] ' . count($rows) . ' channel price push(es) not pushed');
            });

            return true;
        } catch (\Throwable $e) {
            Log::warning('PricePushPending alert mail failed: ' . $e->getMessage());

            return false;
        }
    }

    /** Hourly digest: only when something is outstanding and the last alert is older than REALERT_HOURS. */
    public function alertDigestIfDue(): int
    {
        if (! $this->available()) {
            return 0;
        }
        $rows = self::alertableOutstanding()->sortBy('sku')->values();
        if ($rows->isEmpty()) {
            return 0;
        }
        $due = $rows->contains(function ($r) {
            return $r->alerted_at === null || $r->alerted_at->lt(now()->subHours(self::REALERT_HOURS));
        });
        if (! $due) {
            return 0;
        }
        if ($this->sendAlert($rows, 'These channel prices are still NOT pushed (hourly retry did not fix them).')) {
            PricePushPending::whereIn('id', $rows->pluck('id'))->update(['alerted_at' => now()]);

            return $rows->count();
        }

        return 0;
    }
}
