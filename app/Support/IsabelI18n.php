<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

class IsabelI18n
{
    protected static array $cache = [];

    public static function get(string $key, string $locale = 'en', string $fallback = 'en'): string
    {
        $val = self::getFromLocale($key, $locale);
        if ($val !== null && $val !== '') {
            return $val;
        }

        if ($locale !== $fallback) {
            $val = self::getFromLocale($key, $fallback);
            if ($val !== null && $val !== '') {
                return $val;
            }
        }

        // final fallback: show the key (debug-friendly)
        return $key;
    }

    protected static function getFromLocale(string $key, string $locale): ?string
    {
        $data = self::load($locale);
        if (!$data) {
            return null;
        }

        // Support two shapes:
        // A) your current file returns ['questions'=>..., 'answers'=>...]
        // B) grouped file returns ['IsabelQuestionAnswerTranslation'=>['questions'=>...,'answers'=>...]]
        if (isset($data['IsabelQuestionAnswerTranslation']) && is_array($data['IsabelQuestionAnswerTranslation'])) {
            $data = $data['IsabelQuestionAnswerTranslation'];
        }

        return self::arrayGetDot($data, $key);
    }

    protected static function load(string $locale): array
    {
        if (isset(self::$cache[$locale])) {
            return self::$cache[$locale];
        }

        // Try multiple candidate paths to match your deployment
        $candidates = [
            base_path("public_html/resources/lang/IsabelQuestionAnswerTranslation/{$locale}.php"),
            base_path("resources/lang/IsabelQuestionAnswerTranslation/{$locale}.php"),
            resource_path("lang/IsabelQuestionAnswerTranslation/{$locale}.php"),
            // bonus: allow standard Laravel layout if you ever move them
            resource_path("lang/{$locale}/IsabelQuestionAnswerTranslation.php"),
        ];

        foreach ($candidates as $path) {
            if (is_file($path)) {
                $arr = include $path;

                if (!is_array($arr)) {
                    Log::warning('IsabelI18n: lang file did not return array', ['path' => $path, 'locale' => $locale]);
                    continue;
                }

                // success
                self::$cache[$locale] = $arr;
                return self::$cache[$locale];
            }
        }

        Log::warning('IsabelI18n: lang file not found for locale', ['locale' => $locale, 'paths_tried' => $candidates]);
        return self::$cache[$locale] = [];
    }

    protected static function arrayGetDot(array $array, string $key)
    {
        $segments = explode('.', $key);
        foreach ($segments as $segment) {
            // Handle int keys like 1, 2, 3
            if (is_array($array) && array_key_exists($segment, $array)) {
                $array = $array[$segment];
            } elseif (is_array($array) && array_key_exists((int)$segment, $array)) {
                $array = $array[(int)$segment];
            } else {
                return null;
            }
        }
        return is_scalar($array) ? (string)$array : null;
    }
}
