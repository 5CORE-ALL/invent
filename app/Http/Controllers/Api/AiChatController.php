<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\EscalationMail;
use App\Models\AiEscalation;
use App\Models\AiKnowledgeBase;
use App\Models\AiKnowledgeFile;
use App\Models\AiQuestion;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AiChatController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if ($request->user() && !$request->user()->is5CoreMember()) {
                return response()->json(['error' => 'Access denied. Only 5Core members can use this assistant.'], 403);
            }
            return $next($request);
        });
    }


    /**
     * Internal support agent: KB first, then OpenAI; if OpenAI is not confident or fails, escalate to senior.
     */
    public function chat(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'question' => ['required', 'string', 'max:8000'],
            ]);

            $question = $this->sanitizeInput($validated['question']);
            $user = $request->user();

            Log::info('🚀 CHAT REQUEST', ['user' => $user->email, 'question' => $question]);
            Log::info('🌐 ENV CHECK', ['APP_ENV' => config('app.env'), 'APP_URL' => config('app.url')]);

            // Task assign feature commented out
            // $taskResponse = $this->handleTaskQuery($question, $user);
            // if ($taskResponse) {
            //     return $taskResponse;
            // }

            Log::info('🔍 KB SEARCH', ['question' => $question]);
            Log::info('📊 KB COUNT', ['count' => \Illuminate\Support\Facades\Schema::hasTable('ai_knowledge_base') ? AiKnowledgeBase::count() : 0]);
            $kbMatch = $this->searchKnowledgeBase($question);
            Log::info('📚 KB RESULT', ['found' => $kbMatch ? 'YES' : 'NO']);

            if ($kbMatch) {
                $record = null;
                $answer = $this->formatAnswerSteps($kbMatch->answer_steps, $kbMatch->video_link);

                try {
                    if (\Illuminate\Support\Facades\Schema::hasTable('ai_questions')) {
                        $record = AiQuestion::create([
                            'user_id' => $user->id,
                            'question' => $question,
                            'ai_answer' => $answer,
                        ]);
                    }
                } catch (\Exception $e) {
                }

                return response()->json([
                    'answer' => $answer,
                    'id' => $record?->id,
                    'status' => 'answered',
                ]);
            }

            Log::info('🤖 CALLING CLAUDE', ['question' => $question]);
            $claudeResult = $this->callClaude($question);
            Log::info('✅ CLAUDE RESPONSE', ['confident' => $claudeResult['confident'] ?? false]);

            if ($claudeResult && $claudeResult['confident']) {
                $answer = $claudeResult['answer'];
                $record = null;
                try {
                    if (\Illuminate\Support\Facades\Schema::hasTable('ai_questions')) {
                        $record = AiQuestion::create([
                            'user_id' => $user->id,
                            'question' => $question,
                            'ai_answer' => $answer,
                        ]);
                    }
                } catch (\Exception $e) {
                }
                return response()->json([
                    'answer' => $answer,
                    'id' => $record?->id,
                    'status' => 'answered',
                ]);
            }

            $domain = $this->detectDomain($question);
            Log::info('⚠️ ESCALATING', ['domain' => $domain]);
            return $this->escalateToSenior($question, $user);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return response()->json([
                'answer' => 'Something went wrong. Please try again.',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Handle task inquiry questions. Returns JsonResponse if it's a task question, null otherwise.
     * TASK ASSIGN FEATURE COMMENTED OUT - body disabled, returns null.
     */
    private function handleTaskQuery(string $question, $user): ?JsonResponse
    {
        return null; // Task assign feature commented out
        /*
        $keywords = [
            'task', 'tasks', 'assign', 'assigned', 'pending', 'assign to',
            'task list', 'my tasks', 'tasks of', 'tasks for', 'working on',
            'how many', 'does', 'have',
        ];
        $q = strtolower(trim($question));
        $hasKeyword = false;
        foreach ($keywords as $kw) {
            if (str_contains($q, $kw)) {
                $hasKeyword = true;
                break;
            }
        }
        if (!$hasKeyword) {
            return null;
        }

        try {
            if (!\Illuminate\Support\Facades\Schema::hasTable('tasks')) {
                return null;
            }

            $targetUser = $this->resolveTargetUserFromQuestion($question, $user);
            if (!$targetUser) {
                $extracted = $this->extractNameFromQuestion($question);
                $answer = $extracted
                    ? "I couldn't find any team member with name \"{$extracted}\". Please check the spelling or try with full name/email."
                    : "I couldn't identify which team member you're asking about. Try: \"Tasks assigned to [name]\" or \"My tasks\".";
                return $this->taskQueryResponse($answer, $user, $question);
            }

            $tasksQuery = $this->buildTasksQueryForUser($targetUser);
            $total = $tasksQuery->count();
            $pendingStatuses = ['pending', 'in_progress', 'Todo', 'Working', 'Need Help', 'Need Approval', 'Dependent', 'Approved', 'Hold', 'Rework'];
            $pendingTasks = (clone $tasksQuery)->whereIn('status', $pendingStatuses)->get();
            $pendingCount = $pendingTasks->count();

            $isCurrentUser = $targetUser->id === $user->id;
            $displayName = $targetUser->name ?: $targetUser->email;

            if ($total === 0) {
                $answer = $isCurrentUser
                    ? "You have no tasks assigned."
                    : "{$displayName} has no tasks assigned.";
                return $this->taskQueryResponse($answer, $user, $question);
            }

            if ($pendingCount === 0) {
                $answer = $isCurrentUser
                    ? "You have no pending tasks. All assigned tasks are completed."
                    : "{$displayName} has no pending tasks. All assigned tasks are completed.";
                return $this->taskQueryResponse($answer, $user, $question);
            }

            $lines = [];
            if ($isCurrentUser) {
                $lines[] = "You have {$pendingCount} pending task" . ($pendingCount !== 1 ? 's' : '') . ":";
            } else {
                $lines[] = "{$displayName} has {$pendingCount} pending task" . ($pendingCount !== 1 ? 's' : '') . " out of {$total} total assigned tasks:";
            }

            $emojiByPriority = ['high' => '🔴', 'normal' => '🔵', 'low' => '🟢'];
            foreach ($pendingTasks->take(20) as $i => $t) {
                $due = $t->due_date ?? $t->start_date ?? $t->tid ?? null;
                $dueStr = $due ? $this->formatDueDate($due) : 'No due date';
                $prio = strtolower($t->priority ?? 'normal');
                $emoji = $emojiByPriority[$prio] ?? '🟡';
                $lines[] = ($i + 1) . ". {$emoji} " . ($t->title ?: 'Untitled') . " (Due: {$dueStr})";
            }
            if ($pendingTasks->count() > 20) {
                $lines[] = '... and ' . ($pendingTasks->count() - 20) . ' more.';
            }

            $statusCounts = (clone $tasksQuery)->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');
            $inProgress = ($statusCounts['in_progress'] ?? 0) + ($statusCounts['Working'] ?? 0);
            $completed = ($statusCounts['completed'] ?? 0) + ($statusCounts['Done'] ?? 0);
            $summary = [];
            if ($pendingCount > 0) $summary[] = "{$pendingCount} pending";
            if ($inProgress > 0) $summary[] = "{$inProgress} in progress";
            if ($completed > 0) $summary[] = "{$completed} completed";
            if (!empty($summary)) {
                $lines[] = '';
                $lines[] = 'Total assigned: ' . $total . ' tasks (' . implode(', ', $summary) . ')';
            }

            $answer = implode("\n", $lines);
            return $this->taskQueryResponse($answer, $user, $question);
        } catch (\Throwable $e) {
            return null;
        }
        */
    }

    private function resolveTargetUserFromQuestion(string $question, $currentUser): ?User
    {
        $q = strtolower(trim($question));
        $myPatterns = [
            'my tasks',
            'my pending tasks',
            'tasks assigned to me',
            'my task list',
            'what are my tasks',
            'show my tasks',
            'my task',
            'show me tasks',
            'show tasks',
        ];
        foreach ($myPatterns as $p) {
            if (str_contains($q, $p)) {
                return $currentUser;
            }
        }

        $name = $this->extractNameFromQuestion($question);
        if ($name !== null && $name !== '') {
            $found = User::where('name', 'like', '%' . $name . '%')
                ->orWhere('email', 'like', '%' . $name . '%')
                ->first();
            return $found;
        }

        if (preg_match('/\b[\w._%+-]+@[\w.-]+\.\w+\b/', $question, $m)) {
            return User::where('email', $m[0])->first();
        }

        return null;
    }

    private function extractNameFromQuestion(string $question): ?string
    {
        $patterns = [
            '/tasks?\s+assigned\s+to\s+([^?.!]+)/i',
            '/tasks?\s+of\s+([^?.!]+)/i',
            '/tasks?\s+for\s+([^?.!]+)/i',
            '/pending\s+tasks?\s+(?:of|for)\s+([^?.!]+)/i',
            '/task\s+list\s+of\s+([^?.!]+)/i',
            '/what\s+is\s+([^?\s]+(?:\s+[^?\s]+)?)\s+working\s+on/i',
            '/how\s+many\s+tasks?\s+(?:does\s+)?([^?]+?)\s+have/i',
            '/show\s+tasks?\s+of\s+([^?.!]+)/i',
        ];
        foreach ($patterns as $pat) {
            if (preg_match($pat, $question, $m)) {
                return trim($m[1], " \t\n\r\0\x0B\"'");
            }
        }
        return null;
    }

    private function buildTasksQueryForUser(User $targetUser)
    {
        if (\Illuminate\Support\Facades\Schema::hasColumn('tasks', 'assignee_id')) {
            return Task::where('assignee_id', $targetUser->id);
        }
        $email = $targetUser->email;
        return Task::where(function ($q) use ($email) {
            $q->where('assign_to', $email)
                ->orWhere('assign_to', 'like', $email . ',%')
                ->orWhere('assign_to', 'like', '%,' . $email)
                ->orWhere('assign_to', 'like', '%,' . $email . ',%');
        });
    }

    private function formatDueDate($date): string
    {
        if (!$date) return 'No due date';
        $d = \Carbon\Carbon::parse($date);
        $tomorrow = \Carbon\Carbon::tomorrow();
        if ($d->isSameDay($tomorrow)) {
            return 'Tomorrow';
        }
        return $d->format('Y-m-d');
    }

    private function taskQueryResponse(string $answer, $user, string $question): JsonResponse
    {
        $record = null;
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('ai_questions')) {
                $record = AiQuestion::create([
                    'user_id' => $user->id,
                    'question' => $question,
                    'ai_answer' => $answer,
                ]);
            }
        } catch (\Exception $e) {
        }
        return response()->json([
            'answer' => $answer,
            'id' => $record?->id,
            'status' => 'answered',
        ]);
    }

    /** Minimum score to accept a KB match; avoids false matches from common/short words. */
    private const KNOWLEDGE_BASE_MIN_SCORE = 5;

    /** Common words to skip when scoring (reduce false matches like "where to manage things" → Task). */
    private const KB_STOP_WORDS = [
        'where',
        'what',
        'how',
        'to',
        'the',
        'a',
        'an',
        'is',
        'are',
        'can',
        'i',
        'you',
        'do',
        'does',
        'for',
        'with',
        'at',
        'in',
        'on',
        'by',
        'from',
        'of',
        'and',
        'or',
    ];

    /**
     * Search KB by question. Remove fake login/Google entries via tinker:
     * \App\Models\AiKnowledgeBase::where('category', 'Login')->orWhere('question_pattern', 'like', '%Google%')->delete();
     */
    private function searchKnowledgeBase(string $question): ?AiKnowledgeBase
    {
        try {
            $qLower = strtolower(trim($question));
            $allWords = preg_split('/\s+/', $qLower, -1, PREG_SPLIT_NO_EMPTY);
            $stopWords = array_fill_keys(array_map('strtolower', self::KB_STOP_WORDS), true);
            // Only score words that are at least 3 chars and not stop words
            $words = array_values(array_filter($allWords, function ($w) use ($stopWords) {
                return strlen($w) >= 3 && !isset($stopWords[$w]);
            }));

            if (empty($words)) {
                Log::debug('KB SEARCH: no significant words after filter', ['question' => $question]);
                return null;
            }

            if (!\Illuminate\Support\Facades\Schema::hasTable('ai_knowledge_base')) {
                return null;
            }

            $entries = AiKnowledgeBase::all();
            if ($entries->isEmpty()) {
                return null;
            }

            $best = null;
            $bestScore = 0;

            foreach ($entries as $entry) {
                $pattern = strtolower(trim($entry->question_pattern ?? ''));
                $tags = $entry->tags ?? [];
                $tags = is_array($tags) ? array_map('strtolower', array_filter($tags, 'is_string')) : [];
                $category = strtolower(trim($entry->category ?? ''));
                $subcategory = strtolower(trim($entry->subcategory ?? ''));

                $wordScore = 0;
                foreach ($words as $word) {
                    if (str_contains($pattern, $word)) {
                        $wordScore += 5; // pattern match (increased so product/address entries rank higher)
                        continue;
                    }
                    foreach ($tags as $tag) {
                        if (str_contains($tag, $word) || str_contains($word, $tag)) {
                            $wordScore += 2; // tag partial match
                            break;
                        }
                    }
                }

                $tagFullScore = 0;
                foreach ($tags as $tag) {
                    if ($tag === '' || strlen($tag) < 3) continue;
                    if (str_contains($qLower, $tag)) {
                        $tagFullScore += 3;
                    }
                }
                $tagFullScore = min(9, $tagFullScore);

                $similarityScore = 0;
                if ($pattern !== '') {
                    similar_text($qLower, $pattern, $percent);
                    $similarityScore = min(5, (int) round((float) $percent / 20));
                }

                $categoryScore = 0;
                if ($category !== '' && str_contains($qLower, $category)) $categoryScore += 1;
                if ($subcategory !== '' && str_contains($qLower, $subcategory)) $categoryScore += 1;

                $score = $wordScore + $tagFullScore + $similarityScore + $categoryScore;

                // Boost Website category entries
                if ($category === 'website') {
                    $score += 2;
                }

                // Penalize login/Google-like entries so "products" / "address" queries match product/address entries
                $entryText = $pattern . ' ' . implode(' ', $tags);
                if (str_contains($entryText, 'login') || str_contains($entryText, 'google')) {
                    $score -= 4;
                }

                Log::debug('KB ENTRY SCORE', [
                    'question' => $question,
                    'pattern' => $entry->question_pattern,
                    'category' => $entry->category,
                    'score' => $score,
                    'wordScore' => $wordScore,
                    'tagFullScore' => $tagFullScore,
                    'similarityScore' => $similarityScore,
                    'categoryScore' => $categoryScore,
                ]);

                if ($score > $bestScore && $score >= self::KNOWLEDGE_BASE_MIN_SCORE) {
                    $bestScore = $score;
                    $best = $entry;
                }
            }

            if ($best !== null) {
                Log::debug('KB BEST MATCH', [
                    'question' => $question,
                    'pattern' => $best->question_pattern,
                    'category' => $best->category,
                    'score' => $bestScore,
                ]);
            } else {
                Log::debug('KB NO MATCH', ['question' => $question, 'minScore' => self::KNOWLEDGE_BASE_MIN_SCORE]);
            }

            return $best;
        } catch (\Throwable $e) {
            Log::debug('KB SEARCH ERROR', ['message' => $e->getMessage()]);
            return null;
        }
    }

    private function formatAnswerSteps($answerSteps, ?string $videoLink = null, ?string $sourceUrl = null): string
    {
        $steps = is_array($answerSteps) ? $answerSteps : (is_string($answerSteps) ? json_decode($answerSteps, true) : []);

        if (!is_array($steps)) {
            $steps = [$steps];
        }

        $formattedSteps = [];
        foreach ($steps as $index => $step) {
            $cleanStep = preg_replace('/^\d+\.\s*/', '', trim($step));
            $formattedSteps[] = ($index + 1) . '. ' . $cleanStep;
        }

        $out = implode("\n", $formattedSteps);

        if ($videoLink) {
            $out .= "\n\n📹 Video tutorial: " . $videoLink;
        }

        // Add source URL if available (for website entries)
        if ($sourceUrl) {
            $out .= "\n\n🔗 Source: " . $sourceUrl;
        }

        return $out;
    }

    private function detectDomain(string $question): string
    {
        $q = strtolower($question);
        $domains = config('services.5core.domains', []);

        foreach ($domains as $domain => $config) {
            if ($domain === 'General') {
                continue;
            }
            $keywords = $config['keywords'] ?? [];
            foreach ($keywords as $kw) {
                if (str_contains($q, strtolower($kw))) {
                    return $domain;
                }
            }
        }

        return 'General';
    }

    /** All escalations use the single senior email (president@5core.com). */
    private function getSeniorEmailByDomain(string $domain): string
    {
        return 'president@5core.com';
    }

    private const CLAUDE_SYSTEM_PROMPT = "You are 5Core AI Assistant, an internal support agent for 5Core team members. "
        . "ABOUT 5CORE: 5Core is a company that provides integrated business solutions. "
        . "We have products across audio equipment (speakers, microphones, stands), e-commerce platforms, and business management tools. "
        . "IMPORTANT: If you don't know the answer, say 'I don't have this information.' and the system will escalate to a senior team member.";

    /**
     * Call OpenAI Chat API. Returns ['answer' => string, 'confident' => bool] or null on failure.
     * Confident is false if answer indicates uncertainty (so we escalate).
     */

    private function callClaude(string $question): ?array
    {
        $apiKey = config('services.anthropic.key');
        Log::info('🔑 CLAUDE API KEY', [
            'exists' => !empty($apiKey),
            'prefix' => $apiKey ? (substr($apiKey, 0, 10) . '...') : '',
        ]);
        if (empty($apiKey)) {
            return null;
        }

        try {
            $response = Http::timeout(60)
                ->withHeaders([
                    'x-api-key' => $apiKey,
                    'anthropic-version' => '2023-06-01',
                    'content-type' => 'application/json',
                ])
                ->post('https://api.anthropic.com/v1/messages', [
                    'model' => 'claude-3-haiku-20240307', // Fast & cheap
                    'max_tokens' => 1024,
                    'temperature' => 0.5,
                    'system' => self::CLAUDE_SYSTEM_PROMPT,
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => [
                                [
                                    'type' => 'text',
                                    'text' => $question
                                ]
                            ]
                        ]
                    ],
                ]);

            Log::info('📡 CLAUDE HTTP RESPONSE', ['status' => $response->status()]);
            if (!$response->successful()) {
                Log::error('❌ CLAUDE API ERROR', ['status' => $response->status(), 'body' => $response->body()]);
                return null;
            }

            $data = $response->json();

            // Claude ka response format alag hota hai
            $answer = '';
            if (isset($data['content'][0]['text'])) {
                $answer = trim($data['content'][0]['text']);
            } elseif (isset($data['content'][0])) {
                $answer = trim($data['content'][0]);
            }

            if ($answer === '') {
                return null;
            }

            $confident = !$this->isAnswerUncertain($answer);

            return [
                'answer' => $answer,
                'confident' => $confident
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }


    private function isAnswerUncertain(string $answer): bool
    {
        $lower = strtolower($answer);

        // ✅ GREETINGS ARE CONFIDENT
        $greetings = ['hi', 'hello', 'hey', 'thanks', 'thank you', 'good morning', 'good afternoon', 'good evening'];
        foreach ($greetings as $greeting) {
            if (trim($lower) === $greeting || str_starts_with($lower, $greeting . ' ') || str_contains($lower, ' ' . $greeting . ' ')) {
                return false;
            }
        }

        // ❌ UNCERTAIN PHRASES - UPDATED WITH CLAUDE'S PHRASES
        $phrases = [
            // Direct uncertainty
            "i don't know",
            "i do not know",
            "i'm not sure",
            "i am not sure",
            "i cannot",
            "i can't",
            "cannot answer",
            "can't answer",
            "not able to answer",

            // Information not available
            "i don't have this information",
            "i do not have this information",
            "i don't have that information",
            "i don't have enough information",
            "i do not have enough information",
            "don't have specific information",
            "don't have detailed knowledge",
            "don't have the necessary information",
            "don't have any information",

            // Polite uncertainty - CLAUDE STYLE
            "i'm afraid i don't",
            "i am afraid i don't",
            "i'm afraid i cannot",
            "i am afraid i cannot",
            "i'm afraid i can't",

            // Scope related
            "outside 5core scope",
            "outside of 5core",
            "not within 5core",
            "beyond my scope",
            "not in my knowledge base",
            "i don't have specific information about",
            "i don't have information on that",

            // Recommendations to ask others
            "recommend checking with your it support",
            "consult your it team",
            "ask your system administrator",
            "contact your network administrator",
            "check with your technical support",

            // Lack of capability
            "i am not aware",
            "i'm not aware",
            "not familiar with",
            "haven't been trained on",
        ];

        foreach ($phrases as $phrase) {
            if (str_contains($lower, $phrase)) {
                return true;
            }
        }

        if (
            str_contains($lower, "i don't") &&
            (str_contains($lower, "information") || str_contains($lower, "knowledge") || str_contains($lower, "specifics"))
        ) {
            return true;
        }

        return false; // Confident
    }

    private function escalateToSenior(string $question, $user): JsonResponse
    {
        try {
            $domain = $this->detectDomain($question);
            $seniorEmail = $this->getSeniorEmailByDomain($domain);

            $escalation = AiEscalation::create([
                'user_id' => $user->id,
                'original_question' => $question,
                'domain' => $domain,
                'assigned_senior_email' => $seniorEmail,
                'status' => 'pending',
            ]);

            $baseUrl = rtrim(config('services.5core.escalation_reply_url', 'https://inventory.5coremanagement.com'), '/');
            $replyLink = $baseUrl . '/ai/escalation/' . $escalation->id . '/reply';

            Log::info('📧 Sending escalation email', ['to' => $seniorEmail, 'escalation_id' => $escalation->id, 'link' => $replyLink]);

            try {
                Mail::to($seniorEmail)->send(new EscalationMail(
                    $user->name ?? $user->email,
                    $user->email,
                    $question,
                    $escalation->id,
                    $domain,
                    $replyLink
                ));
                Log::info('📧 AI escalation: mail sent successfully', ['to' => $seniorEmail]);
            } catch (\Exception $e) {
                Log::warning('Escalation email send failed', ['to' => $seniorEmail, 'error' => $e->getMessage()]);
            }

            $answer = "I don't have this information.";

            return response()->json([
                'answer' => $answer,
                'status' => 'escalated',
                'escalation_id' => $escalation->id,
            ]);
        } catch (\Throwable $e) {
            throw $e;
        }
    }

    public function uploadKnowledge(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ]);

        $file = $request->file('file');
        $path = $file->store('ai_knowledge', 'local');
        $originalName = $file->getClientOriginalName();

        $record = AiKnowledgeFile::create([
            'filename' => basename($path),
            'original_name' => $originalName,
            'file_path' => $path,
            'status' => 'pending',
        ]);

        try {
            $this->processCSVTraining(storage_path('app/' . $path), $record->id);
            $record->update(['status' => 'processed', 'processed_at' => now()]);

            return response()->json(['success' => true, 'message' => 'File uploaded and processed successfully.']);
        } catch (\Throwable $e) {
            $record->update(['status' => 'failed']);

            return response()->json([
                'success' => false,
                'message' => 'File processing failed. Please check the CSV format.',
                'debug' => app()->environment('local') ? $e->getMessage() : null
            ], 422);
        }
    }

    private function processCSVTraining(string $filePath, int $fileId): void
    {
        $content = file_get_contents($filePath);
        $bom = "\xef\xbb\xbf";
        if (substr($content, 0, 3) === $bom) {
            $content = substr($content, 3);
            file_put_contents($filePath, $content);
        }
        $content = str_replace("\0", '', $content);
        file_put_contents($filePath, $content);

        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new \RuntimeException('Could not open file.');
        }

        // 🔥 FIX: Skip empty lines
        $header = null;
        while (($row = fgetcsv($handle)) !== false) {
            if ($row === [null] || empty(array_filter($row))) {
                continue; // Skip empty lines
            }
            $header = $row;
            break;
        }

        if (!$header) {
            fclose($handle);
            throw new \RuntimeException('CSV file is empty or has no header row.');
        }

        $expected = ['category', 'subcategory', 'question_pattern', 'answer_steps', 'video_link', 'tags'];
        $header = array_map('trim', $header);
        $map = [];

        foreach ($expected as $col) {
            $idx = array_search($col, $header);
            if ($idx !== false) {
                $map[$col] = $idx;
            }
        }

        if (!isset($map['question_pattern'])) {
            fclose($handle);
            throw new \RuntimeException('CSV must have a question_pattern column.');
        }

        while (($row = fgetcsv($handle)) !== false) {
            try {
                $category = isset($map['category']) ? trim($row[$map['category']] ?? '') : 'General';
                $subcategory = isset($map['subcategory']) ? trim($row[$map['subcategory']] ?? '') : null;
                $question_pattern = trim($row[$map['question_pattern']] ?? '');

                if ($question_pattern === '') {
                    continue;
                }

                $answer_steps_raw = isset($map['answer_steps']) ? ($row[$map['answer_steps']] ?? '[]') : '[]';
                $video_link = isset($map['video_link']) ? trim($row[$map['video_link']] ?? '') : null;
                $tags_raw = isset($map['tags']) ? ($row[$map['tags']] ?? '[]') : '[]';

                $decoded = json_decode($answer_steps_raw, true);
                $answer_steps = ($decoded === null && json_last_error() !== JSON_ERROR_NONE) ? [$answer_steps_raw] : (is_array($decoded) ? $decoded : [$answer_steps_raw]);

                $tagsDecoded = json_decode($tags_raw, true);
                $tags = ($tagsDecoded === null && json_last_error() !== JSON_ERROR_NONE) ? (array) $tags_raw : (is_array($tagsDecoded) ? $tagsDecoded : (array) $tags_raw);

                AiKnowledgeBase::create([
                    'category' => $category ?: 'General',
                    'subcategory' => $subcategory ?: null,
                    'question_pattern' => $question_pattern,
                    'answer_steps' => $answer_steps,
                    'video_link' => $video_link ?: null,
                    'tags' => $tags,
                ]);
            } catch (\Throwable $e) {
                throw $e;
            }
        }

        fclose($handle);
    }
    public function checkNotifications(Request $request): JsonResponse
    {
        $user = $request->user();
        $count = AiEscalation::where('user_id', $user->id)
            ->where('status', 'answered')
            ->whereNull('junior_read_at')
            ->count();
        return response()->json(['count' => $count]);
    }

    public function getPendingReplies(Request $request): JsonResponse
    {
        $user = $request->user();
        $escalations = AiEscalation::where('user_id', $user->id)
            ->where('status', 'answered')
            ->whereNotNull('senior_reply')
            ->whereNull('junior_read_at')
            ->orderByDesc('answered_at')
            ->limit(20)
            ->get()
            ->map(fn($e) => [
                'id' => $e->id,
                'original_question' => $e->original_question,
                'senior_reply' => $e->senior_reply,
                'answered_at' => $e->answered_at?->toIso8601String(),
            ]);
        return response()->json(['replies' => $escalations]);
    }

    /**
     * Mark escalation replies as read (called after displaying in chat).
     */
    public function markRepliesRead(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        AiEscalation::where('user_id', $request->user()->id)
            ->whereIn('id', $validated['ids'])
            ->where('status', 'answered')
            ->update(['junior_read_at' => now()]);

        return response()->json(['success' => true]);
    }

    /**
     * Download sample CSV for AI training upload format.
     */
    public function downloadSampleCsv()
    {
        $headers = [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="5core-ai-training-sample.csv"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0'
        ];

        $callback = function () {
            $handle = fopen('php://output', 'w');

            // Add UTF-8 BOM for Excel compatibility
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // Headers - EXACT format
            fputcsv($handle, ['category', 'subcategory', 'question_pattern', 'answer_steps', 'video_link', 'tags']);

            // Sample Row 1: Task
            fputcsv($handle, [
                'Task',
                'Assignment',
                'how to assign task',
                '["1. Navigate to Tasks section", "2. Click Create New Task", "3. Fill details", "4. Select assignee", "5. Set due date", "6. Click Assign"]',
                'https://5core.com/task-assign-video',
                '["task", "assign", "dashboard"]'
            ]);

            // Sample Row 2: HR - Leave
            fputcsv($handle, [
                'HR',
                'Leave',
                'how to apply for leave',
                '["1. Go to HR Module", "2. Click Apply Leave", "3. Select leave type", "4. Choose dates", "5. Add reason", "6. Submit"]',
                '',
                '["leave", "hr", "attendance"]'
            ]);

            // Sample Row 3: Sales - Invoice
            fputcsv($handle, [
                'Sales',
                'Invoicing',
                'how to create invoice',
                '["1. Open Sales module", "2. Click New Invoice", "3. Add client details", "4. Add products", "5. Calculate totals", "6. Generate PDF"]',
                'https://5core.com/invoice-tutorial',
                '["invoice", "sales", "billing"]'
            ]);

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function feedback(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => ['required', 'integer'],
            'helpful' => ['required', 'boolean'],
        ]);

        $record = AiQuestion::where('id', $validated['id'])
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$record) {
            return response()->json(['success' => false], 404);
        }

        $record->update(['helpful' => $validated['helpful']]);
        return response()->json(['success' => true]);
    }

    private function sanitizeInput(string $value): string
    {
        return strip_tags($value);
    }
}
