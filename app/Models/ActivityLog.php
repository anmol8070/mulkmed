<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    protected $fillable = [
        'user_id',
        'user_type',
        'action',
        'module',
        'record_id',
        'description',
        'old_data',
        'new_data',
        'ip_address',
        'user_agent',
        'request_id',
    ];

    protected $casts = [
        'old_data' => 'array',
        'new_data' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['user_id'] ?? null, function (Builder $q, $userId): Builder {
                return $q->where('user_id', $userId);
            })
            ->when($filters['module'] ?? null, function (Builder $q, $module): Builder {
                return $q->where('module', $module);
            })
            ->when($filters['action'] ?? null, function (Builder $q, $action): Builder {
                return $q->where('action', $action);
            })
            ->when($filters['from'] ?? null, function (Builder $q, $from): Builder {
                return $q->whereDate('created_at', '>=', $from);
            })
            ->when($filters['to'] ?? null, function (Builder $q, $to): Builder {
                return $q->whereDate('created_at', '<=', $to);
            });
    }
}
