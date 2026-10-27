<?php

namespace App\Services\Meetings;

use App\Models\Meeting;
use App\Models\MeetingActionItem;
use App\Models\MeetingRecording;
use App\Models\Task;
use App\Models\User;
use App\Support\OpenAiRequest;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Turns a finished meeting's recordings into: transcript -> AI minutes -> action items -> Tasks.
 *
 * Every participant records their own microphone in the browser, so each recording belongs to
 * exactly one known user. That gives exact speaker attribution without any diarization.
 */
class MeetingMinutesService
{
    /** OpenAI transcription endpoint rejects files above 25 MB. */
    private const MAX_TRANSCRIBE_BYTES = 24 * 1024 * 1024;

    /** Safety cap on transcript characters sent to the minutes model. */
    private const MAX_TRANSCRIPT_CHARS = 300000;

    /**
     * Claim and process a meeting. Safe to call from several workers: only one wins the claim.
     *
     * @param  bool  $force  also retry meetings already marked ready/failed (manual regenerate)
     */
    public function process(Meeting $meeting, bool $force = false): bool
    {
        $allowed = $force
            ? [Meeting::MINUTES_PENDING, Meeting::MINUTES_FAILED, Meeting::MINUTES_READY, Meeting::MINUTES_NO_RECORDING]
            : [Meeting::MINUTES_PENDING];

        $claimed = Meeting::query()
            ->whereKey($meeting->id)
            ->whereIn('minutes_status', $allowed)
            ->update([
                'minutes_status' => Meeting::MINUTES_PROCESSING,
                'minutes_started_at' => now(),
                'minutes_error' => null,
            ]);

        if (! $claimed) {
            return false;
        }

        $meeting->refresh();
        @set_time_limit(0);

        try {
            $this->transcribeRecordings($meeting);
            $transcript = $this->buildTranscript($meeting);

            if ($transcript === '') {
                // A transcription error (quota, outage, missing key) is not "silence": surface it so it can be retried.
                $failedErrors = $meeting->recordings()->where('transcript_status', 'failed')->pluck('transcript_error')->filter()->unique();
                if ($failedErrors->isNotEmpty()) {
                    $meeting->forceFill([
                        'minutes_status' => Meeting::MINUTES_FAILED,
                        'minutes_error' => mb_substr('Transcription failed: '.$failedErrors->implode(' | '), 0, 1000),
                    ])->save();

                    return true;
                }

                $hasAny = $meeting->recordings()->exists();
                $meeting->forceFill([
                    'minutes_status' => Meeting::MINUTES_NO_RECORDING,
                    'minutes_error' => $hasAny
                        ? 'No speech was detected in the recordings, or transcription failed (see recordings below).'
                        : 'No recordings were uploaded for this meeting.',
                    'transcript' => null,
                ])->save();

                return true;
            }

            $attendees = $this->attendees($meeting);
            $minutes = $this->generateMinutes($meeting, $transcript, $attendees);

            DB::transaction(function () use ($meeting, $transcript, $minutes, $attendees) {
                $meeting->forceFill([
                    'transcript' => $transcript,
                    'summary' => $minutes['summary'],
                    'minutes' => [
                        'topics' => $minutes['topics'],
                        'decisions' => $minutes['decisions'],
                        'open_questions' => $minutes['open_questions'],
                    ],
                    'ai_provider' => $minutes['provider'],
                    'minutes_status' => Meeting::MINUTES_READY,
                    'minutes_generated_at' => now(),
                    'minutes_error' => null,
                ])->save();

                // Tasks are generated once. A later regenerate refreshes the text only, so it can
                // never duplicate (or silently replace) tasks people may already be working on.
                if ($meeting->tasks_generated_at === null) {
                    $meeting->actionItems()->delete();
                    foreach ($minutes['action_items'] as $item) {
                        $match = $this->matchAttendee($item['assignee'], $attendees);
                        MeetingActionItem::create([
                            'meeting_id' => $meeting->id,
                            'title' => $item['title'],
                            'description' => $item['description'],
                            'assignee_user_id' => $match?->id,
                            'assignee_name' => $item['assignee'],
                            'assignee_matched' => $match !== null,
                            'priority' => $item['priority'],
                            'due_date' => $item['due_date'],
                        ]);
                    }
                }
            });

            if ($meeting->tasks_generated_at === null) {
                $this->createTasks($meeting);
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('Meeting minutes failed', ['meeting_id' => $meeting->id, 'error' => $e->getMessage()]);
            $meeting->forceFill([
                'minutes_status' => Meeting::MINUTES_FAILED,
                'minutes_error' => mb_substr($e->getMessage(), 0, 1000),
            ])->save();

            return true;
        }
    }

    // ---------------------------------------------------------------------
    // Transcription
    // ---------------------------------------------------------------------

    private function transcribeRecordings(Meeting $meeting): void
    {
        $names = $this->attendees($meeting)->pluck('name')->filter()->implode(', ');
        $prompt = mb_substr('Company meeting: '.$meeting->title.'. Participants: '.$names.'.', 0, 600);

        $meeting->recordings()->get()->each(function (MeetingRecording $rec) use ($prompt) {
            if ($rec->transcript_status === 'done' || $rec->transcript_status === 'empty') {
                return;
            }

            try {
                $segments = $this->transcribeFile($rec, $prompt);
                $rec->forceFill([
                    'transcript_segments' => $segments,
                    'transcript_status' => $segments === [] ? 'empty' : 'done',
                    'transcript_error' => null,
                ])->save();
            } catch (\Throwable $e) {
                Log::warning('Meeting recording transcription failed', ['recording_id' => $rec->id, 'error' => $e->getMessage()]);
                $rec->forceFill([
                    'transcript_status' => 'failed',
                    'transcript_error' => mb_substr($e->getMessage(), 0, 1000),
                ])->save();
            }
        });
    }

    /**
     * @return list<array{start: float, end: float, text: string}>
     */
    private function transcribeFile(MeetingRecording $rec, string $prompt): array
    {
        if (! $rec->path || $rec->size_bytes < 2048) {
            return [];
        }

        $disk = Storage::disk(config('services.meetings.disk', 'local'));
        if (! $disk->exists($rec->path)) {
            throw new \RuntimeException('Recording file is missing from storage.');
        }
        $absolute = $disk->path($rec->path);

        $parts = [['path' => $absolute, 'offset' => 0.0, 'cleanup' => false]];
        if (filesize($absolute) > self::MAX_TRANSCRIBE_BYTES) {
            $parts = $this->splitLargeFile($absolute);
        }

        $segments = [];
        foreach ($parts as $part) {
            try {
                foreach ($this->callTranscription($part['path'], $prompt) as $seg) {
                    $segments[] = [
                        'start' => round($seg['start'] + $part['offset'], 2),
                        'end' => round($seg['end'] + $part['offset'], 2),
                        'text' => $seg['text'],
                    ];
                }
            } finally {
                if ($part['cleanup'] && is_file($part['path'])) {
                    @unlink($part['path']);
                }
            }
        }
        if ($parts[0]['cleanup'] ?? false) {
            @rmdir(dirname($parts[0]['path']));
        }

        return $segments;
    }

    /**
     * @return list<array{path: string, offset: float, cleanup: bool}>
     */
    private function splitLargeFile(string $absolute): array
    {
        $ffmpeg = (new ExecutableFinder)->find('ffmpeg');
        if ($ffmpeg === null) {
            throw new \RuntimeException('Recording is larger than 24 MB and ffmpeg is not installed on the server to split it.');
        }

        $segmentSeconds = 1200;
        $dir = sys_get_temp_dir().'/meeting_split_'.bin2hex(random_bytes(6));
        mkdir($dir, 0775, true);

        $process = new Process([
            $ffmpeg, '-hide_banner', '-loglevel', 'error', '-i', $absolute,
            '-vn', '-c:a', 'libopus', '-b:a', '24k',
            '-f', 'segment', '-segment_time', (string) $segmentSeconds,
            $dir.'/part_%03d.webm',
        ]);
        $process->setTimeout(1800);
        $process->run();
        if (! $process->isSuccessful()) {
            throw new \RuntimeException('ffmpeg could not split the recording: '.trim($process->getErrorOutput()));
        }

        $files = glob($dir.'/part_*.webm') ?: [];
        sort($files);
        $parts = [];
        foreach ($files as $i => $file) {
            $parts[] = ['path' => $file, 'offset' => (float) ($i * $segmentSeconds), 'cleanup' => true];
        }

        if ($parts === []) {
            @rmdir($dir);
            throw new \RuntimeException('ffmpeg produced no output while splitting the recording.');
        }

        return $parts;
    }

    /**
     * @return list<array{start: float, end: float, text: string}>
     */
    private function callTranscription(string $path, string $prompt): array
    {
        $key = config('services.openai.key');
        if (! is_string($key) || $key === '') {
            throw new \RuntimeException('OPENAI_API_KEY is not configured, so recordings cannot be transcribed.');
        }

        $model = (string) config('services.meetings.transcribe_model', 'whisper-1');
        $verbose = str_starts_with($model, 'whisper');

        $headers = OpenAiRequest::authHeaders();
        unset($headers['Content-Type']); // multipart sets its own

        $handle = fopen($path, 'r');
        try {
            $response = Http::withHeaders($headers)
                ->timeout(900)
                ->attach('file', $handle, basename($path))
                ->post('https://api.openai.com/v1/audio/transcriptions', array_filter([
                    'model' => $model,
                    'response_format' => $verbose ? 'verbose_json' : 'json',
                    'prompt' => $prompt,
                    'temperature' => 0,
                ], static fn ($v) => $v !== null));
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }

        if (! $response->successful()) {
            throw new \RuntimeException('Transcription failed ('.$response->status().'): '.($response->json('error.message') ?? 'unknown error'));
        }

        $out = [];
        $rawSegments = $response->json('segments');
        if (is_array($rawSegments)) {
            foreach ($rawSegments as $seg) {
                $text = trim((string) ($seg['text'] ?? ''));
                if ($text === '') {
                    continue;
                }
                // Whisper invents text ("Thank you.") over silence / a muted mic: drop those segments.
                $noSpeech = (float) ($seg['no_speech_prob'] ?? 0);
                $logprob = (float) ($seg['avg_logprob'] ?? 0);
                $compression = (float) ($seg['compression_ratio'] ?? 0);
                if (($noSpeech > 0.6 && $logprob < -0.7) || $compression > 2.4) {
                    continue;
                }
                $out[] = [
                    'start' => (float) ($seg['start'] ?? 0),
                    'end' => (float) ($seg['end'] ?? 0),
                    'text' => $text,
                ];
            }

            return $out;
        }

        $text = trim((string) $response->json('text'));

        return $text === '' ? [] : [['start' => 0.0, 'end' => 0.0, 'text' => $text]];
    }

    // ---------------------------------------------------------------------
    // Transcript
    // ---------------------------------------------------------------------

    private function buildTranscript(Meeting $meeting): string
    {
        $lines = [];
        $meeting->recordings()->with('user:id,name')->get()->each(function (MeetingRecording $rec) use (&$lines) {
            if (! $rec->started_at || ! is_array($rec->transcript_segments)) {
                return;
            }
            $speaker = $rec->user?->name ?: 'Unknown';
            foreach ($rec->transcript_segments as $seg) {
                $lines[] = [
                    'at' => $rec->started_at->copy()->addMilliseconds((int) round(((float) $seg['start']) * 1000)),
                    'speaker' => $speaker,
                    'text' => (string) $seg['text'],
                ];
            }
        });

        if ($lines === []) {
            return '';
        }

        usort($lines, fn ($a, $b) => $a['at'] <=> $b['at']);

        $base = $meeting->started_at ?? $lines[0]['at'];
        $merged = [];
        foreach ($lines as $line) {
            $last = $merged ? $merged[array_key_last($merged)] : null;
            if ($last && $last['speaker'] === $line['speaker'] && $last['at']->diffInSeconds($line['at']) <= 3) {
                $merged[array_key_last($merged)]['text'] .= ' '.$line['text'];

                continue;
            }
            $merged[] = $line;
        }

        return collect($merged)->map(function (array $l) use ($base) {
            $sec = max(0, (int) $base->diffInSeconds($l['at'], false));

            return sprintf('[%02d:%02d:%02d] %s: %s', intdiv($sec, 3600), intdiv($sec % 3600, 60), $sec % 60, $l['speaker'], $l['text']);
        })->implode("\n");
    }

    // ---------------------------------------------------------------------
    // AI minutes
    // ---------------------------------------------------------------------

    /**
     * @return Collection<int, User>
     */
    private function attendees(Meeting $meeting): Collection
    {
        $ids = $meeting->participants()->pluck('user_id')->push($meeting->host_user_id)->unique()->all();

        return User::query()->whereIn('id', $ids)->get(['id', 'name', 'email']);
    }

    /**
     * @param  Collection<int, User>  $attendees
     * @return array{summary: string, topics: list<array{title: string, points: list<string>}>, decisions: list<string>, open_questions: list<string>, action_items: list<array{title: string, description: ?string, assignee: ?string, priority: string, due_date: ?string}>, provider: string}
     */
    private function generateMinutes(Meeting $meeting, string $transcript, Collection $attendees): array
    {
        if (mb_strlen($transcript) > self::MAX_TRANSCRIPT_CHARS) {
            $transcript = mb_substr($transcript, 0, self::MAX_TRANSCRIPT_CHARS)."\n[transcript truncated]";
        }

        $system = <<<'PROMPT'
You are the secretary of a company meeting. You get a speaker-labelled transcript (transcribed speech, may contain
transcription errors and mixed Hindi/English) and must write precise, professional minutes IN ENGLISH.

Rules:
- Use ONLY what is in the transcript. Never invent decisions, owners, numbers or deadlines.
- An action item is a concrete job someone committed to or was asked to do. Do not list vague discussion as action items.
- "assignee" must be copied exactly from the ATTENDEES list, or null when the transcript does not make the owner clear.
- "due_date" must be YYYY-MM-DD, resolved relative to the meeting date given, or null when no deadline was mentioned.
- "priority" is one of: low, normal, high (high only if urgency was stated).
- If the transcript is too thin to extract something, return an empty list for it.

Return ONLY a JSON object with exactly these keys:
{
  "summary": "3-6 sentence overview of the meeting",
  "topics": [{"title": "topic", "points": ["key point", "..."]}],
  "decisions": ["decision made", "..."],
  "action_items": [{"title": "short imperative task title", "description": "context from the discussion", "assignee": "Attendee Name or null", "priority": "low|normal|high", "due_date": "YYYY-MM-DD or null"}],
  "open_questions": ["unresolved question or follow-up", "..."]
}
PROMPT;

        $when = ($meeting->started_at ?? $meeting->created_at ?? now());
        $user = "MEETING: {$meeting->title}\n"
            .'AGENDA: '.($meeting->agenda ?: '(none given)')."\n"
            .'MEETING DATE: '.$when->format('l, Y-m-d')."\n"
            .'HOST: '.($meeting->host?->name ?? 'unknown')."\n"
            .'ATTENDEES: '.$attendees->pluck('name')->implode(', ')."\n\n"
            ."TRANSCRIPT:\n".$transcript;

        $ai = $this->callAiJson($system, $user);
        $data = json_decode($ai['text'], true);
        if (! is_array($data)) {
            throw new \RuntimeException('AI returned minutes that could not be parsed.');
        }

        $strings = static fn ($v): array => collect(is_array($v) ? $v : [])
            ->map(fn ($s) => trim((string) (is_array($s) ? json_encode($s) : $s)))
            ->filter()->values()->all();

        $topics = collect($data['topics'] ?? [])->map(fn ($t) => [
            'title' => trim((string) ($t['title'] ?? '')),
            'points' => $strings($t['points'] ?? []),
        ])->filter(fn ($t) => $t['title'] !== '')->values()->all();

        $items = collect($data['action_items'] ?? [])->map(function ($i) {
            $title = trim((string) ($i['title'] ?? ''));
            $priority = strtolower(trim((string) ($i['priority'] ?? 'normal')));
            $due = null;
            if (! empty($i['due_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $i['due_date'])) {
                try {
                    $due = Carbon::parse($i['due_date'])->toDateString();
                } catch (\Throwable) {
                    $due = null;
                }
            }
            $assignee = isset($i['assignee']) ? trim((string) $i['assignee']) : '';

            return [
                'title' => mb_substr($title, 0, 1000),
                'description' => trim((string) ($i['description'] ?? '')) ?: null,
                'assignee' => ($assignee === '' || strtolower($assignee) === 'null') ? null : $assignee,
                'priority' => in_array($priority, ['low', 'normal', 'high'], true) ? $priority : 'normal',
                'due_date' => $due,
            ];
        })->filter(fn ($i) => $i['title'] !== '')->values()->all();

        return [
            'summary' => trim((string) ($data['summary'] ?? '')),
            'topics' => $topics,
            'decisions' => $strings($data['decisions'] ?? []),
            'open_questions' => $strings($data['open_questions'] ?? []),
            'action_items' => $items,
            'provider' => $ai['provider'],
        ];
    }

    /**
     * OpenAI first, Anthropic as fallback (same pattern the rest of the app uses).
     *
     * @return array{text: string, provider: string}
     */
    private function callAiJson(string $system, string $userMsg): array
    {
        $strip = static function (string $t): string {
            $t = trim($t);
            $t = preg_replace('/^```(?:json)?\s*/i', '', $t) ?? $t;
            $t = preg_replace('/\s*```\s*$/i', '', $t) ?? $t;

            return trim($t);
        };

        $errors = [];
        $openAiKey = config('services.openai.key');
        if (is_string($openAiKey) && $openAiKey !== '') {
            try {
                $response = Http::withHeaders(OpenAiRequest::authHeaders())
                    ->timeout(300)
                    ->post('https://api.openai.com/v1/chat/completions', [
                        'model' => (string) config('services.meetings.minutes_model', 'gpt-4o-mini'),
                        'temperature' => 0.2,
                        'response_format' => ['type' => 'json_object'],
                        'messages' => [
                            ['role' => 'system', 'content' => $system],
                            ['role' => 'user', 'content' => $userMsg],
                        ],
                    ]);
                if ($response->successful()) {
                    $text = $strip((string) $response->json('choices.0.message.content'));
                    if ($text !== '') {
                        return ['text' => $text, 'provider' => 'openai'];
                    }
                    $errors[] = 'OpenAI returned an empty response.';
                } else {
                    $errors[] = 'OpenAI '.$response->status().': '.($response->json('error.message') ?? '');
                }
            } catch (\Throwable $e) {
                $errors[] = 'OpenAI exception: '.$e->getMessage();
            }
        } else {
            $errors[] = 'OpenAI key not configured.';
        }

        $claudeKey = config('services.anthropic.key');
        if (is_string($claudeKey) && $claudeKey !== '') {
            try {
                $response = Http::withHeaders([
                    'x-api-key' => $claudeKey,
                    'anthropic-version' => (string) config('services.anthropic.version', '2023-06-01'),
                    'content-type' => 'application/json',
                ])->timeout(300)->post('https://api.anthropic.com/v1/messages', [
                    'model' => (string) config('services.anthropic.model', 'claude-haiku-4-5-20251001'),
                    'max_tokens' => 4096,
                    'temperature' => 0.2,
                    'system' => $system,
                    'messages' => [['role' => 'user', 'content' => $userMsg]],
                ]);
                if ($response->successful()) {
                    $text = $strip((string) $response->json('content.0.text'));
                    if ($text !== '') {
                        return ['text' => $text, 'provider' => 'anthropic'];
                    }
                    $errors[] = 'Claude returned an empty response.';
                } else {
                    $errors[] = 'Claude '.$response->status().': '.($response->json('error.message') ?? '');
                }
            } catch (\Throwable $e) {
                $errors[] = 'Claude exception: '.$e->getMessage();
            }
        }

        throw new \RuntimeException('No AI provider could write the minutes. '.implode(' | ', $errors));
    }

    // ---------------------------------------------------------------------
    // Assignee matching + task creation
    // ---------------------------------------------------------------------

    /**
     * @param  Collection<int, User>  $attendees
     */
    private function matchAttendee(?string $name, Collection $attendees): ?User
    {
        $needle = $this->norm((string) $name);
        if ($needle === '') {
            return null;
        }

        $exact = $attendees->first(fn (User $u) => $this->norm($u->name) === $needle);
        if ($exact) {
            return $exact;
        }

        // Fall back to a unique first-name / partial match among attendees only.
        $partial = $attendees->filter(function (User $u) use ($needle) {
            $n = $this->norm($u->name);
            $first = explode(' ', $n)[0] ?? '';

            return $n !== '' && ($first === $needle || str_contains($n, $needle) || str_contains($needle, $n));
        });

        return $partial->count() === 1 ? $partial->first() : null;
    }

    private function norm(string $s): string
    {
        return trim(preg_replace('/\s+/', ' ', mb_strtolower($s)) ?? '');
    }

    /**
     * Create Task Manager tasks for the action items. Items without a clear owner go to the host
     * so nothing is lost; the description says so.
     */
    private function createTasks(Meeting $meeting): void
    {
        $host = $meeting->host;
        $group = mb_substr('Meeting: '.$meeting->title, 0, 255);
        $minutesUrl = route('meetings.minutes.show', $meeting);
        $autoIncrement = $this->tasksIdAutoIncrements();

        foreach ($meeting->actionItems()->whereNull('task_id')->get() as $item) {
            try {
                $assignee = $item->assignee_user_id ? User::find($item->assignee_user_id) : null;
                $owner = $assignee ?: $host;
                if (! $owner || ! $owner->email) {
                    continue;
                }

                $note = $assignee
                    ? ''
                    : "\n\n(No clear owner was stated in the meeting"
                        .($item->assignee_name ? ' — AI heard "'.$item->assignee_name.'"' : '')
                        .', so this was assigned to the meeting host. Please reassign if needed.)';

                $start = now();
                $due = $item->due_date ? Carbon::parse($item->due_date)->endOfDay() : $start->copy()->addDays(5);
                if ($due->lt($start)) {
                    $due = $start->copy()->addDays(5);
                }

                $task = new Task([
                    'title' => $item->title,
                    'description' => trim(($item->description ?? '')
                        ."\n\nGenerated from meeting \"{$meeting->title}\" (".($meeting->started_at ?? $meeting->created_at)->format('d M Y').").\nMinutes: {$minutesUrl}".$note),
                    'group' => $group,
                    'priority' => $item->priority,
                    'assignor' => $host?->email ?: $owner->email,
                    'assign_to' => $owner->email,
                    'split_tasks' => 0,
                    'parent_task_id' => null,
                    'subtask_order' => 0,
                    'status' => 'Todo',
                    'eta_time' => 10,
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
                    'link1' => '', 'link2' => '', 'link3' => '', 'link4' => '', 'link5' => '',
                    'link6' => '', 'link7' => '', 'link8' => '', 'link9' => '',
                    'is_data_from' => 0,
                    'is_automate_task' => 0,
                    'task_type' => 'manual',
                    'rework_reason' => '',
                    'delete_rating' => 0,
                    'delete_feedback' => '',
                ]);

                if (! $autoIncrement) {
                    $max = (int) (DB::table('tasks')->max('id') ?? 0);
                    $task->incrementing = false;
                    $task->id = $max + 1;
                }
                $task->save();

                $item->forceFill(['task_id' => $task->id])->save();
            } catch (\Throwable $e) {
                Log::error('Meeting task creation failed', ['meeting_id' => $meeting->id, 'item_id' => $item->id, 'error' => $e->getMessage()]);
            }
        }

        $meeting->forceFill(['tasks_generated_at' => now()])->save();
    }

    private function tasksIdAutoIncrements(): bool
    {
        try {
            $col = DB::selectOne("SHOW COLUMNS FROM `tasks` WHERE Field = 'id'");

            return str_contains(strtolower((string) ($col->Extra ?? '')), 'auto_increment');
        } catch (\Throwable) {
            return true;
        }
    }
}
