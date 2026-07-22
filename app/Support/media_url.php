<?php

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

if (! function_exists('media_url')) {
    function media_url(?string $path): ?string {
        if (!$path) return null;
        if (Str::startsWith($path, ['http://','https://'])) return $path;
        return Storage::disk('public')->url($path); // /storage/uploads/...
    }
}