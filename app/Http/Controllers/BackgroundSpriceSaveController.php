<?php

namespace App\Http\Controllers;

use App\Services\Support\BackgroundSpriceSave;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Hand-over point for "Save and Apply": takes the S PRC values the page computed and writes them
 * in a detached server process, so closing the page does not stop the save.
 */
class BackgroundSpriceSaveController extends Controller
{
    public function enqueue(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Not signed in'], 401);
        }

        $path = BackgroundSpriceSave::allowedPath((string) $request->input('url', ''));
        if ($path === null) {
            return response()->json(['success' => false, 'message' => 'Not an S PRC save route'], 422);
        }

        $updates = $request->input('updates');
        if (! is_array($updates) || $updates === []) {
            return response()->json(['success' => false, 'message' => 'Nothing to save'], 422);
        }
        if (count($updates) > BackgroundSpriceSave::MAX_UPDATES) {
            return response()->json(['success' => false, 'message' => 'Too many rows'], 422);
        }
        $updates = array_values(array_filter($updates, 'is_array'));

        $extra = [];
        foreach ((array) $request->input('extra', []) as $k => $v) {
            if (is_string($k) && (is_scalar($v) || $v === null)) {
                $extra[$k] = $v;
            }
        }
        unset($extra['updates']);

        $mode = (string) $request->input('mode', 'batch') === 'single' ? 'single' : 'batch';
        $label = mb_substr(trim((string) $request->input('label', 'S PRC')), 0, 120);

        $result = BackgroundSpriceSave::enqueue((int) $user->id, $path, $updates, $extra, $mode, $label);
        if (! $result['spawned']) {
            @unlink(BackgroundSpriceSave::payloadPath($result['id']));

            return response()->json(['success' => false, 'message' => 'Could not start the background process'], 500);
        }

        return response()->json([
            'success' => true,
            'id' => $result['id'],
            'count' => count($updates),
            'message' => 'Saving '.count($updates).' SKU(s) in the background. You can close this page.',
        ]);
    }

    public function status(string $id): JsonResponse
    {
        if (! BackgroundSpriceSave::isValidId($id)) {
            return response()->json(['success' => false, 'message' => 'Bad id'], 422);
        }
        $status = BackgroundSpriceSave::readStatus($id);
        if (! $status) {
            return response()->json(['success' => false, 'message' => 'Unknown job'], 404);
        }

        return response()->json(['success' => true] + $status);
    }
}
