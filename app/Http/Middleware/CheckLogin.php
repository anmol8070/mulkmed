<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Session;

class CheckLogin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $preContext = $this->buildPreContext($request);
        $response = $next($request);
        $response->headers->set('Cache-Control', 'nocache, no-store, max-age=0, must-revalidate');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', 'Sun, 02 Jan 2021 00:00:00 GMT');


      if (Session::get('user_name')) {
            $this->logAdminActivity($request, $response, $preContext);
            return $response;
        } else {
            return redirect(url('/'));
        }
    }

    private function logAdminActivity(Request $request, $response, array $preContext = []): void
    {
        try {
            if ($response->getStatusCode() >= 400) {
                return;
            }

            $method = strtoupper($request->method());
            $path = trim($request->path(), '/');

            $skipKeywords = ['fetch', 'show', 'view', 'get', 'login'];
            $actionKeywords = ['delete', 'remove', 'block', 'unblock', 'ban', 'activate', 'update', 'edit', 'add', 'create', 'store', 'upload'];

            $isStateChangingMethod = in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true);
            $hasActionKeyword = false;
            foreach ($actionKeywords as $keyword) {
                if (stripos($path, $keyword) !== false) {
                    $hasActionKeyword = true;
                    break;
                }
            }

            if (!$isStateChangingMethod && !$hasActionKeyword) {
                return;
            }

            foreach ($skipKeywords as $skipKeyword) {
                if (stripos($path, $skipKeyword) !== false && !$isStateChangingMethod) {
                    return;
                }
            }

            $segments = $request->segments();
            $module = !empty($segments) ? ucfirst($segments[0]) : 'Admin';
            $recordId = $preContext['record_id'] ?? $this->resolveRecordId($request);
            $action = $this->resolveAction($request, $method, $path);
            $actorName = Session::get('user_name');
            $actorType = Session::get('user_type');
            $sanitizedPayload = $this->sanitizePayload($request->all());
            [$oldData, $newData] = $this->buildChangeData($action, $preContext, $sanitizedPayload, $request, $recordId);

            activity_log(
                $action,
                $module,
                $recordId ? (int) $recordId : null,
                'Admin action by ' . ($actorName ?: 'unknown') . ': ' . $method . ' ' . $path,
                $oldData,
                array_merge($newData, [
                    'actor_name' => $actorName,
                    'actor_type' => $actorType,
                    'method' => $method,
                    'path' => $path,
                    'status' => $response->getStatusCode(),
                    'query' => $request->query(),
                ])
            );
        } catch (\Throwable $e) {
            // Never break admin requests due to activity log failures.
        }
    }

    private function resolveAction(Request $request, string $method, string $path): string
    {
        $strictAction = $this->resolveStrictActionFromConfig($request, $path);
        if ($strictAction) {
            return $strictAction;
        }

        if (stripos($path, 'delete') !== false || $method === 'DELETE') {
            return 'deleted';
        }
        if (stripos($path, 'create') !== false || stripos($path, 'store') !== false || stripos($path, 'add') !== false) {
            return 'created';
        }
        if (stripos($path, 'block') !== false) {
            return 'blocked';
        }
        if (stripos($path, 'unblock') !== false) {
            return 'unblocked';
        }
        if (stripos($path, 'ban') !== false) {
            return 'banned';
        }
        if (stripos($path, 'activate') !== false) {
            return 'activated';
        }
        if (stripos($path, 'logout') !== false) {
            return 'logout';
        }

        if (in_array($method, ['PUT', 'PATCH'], true) || stripos($path, 'edit') !== false || stripos($path, 'update') !== false) {
            return 'updated';
        }

        if ($method === 'POST') {
            return 'updated';
        }

        return 'accessed';
    }

    private function resolveStrictActionFromConfig(Request $request, string $path): ?string
    {
        $map = config('activitylog.strict_action_map', []);
        if (empty($map) || !is_array($map)) {
            return null;
        }

        $route = $request->route();
        $routeName = $route ? (string) $route->getName() : '';
        $actionName = $route ? (string) $route->getActionName() : '';
        $methodName = '';
        if (strpos($actionName, '@') !== false) {
            [, $methodName] = explode('@', $actionName, 2);
        }
        $methodName = trim($methodName);
        $normalizedPath = strtolower(trim($path));

        foreach ($map as $key => $action) {
            $key = (string) $key;
            $action = (string) $action;

            if (stripos($key, 'method:') === 0) {
                $value = substr($key, strlen('method:'));
                if ($value !== '' && strcasecmp($methodName, $value) === 0) {
                    return $action;
                }
                continue;
            }

            if (stripos($key, 'route:') === 0) {
                $value = substr($key, strlen('route:'));
                if ($value !== '' && $routeName !== '' && strcasecmp($routeName, $value) === 0) {
                    return $action;
                }
                continue;
            }

            if (stripos($key, 'path:') === 0) {
                $value = strtolower(substr($key, strlen('path:')));
                if ($value !== '' && Str::contains($normalizedPath, $value)) {
                    return $action;
                }
                continue;
            }
        }

        return null;
    }

    private function sanitizePayload(array $payload): array
    {
        $sensitiveKeys = ['password', 'user_password', 'token', '_token', 'authorization', 'secret'];

        foreach ($payload as $key => $value) {
            if (in_array(Str::lower((string) $key), $sensitiveKeys, true)) {
                $payload[$key] = '***';
            }
        }

        return $payload;
    }

    private function resolveOldDataSnapshot(Request $request, $recordId, string $action): ?array
    {
        if (!$recordId) {
            return null;
        }

        if (!in_array($action, ['updated', 'deleted', 'blocked', 'unblocked', 'banned', 'activated'], true)) {
            return null;
        }

        $modelClass = $this->guessModelClass($request);
        if (!$modelClass || !class_exists($modelClass)) {
            return null;
        }

        try {
            $record = $modelClass::query()->find($recordId);
            return $record ? $record->toArray() : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function buildPreContext(Request $request): array
    {
        try {
            $method = strtoupper($request->method());
            $path = trim($request->path(), '/');
            $recordId = $this->resolveRecordId($request);
            $action = $this->resolveAction($request, $method, $path);
            $oldSnapshot = $this->resolveOldDataSnapshot($request, $recordId, $action);

            return [
                'action' => $action,
                'record_id' => $recordId,
                'old_snapshot' => $oldSnapshot,
            ];
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function buildChangeData(string $action, array $preContext, array $payload, Request $request, $recordId): array
    {
        $oldSnapshot = $preContext['old_snapshot'] ?? null;
        $newData = ['payload' => $payload];
        $oldData = $oldSnapshot;

        // For update-like actions, store only changed columns:
        // new_data = changed field -> new value
        // old_data = same field -> old value
        if (in_array($action, ['updated', 'blocked', 'unblocked', 'banned', 'activated'], true) && is_array($oldSnapshot)) {
            $changedOld = [];
            $changedNew = [];

            foreach ($payload as $key => $value) {
                if (!array_key_exists($key, $oldSnapshot)) {
                    continue;
                }

                if (in_array($key, ['updated_at', 'created_at'], true)) {
                    continue;
                }

                if ($this->valuesAreSame($oldSnapshot[$key], $value)) {
                    continue;
                }

                $changedOld[$key] = $oldSnapshot[$key];
                $changedNew[$key] = $value;
            }

            if (!empty($changedNew)) {
                $oldData = $changedOld;
                $newData = $changedNew;
            } else {
                // Fallback if changed fields are not present in request payload.
                $oldData = $oldSnapshot;
                $newData = ['payload' => $payload];
            }
        }

        if ($action === 'deleted' && is_array($oldSnapshot)) {
            $oldData = $oldSnapshot;
            $newData = ['deleted' => true];
        }

        return [$oldData, $newData];
    }

    private function valuesAreSame($oldValue, $newValue): bool
    {
        if (is_bool($oldValue) || is_bool($newValue)) {
            return (bool) $oldValue === (bool) $newValue;
        }

        if (is_numeric($oldValue) && is_numeric($newValue)) {
            return (string) $oldValue === (string) $newValue;
        }

        if (is_array($oldValue) || is_array($newValue)) {
            return json_encode($oldValue) === json_encode($newValue);
        }

        return (string) $oldValue === (string) $newValue;
    }

    private function guessModelClass(Request $request): ?string
    {
        $route = $request->route();
        if (!$route) {
            return null;
        }

        $actionName = (string) $route->getActionName();
        $methodName = '';
        if (strpos($actionName, '@') !== false) {
            [, $methodName] = explode('@', $actionName, 2);
        }

        $source = trim($methodName . ' ' . implode(' ', $request->segments()));
        $source = preg_replace('/([a-z])([A-Z])/', '$1 $2', $source ?? '');
        $source = str_replace(['-', '_', '/'], ' ', (string) $source);
        $words = preg_split('/\s+/', strtolower((string) $source)) ?: [];

        $ignoreWords = [
            'delete', 'remove', 'update', 'edit', 'add', 'create', 'store', 'fetch', 'view',
            'get', 'post', 'put', 'patch', 'from', 'admin', 'by', 'to', 'list', 'all',
            'block', 'unblock', 'ban', 'activate', 'deactivate', 'index', 'show'
        ];

        $entityWords = array_values(array_filter($words, function ($word) use ($ignoreWords) {
            return $word !== '' && !in_array($word, $ignoreWords, true);
        }));

        $candidates = [];
        $count = count($entityWords);
        for ($i = 0; $i < $count; $i++) {
            // Build candidates with contiguous 1..4 word n-grams
            // so method names like "editCommonHealthProblems" map correctly.
            for ($len = 1; $len <= 4; $len++) {
                if (($i + $len) > $count) {
                    break;
                }

                $chunk = array_slice($entityWords, $i, $len);
                $phrase = implode(' ', $chunk);

                $candidates[] = Str::studly($phrase);
                $candidates[] = Str::studly(Str::singular($phrase));
            }
        }

        $fallbackMap = [
            'blockuserfromadmin' => 'App\\Models\\User',
            'unblockuserfromadmin' => 'App\\Models\\User',
            'deletetophospitals' => 'App\\Models\\TopHospitals',
            'deletebestoffersplans' => 'App\\Models\\BestOfferPlans',
        ];
        $methodKey = strtolower($methodName);
        if (isset($fallbackMap[$methodKey])) {
            return $fallbackMap[$methodKey];
        }

        $candidateClasses = [];
        foreach (array_unique($candidates) as $candidate) {
            $class = 'App\\Models\\' . $candidate;
            if (class_exists($class)) {
                return $class;
            }
            $candidateClasses[] = $candidate;

            // Handle projects with plural model naming style (e.g. Agencies).
            $pluralClass = 'App\\Models\\' . Str::plural($candidate);
            if (class_exists($pluralClass)) {
                return $pluralClass;
            }
            $candidateClasses[] = Str::plural($candidate);
        }

        $fuzzyClass = $this->findBestMatchingModelClass(
            $candidateClasses,
            $entityWords,
            $methodName,
            $request->segments()
        );
        if ($fuzzyClass) {
            return $fuzzyClass;
        }

        return null;
    }

    private function findBestMatchingModelClass(array $candidateClasses, array $entityWords, string $methodName, array $segments): ?string
    {
        $allModelClasses = $this->getAllAppModelClasses();
        if (empty($allModelClasses)) {
            return null;
        }

        $candidateTokens = [];
        foreach ($candidateClasses as $candidateClass) {
            $candidateTokens = array_merge($candidateTokens, $this->tokenizeIdentifier($candidateClass));
        }
        foreach ($entityWords as $word) {
            $candidateTokens[] = Str::singular(Str::lower((string) $word));
        }
        $candidateTokens = array_values(array_unique(array_filter($candidateTokens)));

        if (empty($candidateTokens)) {
            $candidateTokens = $this->tokenizeIdentifier($methodName . ' ' . implode(' ', $segments));
        }
        if (empty($candidateTokens)) {
            return null;
        }

        $bestClass = null;
        $bestScore = 0;

        foreach ($allModelClasses as $class) {
            $modelTokens = $this->tokenizeIdentifier(class_basename($class));
            if (empty($modelTokens)) {
                continue;
            }

            $intersections = array_intersect($candidateTokens, $modelTokens);
            $score = count($intersections);

            if ($score > 0 && empty(array_diff($candidateTokens, $modelTokens))) {
                $score += 2;
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestClass = $class;
            }
        }

        if ($bestScore < 2) {
            return null;
        }

        return $bestClass;
    }

    private function tokenizeIdentifier(string $value): array
    {
        $normalized = preg_replace('/([a-z])([A-Z])/', '$1 $2', $value);
        $normalized = str_replace(['\\', '/', '-', '_'], ' ', (string) $normalized);
        $parts = preg_split('/\s+/', strtolower(trim((string) $normalized))) ?: [];

        $tokens = [];
        foreach ($parts as $part) {
            if ($part === '' || is_numeric($part)) {
                continue;
            }
            $tokens[] = Str::singular($part);
        }

        return array_values(array_unique($tokens));
    }

    private function getAllAppModelClasses(): array
    {
        static $classes = null;
        if ($classes !== null) {
            return $classes;
        }

        $classes = [];
        foreach (glob(app_path('Models/*.php')) ?: [] as $filePath) {
            $base = pathinfo($filePath, PATHINFO_FILENAME);
            if ($base === '' || Str::contains(Str::lower($base), 'copy')) {
                continue;
            }
            $class = 'App\\Models\\' . $base;
            if (class_exists($class)) {
                $classes[] = $class;
            }
        }

        return $classes;
    }

    private function resolveRecordId(Request $request)
    {
        $routeId = $request->route('id');
        if ($this->isUsableId($routeId)) {
            return $routeId;
        }

        $route = $request->route();
        if ($route) {
            foreach ((array) $route->parameters() as $value) {
                if ($this->isUsableId($value)) {
                    return $value;
                }
            }
        }

        $payload = $request->all();
        if ($this->isUsableId($payload['id'] ?? null)) {
            return $payload['id'];
        }

        $priorityKeys = [
            'agency_id', 'doctor_id', 'user_id', 'category_id', 'cat_id', 'service_id', 'item_id',
            'holiday_id', 'experience_id', 'award_id', 'slot_id', 'faq_id', 'review_id', 'coupon_id'
        ];
        foreach ($priorityKeys as $key) {
            if ($this->isUsableId($payload[$key] ?? null)) {
                return $payload[$key];
            }
        }

        foreach ($payload as $key => $value) {
            if (Str::endsWith((string) $key, '_id') && $this->isUsableId($value)) {
                return $value;
            }
        }

        return null;
    }

    private function isUsableId($value): bool
    {
        return $value !== null && $value !== '' && is_numeric($value);
    }

}
