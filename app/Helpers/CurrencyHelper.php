<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Http;

class CurrencyHelper
{
    public static function rates(string $base = 'AED'): array
    {
        return cache()->remember("currency_rates_{$base}", 3600, function () use ($base) {
            $response = Http::get("https://open.er-api.com/v6/latest/{$base}");

            if (! $response->successful()) {
                return [];
            }

            return $response->json('rates') ?? [];
        });
    }

    public static function convert(
        float $amount,
        string $to,
        string $base = 'AED'
    ): float {
        $rates = self::rates($base);

        if (!isset($rates[$to])) {
            return 0;
        }

        return round($amount * $rates[$to], 2);
    }

    public static function currencies(string $base = 'AED'): array
    {
        $rates = self::rates($base);

        return array_map(
            fn ($symbol, $value) => [
                'symbol' => $symbol
            ],
            array_keys($rates),
            $rates
        );
    }


}
