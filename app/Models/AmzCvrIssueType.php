<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AmzCvrIssueType extends Model
{
    protected $table = 'amz_cvr_issue_types';

    protected $fillable = [
        'issue_key',
        'label',
        'assignee_user_id',
        'assignee_email',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'assignee_user_id' => 'integer',
        'sort_order' => 'integer',
    ];

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_user_id');
    }

    public static function makeIssueKey(string $label): string
    {
        $base = strtolower(trim($label));
        $base = preg_replace('/\s+issue$/i', '', $base) ?? $base;
        $base = preg_replace('/[^a-z0-9]+/', '_', $base) ?? '';
        $base = trim($base, '_');
        if ($base === '') {
            $base = 'custom';
        }

        $key = 'custom_' . $base;
        $candidate = $key;
        $i = 2;
        while (static::query()->where('issue_key', $candidate)->exists()) {
            $candidate = $key . '_' . $i;
            $i++;
        }

        return $candidate;
    }

    public static function normalizeLabel(string $label): string
    {
        $label = trim(preg_replace('/\s+/', ' ', $label) ?? '');
        if ($label === '') {
            return '';
        }
        if (! preg_match('/\bissue$/i', $label)) {
            $label .= ' Issue';
        }

        return $label;
    }
}
