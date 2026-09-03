<?php

namespace App\Helpers;

use Stichoza\GoogleTranslate\GoogleTranslate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class TranslationHelper
{
    /**
     * Safely translate text into target language with caching and rate-limit exception handling.
     *
     * @param string|null $text Text to translate
     * @param string $targetLang Target language code (e.g. 'ar', 'fr', 'hi', 'ur')
     * @param string $sourceLang Source language code (default 'en')
     * @return string|null
     */
    public static function translate(?string $text, string $targetLang, string $sourceLang = 'en'): ?string
    {
        if ($text === null) {
            return null;
        }

        if (trim($text) === '') {
            return $text;
        }

        $hash = md5($text);
        $src = strtolower($sourceLang);
        $tgt = strtolower($targetLang);

        $cacheKey = "trans_{$src}_{$tgt}_{$hash}";
        $failKey  = "trans_fail_{$src}_{$tgt}_{$hash}";

        // 1. Check successful translation cache (30-day lifetime)
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        // 2. Check 429 / failure cooldown marker (5-minute lifetime)
        if (Cache::has($failKey)) {
            return $text;
        }

        try {
            $tr = new GoogleTranslate($targetLang, $sourceLang);
            $translatedText = $tr->translate($text);

            if ($translatedText !== null && $translatedText !== '') {
                // Cache successful translation for 30 days
                Cache::put($cacheKey, $translatedText, 86400 * 30);
                // Clear failure marker if previously set
                Cache::forget($failKey);
            }

            return $translatedText;
        } catch (Throwable $e) {
            Log::warning("Google Translate failed [{$sourceLang} -> {$targetLang}] for query '{$text}': " . $e->getMessage());

            // Set 5-minute failure cooldown marker to avoid hammering Google on 429 rate limit
            Cache::put($failKey, true, 300);

            // Return original text as fallback
            return $text;
        }
    }
}
