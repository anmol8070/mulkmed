<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        'senoclock/test/*',
        'newshenai-care/*',
        'api/newshenai-care/*',
        'api/v1/newshenai-care/*',
        'api/v2/newshenai-care/*',
    ];
}
