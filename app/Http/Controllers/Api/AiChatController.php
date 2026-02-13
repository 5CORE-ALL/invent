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


            // FIX 1: Hardcoded answers for company/product queries
            $hardcodedAnswer = $this->getHardcodedCompanyAnswer($question);
            if ($hardcodedAnswer !== null) {
                $record = null;
                try {
                    if (\Illuminate\Support\Facades\Schema::hasTable('ai_questions')) {
                        $record = AiQuestion::create([
                            'user_id' => $user->id,
                            'question' => $question,
                            'ai_answer' => $hardcodedAnswer,
                        ]);
                    }
                } catch (\Exception $e) {
                }
                return response()->json([
                    'answer' => $hardcodedAnswer,
                    'id' => $record?->id,
                    'status' => 'answered',
                ]);
            }

            $kbMatch = $this->searchKnowledgeBase($question);

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

            $claudeResult = $this->callClaude($question);

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

    private const KNOWLEDGE_BASE_MIN_SCORE = 5;

    private function searchKnowledgeBase(string $question): ?AiKnowledgeBase
    {
        try {
            $qLower = strtolower(trim($question));
            $words = preg_split('/\s+/', $qLower, -1, PREG_SPLIT_NO_EMPTY);
            $words = array_values(array_filter($words, fn($w) => strlen($w) >= 2));

            if (empty($words)) {
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

                // Word match: question words found in pattern or tags
                $wordScore = 0;
                foreach ($words as $word) {
                    if (str_contains($pattern, $word)) {
                        $wordScore += 2; // pattern match weighted higher
                        continue;
                    }
                    foreach ($tags as $tag) {
                        if (str_contains($tag, $word) || str_contains($word, $tag)) {
                            $wordScore += 1;
                            break;
                        }
                    }
                }

                // similar_text: 0–100, scale to 0–5 points (FIX 2)
                $similarity = 0;
                if ($pattern !== '') {
                    similar_text($qLower, $pattern, $percent);
                    $similarity = (float) $percent;
                }
                $similarityScore = min(5, round($similarity / 20)); // 100% => 5, 40% => 2

                // Tag match: question contains a full tag (e.g. "invoice" in question)
                $tagMatchScore = 0;
                foreach ($tags as $tag) {
                    if ($tag === '' || strlen($tag) < 2) continue;
                    if (str_contains($qLower, $tag)) {
                        $tagMatchScore += 2;
                    }
                }
                $tagMatchScore = min(5, $tagMatchScore);

                // Category/subcategory in question (bonus)
                $categoryScore = 0;
                if ($category !== '' && str_contains($qLower, $category)) {
                    $categoryScore += 1;
                }
                if ($subcategory !== '' && str_contains($qLower, $subcategory)) {
                    $categoryScore += 1;
                }

                $score = $wordScore + $similarityScore + $tagMatchScore + $categoryScore;

                if ($score > $bestScore && $score >= self::KNOWLEDGE_BASE_MIN_SCORE) {
                    $bestScore = $score;
                    $best = $entry;
                }
            }

            return $best;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * FIX 1: Hardcoded answers for "What is 5 Core", "What is Oops", etc.
     */
    private function getHardcodedCompanyAnswer(string $question): ?string
    {
        $q = preg_replace('/\s+/', ' ', strtolower(trim($question)));
        $q = trim($q);

        // 5Core / 5 Core
        if (
            preg_match('/\b(what|who|tell me about)\s+is\s+(5\s*core|5core)\b/i', $q)
            || preg_match('/\b(5\s*core|5core)\s*[?\s]*(what|who|about)/i', $q)
            || $q === 'what is 5 core'
            || $q === 'what is 5core'
            || $q === 'who is 5core'
            || str_contains($q, 'what is 5 core')
            || str_contains($q, 'what is 5core')
        ) {
            return "5Core is our company and product platform. It provides integrated solutions for business operations including tasks, HR, sales, invoicing, and workflows. For specific how-to steps, ask a detailed question or check the knowledge base.";
        }

        // Oops (OOP / product name – adjust wording to your actual product)
        if (
            preg_match('/\b(what|who|tell me about)\s+is\s+(oops?|oop)\b/i', $q)
            || $q === 'what is oops'
            || $q === 'what is oop'
            || str_contains($q, 'what is oops')
            || str_contains($q, 'what is oop')
        ) {
            return "Oops (OOP) in our context refers to Object-Oriented Programming principles used in our systems, or the related product/module name. For feature-specific steps, ask a detailed question (e.g. how to create an invoice, how to apply leave).";
        }

        return null;
    }

    private function formatAnswerSteps($answerSteps, ?string $videoLink = null): string
    {
        $steps = is_array($answerSteps) ? $answerSteps : (is_string($answerSteps) ? json_decode($answerSteps, true) : []);

        if (!is_array($steps)) {
            $steps = [$steps];
        }

        // 🔥 FIX: Check if steps already have numbers (1., 2., etc.)
        $formattedSteps = [];
        foreach ($steps as $index => $step) {
            // Remove existing numbers if present
            $cleanStep = preg_replace('/^\d+\.\s*/', '', trim($step));
            // Add single number
            $formattedSteps[] = ($index + 1) . '. ' . $cleanStep;
        }

        $out = implode("\n", $formattedSteps);

        if ($videoLink) {
            $out .= "\n\n📹 Video tutorial: " . $videoLink;
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

    private function getSeniorEmailByDomain(string $domain): string
    {
        $emails = config('services.5core.senior_emails', []);
        return $emails[$domain] ?? $emails['General'] ?? 'support@5core.com';
    }

    /** FIX 3: System prompt with accurate 5Core product/company info for consistent answers. */
    private const CLAUDE_SYSTEM_PROMPT = "You are 5Core AI Assistant, an internal support agent for 5Core team members. "

        . "ABOUT 5CORE: 5Core is the company and product platform. It provides integrated business solutions: Tasks (assignment, due dates, dashboard), HR (leave, attendance), Sales (invoicing, billing, clients), and general workflows. "

        . "When users ask 'What is 5Core' or 'What is 5 Core', answer: 5Core is our company and product platform that provides integrated solutions for tasks, HR, sales, invoicing, and workflows. Keep it to 1-2 sentences unless they ask for more. "

        . "When users ask about 'Oops' or 'OOP', clarify: in our context it can mean Object-Oriented Programming in our systems or a related product/module; suggest they ask a specific how-to question if they need steps. "

        . "Answer questions about 5Core products, processes, and workflows using the above. For greetings like 'hi', 'hello', 'hey', 'thanks', 'good morning' - respond politely and warmly. "
        . "Keep responses professional, friendly, and concise in English. "

        . "IMPORTANT: If you don't know the answer or the question is about IT, VPN, server, network, or anything outside 5Core scope, "
        . "you MUST say: 'I don't have this information.' Do NOT give long explanations or recommendations to contact IT. "
        . "Just say 'I don't have this information.' and the system will escalate. "
        . "Do NOT say 'I'm afraid' or 'I recommend checking with IT' - just say you don't have the information.";


    private function callClaude(string $question): ?array
    {
        $apiKey = config('services.anthropic.key');
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

            if (!$response->successful()) {
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

        return false;
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

            $replyLink = url('/ai/escalation/' . $escalation->id . '/reply');

            try {
                Mail::to($seniorEmail)->send(new EscalationMail(
                    $user->name ?? $user->email,
                    $user->email,
                    $question,
                    $escalation->id,
                    $domain,
                    $replyLink
                ));
            } catch (\Exception $e) {
            }

            $answer = "I don't have this information. Your question has been escalated to the {$domain} team senior. You will be notified when they respond.";

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
            $entriesAdded = $this->processCSVTraining(storage_path('app/' . $path), $record->id);
            $record->update(['status' => 'processed', 'processed_at' => now()]);

            return response()->json([
                'success' => true,
                'message' => 'File uploaded and processed successfully.',
                'entries_added' => $entriesAdded,
            ]);
        } catch (\Throwable $e) {
            $record->update(['status' => 'failed']);

            return response()->json([
                'success' => false,
                'message' => 'File processing failed. Please check the CSV format.',
                'debug' => app()->environment('local') ? $e->getMessage() : null
            ], 422);
        }
    }

    /**
     * Process CSV and insert into ai_knowledge_base. Returns number of entries added (FIX 4).
     */
    private function processCSVTraining(string $filePath, int $fileId): int
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

        $header = null;
        while (($row = fgetcsv($handle)) !== false) {
            if ($row === [null] || empty(array_filter($row))) {
                continue;
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

        $entriesAdded = 0;
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
                $entriesAdded++;
            } catch (\Throwable $e) {
                throw $e;
            }
        }

        fclose($handle);
        return $entriesAdded;
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

            // 5Core company info (train bot for "What is 5 Core" type questions)
            fputcsv($handle, [
                'Company',
                'About',
                'what is 5core what is 5 core',
                '["5Core is our company and product platform.", "It provides integrated solutions: Tasks, HR, Sales, Invoicing, and workflows.", "Ask specific how-to questions for step-by-step guides."]',
                '',
                '["5core", "5 core", "company", "about"]'
            ]);
            fputcsv($handle, [
                'Company',
                'Products',
                'what is oops what is oop',
                '["Oops/OOP in our context refers to Object-Oriented Programming or the related product module.", "For feature steps, ask e.g. how to create invoice, how to apply leave."]',
                '',
                '["oops", "oop", "product"]'
            ]);

            // Task
            fputcsv($handle, [
                'Task',
                'Assignment',
                'how to assign task',
                '["1. Navigate to Tasks section", "2. Click Create New Task", "3. Fill details", "4. Select assignee", "5. Set due date", "6. Click Assign"]',
                'https://5core.com/task-assign-video',
                '["task", "assign", "dashboard"]'
            ]);

            // HR - Leave
            fputcsv($handle, [
                'HR',
                'Leave',
                'how to apply for leave',
                '["1. Go to HR Module", "2. Click Apply Leave", "3. Select leave type", "4. Choose dates", "5. Add reason", "6. Submit"]',
                '',
                '["leave", "hr", "attendance"]'
            ]);

            // Sales - Invoice
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
