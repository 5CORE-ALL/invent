<?php

namespace App\Services;

use App\Models\Task;
use App\Models\User;
use App\Support\ChatWorkspace;
use App\Support\GoogleShoppingCampaignsRawRule;
use App\Support\GoogleShoppingLiveSyncStatus;
use App\Support\InventChatBot;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Tells Shobha (5Core Chat bot inbox + a Task that sends the approved task WhatsApp template) when an ENABLED Google Shopping campaign still has
 * SBGT != LBgt or SBID != LBid after the background match run, with the reason for each one.
 *
 * The same issue is not repeated within `services.google_shopping_alerts.cooldown_hours`.
 * Hooks (tests): rule, send.
 */
class GoogleShoppingMismatchAlertService
{
    public const CHAT_COMMAND = 'google_shopping_mismatch';

    /** WhatsApp text limit is 4096 characters; stay well under it. */
    private const MAX_BODY_CHARS = 3300;

    /** @param  array<string, callable>  $hooks */
    public function __construct(private array $hooks = []) {}

    /**
     * @param  array<string, array<string, mixed>>  $targets  campaign_id => sbid, sbgt, name, inventory, price, dil
     * @param  array<string, array<string, mixed>>  $states  campaign_id => live_bid, live_bgt, *_fetch_ok, *_fetch_error
     * @param  callable(string, string): bool  $isWaiting  (field 'bid'|'bgt', campaign_id) → last push did not stick
     * @return list<array{key: string, cid: string, name: string, kind: string, label: string, text: string}>
     */
    public function findIssues(array $targets, array $states, callable $isWaiting): array
    {
        $rule = $this->sbgtRule();
        $issues = [];

        foreach ($targets as $cid => $target) {
            $cid = (string) $cid;
            $state = $states[$cid] ?? [];
            $name = trim((string) ($target['name'] ?? '')) !== '' ? trim((string) $target['name']) : $cid;

            // SBGT vs LBgt
            $sbgt = self::positive($target['sbgt'] ?? null);
            $liveBgt = self::positive($state['live_bgt'] ?? null);
            if ($sbgt === null || $sbgt < 1) {
                if ($liveBgt !== null) {
                    $issues[] = $this->issue($cid, $name, 'sbgt_zero', 'SBGT 0 vs LBgt $'.self::money($liveBgt),
                        $this->zeroSbgtReason($target, $rule).' A $0 budget cannot be pushed, so Google keeps the old budget until the campaign is paused.');
                }
            } elseif ($liveBgt === null) {
                if (! self::boolish($state['bgt_fetch_ok'] ?? false)) {
                    $issues[] = $this->issue($cid, $name, 'lbgt_unread', 'SBGT '.(int) $sbgt.' vs LBgt unknown',
                        'Live budget could not be read from Google Ads'.self::errorSuffix($state['bgt_fetch_error'] ?? null).'.');
                }
            } elseif (abs($liveBgt - $sbgt) > GoogleShoppingLiveSyncStatus::BGT_TOLERANCE) {
                $issues[] = $this->issue($cid, $name, 'bgt_mismatch', 'SBGT '.(int) $sbgt.' vs LBgt $'.self::money($liveBgt),
                    $isWaiting('bgt', $cid)
                        ? 'The last push did not stick (Google Ads still shows the old budget). Auto-retry is paused for up to 3 hours.'
                        : 'Push was sent but Google Ads still shows the old budget.');
            }

            // SBID vs LBid (no SBID means the rule says "leave the bid alone")
            $sbid = self::positive($target['sbid'] ?? null);
            if ($sbid === null) {
                continue;
            }
            $liveBid = self::positive($state['live_bid'] ?? null);
            if ($liveBid === null) {
                if (! self::boolish($state['bid_fetch_ok'] ?? false)) {
                    $issues[] = $this->issue($cid, $name, 'lbid_unread', 'SBID $'.self::money($sbid, 2).' vs LBid unknown',
                        'Live bid could not be read from Google Ads'.self::errorSuffix($state['bid_fetch_error'] ?? null).'.');
                }
            } elseif (abs($liveBid - round($sbid, 2)) > GoogleShoppingLiveSyncStatus::BID_TOLERANCE) {
                $issues[] = $this->issue($cid, $name, 'bid_mismatch', 'SBID $'.self::money($sbid, 2).' vs LBid $'.self::money($liveBid, 2),
                    $isWaiting('bid', $cid)
                        ? 'The last push did not stick (Google Ads still shows the old bid). Auto-retry is paused for up to 3 hours.'
                        : 'Push was sent but Google Ads still shows the old bid.');
            }
        }

        return $issues;
    }

