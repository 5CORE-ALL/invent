<?php

namespace App\Services\DynamicMessages;

use App\Models\DynamicMessage;
use App\Models\DynamicMessageRule;
use App\Models\User;
use Illuminate\Support\Collection;

class DynamicMessageEngine
{
    /**
     * Evaluate one rule and (unless $dryRun) deliver the messages.
     *
     * @return array{matches: list<array{user_id: int, name: string, title: string, body: string, duplicate: bool}>, created: int, skipped_duplicates: int, no_match: int}
     */
    public function runRule(DynamicMessageRule $rule, bool $dryRun = false): array
    {
        $result = ['matches' => [], 'created' => 0, 'skipped_duplicates' => 0, 'no_match' => 0];

        $def = DynamicMessageEvents::definition($rule->event_key);
        if (! $def) {
            return $result;
        }

        $recipients = $this->recipients($rule);
        if ($recipients->isEmpty()) {
            return $result;
        }

        $contexts = DynamicMessageEvents::resolve($rule->event_key, $recipients);
        $replyAllowed = $rule->allow_reply;

        foreach ($contexts as $ctx) {
            // user scoped context -> only that user; global -> everyone on the list
            $targets = $ctx['user_id'] !== null
                ? $recipients->only([$ctx['user_id']])
                : $recipients;

            if (! $this->conditionPasses($rule, $ctx['vars'])) {
                $result['no_match'] += $targets->count();

                continue;
            }

            foreach ($targets as $user) {
                $vars = $ctx['vars'];
                $title = $this->render($rule->title, $user, $vars);
                $body = $this->render($rule->body, $user, $vars);
                $dedupe = $this->dedupeKey($rule, $def);

                $duplicate = DynamicMessage::query()
                    ->where('rule_id', $rule->id)
                    ->where('recipient_user_id', $user->id)
                    ->where('dedupe_key', $dedupe)
                    ->exists();

                $result['matches'][] = [
                    'user_id' => (int) $user->id,
                    'name' => (string) $user->name,
                    'title' => $title,
                    'body' => $body,
                    'duplicate' => $duplicate,
                ];

                if ($duplicate) {
                    $result['skipped_duplicates']++;

                    continue;
                }
                if ($dryRun) {
                    continue;
                }

                $message = DynamicMessage::firstOrCreate(
                    ['rule_id' => $rule->id, 'recipient_user_id' => $user->id, 'dedupe_key' => $dedupe],
                    [
                        'event_key' => $rule->event_key,
                        'page_label' => $def['page'],
                        'title' => $title,
                        'body' => $body,
                        'context' => $vars,
                        'allow_reply' => $replyAllowed,
                    ]
                );
                $message->wasRecentlyCreated ? $result['created']++ : $result['skipped_duplicates']++;
            }
        }

        if (! $dryRun) {
            $rule->forceFill(['last_run_at' => now()])->saveQuietly();
        }

        return $result;
    }

    /**
     * "Send now" for the manual event: always a fresh delivery (unique key per click).
     */
    public function sendManualNow(DynamicMessageRule $rule): array
    {
        $def = DynamicMessageEvents::definition($rule->event_key);
        $result = ['matches' => [], 'created' => 0, 'skipped_duplicates' => 0, 'no_match' => 0];
        if (! $def) {
            return $result;
        }

        $stamp = 'manual:'.now()->format('YmdHis').':'.bin2hex(random_bytes(3));
        foreach ($this->recipients($rule) as $user) {
            DynamicMessage::create([
                'rule_id' => $rule->id,
                'recipient_user_id' => $user->id,
                'dedupe_key' => $stamp,
                'event_key' => $rule->event_key,
                'page_label' => $def['page'],
                'title' => $this->render($rule->title, $user, []),
                'body' => $this->render($rule->body, $user, []),
                'context' => null,
                'allow_reply' => $rule->allow_reply,
            ]);
            $result['created']++;
        }
        $rule->forceFill(['last_run_at' => now()])->saveQuietly();

        return $result;
    }

