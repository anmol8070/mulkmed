<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class MergeRawJsonBody
{
    /**
     * When Postman sends raw JSON with Content-Type "text/plain" or missing,
     * Laravel does not populate $request->all(). Merge decoded JSON into the request.
     */
    public function handle(Request $request, Closure $next)
    {
        if (!in_array($request->getMethod(), ['POST', 'PUT', 'PATCH'], true)) {
            return $next($request);
        }

        if (count($request->request->all()) > 0) {
            return $next($request);
        }

        $content = $request->getContent();
        if ($content === '' || $content === false) {
            return $next($request);
        }

        $trimmed = ltrim($content);
        if ($trimmed === '' || ($trimmed[0] !== '{' && $trimmed[0] !== '[')) {
            return $next($request);
        }

        $decoded = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return $next($request);
        }

        if (array_is_list($decoded)) {
            return $next($request);
        }

        $request->merge($decoded);

        return $next($request);
    }
}
