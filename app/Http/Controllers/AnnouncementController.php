<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\AnnouncementComment;
use App\Models\AnnouncementView;
use App\Models\User;
use App\Support\OpenAiRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public function index(): View
    {
        return view('announcements.index', [
            'canAnnounce' => $this->canAnnounce(),
        ]);
    }

    public function data(): JsonResponse
    {
        if (! Schema::hasTable('announcements')) {
            return response()->json(['data' => []]);
        }

        $query = Announcement::query()
            ->with('user:id,name,avatar')
            ->orderByDesc('announced_on')
            ->orderByDesc('id');

        if (Schema::hasTable('announcement_comments')) {
            $query->with(['comments.user:id,name,avatar']);
        }

        if (Schema::hasTable('announcement_views')) {
            $query->withCount('views');
        }

        $rows = $query
            ->get()
            ->map(fn (Announcement $row) => $this->serializeAnnouncement($row));

        return response()->json(['data' => $rows]);
    }

    public function board(): JsonResponse
    {
        if (! Schema::hasTable('announcements')) {
            return response()->json(['data' => []]);
        }

        if (! Schema::hasColumn('announcements', 'posted_at')) {
            return response()->json(['data' => []]);
        }

        $userId = (int) Auth::id();
        $query = Announcement::query()
            ->with('user:id,name,avatar')
            ->posted()
            ->orderByDesc('posted_at')
            ->orderByDesc('id');

        if (Schema::hasTable('announcement_comments')) {
            $query->with(['comments.user:id,name,avatar']);
        }

        if (Schema::hasTable('announcement_views')) {
            $query->withCount('views');
            if ($userId > 0) {
                $query->unreadBy($userId);
            }
        }

        $rows = $query->get();

        return response()->json([
            'data' => $rows->map(fn (Announcement $row) => $this->serializeAnnouncement($row)),
            'unread_count' => $rows->count(),
        ]);
    }

    public function markRead(int $id): JsonResponse
    {
        $row = Announcement::query()->posted()->findOrFail($id);
        $this->recordBoardViews([$row->id]);

        return response()->json([
            'success' => true,
            'id' => $row->id,
            'unread_count' => $this->unreadCountFor((int) Auth::id()),
        ]);
    }

    public function markAllRead(): JsonResponse
    {
        $userId = (int) Auth::id();
        $ids = Announcement::query()
            ->posted()
            ->when(
                Schema::hasTable('announcement_views') && $userId > 0,
                fn ($query) => $query->unreadBy($userId)
            )
            ->pluck('id')
            ->all();

        $this->recordBoardViews($ids);

        return response()->json([
            'success' => true,
            'unread_count' => 0,
        ]);
    }

    public function viewers(int $id): JsonResponse
    {
        $row = Announcement::findOrFail($id);
        $viewerIds = Schema::hasTable('announcement_views')
            ? $row->views()->pluck('user_id')->map(fn ($uid) => (int) $uid)->all()
            : [];
        $viewerLookup = array_flip($viewerIds);

        $people = User::query()
            ->where('is_active', true)
            ->whereNull('deactivated_at')
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $viewers = [];
        $nonViewers = [];
        foreach ($people as $person) {
            $item = [
                'id' => (int) $person->id,
                'name' => $person->name ?: ($person->email ?: 'User #'.$person->id),
            ];
            if (isset($viewerLookup[(int) $person->id])) {
                $viewers[] = $item;
            } else {
                $nonViewers[] = $item;
            }
        }

        return response()->json([
            'success' => true,
            'id' => $row->id,
            'announced_on' => optional($row->announced_on)->format('d M Y'),
            'viewed_count' => count($viewers),
            'not_viewed_count' => count($nonViewers),
            'viewers' => $viewers,
            'non_viewers' => $nonViewers,
        ]);
    }

    public function comments(int $id): JsonResponse
    {
        $row = Announcement::findOrFail($id);

        return response()->json([
            'data' => $this->serializeComments($row),
        ]);
    }

    public function comment(Request $request, int $id): JsonResponse
    {
        if (! Schema::hasTable('announcement_comments')) {
            abort(503, 'Announcement comments table is missing. Run php artisan migrate.');
        }

        $row = Announcement::findOrFail($id);
        $validated = $request->validate([
            'comment' => 'required|string|max:2000',
        ]);
        $text = trim($validated['comment']);
        if ($text === '') {
            return response()->json(['message' => 'Enter a comment.'], 422);
        }

        $comment = AnnouncementComment::query()->create([
            'announcement_id' => $row->id,
            'user_id' => (int) Auth::id(),
            'comment' => $text,
        ]);

        return response()->json([
            'success' => true,
            'comment' => $this->serializeComment($comment->load('user:id,name,avatar')),
        ]);
    }

    public function post(int $id): JsonResponse
    {
        $this->authorizeDirector();
        $this->ensureTable();
        if (! Schema::hasColumn('announcements', 'posted_at')) {
            abort(503, 'Announcements need a posted_at column. Run php artisan migrate.');
        }

        $row = Announcement::findOrFail($id);
        if ($row->isPosted()) {
            $row->posted_at = null;
            $row->save();
        } else {
            $row->posted_at = now();
            $row->save();
            if (Schema::hasTable('announcement_views')) {
                $row->views()->delete();
            }
        }

        $row->load('user:id,name,avatar');
        if (Schema::hasTable('announcement_views')) {
            $row->loadCount('views');
        }

        return response()->json([
            'success' => true,
            'posted' => $row->isPosted(),
            'unread_count' => $this->unreadCountFor((int) Auth::id()),
            'row' => $this->serializeAnnouncement($row),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeDirector();
        $this->ensureTable();

        $validated = $this->validatedPayload($request);
        $images = array_values(array_unique(array_merge(
            $this->keptImages($request),
            $this->storeImages($request)
        )));

        if ($validated['message'] === '' && $images === []) {
            return response()->json([
                'message' => 'Add announcement text or at least one image.',
            ], 422);
        }

        $user = Auth::user();
        $row = Announcement::create([
            'user_id' => (int) $user->id,
            'message' => $validated['message'] !== '' ? $validated['message'] : null,
            'images' => $images ?: null,
            'announced_on' => $validated['announced_on'],
            'created_by' => $user->name ?: $user->email,
        ]);

        return response()->json([
            'success' => true,
            'id' => $row->id,
            'row' => $this->serializeAnnouncement($row->load('user:id,name,avatar')),
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->authorizeDirector();
        $this->ensureTable();

        $row = Announcement::findOrFail($id);
        $validated = $this->validatedPayload($request);
        $keep = $this->keptImages($request);

        $existing = $row->imagePaths();
        foreach ($existing as $path) {
            if (! in_array($path, $keep, true)) {
                Storage::disk('public')->delete($path);
            }
        }

        $images = array_values(array_unique(array_merge(
            array_values(array_intersect($existing, $keep)),
            $this->storeImages($request)
        )));

        if ($validated['message'] === '' && $images === []) {
            return response()->json([
                'message' => 'Add announcement text or at least one image.',
            ], 422);
        }

        $row->update([
            'message' => $validated['message'] !== '' ? $validated['message'] : null,
            'images' => $images ?: null,
            'announced_on' => $validated['announced_on'],
        ]);

        return response()->json([
            'success' => true,
            'id' => $row->id,
            'row' => $this->serializeAnnouncement($row->fresh()->load('user:id,name,avatar')),
        ]);
    }

    public function ai(Request $request): JsonResponse
    {
        $this->authorizeDirector();

        $validated = $request->validate([
            'prompt' => 'required|string|max:2000',
            'kind' => 'required|in:text,image',
        ]);
        $prompt = trim($validated['prompt']);
        if ($prompt === '') {
            return response()->json(['message' => 'Enter an AI prompt.'], 422);
        }

        $kind = $validated['kind'];
        if ($kind !== 'image' && $this->promptWantsImage($prompt)) {
            $kind = 'image';
        }

        if ($kind === 'image') {
            [$image, $error] = $this->generateAiImage($prompt);
            if ($image === null) {
                return response()->json([
                    'message' => $error ?: 'Could not generate an image. Check GEMINI_API_KEY and try again.',
                ], 422);
            }

            return response()->json([
                'success' => true,
                'kind' => 'image',
                'image' => $image,
            ]);
        }

        [$text, $error] = $this->generateAiText($prompt);
        if ($text === null || $text === '') {
            return response()->json([
                'message' => $error ?: 'Could not generate announcement text. Check CLAUDE_API_KEY / GEMINI_API_KEY and try again.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'kind' => 'text',
            'text' => $text,
        ]);
    }

    public function file(string $filename)
    {
        $filename = basename($filename);
        if (! preg_match('/^[A-Za-z0-9._-]+\.(jpe?g|png|gif|webp)$/i', $filename)) {
            abort(404);
        }

        $path = 'announcements/'.$filename;
        if (! Storage::disk('public')->exists($path)) {
            abort(404);
        }

        return Storage::disk('public')->response($path);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->authorizeDirector();
        $this->ensureTable();

        $row = Announcement::findOrFail($id);
        $wasPosted = $row->isPosted();
        foreach ($row->imagePaths() as $path) {
            Storage::disk('public')->delete($path);
        }
        if (Schema::hasTable('announcement_views')) {
            $row->views()->delete();
        }
        if (Schema::hasTable('announcement_comments')) {
            $row->comments()->delete();
        }
        $row->delete();

        return response()->json([
            'success' => true,
            'id' => $id,
            'posted' => $wasPosted,
            'unread_count' => $this->unreadCountFor((int) Auth::id()),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeAnnouncement(Announcement $row): array
    {
        $paths = $row->imagePaths();

        return [
            'id' => $row->id,
            'message' => $row->message,
            'announced_on' => optional($row->announced_on)->format('Y-m-d'),
            'announced_on_display' => optional($row->announced_on)->format('d M Y'),
            'images' => array_map(fn (string $path) => [
                'path' => $path,
                'url' => $this->announcementImageUrl($path),
            ], $paths),
            'posted' => $row->isPosted(),
            'posted_at' => optional($row->posted_at)->format('Y-m-d H:i'),
            'posted_by' => optional($row->user)->name ?: ($row->created_by ?: '—'),
            'posted_by_avatar' => $this->userAvatarUrl($row->user),
            'comments' => $this->serializeComments($row),
            'viewed_count' => (int) ($row->views_count ?? 0),
            'created_at' => optional($row->created_at)->format('Y-m-d H:i'),
        ];
    }

    private function unreadCountFor(int $userId): int
    {
        if (! Schema::hasTable('announcements') || ! Schema::hasColumn('announcements', 'posted_at')) {
            return 0;
        }

        $query = Announcement::query()->posted();
        if (Schema::hasTable('announcement_views') && $userId > 0) {
            $query->unreadBy($userId);
        }

        return (int) $query->count();
    }

    /**
     * @param list<int|string> $ids
     */
    private function recordBoardViews(array $ids): void
    {
        $userId = Auth::id();
        if (! $userId || $ids === [] || ! Schema::hasTable('announcement_views')) {
            return;
        }

        $now = now();
        foreach ($ids as $id) {
            AnnouncementView::query()->firstOrCreate(
                [
                    'announcement_id' => (int) $id,
                    'user_id' => (int) $userId,
                ],
                ['viewed_at' => $now]
            );
        }
    }

    /**
     * @return array{message: string, announced_on: string}
     */
    private function validatedPayload(Request $request): array
    {
        $validated = $request->validate([
            'message' => 'nullable|string|max:5000',
            'announced_on' => 'required|date',
            'images' => 'nullable|array|max:6',
            'images.*' => 'image|mimes:jpg,jpeg,png,gif,webp|max:5120',
            'keep_images' => 'nullable|array',
            'keep_images.*' => 'nullable|string',
        ]);

        return [
            'message' => trim((string) ($validated['message'] ?? '')),
            'announced_on' => $validated['announced_on'],
        ];
    }

    /**
     * @return array{0: ?string, 1: string}
     */
    private function generateAiText(string $prompt): array
    {
        $system = 'You write short internal company announcements. Return only the announcement body, no title, no quotes, no markdown. Never say you cannot make an image. Never suggest other tools.';
        $lastError = 'No AI key is configured.';

        $claude = $this->generateAiTextFromClaude($system, $prompt);
        if ($claude[0] !== null) {
            return $claude;
        }
        if ($claude[1] !== '') {
            $lastError = $claude[1];
        }

        $gemini = $this->generateAiTextFromGemini($system, $prompt);
        if ($gemini[0] !== null) {
            return $gemini;
        }
        if ($gemini[1] !== '') {
            $lastError = $gemini[1];
        }

        $openai = $this->generateAiTextFromOpenAi($system, $prompt);
        if ($openai[0] !== null) {
            return $openai;
        }
        if ($openai[1] !== '') {
            $lastError = $openai[1];
        }

        return [null, $lastError];
    }

    /**
     * @return array{0: ?string, 1: string}
     */
    private function generateAiTextFromClaude(string $system, string $prompt): array
    {
        $key = $this->claudeApiKey();
        if ($key === null) {
            return [null, ''];
        }

        try {
            $response = Http::timeout(60)
                ->withHeaders([
                    'x-api-key' => $key,
                    'anthropic-version' => (string) config('services.anthropic.version', '2023-06-01'),
                    'content-type' => 'application/json',
                ])
                ->post('https://api.anthropic.com/v1/messages', [
                    'model' => (string) config('services.anthropic.model', 'claude-haiku-4-5-20251001'),
                    'max_tokens' => 400,
                    'temperature' => 0.4,
                    'system' => $system,
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt],
                    ],
                ]);
        } catch (\Throwable $e) {
            Log::warning('Announcement AI text Claude exception', ['msg' => $e->getMessage()]);

            return [null, 'Claude error: '.$e->getMessage()];
        }

        if (! $response->successful()) {
            $msg = (string) ($response->json('error.message') ?? ('HTTP '.$response->status()));
            Log::warning('Announcement AI text Claude failed', [
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 400),
            ]);

            return [null, 'Claude error: '.$msg];
        }

        $text = '';
        foreach ((array) $response->json('content', []) as $block) {
            if (($block['type'] ?? '') === 'text') {
                $text .= (string) ($block['text'] ?? '');
            }
        }
        $text = trim($text);

        return $text !== '' ? [mb_substr($text, 0, 5000), ''] : [null, 'Claude returned empty text.'];
    }

    /**
     * @return array{0: ?string, 1: string}
     */
    private function generateAiTextFromGemini(string $system, string $prompt): array
    {
        $key = $this->geminiApiKey();
        if ($key === null) {
            return [null, ''];
        }

        $models = [];
        foreach ([
            (string) config('services.gemini.text_model', 'gemini-3.5-flash-lite'),
            'gemini-3.5-flash-lite',
            'gemini-flash-lite-latest',
            'gemini-flash-latest',
        ] as $name) {
            $name = trim($name);
            if ($name !== '' && ! in_array($name, $models, true)) {
                $models[] = $name;
            }
        }

        $lastError = 'Gemini request failed.';
        foreach ($models as $model) {
            try {
                $response = Http::timeout(60)
                    ->withHeaders([
                        'x-goog-api-key' => $key,
                        'Content-Type' => 'application/json',
                    ])
                    ->post('https://generativelanguage.googleapis.com/v1beta/models/'.$model.':generateContent', [
                        'systemInstruction' => [
                            'parts' => [['text' => $system]],
                        ],
                        'contents' => [[
                            'role' => 'user',
                            'parts' => [['text' => $prompt]],
                        ]],
                        'generationConfig' => [
                            'temperature' => 0.4,
                            'maxOutputTokens' => 400,
                        ],
                    ]);
            } catch (\Throwable $e) {
                $lastError = 'Gemini error: '.$e->getMessage();
                continue;
            }

            if (! $response->successful()) {
                $msg = (string) (data_get($response->json(), 'error.message') ?? ('HTTP '.$response->status()));
                $lastError = 'Gemini error: '.$msg;
                Log::warning('Announcement AI text Gemini failed', [
                    'model' => $model,
                    'status' => $response->status(),
                ]);
                continue;
            }

            $text = trim((string) data_get($response->json(), 'candidates.0.content.parts.0.text', ''));
            if ($text !== '') {
                return [mb_substr($text, 0, 5000), ''];
            }
            $lastError = 'Gemini returned empty text.';
        }

        return [null, $lastError];
    }

    /**
     * @return array{0: ?string, 1: string}
     */
    private function generateAiTextFromOpenAi(string $system, string $prompt): array
    {
        $headers = OpenAiRequest::authHeaders();
        if ($headers === []) {
            return [null, ''];
        }

        try {
            $response = Http::withHeaders($headers)
                ->timeout(60)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => (string) config('services.openai.title_master_stack_model', 'gpt-4o-mini'),
                    'temperature' => 0.4,
                    'max_tokens' => 400,
                    'messages' => [
                        ['role' => 'system', 'content' => $system],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                ]);
        } catch (\Throwable $e) {
            Log::warning('Announcement AI text OpenAI exception', ['msg' => $e->getMessage()]);

            return [null, 'OpenAI error: '.$e->getMessage()];
        }

        if ($response->successful()) {
            $text = trim((string) ($response->json('choices.0.message.content') ?? ''));
            if ($text !== '') {
                return [mb_substr($text, 0, 5000), ''];
            }

            return [null, 'OpenAI returned empty text.'];
        }

        $msg = (string) ($response->json('error.message') ?? ('HTTP '.$response->status()));
        Log::warning('Announcement AI text OpenAI failed', [
            'status' => $response->status(),
            'body' => mb_substr($response->body(), 0, 400),
        ]);

        return [null, 'OpenAI error: '.$msg];
    }

    private function promptWantsImage(string $prompt): bool
    {
        return (bool) preg_match(
            '/\b(image|img|poster|banner|graphic|picture|photo|illustration|flyer|artwork)\b/i',
            $prompt
        );
    }

    /**
     * @return array{0: array{path: string, url: string}|null, 1: string}
     */
    private function generateAiImage(string $prompt): array
    {
        $fullPrompt = 'Create a clean, professional internal company announcement graphic. '
            .'Square composition, no watermarks, no logos unless requested, no unreadable tiny text. Subject: '.$prompt;

        $imagen = $this->generateAiImageFromImagen($fullPrompt);
        if ($imagen[0] !== null) {
            return $imagen;
        }

        $gemini = $this->generateAiImageFromGemini($fullPrompt);
        if ($gemini[0] !== null) {
            return $gemini;
        }

        $openai = $this->generateAiImageFromOpenAi($fullPrompt);
        if ($openai[0] !== null) {
            return $openai;
        }

        $error = $imagen[1] !== '' ? $imagen[1] : ($gemini[1] !== '' ? $gemini[1] : $openai[1]);

        return [null, $error !== '' ? $error : 'No image AI key is configured.'];
    }

    /**
     * @return array{0: array{path: string, url: string}|null, 1: string}
     */
    private function generateAiImageFromImagen(string $prompt): array
    {
        $key = $this->geminiApiKey();
        if ($key === null) {
            return [null, ''];
        }

        $models = [
            'imagen-4.0-generate-001',
            'imagen-4.0-fast-generate-001',
            'imagen-3.0-generate-002',
            'imagen-3.0-fast-generate-001',
        ];

        $lastError = 'Gemini Imagen request failed.';
        foreach ($models as $model) {
            try {
                $response = Http::timeout(120)
                    ->withHeaders([
                        'x-goog-api-key' => $key,
                        'Content-Type' => 'application/json',
                    ])
                    ->post('https://generativelanguage.googleapis.com/v1beta/models/'.$model.':predict', [
                        'instances' => [
                            ['prompt' => $prompt],
                        ],
                        'parameters' => [
                            'sampleCount' => 1,
                            'aspectRatio' => '1:1',
                        ],
                    ]);
            } catch (\Throwable $e) {
                $lastError = 'Gemini Imagen error: '.$e->getMessage();
                continue;
            }

            if (! $response->successful()) {
                $msg = (string) (data_get($response->json(), 'error.message') ?? ('HTTP '.$response->status()));
                $lastError = 'Gemini Imagen error: '.$msg;
                Log::warning('Announcement AI Imagen failed', [
                    'model' => $model,
                    'status' => $response->status(),
                ]);
                continue;
            }

            $b64 = (string) (
                data_get($response->json(), 'predictions.0.bytesBase64Encoded')
                ?: data_get($response->json(), 'predictions.0.bytes_base64_encoded')
                ?: ''
            );
            $bytes = $b64 !== '' ? base64_decode($b64, true) : false;
            $stored = is_string($bytes) ? $this->storeAnnouncementAiImage($bytes) : null;
            if ($stored !== null) {
                return [$stored, ''];
            }
            $lastError = 'Gemini Imagen did not return an image.';
        }

        return [null, $lastError];
    }

    /**
     * @return array{0: array{path: string, url: string}|null, 1: string}
     */
    private function generateAiImageFromGemini(string $prompt): array
    {
        $key = $this->geminiApiKey();
        if ($key === null) {
            return [null, ''];
        }

        $models = [];
        foreach ([
            (string) config('services.raw_images_ai.gemini_model', 'gemini-3.1-flash-image-preview'),
            'gemini-3.1-flash-image-preview',
            'gemini-2.5-flash-image',
            'gemini-2.5-flash-image-preview',
            'gemini-2.0-flash-preview-image-generation',
        ] as $name) {
            $name = trim($name);
            if ($name !== '' && ! in_array($name, $models, true)) {
                $models[] = $name;
            }
        }

        $lastError = 'Gemini image request failed.';
        $configs = [
            ['responseModalities' => ['IMAGE']],
            ['responseModalities' => ['TEXT', 'IMAGE']],
        ];
        foreach ($models as $model) {
            foreach ($configs as $generationConfig) {
                try {
                    $response = Http::timeout(120)
                        ->withHeaders([
                            'x-goog-api-key' => $key,
                            'Content-Type' => 'application/json',
                        ])
                        ->post('https://generativelanguage.googleapis.com/v1beta/models/'.$model.':generateContent', [
                            'contents' => [[
                                'role' => 'user',
                                'parts' => [['text' => 'Generate an image. '.$prompt]],
                            ]],
                            'generationConfig' => $generationConfig,
                        ]);
                } catch (\Throwable $e) {
                    $lastError = 'Gemini error: '.$e->getMessage();
                    continue;
                }

                if (! $response->successful()) {
                    $msg = (string) (data_get($response->json(), 'error.message') ?? ('HTTP '.$response->status()));
                    $lastError = 'Gemini error: '.$msg;
                    Log::warning('Announcement AI image Gemini failed', [
                        'model' => $model,
                        'status' => $response->status(),
                    ]);
                    continue;
                }

                $bytes = $this->bytesFromGeminiImage($response->json());
                $stored = $this->storeAnnouncementAiImage($bytes);
                if ($stored !== null) {
                    return [$stored, ''];
                }
                $lastError = 'Gemini did not return an image.';
            }
        }

        return [null, $lastError];
    }

    /**
     * @return array{0: array{path: string, url: string}|null, 1: string}
     */
    private function generateAiImageFromOpenAi(string $prompt): array
    {
        $headers = OpenAiRequest::authHeaders();
        if ($headers === []) {
            return [null, ''];
        }

        try {
            $response = Http::withHeaders($headers)
                ->timeout(90)
                ->post('https://api.openai.com/v1/images/generations', [
                    'model' => 'dall-e-3',
                    'prompt' => $prompt,
                    'n' => 1,
                    'size' => '1024x1024',
                    'quality' => 'standard',
                ]);
        } catch (\Throwable $e) {
            Log::warning('Announcement AI image OpenAI exception', ['msg' => $e->getMessage()]);

            return [null, 'OpenAI error: '.$e->getMessage()];
        }

        if (! $response->successful()) {
            $msg = (string) ($response->json('error.message') ?? ('HTTP '.$response->status()));
            Log::warning('Announcement AI image OpenAI failed', [
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 400),
            ]);

            return [null, 'OpenAI error: '.$msg];
        }

        $bytes = null;
        $b64 = (string) ($response->json('data.0.b64_json') ?? '');
        if ($b64 !== '') {
            $decoded = base64_decode($b64, true);
            if (is_string($decoded) && $decoded !== '') {
                $bytes = $decoded;
            }
        }
        if ($bytes === null) {
            $url = trim((string) ($response->json('data.0.url') ?? ''));
            if ($url === '') {
                return [null, 'OpenAI did not return an image.'];
            }
            try {
                $file = Http::timeout(60)->get($url);
            } catch (\Throwable $e) {
                return [null, 'OpenAI error: '.$e->getMessage()];
            }
            if (! $file->successful() || strlen($file->body()) < 200) {
                return [null, 'OpenAI image download failed.'];
            }
            $bytes = $file->body();
        }

        $stored = $this->storeAnnouncementAiImage($bytes);

        return $stored !== null ? [$stored, ''] : [null, 'Could not save the generated image.'];
    }

    /**
     * @param  array<string, mixed>|null  $json
     */
    private function bytesFromGeminiImage(?array $json): ?string
    {
        if (! is_array($json)) {
            return null;
        }

        foreach ([
            'predictions.0.bytesBase64Encoded',
            'predictions.0.bytes_base64_encoded',
            'candidates.0.content.parts',
        ] as $path) {
            $value = data_get($json, $path);
            if (is_string($value) && $value !== '') {
                $bytes = base64_decode($value, true);
                if (is_string($bytes) && strlen($bytes) > 200) {
                    return $bytes;
                }
            }
            if (is_array($value)) {
                $fromParts = $this->bytesFromGeminiImageParts($value);
                if ($fromParts !== null) {
                    return $fromParts;
                }
            }
        }

        return $this->bytesFromGeminiImageParts($json);
    }

    /**
     * @param  array<string, mixed>|list<mixed>  $node
     */
    private function bytesFromGeminiImageParts(array $node): ?string
    {
        $b64 = (string) ($node['inlineData']['data'] ?? $node['inline_data']['data'] ?? '');
        if ($b64 === '' && isset($node['data']) && is_string($node['data']) && strlen($node['data']) > 200) {
            $b64 = $node['data'];
        }
        if ($b64 !== '') {
            $bytes = base64_decode($b64, true);
            if (is_string($bytes) && strlen($bytes) > 200) {
                return $bytes;
            }
        }

        foreach ($node as $value) {
            if (! is_array($value)) {
                continue;
            }
            $found = $this->bytesFromGeminiImageParts($value);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    /**
     * @return array{path: string, url: string}|null
     */
    private function storeAnnouncementAiImage(?string $bytes): ?array
    {
        if (! is_string($bytes) || strlen($bytes) < 200) {
            return null;
        }

        $ext = str_starts_with($bytes, "\xFF\xD8\xFF") ? 'jpg' : 'png';
        $path = 'announcements/'.uniqid('ai_', true).'.'.$ext;
        Storage::disk('public')->put($path, $bytes);

        return [
            'path' => $path,
            'url' => $this->announcementImageUrl($path),
        ];
    }

    private function announcementImageUrl(string $path): string
    {
        return route('announcements.file', ['filename' => basename($path)]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function serializeComments(Announcement $row): array
    {
        if (! Schema::hasTable('announcement_comments')) {
            return [];
        }

        $comments = $row->relationLoaded('comments')
            ? $row->comments
            : $row->comments()->with('user:id,name,avatar')->get();

        return $comments
            ->map(fn (AnnouncementComment $comment) => $this->serializeComment($comment))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeComment(AnnouncementComment $comment): array
    {
        return [
            'id' => $comment->id,
            'comment' => $comment->comment,
            'user_id' => (int) $comment->user_id,
            'user_name' => optional($comment->user)->name ?: 'User',
            'user_avatar' => $this->userAvatarUrl($comment->user),
            'created_at' => optional($comment->created_at)->format('d M Y H:i'),
        ];
    }

    private function userAvatarUrl(?User $user): string
    {
        $avatar = trim((string) ($user?->avatar ?? ''));
        if ($avatar === '') {
            return asset('images/users/avatar-2.jpg');
        }
        if (str_starts_with($avatar, 'http://') || str_starts_with($avatar, 'https://')) {
            return $avatar;
        }

        return asset('storage/'.$avatar);
    }

    private function claudeApiKey(): ?string
    {
        $key = config('services.claude.key') ?: config('services.anthropic.key');

        return is_string($key) && trim($key) !== '' ? trim($key) : null;
    }

    private function geminiApiKey(): ?string
    {
        $key = config('services.gemini.key') ?: config('services.raw_images_ai.gemini_key');

        return is_string($key) && trim($key) !== '' ? trim($key) : null;
    }

    /**
     * @return list<string>
     */
    private function keptImages(Request $request): array
    {
        return collect($request->input('keep_images', []))
            ->map(function ($path) {
                $path = trim((string) $path);
                $path = str_replace('\\', '/', $path);
                $path = ltrim($path, '/');
                if (str_starts_with($path, 'storage/')) {
                    $path = substr($path, strlen('storage/'));
                }
                if ($path !== '' && ! str_contains($path, '/')) {
                    $path = 'announcements/'.$path;
                }

                return $path;
            })
            ->filter(function (string $path) {
                return $path !== ''
                    && ! str_contains($path, '..')
                    && str_starts_with($path, 'announcements/')
                    && Storage::disk('public')->exists($path);
            })
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function storeImages(Request $request): array
    {
        $stored = [];
        foreach ($request->file('images', []) ?? [] as $file) {
            if (! $file || ! $file->isValid()) {
                continue;
            }
            $stored[] = $file->store('announcements', 'public');
        }

        return $stored;
    }

    private function canAnnounce(?User $user = null): bool
    {
        $user ??= Auth::user();

        return $user?->isDirector() ?? false;
    }

    private function authorizeDirector(): void
    {
        if (! $this->canAnnounce()) {
            abort(403, 'Only directors can make announcements.');
        }
    }

    private function ensureTable(): void
    {
        if (! Schema::hasTable('announcements')) {
            abort(503, 'Announcements table is missing. Run php artisan migrate.');
        }
    }
}
