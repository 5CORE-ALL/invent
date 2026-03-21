<?php

namespace App\Services\Wms;

use App\Models\User;
use App\Models\Wms\WmsAuditLog;
use Illuminate\Http\Request;

class WmsAuditService
{
    public function log(?User $user, string $action, ?string $subjectType = null, ?int $subjectId = null, ?array $meta = null, ?Request $request = null): WmsAuditLog
    {
        return WmsAuditLog::create([
            'user_id' => $user?->id,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'meta' => $meta,
            'ip_address' => $request?->ip(),
        ]);
    }
}
