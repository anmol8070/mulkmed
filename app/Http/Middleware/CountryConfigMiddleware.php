<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

class CountryConfigMiddleware
{
    public function handle($request, Closure $next)
    {
        $country = strtolower(
            $request->header('X-Country')
            ?? $request->get('country')
            ?? optional($request->user())->country
        );

        if ($country == "ind") {
            // 1. Dynamic DB name
            $dbConnection = "mulkmed_india";
            Config::set('database.default', $dbConnection);

            // 2. Dynamic domain
            $domain = "https://indiamulkmed.reapmind.com";
            Config::set('app.url', $domain);
        }

        if (Str::lower(request()->getHost()) === 'indiamulkmed.reapmind.com') {
            // 1. Dynamic DB connection
            Config::set('database.default', 'mulkmed_india');
            Config::set('app.url', 'https://indiamulkmed.reapmind.com');
        }

        return $next($request);
    }
}
