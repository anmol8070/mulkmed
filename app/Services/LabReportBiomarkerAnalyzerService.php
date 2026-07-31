<?php

namespace App\Services;

use App\Models\MajorOrganTest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LabReportBiomarkerAnalyzerService
{
    /** @var array<string, string[]> */
    protected array $aliases = [
        'complete blood count' => ['cbc', 'cbs', 'complete blood count (cbs)', 'complete blood count (cbc)', 'haemogram', 'hemogram'],
        'hemoglobin' => ['hb', 'haemoglobin', 'hgb'],
        'total wbc count' => ['wbc', 'white blood cell', 'white blood cells', 'leucocyte', 'leukocyte', 'tlc'],
        'rbc count' => ['rbc', 'red blood cell', 'red blood cells', 'erythrocyte'],
        'platelet count' => ['platelet', 'platelets', 'plt', 'thrombocyte'],
        'lipid profile' => ['lipid panel', 'lipids', 'cholesterol panel'],
        'total cholesterol' => ['cholesterol', 'serum cholesterol', 'tc'],
        'hdl cholesterol' => ['hdl', 'hdl-c', 'good cholesterol'],
        'ldl cholesterol' => ['ldl', 'ldl-c', 'bad cholesterol'],
        'triglycerides' => ['tg', 'triglyceride', 'trigs'],
        'liver function test' => ['lft', 'liver function', 'liver panel', 'hepatic panel'],
        'sgot' => ['ast', 'sgot (ast)', 'aspartate aminotransferase'],
        'sgpt' => ['alt', 'sgpt (alt)', 'alanine aminotransferase'],
        'blood sugar (fasting)' => ['blood glucose (fasting)', 'fasting blood sugar', 'fasting glucose', 'fbs', 'glucose fasting', 'blood sugar fasting'],
        'blood glucose (fasting)' => ['blood sugar (fasting)', 'fasting blood sugar', 'fasting glucose', 'fbs'],
        'hba1c' => ['hb a1c', 'glycated hemoglobin', 'glycosylated haemoglobin', 'hemoglobin a1c', 'hba1c %'],
        'creatine' => ['creatinine', 'serum creatinine', 'creat'],
        'creatinine' => ['creatine', 'serum creatinine', 'creat'],
        'vitamin d' => ['vit d', '25-oh vitamin d', '25 hydroxy vitamin d', '25(oh)d', 'cholecalciferol'],
        'tsh' => ['thyroid stimulating hormone', 'serum tsh'],
    ];

    /**
     * Analyze an uploaded lab report (image/PDF) and/or OCR text against major organ tests.
     *
     * @param  Collection<int, MajorOrganTest>  $organTests
     */
    public function analyze(?UploadedFile $file, ?string $ocrText, Collection $organTests): array
    {
        $extraction = $this->extractFromDocument($file, $ocrText);
        $extractedNames = $extraction['extracted_biomarkers'];
        $extractedText = $extraction['ocr_text'];

        $available = [];
        $missing = [];
        $matchingFields = [];
        $missingFields = [];
        $modifiedFields = [];
        $mismatches = [];
        $sectionScores = [];

        foreach ($organTests as $test) {
            $biomarkers = is_array($test->biomarkers) ? $test->biomarkers : [];
            $match = $this->matchOrganTest($test->name, $biomarkers, $extractedNames, $extractedText);

            $price = number_format((float) $test->price, 2, '.', '');
            $entry = [
                'id' => $test->id,
                'name' => $test->name,
                'price' => $price,
                'biomarker_count' => count($biomarkers),
                'biomarkers' => $biomarkers,
                'matched_biomarkers' => $match['matched_biomarkers'],
                'missing_internal_biomarkers' => $match['missing_biomarkers'],
                'confidence' => $match['confidence'],
            ];

            $sectionScores[] = $match['confidence'];

            if ($match['is_present']) {
                $available[] = $entry;
                $matchingFields[] = [
                    'id' => $test->id,
                    'name' => $test->name,
                    'matched_biomarkers' => $match['matched_biomarkers'],
                    'confidence' => $match['confidence'],
                ];

                if (!empty($match['missing_biomarkers'])) {
                    $modifiedFields[] = [
                        'id' => $test->id,
                        'name' => $test->name,
                        'reason' => 'Organ test panel detected but some expected biomarkers were not found in the report.',
                        'missing_biomarkers' => $match['missing_biomarkers'],
                    ];
                    $mismatches[] = [
                        'field' => $test->name,
                        'type' => 'partial_match',
                        'explanation' => 'Panel "' . $test->name . '" is present, but missing: ' . implode(', ', $match['missing_biomarkers']),
                    ];
                }
            } else {
                $missing[] = $entry;
                $missingFields[] = [
                    'id' => $test->id,
                    'name' => $test->name,
                    'price' => $price,
                    'confidence' => $match['confidence'],
                ];
                $mismatches[] = [
                    'field' => $test->name,
                    'type' => 'missing',
                    'explanation' => 'Required organ test "' . $test->name . '" was not found in the lab report (semantic comparison).',
                ];
            }
        }

        $extraFields = $this->findExtraFields($extractedNames, $organTests);

        $totalTests = max(1, $organTests->count());
        $overallMatchPercentage = $this->score((count($available) / $totalTests) * 100);
        $toPay = array_sum(array_map(static fn ($item) => (float) $item['price'], $missing));

        $avgSectionConfidence = empty($sectionScores)
            ? 0.0
            : $this->score(array_sum($sectionScores) / count($sectionScores));

        $extractionConfidence = $this->score((float) ($extraction['confidence'] ?? 0.8));

        return [
            'available_count' => count($available),
            'available_biomarkers' => array_map(function ($item) {
                return [
                    'id' => $item['id'],
                    'name' => $item['name'],
                    'matched_biomarkers' => $item['matched_biomarkers'],
                    'confidence' => $this->score((float) $item['confidence']),
                ];
            }, $available),
            
            'missing_count' => count($missing),
            'missing_biomarkers' => array_map(function ($item) {
                return [
                    'id' => $item['id'],
                    'name' => $item['name'],
                    'price' => $item['price'],
                    'confidence' => $this->score((float) $item['confidence']),
                ];
            }, $missing),
            
            'total_count' => $organTests->count(),
            'to_pay' => number_format($toPay, 2, '.', ''),
            'currency' => 'AED',
            'overall_match_percentage' => $overallMatchPercentage,
            'confidence_score' => $this->score(($avgSectionConfidence * 0.7) + ($extractionConfidence * 0.3)),
            'matching_fields' => $matchingFields,
            'missing_fields' => $missingFields,
            'extra_fields' => $extraFields,
            'modified_fields' => $modifiedFields,
            'mismatches' => $mismatches,
            'section_confidence' => [
                'extraction' => $extractionConfidence,
                'available_biomarkers' => $this->averageConfidence($available),
                'missing_biomarkers' => $this->averageConfidence($missing),
                'comparison' => $avgSectionConfidence,
            ],
            'extracted_biomarkers' => $extractedNames,
            'ocr_text' => $extractedText,
            'ocr_text_preview' => Str::limit($extractedText, 1500),
            'extraction_source' => $extraction['source'],
        ];
    }

    protected function score(float $value): float
    {
        return (float) number_format($value, 2, '.', '');
    }

    /**
     * @return array{extracted_biomarkers: string[], ocr_text: string, confidence: float, source: string}
     */
    protected function extractFromDocument(?UploadedFile $file, ?string $ocrText): array
    {
        $ocrText = trim((string) $ocrText);

        if ($file) {
            $openAiResult = $this->extractWithOpenAi($file, $ocrText);
            if ($openAiResult !== null) {
                return $openAiResult;
            }

            $ocrSpaceResult = $this->extractWithOcrSpace($file);
            if ($ocrSpaceResult !== null) {
                return $ocrSpaceResult;
            }
        }

        if ($ocrText !== '') {
            return [
                'extracted_biomarkers' => $this->extractBiomarkerNamesFromText($ocrText),
                'ocr_text' => $ocrText,
                'confidence' => 0.75,
                'source' => 'ocr_text',
            ];
        }

        if ($file) {
            $pdfText = $this->extractTextFromPdf($file);
            if ($pdfText !== '') {
                return [
                    'extracted_biomarkers' => $this->extractBiomarkerNamesFromText($pdfText),
                    'ocr_text' => $pdfText,
                    'confidence' => 0.65,
                    'source' => 'pdf_text',
                ];
            }
        }

        throw new \RuntimeException(
            'Unable to analyze document. OCR failed. Set OPENAI_API_KEY or OCR_SPACE_API_KEY in .env, or send ocr_text with the request.'
        );
    }

    /**
     * Free OCR fallback via OCR.space (works for images/PDFs without OpenAI).
     *
     * @return array{extracted_biomarkers: string[], ocr_text: string, confidence: float, source: string}|null
     */
    protected function extractWithOcrSpace(UploadedFile $file): ?array
    {
        $apiKey = (string) config('services.ocr_space.api_key', 'helloworld');
        $endpoint = (string) config('services.ocr_space.endpoint', 'https://api.ocr.space/parse/image');

        if ($apiKey === '') {
            return null;
        }

        $mime = $file->getMimeType() ?: 'image/jpeg';
        $extension = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        $fileTypeMap = [
            'jpg' => 'JPG',
            'jpeg' => 'JPG',
            'png' => 'PNG',
            'webp' => 'PNG',
            'gif' => 'GIF',
            'pdf' => 'PDF',
            'tif' => 'TIF',
            'tiff' => 'TIF',
            'bmp' => 'BMP',
        ];
        $fileType = $fileTypeMap[$extension] ?? 'JPG';

        if (str_contains($mime, 'pdf')) {
            $mime = 'application/pdf';
            $fileType = 'PDF';
        } elseif (!str_starts_with($mime, 'image/')) {
            $mime = 'image/jpeg';
        }

        $base64 = base64_encode((string) file_get_contents($file->getRealPath()));
        $dataUrl = 'data:' . $mime . ';base64,' . $base64;

        try {
            $response = Http::timeout(90)
                ->withoutVerifying()
                ->asMultipart()
                ->withHeaders(['apikey' => $apiKey])
                ->post($endpoint, [
                    ['name' => 'base64Image', 'contents' => $dataUrl],
                    ['name' => 'language', 'contents' => 'eng'],
                    ['name' => 'isOverlayRequired', 'contents' => 'false'],
                    ['name' => 'OCREngine', 'contents' => '2'],
                    ['name' => 'scale', 'contents' => 'true'],
                    ['name' => 'filetype', 'contents' => $fileType],
                ]);

            if (!$response->successful()) {
                Log::error('OCR.space request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            $json = $response->json();
            if (!empty($json['IsErroredOnProcessing'])) {
                Log::error('OCR.space processing error', [
                    'message' => $json['ErrorMessage'] ?? $json['ErrorDetails'] ?? null,
                    'body' => $json,
                ]);
                return null;
            }

            $parsedText = '';
            foreach (($json['ParsedResults'] ?? []) as $result) {
                $parsedText .= trim((string) ($result['ParsedText'] ?? '')) . "\n";
            }
            $parsedText = trim($parsedText);

            if ($parsedText === '') {
                Log::warning('OCR.space returned empty text');
                return null;
            }

            return [
                'extracted_biomarkers' => $this->extractBiomarkerNamesFromText($parsedText),
                'ocr_text' => $parsedText,
                'confidence' => 0.8,
                'source' => 'ocr_space',
            ];
        } catch (\Throwable $e) {
            Log::error('OCR.space exception', ['message' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * @return array{extracted_biomarkers: string[], ocr_text: string, confidence: float, source: string}|null
     */
    protected function extractWithOpenAi(UploadedFile $file, string $existingOcrText = ''): ?array
    {
        $apiKey = config('services.openai.api_key');
        if (empty($apiKey)) {
            return null;
        }

        $mime = $file->getMimeType() ?: 'image/jpeg';
        $extension = strtolower($file->getClientOriginalExtension() ?: '');
        $isPdf = str_contains($mime, 'pdf') || $extension === 'pdf';

        if ($isPdf) {
            // Vision models need an image; fall back to PDF text extraction path.
            $pdfText = $this->extractTextFromPdf($file);
            if ($pdfText === '' && $existingOcrText === '') {
                Log::warning('OpenAI lab report analysis skipped: PDF has no extractable text and no OCR text provided');
                return null;
            }

            $textForAi = $pdfText !== '' ? $pdfText : $existingOcrText;
            $payload = $this->buildOpenAiTextPayload($textForAi);
        } else {
            $base64 = base64_encode(file_get_contents($file->getRealPath()));
            $dataUrl = 'data:' . $mime . ';base64,' . $base64;
            $payload = $this->buildOpenAiVisionPayload($dataUrl, $existingOcrText);
        }

        try {
            $response = Http::timeout(90)
                ->withoutVerifying()
                ->withToken($apiKey)
                ->acceptJson()
                ->post('https://api.openai.com/v1/chat/completions', $payload);

            if (!$response->successful()) {
                Log::error('OpenAI lab report analysis failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            $content = (string) data_get($response->json(), 'choices.0.message.content', '');
            $parsed = $this->parseJsonFromLlm($content);

            if ($parsed === null) {
                Log::error('OpenAI lab report analysis returned invalid JSON', ['content' => $content]);
                return null;
            }

            $biomarkers = [];
            foreach (($parsed['extracted_biomarkers'] ?? []) as $item) {
                if (is_string($item) && trim($item) !== '') {
                    $biomarkers[] = trim($item);
                } elseif (is_array($item) && !empty($item['name'])) {
                    $biomarkers[] = trim((string) $item['name']);
                }
            }

            $ocrFromAi = trim((string) ($parsed['ocr_text'] ?? ''));
            if ($ocrFromAi === '') {
                $ocrFromAi = implode("\n", $biomarkers);
            }

            return [
                'extracted_biomarkers' => array_values(array_unique($biomarkers)),
                'ocr_text' => $ocrFromAi,
                'confidence' => (float) ($parsed['confidence_score'] ?? 0.9),
                'source' => 'openai',
            ];
        } catch (\Throwable $e) {
            Log::error('OpenAI lab report analysis exception', ['message' => $e->getMessage()]);
            return null;
        }
    }

    protected function buildOpenAiVisionPayload(string $dataUrl, string $existingOcrText): array
    {
        $system = $this->analystSystemPrompt();
        $userText = 'Analyze this lab report image. Extract every test/biomarker name found. '
            . 'Compare meaning, ignore punctuation/capitalization/OCR typos. '
            . 'Return ONLY valid JSON.';

        if ($existingOcrText !== '') {
            $userText .= "\n\nAdditional OCR text provided by client:\n" . $existingOcrText;
        }

        return [
            'model' => config('services.openai.model', 'gpt-4o-mini'),
            'temperature' => 0.1,
            'response_format' => ['type' => 'json_object'],
            'messages' => [
                ['role' => 'system', 'content' => $system],
                [
                    'role' => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => $userText],
                        ['type' => 'image_url', 'image_url' => ['url' => $dataUrl]],
                    ],
                ],
            ],
        ];
    }

    protected function buildOpenAiTextPayload(string $ocrText): array
    {
        return [
            'model' => config('services.openai.model', 'gpt-4o-mini'),
            'temperature' => 0.1,
            'response_format' => ['type' => 'json_object'],
            'messages' => [
                ['role' => 'system', 'content' => $this->analystSystemPrompt()],
                [
                    'role' => 'user',
                    'content' => "OCR text from lab report:\n\n{$ocrText}\n\nExtract biomarkers and return ONLY valid JSON.",
                ],
            ],
        ];
    }

    protected function analystSystemPrompt(): string
    {
        return <<<'PROMPT'
You are an expert AI Document Analyst specializing in OCR document verification and semantic comparison.
Read OCR/lab report content even if it contains minor spelling mistakes.
Extract structured biomarker/test names from the document.
Ignore punctuation, capitalization, and small grammatical differences.
Return ONLY valid JSON with this shape:
{
  "ocr_text": "full readable text extracted from the document",
  "extracted_biomarkers": ["Hemoglobin (Hb)", "Total Cholesterol", "..."],
  "confidence_score": 0.0
}
Do not include markdown. Do not include explanations outside JSON.
PROMPT;
    }

    public function extractSenoclockMarkersWithOpenAi(string $ocrText): array
    {
        $apiKey = config('services.openai.api_key');
        if (empty($apiKey)) {
            return [];
        }

        $system = <<<PROMPT
You are an expert AI Document Analyst specializing in OCR document verification.
Extract structured biomarker/test names from the OCR text of a lab report.
Return ONLY valid JSON with this shape:
{
  "markers": {
    "HDL": {
      "range": "45-999",
      "unit": "mg/dL",
      "value": 42
    },
    "LDL": {
      "range": "0-150",
      "unit": "mg/dL",
      "value": 97
    }
  }
}
If a value is not a number, try to clean it up (e.g. "42.5"). If it's a string like "Positive", you can return it as the value.
Use standard abbreviations as keys if possible (HDL, LDL, TRIG, HGBA1C, GLC, ALT, AST, CRP, WBC, CREA, ALB, GGT, PLT, NA+, K+).
Do not include markdown. Do not include explanations outside JSON.
PROMPT;

        $payload = [
            'model' => config('services.openai.model', 'gpt-4o-mini'),
            'temperature' => 0.1,
            'response_format' => ['type' => 'json_object'],
            'messages' => [
                ['role' => 'system', 'content' => $system],
                [
                    'role' => 'user',
                    'content' => "OCR text from lab report:\n\n{$ocrText}\n\nExtract biomarkers and return ONLY valid JSON.",
                ],
            ],
        ];

        try {
            $response = Http::timeout(90)
                ->withoutVerifying()
                ->withToken($apiKey)
                ->acceptJson()
                ->post('https://api.openai.com/v1/chat/completions', $payload);

            if (!$response->successful()) {
                Log::error('OpenAI Senoclock extraction failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return [];
            }

            $content = (string) data_get($response->json(), 'choices.0.message.content', '');
            $parsed = $this->parseJsonFromLlm($content);

            return $parsed['markers'] ?? [];
        } catch (\Throwable $e) {
            Log::error('OpenAI Senoclock extraction exception', ['message' => $e->getMessage()]);
            return [];
        }
    }

    protected function parseJsonFromLlm(string $content): ?array
    {
        $content = trim($content);
        if ($content === '') {
            return null;
        }

        $decoded = json_decode($content, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/\{.*\}/s', $content, $matches)) {
            $decoded = json_decode($matches[0], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    protected function extractTextFromPdf(UploadedFile $file): string
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: '');
        $mime = $file->getMimeType() ?: '';
        if ($extension !== 'pdf' && !str_contains($mime, 'pdf')) {
            return '';
        }

        $raw = @file_get_contents($file->getRealPath());
        if ($raw === false || $raw === '') {
            return '';
        }

        $texts = [];

        if (preg_match_all('/\((\\\\.|[^\\\\)])*\)/s', $raw, $matches)) {
            foreach ($matches[0] as $match) {
                $inner = substr($match, 1, -1);
                $inner = str_replace(['\\n', '\\r', '\\t', '\\(', '\\)'], ["\n", "\r", "\t", '(', ')'], $inner);
                $inner = preg_replace('/\\\\[0-9]{3}/', '', $inner) ?? $inner;
                $clean = trim(preg_replace('/[^\P{C}\n]+/u', ' ', $inner) ?? $inner);
                if (strlen($clean) >= 3 && preg_match('/[A-Za-z]/', $clean)) {
                    $texts[] = $clean;
                }
            }
        }

        return trim(implode("\n", array_unique($texts)));
    }

    /**
     * @return string[]
     */
    protected function extractBiomarkerNamesFromText(string $text): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];
        $found = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strlen($line) < 3) {
                continue;
            }

            // Common lab report row pattern: "Test Name  12.3  unit  range"
            if (preg_match('/^([A-Za-z][A-Za-z0-9\s\-\/\(\)%\.]+?)(?:\s{2,}|\s+\d)/', $line, $m)) {
                $name = trim($m[1]);
                if ($this->looksLikeTestName($name)) {
                    $found[] = $name;
                }
            } elseif ($this->looksLikeTestName($line) && !preg_match('/^\d/', $line)) {
                $found[] = $line;
            }
        }

        // Also collect known alias hits from full text
        foreach ($this->aliases as $canonical => $aliasList) {
            $all = array_merge([$canonical], $aliasList);
            foreach ($all as $alias) {
                if ($this->textContainsNormalized($text, $alias)) {
                    $found[] = $canonical;
                    break;
                }
            }
        }

        return array_values(array_unique($found));
    }

    protected function looksLikeTestName(string $name): bool
    {
        if (strlen($name) > 80) {
            return false;
        }

        $lower = strtolower($name);
        $blocked = [
            'patient', 'report', 'sample', 'referred', 'registration', 'biological', 'method', 'notes', 'disclaimer',
            'diagnostics', 'accurate', 'reliable', 'caring', 'test name', 'test results', 'result', 'unit',
            'andheri', 'mumbai', 'india', 'reapmind', 'www.', 'info@', 'phone', 'email', 'website',
            'age / gender', 'report no', 'report status', 'final', 'scan to verify',
        ];
        foreach ($blocked as $word) {
            if (str_contains($lower, $word)) {
                return false;
            }
        }

        if (preg_match('/^[:\d]/', $name)) {
            return false;
        }

        return (bool) preg_match('/[A-Za-z]/', $name);
    }

    /**
     * @param  string[]  $biomarkers
     * @param  string[]  $extractedNames
     * @return array{is_present: bool, matched_biomarkers: string[], missing_biomarkers: string[], confidence: float}
     */
    protected function matchOrganTest(string $testName, array $biomarkers, array $extractedNames, string $extractedText): array
    {
        $matched = [];
        $missing = [];

        $panelMatched = $this->semanticContains($testName, $extractedNames, $extractedText);

        foreach ($biomarkers as $biomarker) {
            $biomarker = trim((string) $biomarker);
            if ($biomarker === '') {
                continue;
            }

            if ($this->semanticContains($biomarker, $extractedNames, $extractedText)) {
                $matched[] = $biomarker;
            } else {
                $missing[] = $biomarker;
            }
        }

        $biomarkerTotal = count($biomarkers);
        $matchedCount = count($matched);

        if ($biomarkerTotal === 0) {
            $isPresent = $panelMatched;
            $confidence = $panelMatched ? 0.85 : 0.9;
        } else {
            $ratio = $matchedCount / $biomarkerTotal;
            // Present if panel name found OR at least one biomarker from the panel is found.
            $isPresent = $panelMatched || $matchedCount >= 1;
            $confidence = round(min(0.99, max(0.55, ($panelMatched ? 0.35 : 0) + ($ratio * 0.65) + 0.2)), 2);
            if (!$isPresent) {
                $confidence = round(min(0.99, 0.7 + ((1 - $ratio) * 0.25)), 2);
            }
        }

        return [
            'is_present' => $isPresent,
            'matched_biomarkers' => $matched,
            'missing_biomarkers' => $missing,
            'confidence' => $confidence,
        ];
    }

    /**
     * @param  string[]  $extractedNames
     */
    protected function semanticContains(string $needle, array $extractedNames, string $extractedText): bool
    {
        $needleNorm = $this->normalize($needle);
        if ($needleNorm === '') {
            return false;
        }

        foreach ($extractedNames as $name) {
            if ($this->namesMatch($needleNorm, $this->normalize($name))) {
                return true;
            }
        }

        if ($this->textContainsNormalized($extractedText, $needle)) {
            return true;
        }

        foreach ($this->expandAliases($needleNorm) as $alias) {
            if ($this->textContainsNormalized($extractedText, $alias)) {
                return true;
            }
            foreach ($extractedNames as $name) {
                if ($this->namesMatch($this->normalize($alias), $this->normalize($name))) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function namesMatch(string $a, string $b): bool
    {
        if ($a === '' || $b === '') {
            return false;
        }

        if ($a === $b) {
            return true;
        }

        similar_text($a, $b, $percent);
        if ($percent >= 88) {
            return true;
        }

        if (min(strlen($a), strlen($b)) >= 5
            && levenshtein(substr($a, 0, 255), substr($b, 0, 255)) <= 2) {
            return true;
        }

        $aTokens = array_values(array_filter(explode(' ', $a), static fn ($t) => $t !== ''));
        $bTokens = array_values(array_filter(explode(' ', $b), static fn ($t) => $t !== ''));

        $sortedA = $aTokens;
        $sortedB = $bTokens;
        sort($sortedA);
        sort($sortedB);
        if ($sortedA === $sortedB) {
            return true;
        }

        // Allow "platelet" ~= "platelet count", but NOT "hemoglobin" ~= "hemoglobin a1c".
        $genericExtras = ['count', 'level', 'levels', 'serum', 'test', 'tests', 'profile', 'panel', 'blood', 'total', 'value', 'result'];

        return $this->tokenSubsetMatch($aTokens, $bTokens, $genericExtras);
    }

    /**
     * @param  string[]  $a
     * @param  string[]  $b
     * @param  string[]  $genericExtras
     */
    protected function tokenSubsetMatch(array $a, array $b, array $genericExtras): bool
    {
        if (empty($a) || empty($b)) {
            return false;
        }

        $short = count($a) <= count($b) ? $a : $b;
        $long = count($a) <= count($b) ? $b : $a;

        foreach ($short as $token) {
            if (!in_array($token, $long, true)) {
                return false;
            }
        }

        $extras = array_values(array_diff($long, $short));
        if (empty($extras)) {
            return true;
        }

        foreach ($extras as $extra) {
            if (!in_array($extra, $genericExtras, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return string[]
     */
    protected function expandAliases(string $normalizedName): array
    {
        $aliases = [$normalizedName];

        foreach ($this->aliases as $canonical => $list) {
            $canonicalNorm = $this->normalize($canonical);
            $all = array_merge([$canonicalNorm], array_map([$this, 'normalize'], $list));

            if (in_array($normalizedName, $all, true)) {
                $aliases = array_merge($aliases, $all);
            }
        }

        return array_values(array_unique($aliases));
    }

    protected function textContainsNormalized(string $haystack, string $needle): bool
    {
        $hay = ' ' . $this->normalize($haystack) . ' ';
        $nee = $this->normalize($needle);

        if ($nee === '') {
            return false;
        }

        if (str_contains($hay, $nee)) {
            return true;
        }

        foreach ($this->expandAliases($nee) as $alias) {
            if ($alias !== '' && str_contains($hay, $alias)) {
                return true;
            }
        }

        return false;
    }

    protected function normalize(string $value): string
    {
        $value = strtolower($value);
        // Drop parenthetical abbreviations: "Hemoglobin (Hb)" -> "hemoglobin"
        $value = preg_replace('/\([^)]*\)/', ' ', $value) ?? $value;
        $value = str_replace(['%', '/', '-', '_', ',', '.', ':'], ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return trim($value);
    }

    /**
     * @param  string[]  $extractedNames
     * @param  Collection<int, MajorOrganTest>  $organTests
     * @return array<int, array{name: string, explanation: string}>
     */
    protected function findExtraFields(array $extractedNames, Collection $organTests): array
    {
        $known = [];
        foreach ($organTests as $test) {
            $known[] = $test->name;
            foreach ((is_array($test->biomarkers) ? $test->biomarkers : []) as $biomarker) {
                $known[] = (string) $biomarker;
            }
        }

        $extras = [];
        $seen = [];
        foreach ($extractedNames as $name) {
            $norm = $this->normalize($name);
            if ($norm === '' || isset($seen[$norm])) {
                continue;
            }
            $seen[$norm] = true;

            $matchedKnown = false;
            foreach ($known as $knownName) {
                if ($this->namesMatch($norm, $this->normalize($knownName))) {
                    $matchedKnown = true;
                    break;
                }
                foreach ($this->expandAliases($this->normalize($knownName)) as $alias) {
                    if ($this->namesMatch($norm, $this->normalize($alias))) {
                        $matchedKnown = true;
                        break 2;
                    }
                }
            }

            if (!$matchedKnown) {
                $extras[] = [
                    'name' => $name,
                    'explanation' => 'Found in lab report but not part of required major organ tests.',
                ];
            }
        }

        return $extras;
    }

    /**
     * @param  array<int, array{confidence?: float}>  $items
     */
    protected function averageConfidence(array $items): float
    {
        if (empty($items)) {
            return 0.0;
        }

        $sum = 0.0;
        foreach ($items as $item) {
            $sum += (float) ($item['confidence'] ?? 0);
        }

        return $this->score($sum / count($items));
    }
}