    /**
     * Send one digest with the issues that were not already alerted within the cooldown.
     *
     * @param  list<array{key: string, cid: string, name: string, kind: string, label: string, text: string}>  $issues
     * @return array{found: int, new: int, sent: int, chat: bool, whatsapp: bool}
     */
    public function notify(array $issues): array
    {
        $result = ['found' => count($issues), 'new' => 0, 'sent' => 0, 'chat' => false, 'whatsapp' => false];
        if ($issues === [] || ! (bool) config('services.google_shopping_alerts.enabled', true)) {
            return $result;
        }

        $fresh = array_values(array_filter($issues, fn (array $i) => ! Cache::has(self::cacheKey($i['key']))));
        $result['new'] = count($fresh);
        if ($fresh === []) {
            return $result;
        }

        [$body, $included] = $this->buildBody($fresh, count($issues));

        $delivered = $this->deliver($body, $included, $result);
        if (! $delivered) {
            return $result; // nothing reached anyone: keep the issues "new" so the next run tries again
        }

        $ttl = now()->addHours(max(1, (int) config('services.google_shopping_alerts.cooldown_hours', 24)));
        foreach ($included as $issue) {
            Cache::put(self::cacheKey($issue['key']), 1, $ttl);
        }
        $result['sent'] = count($included);

        return $result;
    }

    /**
     * @param  list<array{key: string, cid: string, name: string, kind: string, label: string, text: string}>  $fresh
     * @return array{0: string, 1: list<array{key: string, cid: string, name: string, kind: string, label: string, text: string}>}
     */
    public function buildBody(array $fresh, int $totalIssues): array
    {
        $head = "⚠️ Google Shopping — SBGT/SBID not matching LBgt/LBid\n"
            .now('Asia/Kolkata')->format('d M Y, h:i A').' IST'."\n";
        $foot = "\nOpen: ".url('/google/shopping/google-shopping');

        $included = [];
        $lines = '';
        foreach ($fresh as $i => $issue) {
            $block = "\n".($i + 1).'. '.$issue['name'].' ('.$issue['cid'].")\n"
                .'   '.$issue['label']."\n"
                .'   Reason: '.$issue['text']."\n";
            if ($included !== [] && mb_strlen($head.$lines.$block.$foot) > self::MAX_BODY_CHARS) {
                break;
            }
            $lines .= $block;
            $included[] = $issue;
        }

        $more = count($fresh) - count($included);
        $summary = count($included).' new issue'.(count($included) === 1 ? '' : 's')
            .' ('.$totalIssues.' campaign check'.($totalIssues === 1 ? '' : 's').' not matching in total)';
        if ($more > 0) {
            $summary .= ' — '.$more.' more will follow in the next run';
        }

        return [$head.$summary."\n".$lines.$foot, $included];
    }

