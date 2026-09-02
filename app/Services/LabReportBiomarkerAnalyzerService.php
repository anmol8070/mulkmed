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
    /**
     * Handle array of files for analysis by combining their extractions.
     */
    public function analyzeMultiple(array $files, ?string $ocrText, Collection $organTests): array
    {
        if (empty($files)) {
            return $this->analyze(null, $ocrText, $organTests);
        }

        $files = array_filter($files);
        if (empty($files)) {
            return $this->analyze(null, $ocrText, $organTests);
        }

        $pdfFiles = [];
        $imageFiles = [];
        foreach ($files as $file) {
            $mime = $file->getMimeType() ?: '';
            $extension = strtolower($file->getClientOriginalExtension() ?: '');
            $isPdf = str_contains($mime, 'pdf') || $extension === 'pdf';
            if ($isPdf) {
                $pdfFiles[] = $file;
            } else {
                $imageFiles[] = $file;
            }
        }

        Log::info('Starting native PDF analysis');

        $uploadedFiles = $this->uploadMultiplePdfs($pdfFiles);

        Log::info('Multi-PDF extraction validation', [
            'document_count' => count($uploadedFiles),
            'documents' => array_column($uploadedFiles, 'original_name'),
            'file_ids' => array_column($uploadedFiles, 'file_id'),
        ]);

        Log::info('All PDFs uploaded successfully', [
            'count' => count($uploadedFiles),
        ]);

        $prompt = <<<PROMPT
You are analyzing a laboratory PDF document.

Analyze the ENTIRE PDF.
Read every page.
Extract EVERY laboratory biomarker/test present.
Do not stop after finding common biomarkers.
Do not return only biomarkers relevant to SenoClock.
Do not return only biomarkers matching the database.
Do not summarize the report.
Do not select a subset.
Preserve every biomarker found.
Include biomarker name.
Include value.
Include unit.
Include reference range.
Include source_document.
If multiple biomarkers occur on different pages, include all of them.
Never omit a biomarker because another PDF contains the same biomarker.
Duplicate biomarkers across documents must remain identifiable by source_document.

Return ONLY valid JSON.

Required structure:

{
  "biomarkers": [
    {
      "name": "Hemoglobin (Hb)",
      "value": 14.2,
      "unit": "g/dL",
      "range": "13.0 - 17.0",
      "source_document": "allbiomarkers.pdf"
    }
  ]
}

Do not invent values.
Do not invent reference ranges.
Do not normalize away clinically distinct tests.
Do not return markdown.
Do not return ```json fences.
Return only JSON.
PROMPT;

        $allExtractedBiomarkers = [];
        $documentSummaries = [];
        $documentResults = [];

        foreach ($uploadedFiles as $uploadedFile) {
            try {
                $biomarkers = $this->analyzeSingleUploadedPdf($uploadedFile, $prompt);
                
                $allExtractedBiomarkers = array_merge($allExtractedBiomarkers, $biomarkers);
                
                $documentSummaries[] = [
                    'filename' => $uploadedFile['original_name'],
                    'file_id' => $uploadedFile['file_id'],
                    'biomarker_count' => count($biomarkers),
                ];
                $documentResults[] = $uploadedFile['file_id'];
                
            } catch (\Exception $e) {
                Log::error('Individual PDF analysis failed', [
                    'filename' => $uploadedFile['original_name'] ?? 'unknown',
                    'file_id' => $uploadedFile['file_id'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ]);
            }
        }

        foreach ($imageFiles as $imageFile) {
            try {
                $result = $this->extractWithOpenAi($imageFile, '');
                if ($result && !empty($result['extracted_biomarkers'])) {
                    $allExtractedBiomarkers = array_merge($allExtractedBiomarkers, $result['extracted_biomarkers']);
                    $documentSummaries[] = [
                        'filename' => $imageFile->getClientOriginalName(),
                        'file_id' => 'image',
                        'biomarker_count' => count($result['extracted_biomarkers']),
                    ];
                    $documentResults[] = 'image';
                }
            } catch (\Exception $e) {
                Log::error('Individual image analysis failed', [
                    'filename' => $imageFile->getClientOriginalName(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $expectedDocs = count($uploadedFiles) + count($imageFiles);
        if (count($documentResults) < $expectedDocs) {
            Log::error('Multi-PDF extraction incomplete', [
                'expected_document_count' => $expectedDocs,
                'actual_extracted_document_count' => count($documentResults),
            ]);
        }

        // Deterministic deduplication in PHP
        $uniqueBiomarkers = [];
        foreach ($allExtractedBiomarkers as $marker) {
            if (!isset($marker['name'])) {
                continue;
            }
            
            $normName = strtolower(trim((string)$marker['name']));
            
            if (!isset($uniqueBiomarkers[$normName])) {
                $uniqueBiomarkers[$normName] = $marker;
            } else {
                // If existing is empty/null, but new one is better, replace it
                $existing = $uniqueBiomarkers[$normName];
                $existingValue = $existing['value'] ?? null;
                $newValue = $marker['value'] ?? null;

                $existingHasValue = !is_null($existingValue) && $existingValue !== '';
                $newHasValue = !is_null($newValue) && $newValue !== '';
                
                $existingIsNumeric = is_numeric($existingValue);
                $newIsNumeric = is_numeric($newValue);

                // Prefer non-null, valid numeric values over empty ones
                if (!$existingHasValue && $newHasValue) {
                    $uniqueBiomarkers[$normName] = $marker;
                } elseif ($existingHasValue && $newHasValue && !$existingIsNumeric && $newIsNumeric) {
                    $uniqueBiomarkers[$normName] = $marker;
                }
                // Otherwise keep existing (do not overwrite earlier valid record)
            }
        }

        $uniqueBiomarkerList = array_values($uniqueBiomarkers);
        $uniqueBiomarkerNames = array_column($uniqueBiomarkerList, 'name');

        Log::info('Multi-PDF final extraction summary', [
            'document_count' => count($uploadedFiles),
            'documents' => array_column($uploadedFiles, 'original_name'),
            'total_raw_biomarkers' => count($allExtractedBiomarkers),
            'unique_biomarker_count' => count($uniqueBiomarkerList),
            'unique_biomarker_names' => $uniqueBiomarkerNames,
        ]);

        Log::info('Multi-PDF document biomarker summary', [
            'documents' => $documentSummaries,
        ]);

        Log::info('Processing extracted biomarkers');

        $combinedExtraction = [
            'extracted_biomarkers' => $uniqueBiomarkerList,
            'ocr_text' => trim((string) $ocrText),
            'confidence' => 0.9,
            'source' => 'openai_responses',
        ];

        Log::info('Native biomarkers before processExtraction', [
            'count' => count($uniqueBiomarkerList),
            'names' => $uniqueBiomarkerNames,
        ]);

        $result = $this->processExtraction($combinedExtraction, $organTests);
        
        Log::info('Biomarkers after processExtraction', [
            'count' => count($result['extracted_biomarkers'] ?? []),
            'names' => array_map(
                fn($item) => $item['name'] ?? $item['marker'] ?? null,
                $result['extracted_biomarkers'] ?? []
            ),
        ]);

        Log::info('Result after processExtraction', [
            'type' => gettype($result),
            'keys' => is_array($result) ? array_keys($result) : [],
        ]);

        Log::info('Native PDF analysis completed successfully');
        
        return $result;
    }

    public function openAiHttpClient(string $apiKey)
    {
        $caPath = base_path('cacert.pem');

        Log::info('OpenAI SSL configuration', [
            'ca_path' => $caPath,
            'ca_exists' => is_file($caPath),
            'ca_readable' => is_readable($caPath),
        ]);

        if (!is_file($caPath) || !is_readable($caPath)) {
            throw new \RuntimeException(
                'OpenAI CA certificate bundle not found or not readable: ' . $caPath
            );
        }

        return Http::withToken($apiKey)
            ->acceptJson()
            ->withOptions([
                'verify' => $caPath,
            ]);
    }

    public function uploadMultiplePdfs(array $files): array
    {
        $uploadedFiles = [];
        foreach ($files as $file) {
            $uploadedFiles[] = $this->uploadPdfToOpenAI($file);
        }
        return $uploadedFiles;
    }

    public function uploadPdfToOpenAI(UploadedFile $file): array
    {
        $mime = $file->getMimeType() ?: '';
        $extension = strtolower($file->getClientOriginalExtension() ?: '');
        
        $isPdf = str_contains($mime, 'pdf') || $extension === 'pdf';
        $isImage = in_array($extension, ['jpg', 'jpeg', 'png']) || str_starts_with($mime, 'image/');

        if (!$isPdf && !$isImage) {
            throw new \RuntimeException('Failed to upload document: ' . $file->getClientOriginalName() . ' is not a valid PDF or Image file.');
        }

        $apiKey = config('services.openai.api_key');
        if (empty($apiKey)) {
            throw new \RuntimeException('OpenAI API key is missing.');
        }

        Log::info('Uploading PDF: ' . $file->getClientOriginalName());

        try {
            $response = $this->openAiHttpClient($apiKey)
                ->attach(
                    'file',
                    file_get_contents($file->getRealPath()),
                    $file->getClientOriginalName()
                )
                ->post('https://api.openai.com/v1/files', [
                    'purpose' => 'user_data',
                ]);
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to upload PDF: ' . $file->getClientOriginalName() . '. Error: ' . $e->getMessage());
        }

        if (!$response->successful()) {
            throw new \RuntimeException('Failed to upload PDF: ' . $file->getClientOriginalName() . '. Status: ' . $response->status());
        }

        $responseData = $response->json();
        $fileId = $responseData['id'] ?? null;

        if (!$fileId) {
            throw new \RuntimeException('Failed to upload PDF: ' . $file->getClientOriginalName() . '. OpenAI did not return a file_id.');
        }

        Log::info('OpenAI file uploaded:', [
            'filename' => $file->getClientOriginalName(),
            'file_id' => $fileId,
        ]);

        return [
            'original_name' => $file->getClientOriginalName(),
            'file_id' => $fileId,
        ];
    }

    public function analyzeSingleUploadedPdf(array $uploadedFile, string $prompt): array
    {
        $apiKey = config('services.openai.api_key');
        
        $contents = [
            [
                'type' => 'input_text',
                'text' => $prompt,
            ],
            [
                'type' => 'input_file',
                'file_id' => $uploadedFile['file_id'],
            ]
        ];

        Log::info('Starting individual PDF analysis', [
            'filename' => $uploadedFile['original_name'] ?? 'unknown',
            'file_id' => $uploadedFile['file_id'],
        ]);

        $payload = [
            'model' => config('services.openai.model', 'gpt-4o'),
            'input' => [
                [
                    'role' => 'user',
                    'content' => $contents,
                ],
            ],
        ];

        $response = $this->openAiHttpClient($apiKey)
            ->post('https://api.openai.com/v1/responses', $payload);

        $responseData = $response->json();
        
        Log::info('OpenAI Responses API completed for individual PDF', [
            'filename' => $uploadedFile['original_name'] ?? 'unknown',
            'file_id' => $uploadedFile['file_id'],
            'response_id' => $responseData['id'] ?? null,
            'status' => $responseData['status'] ?? null,
        ]);

        if (!$response->successful()) {
            Log::error('OpenAI Responses API failed for individual PDF', [
                'filename' => $uploadedFile['original_name'] ?? 'unknown',
                'file_id' => $uploadedFile['file_id'],
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException('OpenAI Responses API failed with status ' . $response->status());
        }

        $outputText = $this->extractResponsesOutputText($responseData);
        
        if (empty($outputText)) {
            Log::error('OpenAI Responses API returned empty output for individual PDF', [
                'filename' => $uploadedFile['original_name'] ?? 'unknown',
                'file_id' => $uploadedFile['file_id'],
                'response_id' => $responseData['id'] ?? null,
                'status' => $responseData['status'] ?? null,
            ]);
            throw new \RuntimeException('OpenAI returned an empty analysis response.');
        }

        $outputText = trim($outputText);
        $outputText = preg_replace('/^```json\s*/i', '', $outputText);
        $outputText = preg_replace('/\s*```$/', '', $outputText);
        
        $data = json_decode($outputText, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('OpenAI lab report analysis returned invalid JSON for individual PDF', [
                'filename' => $uploadedFile['original_name'] ?? 'unknown',
                'file_id' => $uploadedFile['file_id'],
                'json_error' => json_last_error_msg(),
                'response_id' => $responseData['id'] ?? null,
                'preview' => mb_substr($outputText, 0, 500, 'UTF-8'),
            ]);
            throw new \RuntimeException('OpenAI lab report analysis returned invalid JSON');
        }

        if (!isset($data['biomarkers']) || !is_array($data['biomarkers'])) {
            throw new \RuntimeException('OpenAI response does not contain a valid biomarkers array.');
        }

        $biomarkers = $data['biomarkers'];
        
        Log::info('Individual PDF extraction completed', [
            'filename' => $uploadedFile['original_name'] ?? 'unknown',
            'file_id' => $uploadedFile['file_id'],
            'biomarker_count' => count($biomarkers),
            'biomarker_names' => array_column($biomarkers, 'name'),
        ]);

        Log::info('Individual PDF extraction validation', [
            'filename' => $uploadedFile['original_name'] ?? 'unknown',
            'expected_file_id' => $uploadedFile['file_id'],
            'extracted_count' => count($biomarkers),
        ]);

        return $biomarkers;
    }

    public function extractResponsesOutputText(array $responseData): string
    {
        if (
            isset($responseData['output_text']) &&
            is_string($responseData['output_text']) &&
            trim($responseData['output_text']) !== ''
        ) {
            return trim($responseData['output_text']);
        }

        $texts = [];

        foreach (($responseData['output'] ?? []) as $outputItem) {

            if (($outputItem['type'] ?? null) !== 'message') {
                continue;
            }

            foreach (($outputItem['content'] ?? []) as $contentItem) {

                if (($contentItem['type'] ?? null) !== 'output_text') {
                    continue;
                }

                $text = $contentItem['text'] ?? '';

                if (is_string($text) && trim($text) !== '') {
                    $texts[] = trim($text);
                }
            }
        }

        return trim(implode("\n", $texts));
    }

    public function analyze(?UploadedFile $file, ?string $ocrText, Collection $organTests): array
    {
        $extraction = $this->extractFromDocument($file, $ocrText);
        return $this->processExtraction($extraction, $organTests);
    }

    protected function processExtraction(array $extraction, Collection $organTests): array
    {
        $extractedBiomarkers = $extraction['extracted_biomarkers'];
        
        $extractedNames = [];
        foreach ($extractedBiomarkers as $item) {
            if (is_array($item)) {
                $extractedNames[] = $item['name'] ?? '';
            } else {
                $extractedNames[] = $item;
            }
        }
        $extractedNames = array_values(array_filter(array_unique($extractedNames)));
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
                    'biomarkers' => $item['biomarkers'] ?? [],
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
            'extracted_biomarkers' => $extractedBiomarkers,
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
        $apiKey = config('services.openai.api_key');
        $initialOpenAiResult = null;

        if (!empty($apiKey) && $file) {
            $initialOpenAiResult = $this->extractWithOpenAi($file, (string) $ocrText);
            // If OpenAI successfully extracted some biomarkers, return early.
            if ($initialOpenAiResult !== null && !empty($initialOpenAiResult['extracted_biomarkers'])) {
                return $initialOpenAiResult;
            }
        }

        // If we had a valid OpenAI result but it just had 0 biomarkers, return it as a last resort before text fallbacks
        if ($initialOpenAiResult !== null) {
            return $initialOpenAiResult;
        }

        $ocrText = trim((string) $ocrText);
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
            'Unable to analyze document. OCR failed. Set OPENAI_API_KEY in .env, or send ocr_text with the request.'
        );
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
            // BUGFIX: Only try to extract from PDF if we don't already have text from OCR fallback
            $pdfText = $existingOcrText !== '' ? '' : $this->extractTextFromPdf($file);
            if ($pdfText !== '') {
                // Sanitize invalid UTF-8 characters to prevent json_encode errors
                $pdfText = mb_convert_encoding($pdfText, 'UTF-8', 'UTF-8');
            }
            
            $textForAi = $existingOcrText !== '' ? $existingOcrText : $pdfText;

            Log::info('LabReport: PDF extraction result', [
                'pdf_path' => $file->getRealPath(),
                'text_length' => strlen($pdfText),
                'ocr_text_length' => strlen($existingOcrText),
            ]);

            if ($textForAi === '') {
                Log::warning('OpenAI lab report analysis skipped: PDF has no extractable text and no OCR text provided');
                return null;
            }

            Log::info('LabReport: OCR/text preview', [
                'preview' => mb_substr($textForAi, 0, 500, 'UTF-8'),
            ]);

            $payload = $this->buildOpenAiTextPayload($textForAi);
        } else {
            $base64 = base64_encode(file_get_contents($file->getRealPath()));
            $dataUrl = 'data:' . $mime . ';base64,' . $base64;
            $payload = $this->buildOpenAiVisionPayload($dataUrl, $existingOcrText);
        }

        try {
            $response = Http::timeout(240)
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
                    $biomarkers[] = [
                        'name' => trim($item),
                        'value' => null,
                        'unit' => null,
                        'range' => null,
                    ];
                } elseif (is_array($item) && !empty($item['name'])) {
                    $biomarkers[] = [
                        'name' => trim((string) $item['name']),
                        'value' => isset($item['value']) ? trim((string) $item['value']) : null,
                        'unit' => isset($item['unit']) ? trim((string) $item['unit']) : null,
                        'range' => isset($item['range']) ? trim((string) $item['range']) : null,
                    ];
                }
            }

            $ocrFromAi = trim((string) ($parsed['ocr_text'] ?? ''));
            if ($ocrFromAi === '') {
                $names = array_map(fn($b) => $b['name'], $biomarkers);
                $ocrFromAi = implode("\n", $names);
            }

            return [
                'extracted_biomarkers' => $biomarkers,
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
        $userText = <<<PROMPT
You are analyzing a laboratory image document.

Analyze the ENTIRE image.
Extract EVERY laboratory biomarker/test present.
Do not stop after finding common biomarkers.
Do not return only biomarkers relevant to SenoClock.
Do not return only biomarkers matching the database.
Do not summarize the report.
Do not select a subset.
Preserve every biomarker found.
Include biomarker name.
Include value.
Include unit.
Include reference range.

Return ONLY valid JSON.

Required structure:
{
  "extracted_biomarkers": [
    {
      "name": "Hemoglobin (Hb)",
      "value": "14.2",
      "unit": "g/dL",
      "range": "13.0 - 17.0"
    }
  ]
}

Do not invent values.
Do not invent reference ranges.
Do not normalize away clinically distinct tests.
PROMPT;

        if ($existingOcrText !== '') {
            $userText .= "\n\nAdditional OCR text provided by client:\n" . $existingOcrText;
        }

        return [
            'model' => config('services.openai.model', 'gpt-4o'),
            'temperature' => 0.3,
            'max_tokens' => 2000,
            'response_format' => ['type' => 'json_object'],
            'messages' => [
                ['role' => 'system', 'content' => $system],
                [
                    'role' => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => $userText],
                        ['type' => 'image_url', 'image_url' => ['url' => $dataUrl, 'detail' => 'high']],
                    ],
                ],
            ],
        ];
    }

    protected function buildOpenAiTextPayload(string $ocrText): array
    {
        return [
            'model' => config('services.openai.model', 'gpt-4o-mini'),
            'temperature' => 0.3,
            'max_tokens' => 2000,
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
Extract structured biomarker/test details from the document.
Ignore punctuation, capitalization, and small grammatical differences.
EXTRACT ALL BIOMARKERS FOUND IN THE TEXT. DO NOT TRUNCATE OR STOP EARLY. THERE MAY BE 30+ BIOMARKERS.

Use the following STANDARD SHORT NAMES as the JSON key "name" when you encounter their corresponding full forms or variations:
AAMY (Alpha-Amylase), AFP (Alpha Fetoprotein), ALB (Albumin), ALP (Alkaline Phosphatase), ALT (Alanine Transaminase / SGPT), AST (Aspartate Transaminase / SGOT), ATLYMPH (Atypical lymphocytes), BASO% (Basophils), BILID (Direct Bilirubin), BILIT (Total Bilirubin), BUN (Blood Urea Nitrogen), CA (Calcium), CHOLT (Total Cholesterol), CL (Chloride), CREA (Creatinine), EOS% (Eosinophils), ESR (Erythrocyte Sedimentation Rate), FERR (Ferritin), GGT (Gamma-GT), GLOBT (Total Globulin), GLC (Glucose / Blood Sugar Fasting), HCT (Hematocrit), HDL (HDL Cholestrol), HGB (Hemoglobin), HGBA1C (Hemoglobin A1c / HbA1c), IRON (Iron), K+ (Potassium), LDL (LDL Cholesterol), LYMPH% (Lymphocytes), MCH (Mean Corpuscular Haemoglobin), MCHC (Mean Corpuscular Haemoglobin Concentration), MCV (Mean Corpuscular Volume), MONO% (Monocytes), MPV (Mean Platelet Volume), NA+ (Sodium), NEUTR% (Neutrophils), P (Phosphorous), PDW (Platelet Distribution Width), PLT (Platelets / Platelet Count), PROT (Total Protein), RBC (Red Blood Cell / RBC Count), RDW (Red Cell Distribution Width), TRIG (Triglycerides), UA (Uric Acid), WBC (White Blood Cell / Total WBC Count), CRP (C-reactive protein), VIT-D (Vitamin D), PTH (Parathyroid hormone), APOB (Apolipoprotein B), LDH (Lactate Dehydrogenase), MG+ (Magnesium), GFR (Glomerular Filtration Rate), IGF-1 (Insulin-like Growth Factor-1), C-PEPTIDE (Connecting peptide).

For any other biomarkers not in this list, use their name as found in the text.

Return ONLY valid JSON with this shape:
{
  "extracted_biomarkers": [
    {
      "name": "GLC",
      "value": "91",
      "unit": "mg/dL",
      "range": "70-99"
    }
  ],
  "confidence_score": 0.9
}
If a value is not found or is non-numerical, set it to null.
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
EXTRACT EVERY SINGLE BIOMARKER FOUND IN THE TEXT. DO NOT TRUNCATE OR STOP EARLY. THERE MAY BE 30+ BIOMARKERS.
Return ONLY valid JSON with this shape:
{
  "markers": {
    "CHOLT": {
      "range": "115.00 - 190.00",
      "unit": "mg/dl",
      "value": 165.7
    }
  }
}
If a value is not a number, try to clean it up (e.g. "42.5"). If it's a string like "Positive", you can return it as the value.
Use the following STANDARD SHORT NAMES as the JSON key when you encounter their corresponding full forms or variations:
AAMY (Alpha-Amylase), AFP (Alpha Fetoprotein), ALB (Albumin), ALP (Alkaline Phosphatase), ALT (Alanine Transaminase / SGPT), AST (Aspartate Transaminase / SGOT), ATLYMPH (Atypical lymphocytes), BASO% (Basophils), BILID (Direct Bilirubin), BILIT (Total Bilirubin), BUN (Blood Urea Nitrogen), CA (Calcium), CHOLT (Total Cholesterol), CL (Chloride), CREA (Creatinine), EOS% (Eosinophils), ESR (Erythrocyte Sedimentation Rate), FERR (Ferritin), GGT (Gamma-GT), GLOBT (Total Globulin), GLC (Glucose / Blood Sugar Fasting), HCT (Hematocrit), HDL (HDL Cholestrol), HGB (Hemoglobin), HGBA1C (Hemoglobin A1c / HbA1c), IRON (Iron), K+ (Potassium), LDL (LDL Cholesterol), LYMPH% (Lymphocytes), MCH (Mean Corpuscular Haemoglobin), MCHC (Mean Corpuscular Haemoglobin Concentration), MCV (Mean Corpuscular Volume), MONO% (Monocytes), MPV (Mean Platelet Volume), NA+ (Sodium), NEUTR% (Neutrophils), P (Phosphorous), PDW (Platelet Distribution Width), PLT (Platelets / Platelet Count), PROT (Total Protein), RBC (Red Blood Cell / RBC Count), RDW (Red Cell Distribution Width), TRIG (Triglycerides), UA (Uric Acid), WBC (White Blood Cell / Total WBC Count), CRP (C-reactive protein), VIT-D (Vitamin D), PTH (Parathyroid hormone), APOB (Apolipoprotein B), LDH (Lactate Dehydrogenase), MG+ (Magnesium), GFR (Glomerular Filtration Rate), IGF-1 (Insulin-like Growth Factor-1), C-PEPTIDE (Connecting peptide).

For any other biomarkers not in this list, use a sanitized uppercase version of their name (e.g., "UNKNOWN_MARKER") as the key.
Do not include markdown. Do not include explanations outside JSON.
PROMPT;

        $ocrText = mb_convert_encoding($ocrText, 'UTF-8', 'UTF-8');
        
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
            $response = Http::timeout(240)
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

        $cleanText = trim(implode("\n", array_unique($texts)));
        
        // Prevent binary garbage from compressed PDFs from being passed to AI
        $totalCount = strlen($cleanText);
        if ($totalCount > 0) {
            $printableCount = preg_match_all('/[a-zA-Z0-9\s.,;:!?"\'()-]/', $cleanText);
            if (($printableCount / $totalCount) < 0.5) {
                return ''; // Discard garbage text
            }
        }

        return $cleanText;
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
            // Present if panel name found OR ALL biomarkers from the panel are found.
            $isPresent = $panelMatched || $matchedCount === $biomarkerTotal;
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
            $nameStr = is_array($name) ? ($name['name'] ?? '') : $name;
            if ($this->namesMatch($needleNorm, $this->normalize((string) $nameStr))) {
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
                $nameStr = is_array($name) ? ($name['name'] ?? '') : $name;
                if ($this->namesMatch($this->normalize($alias), $this->normalize((string) $nameStr))) {
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
        
        // Use Senoclock mapping for standard biomarker matching
        try {
            $aiService = app(\App\Services\SenoclockAiService::class);
            $mapping = $aiService->getSenoclockMapping();
            $keyA = $aiService->findSenoclockKey($a, $mapping);
            $keyB = $aiService->findSenoclockKey($b, $mapping);
            
            if ($keyA !== null && $keyA === $keyB) {
                return true;
            }
        } catch (\Throwable $e) {
            // Ignore if service not available
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
