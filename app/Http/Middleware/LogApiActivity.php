<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class LogApiActivity
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if (!$request->is('api/*')) {
            return $response;
        }

        if (!$request->user()) {
            return $response;
        }

        activity_log(
            'api_request',
            'API',
            null,
            $request->method() . ' ' . $request->path(),
            [
                'query' => $request->query(),
            ],
            [
                'status' => $response->status(),
            ]
        );

        return $response;
    }
}
