<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\EscalationMail;
use App\Models\AiEscalation;
use App\Models\AiKnowledgeBase;
use App\Models\AiKnowledgeFile;
use App\Models\AiQuestion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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


    /**
     * Internal support agent: KB first, then OpenAI; if OpenAI is not confident or fails, escalate to senior.
     */
    public function chat(Request $request): JsonResponse
    {
        // 🔍 DEBUG LOG - Request aaya ya nahi
        Log::info('===== AI CHAT REQUEST STARTED =====', [
            'user_id' => $request->user()?->id,
            'user_email' => $request->user()?->email,
            'question' => $request->input('question'),
            'timestamp' => now()->toDateTimeString()
        ]);

        try {
            $validated = $request->validate([
                'question' => ['required', 'string', 'max:8000'],
            ]);

            $question = $this->sanitizeInput($validated['question']);
            $user = $request->user();

            // 🔍 DEBUG - Knowledge base search
            Log::info('🔎 Searching knowledge base', ['question' => $question]);

            $kbMatch = $this->searchKnowledgeBase($question);

            Log::info('📚 Knowledge base result', [
                'found' => $kbMatch ? 'YES' : 'NO',
                'id' => $kbMatch?->id,
                'pattern' => $kbMatch?->question_pattern
            ]);

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
                        Log::info('💾 Saved to ai_questions', ['record_id' => $record->id]);
                    }
                } catch (\Exception $e) {
                    Log::warning('AI question not stored', ['message' => $e->getMessage()]);
                }

                return response()->json([
                    'answer' => $answer,
                    'id' => $record?->id,
                    'status' => 'answered',
                ]);
            }

            // No KB match → try OpenAI first
            // No KB match → try Claude first
            Log::info('⚠️ No KB match, calling Claude', ['question' => $question]);
            $claudeResult = $this->callClaude($question);  // ✅ callClaude, NOT callOpenAI

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
                    Log::warning('AI question not stored', ['message' => $e->getMessage()]);
                }
                return response()->json([
                    'answer' => $answer,
                    'id' => $record?->id,
                    'status' => 'answered',
                ]);
            }

            // Claude not confident or failed → escalate to senior
            Log::info('⚠️ Claude not confident or failed, escalating', ['question' => $question, 'user_id' => $user->id]);
            $escalationResponse = $this->escalateToSenior($question, $user);
            return $escalationResponse;

            // OpenAI not confident or failed → escalate to senior
            Log::info('⚠️ OpenAI not confident or failed, escalating', ['question' => $question, 'user_id' => $user->id]);
            $escalationResponse = $this->escalateToSenior($question, $user);
            return $escalationResponse;
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('❌ Validation error', [
                'errors' => $e->errors(),
                'message' => $e->getMessage()
            ]);
            throw $e;
        } catch (\Throwable $e) {
            Log::error('❌ CHAT METHOD CRASHED', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'answer' => 'Something went wrong. Please try again.',
                'error' => app()->environment('local') ? $e->getMessage() : null
            ], 500);
        }
    }

    private function searchKnowledgeBase(string $question): ?AiKnowledgeBase
    {
        Log::debug('🔍 searchKnowledgeBase called', ['question' => $question]);

        try {
            $words = preg_split('/\s+/', strtolower($question), -1, PREG_SPLIT_NO_EMPTY);

            if (empty($words)) {
                Log::debug('⚠️ No words extracted from question');
                return null;
            }

            Log::debug('📊 Extracted words', ['words' => $words]);

            // Check if table exists
            if (!\Illuminate\Support\Facades\Schema::hasTable('ai_knowledge_base')) {
                Log::error('❌ ai_knowledge_base table does not exist!');
                return null;
            }

            $entries = AiKnowledgeBase::all();
            Log::debug('📚 Total KB entries', ['count' => $entries->count()]);

            if ($entries->isEmpty()) {
                Log::warning('⚠️ Knowledge base is empty! Run seeder: php artisan db:seed --class=AiKnowledgeBaseSeeder');
                return null;
            }

            $best = null;
            $bestScore = 0;

            foreach ($entries as $entry) {
                $pattern = strtolower($entry->question_pattern);
                $tags = $entry->tags ?? [];
                $allTerms = array_merge([$pattern], is_array($tags) ? $tags : []);

                $score = 0;
                foreach ($words as $word) {
                    if (strlen($word) < 2) continue;

                    foreach ($allTerms as $term) {
                        if (is_string($term) && str_contains($term, $word)) {
                            $score++;
                            break;
                        }
                    }
                }

                if ($score > $bestScore && $score >= 1) {
                    $bestScore = $score;
                    $best = $entry;
                    Log::debug('🎯 New best match', [
                        'id' => $entry->id,
                        'pattern' => $entry->question_pattern,
                        'score' => $score
                    ]);
                }
            }

            if ($best) {
                Log::info('✅ KB match found', [
                    'id' => $best->id,
                    'pattern' => $best->question_pattern,
                    'score' => $bestScore
                ]);
            } else {
                Log::info('❌ No KB match found', ['question' => $question]);
            }

            return $best;
        } catch (\Throwable $e) {
            Log::error('❌ searchKnowledgeBase CRASHED', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return null;
        }
    }

    private function formatAnswerSteps($answerSteps, ?string $videoLink = null): string
    {
        $steps = is_array($answerSteps) ? $answerSteps : (is_string($answerSteps) ? json_decode($answerSteps, true) : []);
        if (!is_array($steps)) {
            $steps = [$answerSteps];
        }
        $out = implode("\n", array_map(function ($step, $i) {
            return (is_int($i) ? ($i + 1) . '. ' : '') . $step;
        }, $steps, array_keys($steps)));
        if ($videoLink) {
            $out .= "\n\nVideo: " . $videoLink;
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

    private const CLAUDE_SYSTEM_PROMPT = "You are 5Core AI Assistant, an internal support agent for 5Core team members. " .
        "Answer questions about 5Core products, processes, and workflows. " .
        "For greetings like 'hi', 'hello', 'hey', 'thanks', 'good morning' - respond politely and warmly. " .
        "Keep responses professional, friendly, and concise in English. " .
        "IMPORTANT: If you don't know the answer or the question is about IT, VPN, server, network, or anything outside 5Core scope, " .
        "you MUST say: 'I don't have this information.' Do NOT give long explanations or recommendations to contact IT. " .
        "Just say 'I don't have this information.' and the system will escalate. " .
        "Do NOT say 'I'm afraid' or 'I recommend checking with IT' - just say you don't have the information.";

    /**
     * Call OpenAI Chat API. Returns ['answer' => string, 'confident' => bool] or null on failure.
     * Confident is false if answer indicates uncertainty (so we escalate).
     */

    private function callClaude(string $question): ?array
    {
        $apiKey = config('services.anthropic.key');
        if (empty($apiKey)) {
            Log::warning('❌ Anthropic API key not set in config/services.php or .env');
            return null;
        }

        try {
            Log::info('🤖 Calling Claude API', ['question' => substr($question, 0, 50)]);

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
                Log::warning('❌ Claude API error', [
                    'status' => $response->status(),
                    'body' => substr($response->body(), 0, 500)
                ]);

                // Rate limit ya auth error pe bhi null return
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
                Log::warning('❌ Claude returned empty response');
                return null;
            }

            // Confidence check
            $confident = !$this->isAnswerUncertain($answer);

            Log::info('✅ Claude response received', [
                'confident' => $confident,
                'answer_length' => strlen($answer)
            ]);

            return [
                'answer' => $answer,
                'confident' => $confident
            ];
        } catch (\Throwable $e) {
            Log::error('❌ Claude API exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
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
                Log::info('✅ Greeting detected, marking as confident', ['answer' => $answer]);
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
                Log::info('❌ Uncertainty phrase detected', [
                    'phrase' => $phrase,
                    'answer' => substr($answer, 0, 100)
                ]);
                return true; // Uncertain - escalate!
            }
        }

        // 🔍 EXTRA CHECK: Agar answer mein "I don't" + "information" ya "knowledge" ho
        if (
            str_contains($lower, "i don't") &&
            (str_contains($lower, "information") ||
                str_contains($lower, "knowledge") ||
                str_contains($lower, "specifics"))
        ) {
            Log::info('❌ Pattern detected: "I don\'t" + information/knowledge', ['answer' => substr($answer, 0, 100)]);
            return true;
        }

        return false; // Confident
    }

    private function escalateToSenior(string $question, $user): JsonResponse
    {
        Log::info('🚀 Starting escalation process', [
            'user_id' => $user->id,
            'question' => substr($question, 0, 100)
        ]);

        try {
            $domain = $this->detectDomain($question);
            Log::info('🎯 Domain detected', ['domain' => $domain]);

            $seniorEmail = $this->getSeniorEmailByDomain($domain);
            Log::info('📧 Senior email', ['email' => $seniorEmail]);

            Log::info('💾 Creating escalation record');
            $escalation = AiEscalation::create([
                'user_id' => $user->id,
                'original_question' => $question,
                'domain' => $domain,
                'assigned_senior_email' => $seniorEmail,
                'status' => 'pending',
            ]);
            Log::info('✅ Escalation record created', ['escalation_id' => $escalation->id]);

            Log::info('🔗 Generating signed reply link');
            $replyLink = url('/ai/escalation/' . $escalation->id . '/reply');
            Log::info('✅ Reply link generated', ['link' => $replyLink]);

            Log::info('📨 Sending escalation email');
            try {
                Mail::to($seniorEmail)->send(new EscalationMail(
                    $user->name ?? $user->email,
                    $user->email,
                    $question,
                    $escalation->id,
                    $domain,
                    $replyLink
                ));
                Log::info('✅ Email sent successfully');
            } catch (\Exception $e) {
                Log::error('❌ Escalation email failed', [
                    'escalation_id' => $escalation->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }

            $answer = "I don't have this information. Your question has been escalated to the {$domain} team senior. You will be notified when they respond.";

            Log::info('📤 Returning escalation response', [
                'escalation_id' => $escalation->id,
                'domain' => $domain
            ]);

            return response()->json([
                'answer' => $answer,
                'status' => 'escalated',
                'escalation_id' => $escalation->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('❌ ESCALATION METHOD CRASHED', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
    public function uploadKnowledge(Request $request): JsonResponse
    {
        Log::info('📤 CSV UPLOAD STARTED', [
            'user_id' => $request->user()?->id,
            'user_email' => $request->user()?->email,
            'has_file' => $request->hasFile('file'),
            'timestamp' => now()
        ]);

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ]);

        $file = $request->file('file');
        $path = $file->store('ai_knowledge', 'local');
        $originalName = $file->getClientOriginalName();

        Log::info('📁 File stored', [
            'original_name' => $originalName,
            'path' => $path,
            'size' => $file->getSize(),
            'mime' => $file->getMimeType()
        ]);

        $record = AiKnowledgeFile::create([
            'filename' => basename($path),
            'original_name' => $originalName,
            'file_path' => $path,
            'status' => 'pending',
        ]);

        try {
            Log::info('🔄 Processing CSV', ['file_id' => $record->id, 'file_path' => $path]);
            $this->processCSVTraining(storage_path('app/' . $path), $record->id);
            $record->update(['status' => 'processed', 'processed_at' => now()]);
            Log::info('✅ CSV processed successfully', ['file_id' => $record->id]);

            return response()->json(['success' => true, 'message' => 'File uploaded and processed successfully.']);
        } catch (\Throwable $e) {
            Log::error('❌ CSV processing failed', [
                'file_id' => $record->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
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
        Log::debug('📂 Opening CSV file', ['path' => $filePath]);

        // 🔥 FIX: Remove BOM if present
        $content = file_get_contents($filePath);
        $bom = "\xef\xbb\xbf";
        if (substr($content, 0, 3) === $bom) {
            $content = substr($content, 3);
            file_put_contents($filePath, $content);
            Log::info('✅ BOM removed from CSV');
        }

        // 🔥 FIX: Also remove any NULL bytes or control characters
        $content = str_replace("\0", '', $content);
        file_put_contents($filePath, $content);

        $handle = fopen($filePath, 'r');
        if (!$handle) {
            Log::error('❌ Could not open file', ['path' => $filePath]);
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
            Log::error('❌ CSV has no header row');
            throw new \RuntimeException('CSV file is empty or has no header row.');
        }

        Log::debug('📋 CSV Header', ['header' => $header]);

        $expected = ['category', 'subcategory', 'question_pattern', 'answer_steps', 'video_link', 'tags'];
        $header = array_map('trim', $header);
        $map = [];

        foreach ($expected as $col) {
            $idx = array_search($col, $header);
            if ($idx !== false) {
                $map[$col] = $idx;
                Log::debug('✅ Column found', ['column' => $col, 'index' => $idx]);
            } else {
                Log::warning('⚠️ Column missing', ['column' => $col]);
            }
        }

        if (!isset($map['question_pattern'])) {
            fclose($handle);
            Log::error('❌ Missing question_pattern column', ['header' => $header]);
            throw new \RuntimeException('CSV must have a question_pattern column.');
        }

        $rowCount = 0;
        while (($row = fgetcsv($handle)) !== false) {
            $rowCount++;
            Log::debug('📄 Processing row', ['row_number' => $rowCount, 'row_data' => $row]);

            try {
                $category = isset($map['category']) ? trim($row[$map['category']] ?? '') : 'General';
                $subcategory = isset($map['subcategory']) ? trim($row[$map['subcategory']] ?? '') : null;
                $question_pattern = trim($row[$map['question_pattern']] ?? '');

                if ($question_pattern === '') {
                    Log::warning('⚠️ Skipping row - empty question_pattern', ['row' => $rowCount]);
                    continue;
                }

                $answer_steps_raw = isset($map['answer_steps']) ? ($row[$map['answer_steps']] ?? '[]') : '[]';
                $video_link = isset($map['video_link']) ? trim($row[$map['video_link']] ?? '') : null;
                $tags_raw = isset($map['tags']) ? ($row[$map['tags']] ?? '[]') : '[]';

                Log::debug('📊 Raw data', [
                    'category' => $category,
                    'question_pattern' => $question_pattern,
                    'answer_steps_raw' => $answer_steps_raw,
                    'tags_raw' => $tags_raw
                ]);

                // JSON decode with error handling
                $decoded = json_decode($answer_steps_raw, true);
                if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
                    Log::warning('⚠️ Invalid JSON in answer_steps, using as plain text', [
                        'error' => json_last_error_msg(),
                        'value' => $answer_steps_raw
                    ]);
                    $answer_steps = [$answer_steps_raw];
                } else {
                    $answer_steps = is_array($decoded) ? $decoded : [$answer_steps_raw];
                }

                $tagsDecoded = json_decode($tags_raw, true);
                if ($tagsDecoded === null && json_last_error() !== JSON_ERROR_NONE) {
                    Log::warning('⚠️ Invalid JSON in tags, using as plain text', [
                        'error' => json_last_error_msg(),
                        'value' => $tags_raw
                    ]);
                    $tags = (array) $tags_raw;
                } else {
                    $tags = is_array($tagsDecoded) ? $tagsDecoded : (array) $tags_raw;
                }

                AiKnowledgeBase::create([
                    'category' => $category ?: 'General',
                    'subcategory' => $subcategory ?: null,
                    'question_pattern' => $question_pattern,
                    'answer_steps' => $answer_steps,
                    'video_link' => $video_link ?: null,
                    'tags' => $tags,
                ]);

                Log::debug('✅ Row inserted', ['question_pattern' => $question_pattern]);
            } catch (\Throwable $e) {
                Log::error('❌ Error processing row', [
                    'row' => $rowCount,
                    'error' => $e->getMessage(),
                    'row_data' => $row
                ]);
                throw $e;
            }
        }

        fclose($handle);
        Log::info('📊 CSV processing complete', ['total_rows' => $rowCount, 'file_id' => $fileId]);
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
        Log::info('📥 Sample CSV download requested', [
            'user_id' => auth()->id(),
            'timestamp' => now()
        ]);

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