    /**
     * @param  array{found: int, new: int, sent: int, chat: bool, whatsapp: bool}  $result
     */
    private function deliver(string $body, array $included, array &$result): bool
    {
        if (isset($this->hooks['send'])) {
            $out = ($this->hooks['send'])($body);
            $result['chat'] = (bool) ($out['chat'] ?? false);
            $result['whatsapp'] = (bool) ($out['whatsapp'] ?? false);

            return $result['chat'] || $result['whatsapp'];
        }

        $user = User::query()
            ->whereRaw('LOWER(email) = ?', [strtolower(trim((string) config('services.google_shopping_alerts.email')))])
            ->first();

        if ($user !== null) {
            try {
                if (ChatWorkspace::tablesReady()) {
                    $inbox = ChatWorkspace::botInboxFor($user);
                    InventChatBot::postBot($inbox, $body, self::CHAT_COMMAND);
                    ChatWorkspace::forgetUnreadCache((int) $user->id);
                    $result['chat'] = true;
                }
            } catch (Throwable $e) {
                Log::error('Google Shopping mismatch alert: chat post failed', ['error' => $e->getMessage()]);
            }
        } else {
            Log::warning('Google Shopping mismatch alert: no user for the alert email', [
                'email' => config('services.google_shopping_alerts.email'),
            ]);
        }

        // WhatsApp: create a Task for the same user. Creating a task sends the approved "task assigned"
        // WhatsApp template (delivers at any time), which plain WhatsApp text does not.
        if ($user !== null) {
            try {
                $task = $this->createAlertTask($user, $body, $included);
                $status = app(TaskWhatsAppNotificationService::class)->notifyNewTaskAssigned($task);
                $result['whatsapp'] = $status === 'sent';
                if (! $result['whatsapp']) {
                    Log::warning('Google Shopping mismatch alert: task WhatsApp not sent', ['task_id' => $task->id, 'status' => $status]);
                }
            } catch (Throwable $e) {
                Log::error('Google Shopping mismatch alert: task create/WhatsApp failed', ['error' => $e->getMessage()]);
            }
        }

        // Extra numbers (GOOGLE_SHOPPING_ALERT_WHATSAPP_TO): plain text, only delivered inside a 24-hour session.
        $wa = app(WhatsAppService::class);
        foreach ((array) config('services.google_shopping_alerts.whatsapp_to', []) as $phone) {
            $clean = WhatsAppService::cleanPhone($phone);
            if (! $clean) {
                continue;
            }
            try {
                if ($wa->sendText($clean, $body)) {
                    $result['whatsapp'] = true;
                }
            } catch (Throwable $e) {
                Log::error('Google Shopping mismatch alert: WhatsApp failed', ['error' => $e->getMessage()]);
            }
        }

        return $result['chat'] || $result['whatsapp'];
    }

