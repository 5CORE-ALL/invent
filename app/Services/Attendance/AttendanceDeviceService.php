<?php

namespace App\Services\Attendance;

use App\Models\AttendanceDevice;
use App\Models\User;

class AttendanceDeviceService
{
    /**
     * @param  array{machine_id: string, device_name?: string, os_name?: string, os_version?: string, agent_version?: string}  $payload
     */
    public function registerOrUpdate(User $user, array $payload): AttendanceDevice
    {
        $machineId = mb_substr(trim((string) ($payload['machine_id'] ?? '')), 0, 120);
        if ($machineId === '') {
            throw new \InvalidArgumentException('machine_id is required');
        }

        return AttendanceDevice::updateOrCreate(
            ['user_id' => $user->id, 'machine_id' => $machineId],
            [
                'device_name' => $payload['device_name'] ?? null,
                'os_name' => $payload['os_name'] ?? null,
                'os_version' => $payload['os_version'] ?? null,
                'agent_version' => $payload['agent_version'] ?? null,
                'last_seen_at' => now(),
                'is_active' => true,
            ]
        );
    }

    public function touch(AttendanceDevice $device, ?string $agentVersion = null): AttendanceDevice
    {
        $device->update([
            'last_seen_at' => now(),
            'agent_version' => $agentVersion ?? $device->agent_version,
        ]);

        return $device->fresh();
    }
}
