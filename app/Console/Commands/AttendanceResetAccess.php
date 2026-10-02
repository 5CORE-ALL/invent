<?php

namespace App\Console\Commands;

use App\Models\AttendanceDevice;
use App\Models\AttendanceScreenshot;
use App\Models\User;
use App\Support\UserAccessControl;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Hand an existing user id over to a new person: destroys every desktop-agent token and device
 * registration, signs out browser sessions, and optionally purges screenshots that came from a
 * previous holder's machine. Same reset that runs automatically when an account is reactivated.
 */
class AttendanceResetAccess extends Command
{
    protected $signature = 'attendance:reset-access
        {user : users.id or email}
        {--purge-device=* : attendance_devices.id whose screenshots should be deleted as well}
        {--force : Skip the confirmation prompt}';

    protected $description = 'Revoke all attendance agent tokens/devices and web sessions of a user (treat the id as a new person)';

    public function handle(): int
    {
        $key = (string) $this->argument('user');
        $user = ctype_digit($key)
            ? User::withTrashed()->find((int) $key)
            : User::withTrashed()->where('email', $key)->first();
        if (! $user) {
            $this->error("User {$key} not found.");

            return self::FAILURE;
        }

        $this->info("User #{$user->id} {$user->name} <{$user->email}>");
        $devices = AttendanceDevice::query()->where('user_id', $user->id)->orderBy('last_seen_at')->get();
        if ($devices->isEmpty()) {
            $this->line('No registered devices.');
        } else {
            $this->table(
                ['device id', 'machine_id', 'name', 'os', 'agent', 'active', 'last seen', 'screenshots'],
                $devices->map(fn (AttendanceDevice $d) => [
                    $d->id,
                    $d->machine_id,
                    (string) $d->device_name,
                    trim($d->os_name.' '.$d->os_version),
                    (string) $d->agent_version,
                    $d->is_active ? 'yes' : 'no',
                    optional($d->last_seen_at)->toDateTimeString() ?? '-',
                    AttendanceScreenshot::query()->where('attendance_device_id', $d->id)->count(),
                ])->all()
            );
        }
        $tokenCount = $user->tokens()->count();
        $this->line("Personal access tokens: {$tokenCount}");

        $purgeIds = array_values(array_filter(array_map('intval', (array) $this->option('purge-device'))));
        if ($purgeIds !== []) {
            $unknown = array_diff($purgeIds, $devices->pluck('id')->all());
            if ($unknown !== []) {
                $this->error('Unknown device id(s) for this user: '.implode(', ', $unknown));

                return self::FAILURE;
            }
        }

        if (! $this->option('force') && ! $this->confirm('Revoke all tokens, remove all devices and sign the user out everywhere?')) {
            return self::SUCCESS;
        }

        $purged = 0;
        if ($purgeIds !== []) {
            // Before the device rows go away (FK nulls the reference on delete).
            $disk = Storage::disk((string) config('attendance.screenshot_disk', 'attendance'));
            AttendanceScreenshot::query()
                ->whereIn('attendance_device_id', $purgeIds)
                ->chunkById(200, function ($shots) use ($disk, &$purged) {
                    foreach ($shots as $shot) {
                        foreach (array_filter([$shot->storage_path, $shot->thumbnail_path]) as $path) {
                            try {
                                $disk->delete($path);
                            } catch (\Throwable) {
                            }
                        }
                        $shot->delete();
                        $purged++;
                    }
                });
        }

        $result = UserAccessControl::resetForNewHolder($user);

        $this->info("Deleted {$result['tokens']} token(s), {$result['devices']} device(s)".($purged ? ", {$purged} screenshot(s)" : '').'; logined reset, remember token rotated.');
        $this->line('The current holder must sign in again in the desktop app (and the portal).');

        return self::SUCCESS;
    }
}
