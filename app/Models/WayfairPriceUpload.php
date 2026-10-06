<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WayfairPriceUpload extends Model
{
    public const GENERATED = 'GENERATED';

    public const QUEUED = 'QUEUED';

    public const UPLOADING = 'UPLOADING';

    public const UPLOADED = 'UPLOADED';

    public const PROCESSING = 'PROCESSING';

    public const SUCCESS = 'SUCCESS';

    public const FAILED = 'FAILED';

    public const CANCELLED = 'CANCELLED';

    public const NO_CHANGES = 'NO_CHANGES';

    public const REQUIRES_REVIEW = 'REQUIRES_REVIEW';

    public const STUCK = 'STUCK';

    public const AUTH_REQUIRED = 'AUTH_REQUIRED';

    protected $table = 'wayfair_price_uploads';

    protected $fillable = [
        'filename',
        'file_path',
        'file_type',
        'file_sha256',
        'generated_at',
        'upload_started_at',
        'uploaded_at',
        'processed_at',
        'status',
        'total_rows',
        'changed_rows',
        'successful_rows',
        'failed_rows',
        'wayfair_reference',
        'wayfair_response',
        'price_snapshot',
        'error_message',
        'attempts',
        'last_attempt_at',
        'next_retry_at',
        'created_by',
    ];

    protected $casts = [
        'generated_at' => 'datetime',
        'upload_started_at' => 'datetime',
        'uploaded_at' => 'datetime',
        'processed_at' => 'datetime',
        'last_attempt_at' => 'datetime',
        'next_retry_at' => 'datetime',
        'price_snapshot' => 'array',
        'total_rows' => 'integer',
        'changed_rows' => 'integer',
        'successful_rows' => 'integer',
        'failed_rows' => 'integer',
        'attempts' => 'integer',
    ];

    public static function manualRetryStatuses(): array
    {
        return [self::FAILED, self::STUCK, self::AUTH_REQUIRED, self::REQUIRES_REVIEW];
    }
}
