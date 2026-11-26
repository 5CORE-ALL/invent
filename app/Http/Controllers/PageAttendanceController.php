<?php

namespace App\Http\Controllers;

use App\Models\PageAttendanceAssignment;
use App\Services\PageAttendanceService;
use App\Support\SuperAdminAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PageAttendanceController extends Controller
{
    public function __construct(private PageAttendanceService $service) {}

    private function guard(string $pageKey): ?JsonResponse
    {
        if (! isset(PageAttendanceService::pages()[$pageKey])) {
            return response()->json(['success' => false, 'message' => 'Unknown page.'], 404);
        }
        if (! PageAttendanceService::available()) {
            return response()->json(['success' => false, 'message' => 'Page attendance is not set up yet - run the database migration.'], 503);
        }

        return null;
    }

    private function isAdmin(): bool
    {
        return SuperAdminAccess::isTaskAdmin(auth()->user());
    }

    public function state(string $pageKey): JsonResponse
    {
        if ($r = $this->guard($pageKey)) {
            return $r;
        }

        return response()->json($this->service->state($pageKey, auth()->user(), $this->isAdmin()));
    }

    public function checkIn(Request $request, string $pageKey): JsonResponse
    {
        if ($r = $this->guard($pageKey)) {
            return $r;
        }
        $data = $request->validate(['note' => 'nullable|string|max:500']);
        $result = $this->service->checkIn(auth()->user(), $pageKey, $data['note'] ?? null);

        return response()->json(
            ['success' => $result['ok'], 'message' => $result['message']] + $this->service->state($pageKey, auth()->user(), $this->isAdmin()),
            $result['ok'] ? 200 : 403
        );
    }

    public function assign(Request $request, string $pageKey): JsonResponse
    {
        if ($r = $this->guard($pageKey)) {
            return $r;
        }
        if (! $this->isAdmin()) {
            return response()->json(['success' => false, 'message' => 'Only admins can assign people.'], 403);
        }
        $data = $request->validate([
            'users' => 'required|array|min:1',
            'users.*' => 'integer|exists:users,id',
            'frequencies' => 'required|array|min:1',
            'frequencies.*' => 'integer|min:1|max:365',
        ]);

        $r = $this->service->assign(
            $pageKey,
            array_map('intval', $data['users']),
            array_map('intval', $data['frequencies']),
            auth()->user()
        );
        $parts = [];
        if ($r['created'] + $r['reactivated'] > 0) {
            $parts[] = ($r['created'] + $r['reactivated']).' assigned';
        }
        if ($r['existing'] > 0) {
            $parts[] = $r['existing'].' already assigned';
        }

        return response()->json(
            ['success' => true, 'message' => implode(', ', $parts) ?: 'Nothing changed.'] + $this->service->state($pageKey, auth()->user(), true)
        );
    }

    public function remove(string $pageKey, int $id): JsonResponse
    {
        if ($r = $this->guard($pageKey)) {
            return $r;
        }
        if (! $this->isAdmin()) {
            return response()->json(['success' => false, 'message' => 'Only admins can remove people.'], 403);
        }
        PageAttendanceAssignment::query()->where('id', $id)->where('page_key', $pageKey)->update(['active' => false]);

        return response()->json(
            ['success' => true, 'message' => 'Removed.'] + $this->service->state($pageKey, auth()->user(), true)
        );
    }
}
