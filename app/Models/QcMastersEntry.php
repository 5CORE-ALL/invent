<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class QcMastersEntry extends Model
{
    protected $table = 'qc_masters_entries';

    protected $fillable = [
        'product_master_id',
        'problem_issue',
        'suggestion_improve',
        'image_path',
        'image_size_kb',
        'video_path',
        'video_size_kb',
        'user_history',
    ];

    protected $casts = [
        'image_size_kb' => 'integer',
        'video_size_kb' => 'integer',
        'user_history' => 'array',
    ];

    public function productMaster(): BelongsTo
    {
        return $this->belongsTo(ProductMaster::class, 'product_master_id');
    }

    /**
     * Append a history entry. Date label format: 1Apr
     */
    public function appendUserHistory(string $action, ?string $field = null): void
    {
        $history = is_array($this->user_history) ? $this->user_history : [];
        $now = Carbon::now();

        $history[] = [
            'user' => Auth::check() ? (string) (Auth::user()->name ?? Auth::user()->email ?? 'User') : 'System',
            'date' => $now->format('jM'),
            'datetime' => $now->toDateTimeString(),
            'action' => $action,
            'field' => $field,
        ];

        // Keep last 50 entries
        if (count($history) > 50) {
            $history = array_values(array_slice($history, -50));
        }

        $this->user_history = $history;
    }

    /**
     * Latest display label, e.g. "Shobha 1Apr"
     */
    public function latestUserHistoryLabel(): string
    {
        $history = is_array($this->user_history) ? $this->user_history : [];
        if ($history === []) {
            return '';
        }
        $last = $history[count($history) - 1];
        $user = trim((string) ($last['user'] ?? ''));
        $date = trim((string) ($last['date'] ?? ''));
        if ($user === '' && $date === '') {
            return '';
        }
        if ($user === '') {
            return $date;
        }
        if ($date === '') {
            return $user;
        }

        return $user.' '.$date;
    }
}
