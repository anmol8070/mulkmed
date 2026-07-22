<?php

use App\Jobs\LogActivityJob;
use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

if (!function_exists('activity_log')) {
    /**
     * Persist activity logs in a centralized, reusable way.
     */
    function activity_log(
        string $action,
        string $module,
        ?int $recordId = null,
        ?string $description = null,
        $oldData = null,
        $newData = null
    ): ?ActivityLog {
        try {
            $user = Auth::user();
            $sessionUserId = Session::get('user_id');
            $sessionUserName = Session::get('user_name');
            $sessionUserType = Session::get('user_type');
            $resolvedUserId = $user->id ?? $sessionUserId;
            $resolvedUserType = $user ? get_class($user) : ($sessionUserId ? 'admin_session' : 'guest');
            $normalizedNewData = normalize_activity_log_data($newData);

            if ($sessionUserName || $sessionUserType) {
                $normalizedNewData = $normalizedNewData ?? [];
                $normalizedNewData['_actor'] = [
                    'name' => $sessionUserName,
                    'type' => $sessionUserType,
                    'id' => $sessionUserId,
                ];
            }

            $payload = [
                'user_id' => $resolvedUserId,
                'user_type' => $resolvedUserType,
                'action' => Str::lower(trim($action)),
                'module' => trim($module),
                'record_id' => $recordId,
                'description' => $description,
                'old_data' => normalize_activity_log_data($oldData),
                'new_data' => $normalizedNewData,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'request_id' => request()->headers->get('X-Request-Id'),
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (config('activitylog.queue', false)) {
                LogActivityJob::dispatch($payload);
                return null;
            }

            return ActivityLog::create($payload);
        } catch (\Throwable $e) {
            Log::error('activity_log() failed', [
                'message' => $e->getMessage(),
                'action' => $action,
                'module' => $module,
                'record_id' => $recordId,
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }
}

if (!function_exists('normalize_activity_log_data')) {
    function normalize_activity_log_data($data): ?array
    {
        if ($data === null) {
            return null;
        }

        if (is_array($data)) {
            return $data;
        }

        if ($data instanceof \Illuminate\Contracts\Support\Arrayable) {
            return $data->toArray();
        }

        if (is_object($data)) {
            return (array) $data;
        }

        return ['value' => $data];
    }
}