    /**
     * One task per digest, assigned to the alert user. The title goes into the WhatsApp template as one
     * line, so it stays short; the full per-campaign reasons are in the description.
     *
     * @param  list<array{key: string, cid: string, name: string, kind: string, label: string, text: string}>  $included
     */
    private function createAlertTask(User $user, string $body, array $included): Task
    {
        $first = $included[0] ?? null;
        $title = 'Google Shopping: '.count($included).' campaign'.(count($included) === 1 ? '' : 's').' SBGT/SBID not matching live';
        if ($first !== null) {
            $title .= ' — '.$first['name'].': '.$first['label'];
        }
        $title = mb_strimwidth(trim((string) preg_replace('/\s+/', ' ', $title)), 0, 200, '…');

        $start = now();
        $due = $start->copy()->addDay();
        $data = [
            'title' => $title,
            'description' => $body,
            'group' => 'Google Shopping',
            'priority' => 'High',
            // Shobha is both assignor and assignee: nobody else gets this task or its WhatsApp/notifications.
            'assignor' => trim((string) config('services.google_shopping_alerts.task_assignor', '')) !== ''
                ? (string) config('services.google_shopping_alerts.task_assignor')
                : (string) $user->email,
            'assign_to' => (string) $user->email,
            'status' => 'Todo',
            'eta_time' => 15,
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
            'link1' => url('/google/shopping/google-shopping'),
            'link2' => '', 'link3' => '', 'link4' => '', 'link5' => '', 'link6' => '', 'link7' => '',
            'link8' => 'google-shopping-mismatch',
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

        return Task::create($data);
    }

    /**
     * One alert for "the whole live pull failed", so nobody can tell whether SBGT/SBID match.
     *
     * @return array{key: string, cid: string, name: string, kind: string, label: string, text: string}
     */
    public function pullFailedIssue(string $error): array
    {
        $short = mb_strimwidth(trim($error), 0, 200, '…');
        $issue = $this->issue('all', 'All Shopping campaigns', 'pull_failed', 'LBgt / LBid could not be checked',
            $short !== '' ? $short : 'Google Ads live pull failed.');
        // Same failure text repeats every 10 minutes: alert once per cooldown, not per message variant.
        $issue['key'] = 'all|pull_failed';

        return $issue;
    }

    /**
     * @return array{key: string, cid: string, name: string, kind: string, label: string, text: string}
     */
    private function issue(string $cid, string $name, string $kind, string $label, string $text): array
    {
        return [
            'key' => $cid.'|'.$kind.'|'.md5($label.'|'.$text),
            'cid' => $cid,
            'name' => $name,
            'kind' => $kind,
            'label' => $label,
            'text' => $text,
        ];
    }

    /**
     * @param  array<string, mixed>  $target
     * @param  array<string, mixed>  $rule
     */
    private function zeroSbgtReason(array $target, array $rule): string
    {
        $inv = is_numeric($target['inventory'] ?? null) ? (float) $target['inventory'] : null;
        $price = is_numeric($target['price'] ?? null) ? (float) $target['price'] : null;
        $dil = is_numeric($target['dil'] ?? null) ? (float) $target['dil'] : null;

        $pauseInv = (bool) ($rule['pause_if_inv_zero'] ?? true);
        $priceBelow = (float) ($rule['pause_price_below'] ?? 0);
        $dilAbove = (float) ($rule['pause_dil_above'] ?? 0);

        if ($pauseInv && $inv !== null && $inv <= 0) {
            return 'INV is 0 (no stock, or the campaign name does not match any product family in the product master).';
        }
        if ($priceBelow > 0 && $price !== null && $price > 0 && $price < $priceBelow) {
            return 'Price $'.self::money($price, 2).' is below the $'.self::money($priceBelow, 2).' pause price.';
        }
        if ($dilAbove > 0 && $dil !== null && $dil > $dilAbove) {
            return 'Dil '.round($dil).'% is above the '.round($dilAbove).'% pause limit.';
        }

        return 'The BGT parts (views, CVR, ACOS, price, Dil) add up to 0.';
    }

    /** @return array<string, mixed> */
    private function sbgtRule(): array
    {
        try {
            if (isset($this->hooks['rule'])) {
                return (array) ($this->hooks['rule'])();
            }

            return (array) (GoogleShoppingCampaignsRawRule::resolvedRule()['sbgt'] ?? []);
        } catch (Throwable) {
            return [];
        }
    }

    private static function cacheKey(string $issueKey): string
    {
        return 'google-shopping:mismatch-alerted:'.md5($issueKey);
    }

    private static function errorSuffix(mixed $error): string
    {
        $e = trim((string) ($error ?? ''));

        return $e === '' ? '' : ' ('.mb_strimwidth($e, 0, 120, '…').')';
    }

    private static function money(float $v, int $decimals = 2): string
    {
        return number_format($v, $decimals, '.', '');
    }

    private static function positive(mixed $v): ?float
    {
        if ($v === null || $v === '' || ! is_numeric($v)) {
            return null;
        }
        $n = (float) $v;

        return is_finite($n) && $n > 0 ? $n : null;
    }

    private static function boolish(mixed $v): bool
    {
        return $v === true || $v === 1 || $v === '1';
    }
}
