<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\UserAccessControl;
use App\Support\UserAccountStatus;
use App\Support\UserSettingsAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserSettingsController extends Controller
{
    private const ROLES = ['viewer', 'user', 'manager', 'admin', 'superadmin'];

    public function index(): View
    {
        $this->authorizeManage();

        $users = User::withTrashed()
            ->select([
                'id', 'name', 'email', 'phone', 'designation', 'role', 'avatar',
                'org_level', 'date_of_joining', 'is_active', 'deactivated_at',
                'deleted_at', 'stay_logged_in', 'logined',
            ])
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => $this->serialize($user));

        return view('pages.user-settings', [
            'users' => $users,
            'roles' => self::ROLES,
            'currentUserId' => (int) auth()->id(),
        ]);
    }

    public function activate(int $id): JsonResponse
    {
        $this->authorizeManage();
        $user = $this->findUser($id);
        UserAccountStatus::apply($user, UserAccountStatus::ACTIVE);

        return $this->ok($this->refreshUser($user), 'User activated. They can sign in and appear in Team Monitoring.');
    }

    public function deactivate(int $id): JsonResponse
    {
        $this->authorizeManage();
        $user = $this->findUser($id);
        if ($denied = $this->denySelf($user, 'deactivate')) {
            return $denied;
        }
        UserAccountStatus::apply($user, UserAccountStatus::INACTIVE);

        return $this->ok($this->refreshUser($user), 'User deactivated. Inventory, attendance, and Team Monitoring access are blocked.');
    }

    public function destroy(int $id): JsonResponse
    {
        $this->authorizeManage();
        $user = $this->findUser($id);
        if ($denied = $this->denySelf($user, 'delete')) {
            return $denied;
        }
        UserAccountStatus::apply($user, UserAccountStatus::DELETED);

        return $this->ok($this->refreshUser($user), 'User deleted.');
    }

    public function bulk(Request $request): JsonResponse
    {
        $this->authorizeManage();
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'action' => ['required', 'in:activate,deactivate'],
        ]);

        $selfId = (int) auth()->id();
        $updated = 0;
        $skipped = 0;

        foreach ($data['ids'] as $id) {
            $user = User::withTrashed()->find((int) $id);
            if (! $user) {
                $skipped++;
                continue;
            }
            if ($data['action'] === 'deactivate' && $user->id === $selfId) {
                $skipped++;
                continue;
            }
            UserAccountStatus::apply(
                $user,
                $data['action'] === 'activate' ? UserAccountStatus::ACTIVE : UserAccountStatus::INACTIVE
            );
            $updated++;
        }

        return response()->json([
            'success' => true,
            'message' => $data['action'] === 'activate'
                ? "Activated {$updated} user(s)."
                : "Deactivated {$updated} user(s). They are blocked from inventory and Team Monitoring.",
            'updated' => $updated,
            'skipped' => $skipped,
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->authorizeManage();
        $user = $this->findUser($id);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'designation' => ['nullable', 'string', 'max:255'],
            'role' => ['sometimes', 'string', Rule::in(self::ROLES)],
            'stay_logged_in' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('stay_logged_in', $data) && $data['stay_logged_in']) {
            $data['logined'] = 1;
        }

        $user->fill($data);
        $user->save();

        return $this->ok($this->refreshUser($user), 'User settings saved.');
    }

    public function resetPassword(Request $request, int $id): JsonResponse
    {
        $this->authorizeManage();
        $user = $this->findUser($id);

        $data = $request->validate([
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'generate' => ['sometimes', 'boolean'],
        ]);

        $plain = null;
        if (! empty($data['generate']) || empty($data['password'])) {
            $plain = Str::password(12);
        } else {
            $plain = $data['password'];
        }

        $user->password = $plain;
        $user->save();
        UserAccessControl::revoke($user);

        return response()->json([
            'success' => true,
            'message' => 'Password updated. Existing sessions and the desktop agent were signed out.',
            'generated_password' => $plain,
            'user' => $this->serialize($this->refreshUser($user)),
        ]);
    }

    public function kick(int $id): JsonResponse
    {
        $this->authorizeManage();
        $user = $this->findUser($id);
        UserAccessControl::revoke($user);

        return $this->ok($this->refreshUser($user), 'Signed out of inventory and Team Monitoring (desktop agent included).');
    }

    private function authorizeManage(): void
    {
        abort_unless(UserSettingsAccess::canManage(), 403, 'Not authorised to manage users.');
    }

    private function findUser(int $id): User
    {
        return User::withTrashed()->findOrFail($id);
    }

    private function refreshUser(User $user): User
    {
        return User::withTrashed()->findOrFail($user->id);
    }

    private function denySelf(User $user, string $action): ?JsonResponse
    {
        if ($user->id === auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => "You cannot {$action} your own account.",
            ], 422);
        }

        return null;
    }

    private function ok(?User $user, string $message): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'user' => $user ? $this->serialize($user) : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(User $user): array
    {
        $status = UserAccountStatus::for($user);
        $role = strtolower(trim((string) ($user->role ?? 'user')));
        $isAdmin = in_array($role, ['admin', 'superadmin', 'manager'], true)
            || UserSettingsAccess::canManage($user);

        $avatarUrl = null;
        if (! empty($user->avatar)) {
            $avatarUrl = str_starts_with((string) $user->avatar, 'http')
                ? $user->avatar
                : asset('storage/'.$user->avatar);
        }

        $parts = preg_split('/\s+/', trim((string) $user->name)) ?: [];
        $initials = strtoupper(substr($parts[0] ?? 'U', 0, 1).(isset($parts[1]) ? substr($parts[1], 0, 1) : ''));

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'designation' => $user->designation,
            'role' => $role !== '' ? $role : 'user',
            'org_level' => $user->org_level,
            'date_of_joining' => optional($user->date_of_joining)->format('Y-m-d'),
            'avatar_url' => $avatarUrl,
            'initials' => $initials !== '' ? $initials : 'U',
            'is_admin' => $isAdmin,
            'status' => $status,
            'status_label' => UserAccountStatus::label($status),
            'stay_logged_in' => (bool) $user->stay_logged_in,
            'deactivated_at' => optional($user->deactivated_at)->toDateTimeString(),
        ];
    }
}
