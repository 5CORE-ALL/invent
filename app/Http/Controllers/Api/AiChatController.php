<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiQuestion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiChatController extends Controller
{
    /**
     * Accept POST question, call Claude API, DEBUG response, STOP before DB save.
     */
    public function chat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'question' => ['required', 'string', 'max:8000'],
        ]);

        $question = strip_tags($validated['question']);
        $apiKey = config('services.anthropic.key');

        if (empty($apiKey)) {
            Log::error('Claude API Key Missing');
            return response()->json([
                'answer' => 'ANTHROPIC_API_KEY missing in .env',
            ], 500);
        }

        // 🔍 Safe API key debug
        Log::info('Claude API Key Check', [
            'key_exists' => true,
            'key_prefix' => substr($apiKey, 0, 10) . '...',
            'key_length' => strlen($apiKey),
        ]);

        try {
            $payload = [
                'model' => 'claude-3-haiku-20240307',
                'max_tokens' => 500,
                'system' => 'You are a helpful assistant for 5Core product.',
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => $question,
                            ],
                        ],
                    ],
                ],
            ];

            Log::info('Claude Request Payload', [
                'model' => $payload['model'],
                'question_length' => strlen($question),
                'max_tokens' => $payload['max_tokens'],
            ]);

            $response = Http::timeout(60)
                ->withHeaders([
                    'x-api-key' => $apiKey,
                    'anthropic-version' => '2023-06-01',
                    'content-type' => 'application/json',
                ])
                ->post('https://api.anthropic.com/v1/messages', $payload);

            Log::info('Claude HTTP Response', [
                'status' => $response->status(),
                'successful' => $response->successful(),
                'body' => $response->body(),
            ]);

            if (!$response->successful()) {
                $errorBody = $response->json();

                Log::error('Claude API Failed', [
                    'status' => $response->status(),
                    'error' => $errorBody,
                ]);

                return response()->json([
                    'answer' => 'Claude API Error',
                    'debug' => $errorBody,
                ], 500);
            }

            // ✅ Parse JSON
            $data = $response->json();

            // 🔥 LOG FULL RESPONSE
            Log::info('Claude FULL Parsed Response', [
                'response' => $data,
            ]);

            // 🔥 STOP EXECUTION HERE – VERIFY CLAUDE IS WORKING
            dd([
                'raw_response' => $data,
                'content_array' => $data['content'] ?? null,
                'first_text' => $data['content'][0]['text'] ?? null,
            ]);

        } catch (\Throwable $e) {
            Log::error('CLAUDE CHAT EXCEPTION', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'answer' => 'Server error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Feedback (unchanged)
     */
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
}
