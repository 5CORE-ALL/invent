<?php

namespace App\Http\Controllers;

use App\Models\AiQuestion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class AiChatController extends Controller
{
    /**
     * Handle chat: send question to OpenAI GPT-4 and return answer.
     * Optionally store Q&A for feedback tracking.
     */
    public function chat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'question' => ['required', 'string', 'max:4000'],
        ]);

        $question = trim($validated['question']);
        $apiKey = config('services.openai.api_key') ?: env('OPENAI_API_KEY');

        if (! $apiKey) {
            return response()->json([
                'answer' => 'AI chat is not configured. Please set OPENAI_API_KEY in .env.',
            ], 503);
        }

        try {
            $response = Http::timeout(60)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => 'gpt-4',
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'You are a helpful assistant for 5Core product. Answer concisely and clearly.',
                        ],
                        [
                            'role' => 'user',
                            'content' => $question,
                        ],
                    ],
                    'max_tokens' => 1024,
                    'temperature' => 0.7,
                ]);

            if (! $response->successful()) {
                $body = $response->json();
                $message = $body['error']['message'] ?? $response->body();
                return response()->json([
                    'answer' => 'Sorry, the AI service returned an error: ' . substr($message, 0, 200),
                ], 502);
            }

            $data = $response->json();
            $answer = trim($data['choices'][0]['message']['content'] ?? '');

            if ($answer === '') {
                return response()->json(['answer' => 'No response from the AI. Please try again.']);
            }

            // Store for feedback tracking (optional)
            $record = AiQuestion::create([
                'user_id' => $request->user()->id,
                'question' => $question,
                'ai_answer' => $answer,
            ]);

            return response()->json([
                'answer' => $answer,
                'id' => $record->id,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'answer' => 'Sorry, something went wrong. Please try again.',
            ], 500);
        }
    }

    /**
     * Store feedback (helpful / not helpful) for a previous question.
     */
    public function feedback(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => ['required', 'integer'],
            'helpful' => ['required', 'boolean'],
        ]);

        $record = AiQuestion::where('user_id', $request->user()->id)
            ->find($validated['id']);

        if (! $record) {
            return response()->json(['ok' => false, 'message' => 'Record not found.'], 404);
        }

        $record->update(['helpful' => (bool) $validated['helpful']]);

        return response()->json(['ok' => true]);
    }
}
