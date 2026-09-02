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

if (!function_exists('getMetricValue')) {
    function getMetricValue($metric, $fallback = 0) {
        $extractNumber = function($val) use ($fallback) {
            if (is_null($val) || $val === '') return $fallback;
            if (is_numeric($val)) return (float) $val;
            
            // Remove everything except numbers, dot, comma, and minus sign
            $cleaned = preg_replace('/[^0-9.,-]/', '', (string)$val);
            if ($cleaned === '') return $fallback;
            
            // If there's a comma used as thousand separator, remove it for parsing
            // Check if comma is followed by exactly 3 digits
            if (preg_match('/,\d{3}/', $cleaned)) {
                $cleaned = str_replace(',', '', $cleaned);
            } else {
                // If comma is used as decimal separator, replace with dot
                $cleaned = str_replace(',', '.', $cleaned);
            }
            
            return (float) $cleaned;
        };

        if (is_object($metric) && isset($metric->result)) {
            return $extractNumber($metric->result);
        } elseif (is_array($metric) && isset($metric['result'])) {
            return $extractNumber($metric['result']);
        }
        return $extractNumber($metric);
    }

    function getMetricDisplay($metric, $fallbackFormat = null) {
        $extractDisplay = function($val) use ($fallbackFormat) {
            if (is_null($val) || $val === '') return '-';
            
            if ($fallbackFormat === 'round') {
                $num = getMetricValue($val);
                return round($num);
            } elseif ($fallbackFormat === 'format2') {
                $num = getMetricValue($val);
                return number_format($num, 2);
            }
            // For strings with units like "84 bpm", strip the text and keep just the formatted number
            if (is_string($val) && preg_match('/^([0-9.,]+)\s*[a-zA-Z%]+/', trim($val), $matches)) {
                return $matches[1];
            }
            return $val;
        };

        if (is_object($metric) && isset($metric->result)) {
            return $extractDisplay($metric->result);
        } elseif (is_array($metric) && isset($metric['result'])) {
            return $extractDisplay($metric['result']);
        }
        return $extractDisplay($metric);
    }

    function getMetricUnit($metric, $fallbackUnit) {
        if (is_object($metric) && isset($metric->unit)) {
            return $metric->unit ?: $fallbackUnit;
        } elseif (is_array($metric) && isset($metric['unit'])) {
            return $metric['unit'] ?: $fallbackUnit;
        }
        return $fallbackUnit;
    }

    function getMetricRange($metric, $fallbackRange) {
        if (is_object($metric) && isset($metric->normal_range)) {
            return $metric->normal_range ?: $fallbackRange;
        } elseif (is_array($metric) && isset($metric['normal_range'])) {
            return $metric['normal_range'] ?: $fallbackRange;
        }
        return $fallbackRange;
    }
}