    /**
     * One-off message to a single person using a saved rule as the template
     * (Task Summary "Send message" button). Ignores condition and frequency.
     */
    public function sendQuick(DynamicMessageRule $rule, User $user, string $title, string $body, array $vars = []): DynamicMessage
    {
        $def = DynamicMessageEvents::definition($rule->event_key);

        return DynamicMessage::create([
            'rule_id' => $rule->id,
            'recipient_user_id' => $user->id,
            'dedupe_key' => 'quick:'.now()->format('YmdHis').':'.bin2hex(random_bytes(3)),
            'event_key' => $rule->event_key,
            'page_label' => $def['page'] ?? null,
            'title' => mb_substr($title, 0, 190),
            'body' => $body,
            'context' => $vars ?: null,
            'allow_reply' => $rule->allow_reply,
        ]);
    }

    /** Run every active, non-manual rule. @return array<int, array> keyed by rule id */
    public function runAll(bool $dryRun = false): array
    {
        $out = [];
        DynamicMessageRule::query()->where('is_active', true)->orderBy('id')->each(function (DynamicMessageRule $rule) use (&$out, $dryRun) {
            $def = DynamicMessageEvents::definition($rule->event_key);
            if (! $def || ! empty($def['manual'])) {
                return;
            }
            try {
                $out[$rule->id] = $this->runRule($rule, $dryRun);
            } catch (\Throwable $e) {
                report($e); // one broken rule must not block the others
                $out[$rule->id] = ['error' => $e->getMessage()];
            }
        });

        return $out;
    }

    // ---------------------------------------------------------------- helpers

    /** @return Collection<int, User> keyed by id (only existing, active users) */
    public function recipients(DynamicMessageRule|\App\Models\DynamicTaskRule $rule): Collection
    {
        return User::query()
            ->whereIn('id', $rule->recipientIds())
            ->where(function ($q) {
                $q->whereNull('is_active')->orWhere('is_active', 1);
            })
            ->get()
            ->keyBy('id');
    }

    public function conditionPasses(DynamicMessageRule|\App\Models\DynamicTaskRule $rule, array $vars): bool
    {
        if ($rule->metric === null || $rule->metric === '' || $rule->operator === null || $rule->threshold === null) {
            return true;
        }
        $actual = $vars[$rule->metric] ?? null;
        if ($actual === null || ! is_numeric($actual)) {
            return false;
        }
        $actual = (float) $actual;
        $threshold = (float) $rule->threshold;

        return match ($rule->operator) {
            '>' => $actual > $threshold,
            '>=' => $actual >= $threshold,
            '<' => $actual < $threshold,
            '<=' => $actual <= $threshold,
            '=' => abs($actual - $threshold) < 0.005,
            '!=' => abs($actual - $threshold) >= 0.005,
            default => false,
        };
    }

    /** Replace {placeholders}; unknown ones are left untouched so typos are visible. */
    public function render(string $template, User $user, array $vars): string
    {
        $tz = config('dynamic_messages.timezone');
        $name = trim((string) $user->name);
        $all = array_merge([
            'name' => $name,
            'first_name' => $name !== '' ? explode(' ', $name)[0] : '',
            'date' => now($tz)->format('d M Y'),
        ], $vars);

        return preg_replace_callback('/\{([a-z_]+)\}/', function ($m) use ($all) {
            if (! array_key_exists($m[1], $all)) {
                return $m[0];
            }
            $v = $all[$m[1]];

            return $v === null ? '-' : (string) $v;
        }, $template);
    }

    public function dedupeKey(DynamicMessageRule|\App\Models\DynamicTaskRule $rule, array $def): string
    {
        $now = now(config('dynamic_messages.timezone'));

        return match ($rule->frequency) {
            'once' => 'once',
            'weekly' => 'w:'.$now->format('o-W'),
            default => 'd:'.$now->format('Y-m-d'),
        };
    }
}
