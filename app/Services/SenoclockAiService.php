<?php

namespace App\Services;

use App\Models\AI_Vital;
use App\Models\Constants;
use App\Models\Users;
use App\Models\LabReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SenoclockAiService
{
    public function processAiVital(AI_Vital $aiVital, ?Users $user = null, ?Request $request = null, ?string $email = null, ?string $password = null): ?array
    {
        try {
            $email = $email ?: ($request ? $request->input('email') : null) ?: (string) config('services.senoclock.email');
            $password = $password ?: ($request ? $request->input('password') : null) ?: (string) config('services.senoclock.password');

            if (empty($email) || empty($password)) {
                Log::warning('Senoclock AI skipped: credentials not configured', [
                    'ai_vital_id' => $aiVital->id,
                    'user_id' => $aiVital->user_id,
                ]);
                $errorResponse = ['success' => false, 'message' => 'Senoclock AI API Error: Credentials not configured in .env'];
                $aiVital->senoclock_ai_response = $errorResponse;
                $aiVital->save();
                return $errorResponse;
            }

            $age = null;
            $sex = null;

            if ($request) {
                $age = $request->input('age');
                $sex = $request->input('sex') ?? $request->input('gender');
            }

            if ($user) {
                $age = $age ?? $this->resolveAge($user);
                $sex = $sex ?? $this->mapSex($user->gender ?? null);
            }

            $age = $age ? (int) $age : 25;
            $sex = $sex ? strtolower((string) $sex) : 'female';

            $accessToken = $this->fetchAccessToken($email, $password);
            if ($accessToken === null) {
                $errorResponse = ['success' => false, 'message' => 'Senoclock AI API Error: Failed to obtain access token or login failed'];
                $aiVital->senoclock_ai_response = $errorResponse;
                $aiVital->save();
                return $errorResponse;
            }

            $payload = $this->buildClassificationPayload($request ?? new Request(), $age, $sex, $aiVital);
            $payload = $this->normalizeClassificationPayload($payload);
            $responseBody = $this->triggerClassification($accessToken, $payload, $email, $password);
            
            if ($responseBody === null || isset($responseBody['error'])) {
                $errorMsg = $responseBody['message'] ?? 'Senoclock AI API Error: Classification failed or returned no response';
                $errorResponse = ['success' => false, 'message' => $errorMsg, 'status' => $responseBody['status'] ?? 500];
                $aiVital->senoclock_ai_response = $errorResponse;
                $aiVital->save();
                return $errorResponse;
            }

            $aiVital->senoclock_ai_response = $responseBody;
            try {
                if (Schema::hasTable('ai_vitals') && Schema::hasColumn('ai_vitals', 'shen_ai')) {
                    $aiVital->shen_ai = $responseBody;
                }
            } catch (\Throwable $e) {
                // fallback if column missing
            }
            $aiVital->save();

            return $responseBody;
        } catch (\Throwable $e) {
            Log::error('Senoclock AI integration failed', [
                'ai_vital_id' => $aiVital->id,
                'user_id' => $aiVital->user_id,
                'message' => $e->getMessage(),
            ]);
            
            $errorResponse = [
                'success' => false,
                'message' => 'Senoclock AI API Error: ' . $e->getMessage()
            ];
            
            $aiVital->senoclock_ai_response = $errorResponse;
            $aiVital->save();
            
            return $errorResponse;
        }
    }

    public function testLogin(?string $email = null, ?string $password = null): array
    {
        $email = $email ?: (string) config('services.senoclock.email');
        $password = $password ?: (string) config('services.senoclock.password');

        if ($email === '' || $password === '') {
            return [
                'success' => false,
                'message' => 'Senoclock credentials are not configured. Set SENOCLOCK_EMAIL and SENOCLOCK_PASSWORD in .env or provide them in the form.',
                'api_url' => $this->getLoginApiUrl(),
            ];
        }

        $url = $this->getLoginApiUrl();

        $response = Http::timeout(30)
            ->acceptJson()
            ->asJson()
            ->post($url, [
                'email' => $email,
                'password' => $password,
            ]);

        if (!$response->successful()) {
            return [
                'success' => false,
                'message' => 'Login failed.',
                'status' => $response->status(),
                'body' => $response->json() ?? $response->body(),
                'api_url' => $url,
            ];
        }

        $body = $response->json() ?? [];
        $accessToken = $body['access_token'] ?? null;

        if (empty($accessToken)) {
            return [
                'success' => false,
                'message' => 'Login response did not include an access_token.',
                'body' => $body,
                'api_url' => $url,
            ];
        }

        return [
            'success' => true,
            'message' => 'Login successful.',
            'access_token_preview' => substr($accessToken, 0, 20) . '...',
            'user' => $body['user'] ?? null,
            'api_url' => $url,
        ];
    }

    public function getLoginApiUrl(): string
    {
        return $this->apiUrl('/rest-auth/login/');
    }

    public function getClassificationApiUrl(): string
    {
        return $this->apiUrl('/dl-api/mulkmed/trigger-classification/');
    }

    public function testClassification(array $payload, ?string $email = null, ?string $password = null): array
    {
        $email = $email ?: (string) config('services.senoclock.email');
        $password = $password ?: (string) config('services.senoclock.password');

        if ($email === '' || $password === '') {
            return [
                'success' => false,
                'message' => 'Senoclock credentials are not configured. Set SENOCLOCK_EMAIL and SENOCLOCK_PASSWORD in .env or provide them in the form.',
                'api_url' => $this->getClassificationApiUrl(),
            ];
        }

        $payload = $this->normalizeClassificationPayload($payload);
        $classificationUrl = $this->getClassificationApiUrl();

        $accessToken = $this->fetchAccessToken($email, $password);
        if ($accessToken === null) {
            return [
                'success' => false,
                'message' => 'Failed to obtain access token. Check application logs for details.',
                'api_url' => $classificationUrl,
            ];
        }

        $url = $classificationUrl;

        $response = Http::timeout(60)
            ->acceptJson()
            ->asJson()
            ->withToken($accessToken)
            ->post($url, $payload);

        if (!$response->successful()) {
            return [
                'success' => false,
                'message' => 'Classification request failed.',
                'status' => $response->status(),
                'body' => $response->json() ?? $response->body(),
                'payload' => $payload,
                'api_url' => $url,
            ];
        }

        return [
            'success' => true,
            'message' => 'Classification completed successfully.',
            'data' => $response->json() ?? [],
            'api_url' => $url,
        ];
    }

    public function normalizeClassificationPayload(array $payload): array
    {
        if (isset($payload['heartRate']) && !isset($payload['Heart Rate (HR)'])) {
            $payload['Heart Rate (HR)'] = $payload['heartRate'];
        }
        if (isset($payload['respiratoryRate']) && !isset($payload['Breathing Rate'])) {
            $payload['Breathing Rate'] = $payload['respiratoryRate'];
        }
        if (isset($payload['stressLevel']) && !isset($payload['Stress Index'])) {
            $payload['Stress Index'] = $payload['stressLevel'];
        }
        if (isset($payload['bmi']) && !isset($payload['Body Mass Index (BMI)'])) {
            $payload['Body Mass Index (BMI)'] = $payload['bmi'];
        }
        if (isset($payload['bloodPressure']) && is_string($payload['bloodPressure']) && !isset($payload['Blood Pressure'])) {
            if (preg_match('/^(\d+)\/(\d+)/', trim($payload['bloodPressure']), $matches)) {
                $payload['Blood Pressure'] = [
                    'systolic' => (int) $matches[1],
                    'diastolic' => (int) $matches[2],
                ];
            }
        }

        if (empty($payload['age'])) {
            $payload['age'] = 25;
        }
        if (empty($payload['sex'])) {
            $payload['sex'] = 'female';
        }

        $numericKeys = [
            'age',
            'Body Fat %',
            'Stress Index',
            'Vascular Age',
            'Breathing Rate',
            'Wellness Score',
            'Heart Rate (HR)',
            'Cardiac Workload',
            'Conicity Index (CI)',
            'Body Mass Index (BMI)',
            'Parasympathetic Activity',
            'A Body Shape Index (ABSI)',
            'Basal Metabolic Rate (BMR)',
            'Body Roundness Index (BRI)',
            'Cardiovascular Disease Risk',
            'Hard and Fatal Events Risks',
            'Heart Rate Variability (HRV)',
            'Waist-to-Height Ratio (WHtR)',
            'Total Daily Energy Expenditure (TDEE)',
            'Cardiovascular Risk Score (Framingham FRS)',
        ];

        foreach ($numericKeys as $key) {
            if (!array_key_exists($key, $payload)) {
                continue;
            }

            $payload[$key] = $this->castNumericValue($payload[$key]);
        }

        if (isset($payload['Blood Pressure']) && is_array($payload['Blood Pressure'])) {
            foreach (['systolic', 'diastolic'] as $bpKey) {
                if (array_key_exists($bpKey, $payload['Blood Pressure'])) {
                    $payload['Blood Pressure'][$bpKey] = $this->castNumericValue($payload['Blood Pressure'][$bpKey]);
                }
            }
        }

        return array_filter(
            $payload,
            static fn ($value) => $value !== null && $value !== ''
        );
    }

    private function castNumericValue(mixed $value): mixed
    {
        if (is_int($value) || is_float($value)) {
            return $value;
        }

        if (is_string($value) && $value !== '' && is_numeric($value)) {
            return str_contains($value, '.') ? (float) $value : (int) $value;
        }

        return $value;
    }

    private function fetchAccessToken(string $email, string $password, bool $forceRefresh = false): ?string
    {
        $cacheKey = 'senoclock_access_token_' . md5($email);

        if (!$forceRefresh) {
            $cachedToken = Cache::get($cacheKey);
            if (!empty($cachedToken)) {
                return $cachedToken;
            }
        }

        $endpoints = [
            [
                'url' => $this->apiUrl('/rest-auth/login/'),
                'body' => ['email' => $email, 'password' => $password]
            ],
            [
                'url' => $this->apiUrl('/rest-auth/login/'),
                'body' => ['username' => $email, 'password' => $password]
            ],
            [
                'url' => $this->apiUrl('/dl-api/login/'),
                'body' => ['username' => $email, 'password' => $password]
            ],
            [
                'url' => $this->apiUrl('/dl-api/api-token-auth/'),
                'body' => ['username' => $email, 'password' => $password]
            ]
        ];

        foreach ($endpoints as $endpoint) {
            try {
                $response = Http::timeout(30)
                    ->withoutVerifying()
                    ->acceptJson()
                    ->asJson()
                    ->post($endpoint['url'], $endpoint['body']);

                if ($response->successful()) {
                    $token = $response->json('access_token') 
                          ?? $response->json('access') 
                          ?? $response->json('token') 
                          ?? $response->json('key');
                          
                    if (!empty($token)) {
                        Cache::put($cacheKey, $token, now()->addHours(12));
                        Log::info("Senoclock AI background login successful via {$endpoint['url']}.");
                        return $token;
                    }
                }
            } catch (\Throwable $e) {
                Log::warning("Senoclock AI token fetch warning for URL {$endpoint['url']}: " . $e->getMessage());
            }
        }

        Log::error('Senoclock AI background login failed on all endpoints');
        return null;
    }

    private function triggerClassification(string $accessToken, array $payload, string $email = '', string $password = '', bool $isRetry = false): ?array
    {
        $url = $this->apiUrl('/dl-api/mulkmed/trigger-classification/');

        $response = Http::timeout(60)
            ->acceptJson()
            ->asJson()
            ->withToken($accessToken)
            ->post($url, $payload);

        if ($response->status() === 401 && !$isRetry && !empty($email) && !empty($password)) {
            Log::info('Senoclock AI token expired, auto-refreshing token and retrying...');
            $newToken = $this->fetchAccessToken($email, $password, true);
            if ($newToken) {
                return $this->triggerClassification($newToken, $payload, $email, $password, true);
            }
        }

        if (!$response->successful()) {
            Log::error('Senoclock AI classification failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'payload' => $payload,
            ]);
            return ['error' => true, 'status' => $response->status(), 'message' => $response->body()];
        }

        return $response->json() ?? [];
    }

    private function buildClassificationPayload(Request $request, int $age, string $sex, ?AI_Vital $aiVital = null): array
    {
        $report = [];
        if ($aiVital && !empty($aiVital->report)) {
            $report = $this->parseReport($aiVital->report);
        }
        if (empty($report) && $request->has('report')) {
            $report = $this->parseReport($request->report);
        }

        $allInputs = $request->all();
        if (isset($allInputs['payload']) && is_array($allInputs['payload'])) {
            $report = array_merge($allInputs['payload'], $report);
        }
        $report = array_merge($allInputs, $report);

        $payload = [
            'user_id' => (string) ($request->user_id ?? $aiVital?->user_id ?? ''),
            'appointment_id' => (string) ($request->appointment_id ?? $aiVital?->appointment_id ?? '0'),
            'date' => $request->date ?? $request->scan_date ?? $aiVital?->scan_date ?? date('Y-m-d H:i:s'),
            'age' => $age,
            'sex' => $sex,
            'heartRate' => $this->reportValue($report, ['heartRate', 'heart_rate', 'Heart Rate (HR)', 'hr']),
            'bloodPressure' => $this->reportValue($report, ['bloodPressure', 'blood_pressure', 'Blood Pressure', 'bp']),
            'oxygenSaturation' => $this->reportValue($report, ['oxygenSaturation', 'spo2', 'oxygen_saturation', 'SpO2']),
            'temperature' => $this->reportValue($report, ['temperature', 'temp']),
            'respiratoryRate' => $this->reportValue($report, ['respiratoryRate', 'respiratory_rate', 'breathingRate', 'breathing_rate', 'Breathing Rate']),
            'stressLevel' => $this->reportValue($report, ['stressLevel', 'stress_level', 'stressIndex', 'stress_index', 'Stress Index']),
            'bmi' => $this->reportValue($report, ['bmi', 'Body Mass Index (BMI)']),
            'weight' => $this->reportValue($report, ['weight']),
            'height' => $this->reportValue($report, ['height']),
            'hrvSdnnMs' => $this->reportValue($report, ['hrvSdnnMs', 'hrv', 'Heart Rate Variability (HRV)']),
            'parasympatheticActivity' => $this->reportValue($report, ['parasympatheticActivity', 'parasympathetic_activity', 'Parasympathetic Activity']),
            'cardiacWorkload' => $this->reportValue($report, ['cardiacWorkload', 'cardiac_workload', 'Cardiac Workload']),
            'wellnessScore' => $this->reportValue($report, ['wellnessScore', 'wellness_score', 'Wellness Score']),
            'vascularAge' => $this->reportValue($report, ['vascularAge', 'vascular_age', 'Vascular Age']),
            'bodyFat' => $this->reportValue($report, ['bodyFat', 'body_fat', 'Body Fat %']),
        ];

        if (isset($report['healthIndices']) && is_array($report['healthIndices'])) {
            $payload['healthIndices'] = $report['healthIndices'];
        }

        return array_filter(
            $payload,
            static fn ($value) => $value !== null && $value !== ''
        );
    }

    private function parseReport(mixed $report): array
    {
        if (is_string($report)) {
            $trimmed = trim($report);
            $decoded = json_decode($trimmed, true);
            if (is_array($decoded)) {
                return $decoded;
            }

            // Fix Javascript object syntax with unquoted keys/values (e.g. {heartRate: 70.4, bloodPressure: 121/77})
            $fixed = preg_replace('/([{,])\s*([a-zA-Z0-9_]+)\s*:/', '$1"$2":', $trimmed);
            $fixed = preg_replace('/:\s*([a-zA-Z_][a-zA-Z0-9_\.]*)\s*([,}])/', ':"$1"$2', $fixed);
            $decoded = json_decode($fixed, true);
            if (is_array($decoded)) {
                return $decoded;
            }

            return [];
        }

        if (is_array($report)) {
            return $report;
        }

        if (is_object($report)) {
            return (array) $report;
        }

        return [];
    }

    private function reportValue(array $report, string|array $keys): mixed
    {
        $keys = (array) $keys;
        foreach ($keys as $key) {
            if (array_key_exists($key, $report) && $report[$key] !== null) {
                return $report[$key];
            }
        }

        return null;
    }

    private function resolveAge(Users $user): ?int
    {
        if (empty($user->dob)) {
            return null;
        }

        try {
            return $user->age();
        } catch (\Throwable $e) {
            Log::warning('Senoclock AI: could not resolve user age from dob', [
                'user_id' => $user->id,
                'dob' => $user->dob,
                'message' => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function mapSex(mixed $gender): string
    {
        if ($gender === null || $gender === '') {
            return 'male';
        }
        $genderInt = (int)$gender;
        if ($genderInt === Constants::genderMale) {
            return 'male';
        }
        if ($genderInt === Constants::genderFemale) {
            return 'female';
        }
        $genderStr = strtolower(trim((string)$gender));
        if ($genderStr === 'female' || $genderStr === '0' || $genderStr === 'f') {
            return 'female';
        }
        return 'male';
    }

    private function apiUrl(string $path): string
    {
        $baseUrl = rtrim((string) config('services.senoclock.base_url'), '/');

        return $baseUrl . $path;
    }

    /**
     * Process Senoclock integration steps for a LabReport.
     */
    public function processLabReport(LabReport $labReport): void
    {
        try {
            $labReport->senoclock_status = 'processing';
            $labReport->save();

            if (empty($labReport->document_path)) {
                Log::error("Senoclock AI integration skipped: No document path for LabReport #{$labReport->id}");
                $labReport->senoclock_status = 'failed';
                $labReport->save();
                return;
            }

            $documentPath = public_path($labReport->document_path);
            if (!file_exists($documentPath)) {
                $documentPath = storage_path('app/public/' . ltrim($labReport->document_path, '/'));
            }
            if (!file_exists($documentPath)) {
                $documentPath = storage_path('app/' . ltrim($labReport->document_path, '/'));
            }

            if (!file_exists($documentPath)) {
                Log::error("Senoclock AI integration failed: Original PDF not found for LabReport #{$labReport->id} at {$documentPath}");
                $labReport->senoclock_status = 'failed';
                $labReport->save();
                return;
            }

            $email = (string) config('services.senoclock.email');
            $password = (string) config('services.senoclock.password');

            $token = $this->fetchAccessToken($email, $password);
            if (!$token) {
                Log::error("Senoclock AI integration failed: Failed to get API token");
                $labReport->senoclock_status = 'failed';
                $labReport->save();
                return;
            }

            // Step 1: Upload the original PDF
            $fileId = $this->uploadPdfToSenoclock($documentPath, $token);
            if (!$fileId) {
                Log::info("Senoclock AI PDF upload failed. Retrying with a fresh token...");
                $token = $this->fetchAccessToken($email, $password, true);
                if ($token) {
                    $fileId = $this->uploadPdfToSenoclock($documentPath, $token);
                }
            }

            if (!$fileId) {
                Log::error("Senoclock AI integration failed: PDF upload failed after token refresh");
                $labReport->senoclock_status = 'failed';
                $labReport->save();
                return;
            }

            // Save File ID immediately
            $labReport->senoclock_id = $fileId;
            $labReport->save();

            // Step 2: Convert AI Biomarkers to Senoclock Format
            $availableBiomarkers = $labReport->available_biomarkers ?? [];
            $analysisResponse = $labReport->analysis_response;
            $extractedBiomarkers = $analysisResponse['extracted_biomarkers'] ?? [];

            $availableCount = $labReport->available_count ?? count($availableBiomarkers);

            // Case 4: available_count is 0
            if ($availableCount === 0) {
                Log::warning("Senoclock AI integration skipped: available_count is 0 for LabReport #{$labReport->id}");
                $labReport->senoclock_status = 'failed';
                $labReport->save();
                return;
            }

            // 1. Extract ALL markers from the report
            $extractedMarkers = $this->convertBiomarkersToSenoclockFormat($extractedBiomarkers);
            if (empty($extractedMarkers) && !empty($labReport->ocr_text)) {
                $analyzerService = app(\App\Services\LabReportBiomarkerAnalyzerService::class);
                $extractedMarkers = $analyzerService->extractSenoclockMarkersWithOpenAi($labReport->ocr_text);
            }

            // Case 3: No matching markers in extraction
            if (empty($extractedMarkers)) {
                Log::warning("Senoclock AI integration failed: No markers extracted from report for LabReport #{$labReport->id}");
                $labReport->senoclock_status = 'failed';
                $labReport->save();
                return;
            }

            // 2. Determine "available" markers based on business logic
            $availableKeys = $this->getExpectedSenoclockKeys($availableBiomarkers);

            Log::info('SenoclockService: Available markers from analyzeReport', [
                'available_count' => $availableCount,
                'available_test_count' => count($availableBiomarkers),
                'available_markers' => $availableKeys,
                'available_marker_count' => count($availableKeys),
            ]);

            $mapping = $this->getSenoclockMapping();
            $normalizedExtractedMarkers = [];
            foreach ($extractedMarkers as $rawKey => $markerData) {
                $mKey = $this->findSenoclockKey($rawKey, $mapping);
                $finalKey = $mKey ?: strtoupper(trim($rawKey));
                // Only take the first one if there are duplicates
                if (!isset($normalizedExtractedMarkers[$finalKey])) {
                    $normalizedExtractedMarkers[$finalKey] = $markerData;
                }
            }

            // 3. Filter markers (Intersection)
            $filteredMarkers = [];
            foreach ($availableKeys as $markerKey) {
                if (isset($normalizedExtractedMarkers[$markerKey])) {
                    $filteredMarkers[$markerKey] = $normalizedExtractedMarkers[$markerKey];
                }
            }
            
            $filteredMarkerCount = count($filteredMarkers);

            Log::info('SenoclockService: Final marker filtering', [
                'extracted_marker_count' => count($extractedMarkers),
                'normalized_extracted_markers' => array_keys($normalizedExtractedMarkers),
                'available_marker_count' => count($availableKeys),
                'filtered_marker_count' => count($filteredMarkers),
                'sent_markers' => array_keys($filteredMarkers),
            ]);

            // 4. Validate before execution
            if ($availableCount > 0 && empty($filteredMarkers)) {
                Log::error('SenoclockService: No matching available markers found', [
                    'available_count' => $availableCount,
                    'available_markers' => $availableKeys,
                    'extracted_markers' => array_keys($extractedMarkers),
                ]);
                $labReport->senoclock_status = 'failed';
                $labReport->save();
                return; // Stop execution to prevent file-execute with empty markers
            }

            // APP BUSINESS RULE: Minimum 16 markers required for report generation
            if ($filteredMarkerCount < 16) {
                Log::warning('SenoclockService: Insufficient markers for report generation', [
                    'available_marker_count' => count($availableKeys),
                    'extracted_marker_count' => count($extractedMarkers),
                    'filtered_marker_count' => $filteredMarkerCount,
                    'required_marker_count' => 16,
                    'sent_markers' => array_keys($filteredMarkers),
                ]);
                $labReport->senoclock_status = 'failed';
                $labReport->save();
                return; // Stop execution
            }

            // Fix the Logging to exact acceptance criteria
            Log::info('SenoclockService: Report generation eligibility', [
                'extracted_marker_count' => count($extractedMarkers),
                'available_marker_count' => count($availableKeys),
                'filtered_marker_count' => $filteredMarkerCount,
                'minimum_required_for_app' => 16,
                'senoclock_minimum_required' => 15,
                'eligible' => $filteredMarkerCount >= 16,
                'markers' => array_keys($filteredMarkers),
            ]);

            Log::info('SenoclockService: Marker filtering result', [
                'available_count' => $availableCount,
                'available_test_count' => count($availableBiomarkers),
                'available_marker_count' => count($availableKeys),
                'extracted_marker_count' => count($extractedMarkers),
                'filtered_marker_count' => count($filteredMarkers),
                'available_markers' => $availableKeys,
                'extracted_markers' => array_keys($extractedMarkers),
                'sent_markers' => array_keys($filteredMarkers),
                'excluded_markers' => array_values(
                    array_diff(
                        array_keys($extractedMarkers),
                        array_keys($filteredMarkers)
                    )
                ),
            ]);

            // Ensure no later code replaces $filteredMarkers
            $senoclockMarkers = $filteredMarkers;
            
            // Step 3: Execute Senoclock Analysis
            $user = Users::find($labReport->user_id);
            $dob = null;
            $age = 25;
            $gender = 'male';
            
            if ($user) {
                $dob = $user->dob ? \Carbon\Carbon::parse($user->dob)->format('Y-m-d') : null;
                $age = $user->dob ? \Carbon\Carbon::parse($user->dob)->age : 25;
                $gender = $this->mapSex($user->gender);
            }

            $testDate = $labReport->created_at ? $labReport->created_at->format('Y-m-d') : date('Y-m-d');

            $executePayload = [
                'id' => $fileId,
                'external_id' => strval($labReport->user_id),
                'dob' => $dob,
                'age' => $age,
                'gender' => $gender,
                'test_date' => $testDate,
                'markers' => $senoclockMarkers,
            ];

            $executeJson = $this->executeSenoclockAnalysis($fileId, $executePayload, $token);
            if (!$executeJson || (isset($executeJson['status']) && $executeJson['status'] !== 'Ok')) {
                Log::info("Senoclock AI execution failed. Retrying with a fresh token...");
                $token = $this->fetchAccessToken($email, $password, true);
                if ($token) {
                    $executeJson = $this->executeSenoclockAnalysis($fileId, $executePayload, $token);
                }
            }

            if (!$executeJson || (isset($executeJson['status']) && $executeJson['status'] !== 'Ok')) {
                Log::error("Senoclock AI integration: Execution failed for LabReport #{$labReport->id} after token refresh");
                $labReport->senoclock_status = 'failed';
                $labReport->save();
                return;
            }

            $reportId = $executeJson['report_id'] ?? $executeJson['id'] ?? $fileId;
            $labReport->senoclock_id = $reportId;
            $labReport->save();

            // Step 4: Generate and Store PDF Report
            $pdfPath = $this->downloadAndSaveSenoclockReport($reportId, $token);
            if (!$pdfPath) {
                Log::info("Senoclock AI report download failed. Retrying with a fresh token...");
                $token = $this->fetchAccessToken($email, $password, true);
                if ($token) {
                    $pdfPath = $this->downloadAndSaveSenoclockReport($reportId, $token);
                }
            }

            if (!$pdfPath) {
                Log::error("Senoclock AI integration failed: PDF download failed after token refresh");
                $labReport->senoclock_status = 'failed';
                $labReport->save();
                return;
            }

            $labReport->senoclock_pdf_path = $pdfPath;
            $labReport->senoclock_status = 'completed';
            $labReport->senoclock_generated_at = now();
            $labReport->save();

            Log::info("Senoclock AI integration completed successfully for LabReport #{$labReport->id}");

        } catch (\Throwable $e) {
            Log::error("Senoclock AI integration exception: " . $e->getMessage(), [
                'lab_report_id' => $labReport->id,
                'trace' => $e->getTraceAsString(),
            ]);
            $labReport->senoclock_status = 'failed';
            $labReport->save();
        }
    }

    /**
     * Step 1 – Upload PDF file to Senoclock File Upload endpoint.
     */
    public function uploadPdfToSenoclock(string $filePath, string $token): ?string
    {
        if (!file_exists($filePath)) {
            Log::error("Senoclock AI upload: file not found at {$filePath}");
            return null;
        }

        $baseUrl = rtrim((string) config('services.senoclock.base_url'), '/');
        $url = "{$baseUrl}/dl-api/file-upload/";

        $response = Http::withoutVerifying()->withToken($token)
            ->attach('file', file_get_contents($filePath), basename($filePath))
            ->put($url, [
                'process_execute' => 'true',
                'diet_preference' => 'non_veg',
                'preferred_language' => 'en'
            ]);

        if (!$response->successful()) {
            Log::error("Senoclock AI upload failed: " . $response->body());
            return null;
        }

        $fileId = $response->json('id');
        if (empty($fileId)) {
            Log::error("Senoclock AI upload response missing ID: " . $response->body());
            return null;
        }

        return $fileId;
    }

    /**
     * Get the standard mapping for Senoclock biomarkers.
     */
    public function getSenoclockMapping(): array
    {
        return [
            // AAMY - Alpha-Amylase
            'alpha-amylase' => 'AAMY',
            'aamy' => 'AAMY',
            
            // AFP - Alpha Fetoprotein
            'alpha fetoprotein' => 'AFP',
            'afp' => 'AFP',
            
            // ALB - Albumin
            'albumin' => 'ALB',
            'alb' => 'ALB',
            
            // ALP - Alkaline Phosphatase
            'alkaline phosphatase' => 'ALP',
            'alp' => 'ALP',
            
            // ALT - Alanine Transaminase
            'alanine transaminase' => 'ALT',
            'sgpt' => 'ALT',
            'alt' => 'ALT',
            
            // AST - Aspartate Transaminase
            'aspartate transaminase' => 'AST',
            'sgot' => 'AST',
            'ast' => 'AST',
            
            // ATLYMPH - Atypical lymphocytes
            'atypical lymphocytes' => 'ATLYMPH',
            'atlymph' => 'ATLYMPH',
            
            // BASO% - Basophils,%
            'basophils,%' => 'BASO%',
            'basophils' => 'BASO%',
            'baso' => 'BASO%',
            'baso%' => 'BASO%',
            
            // BILID - Direct Bilirubin
            'direct bilirubin' => 'BILID',
            'bilid' => 'BILID',
            
            // BILIT - Total Bilirubin
            'total bilirubin' => 'BILIT',
            'bilit' => 'BILIT',
            
            // BUN - Blood Urea Nitrogen
            'blood urea nitrogen' => 'BUN',
            'blood urea nitrogen (bun)' => 'BUN',
            'bun' => 'BUN',
            
            // CA - Calcium
            'calcium' => 'CA',
            'ca' => 'CA',
            
            // CHOLT - Total Cholesterol
            'total cholesterol' => 'CHOLT',
            'cholesterol' => 'CHOLT',
            'cholt' => 'CHOLT',
            
            // CL - Chloride
            'chloride' => 'CL',
            'cl' => 'CL',
            
            // CREA - Creatinine
            'creatinine' => 'CREA',
            'serum creatinine' => 'CREA',
            'crea' => 'CREA',
            
            // EOS% - Eosinophils,%
            'eosinophils,%' => 'EOS%',
            'eosinophils' => 'EOS%',
            'eos' => 'EOS%',
            'eos%' => 'EOS%',
            
            // ESR - Erythrocyte Sedimentation Rate
            'erythrocyte sedimentation rate' => 'ESR',
            'esr' => 'ESR',
            
            // FERR - Ferritin
            'ferritin' => 'FERR',
            'ferr' => 'FERR',
            
            // GGT - Gamma-GT
            'gamma-gt' => 'GGT',
            'ggt' => 'GGT',
            
            // GLOBT - Total Globulin
            'total globulin' => 'GLOBT',
            'globulin' => 'GLOBT',
            'globt' => 'GLOBT',
            
            // GLC - Glucose
            'glucose' => 'GLC',
            'blood sugar (fasting)' => 'GLC',
            'blood glucose (fasting)' => 'GLC',
            'glc' => 'GLC',
            
            // HCT - Hematocrit
            'hematocrit' => 'HCT',
            'hct' => 'HCT',
            
            // HDL - HDL Cholestrol
            'hdl cholestrol' => 'HDL',
            'hdl cholesterol' => 'HDL',
            'hdl' => 'HDL',
            
            // HGB - Hemoglobin
            'hemoglobin' => 'HGB',
            'hemoglobin (hb)' => 'HGB',
            'hb' => 'HGB',
            'hgb' => 'HGB',
            
            // HGBA1C - Hemoglobin A1c
            'hemoglobin a1c' => 'HGBA1C',
            'hba1c' => 'HGBA1C',
            'hgba1c' => 'HGBA1C',
            
            // IRON - Iron
            'iron' => 'IRON',
            
            // K+ - Potassium
            'potassium' => 'K+',
            'k' => 'K+',
            'k+' => 'K+',
            
            // LDL - LDL Cholesterol
            'ldl cholesterol' => 'LDL',
            'ldl' => 'LDL',
            
            // LYMPH% - Lymphocytes,%
            'lymphocytes,%' => 'LYMPH%',
            'lymphocytes' => 'LYMPH%',
            'lymph' => 'LYMPH%',
            'lymph%' => 'LYMPH%',
            
            // MCH - Mean Corpuscular Haemoglobin
            'mean corpuscular haemoglobin' => 'MCH',
            'mch' => 'MCH',
            
            // MCHC - Mean Corpuscular Haemoglobin Concentration
            'mean corpuscular haemoglobin concentration' => 'MCHC',
            'mchc' => 'MCHC',
            
            // MCV - Mean Corpuscular Volume
            'mean corpuscular volume' => 'MCV',
            'mcv' => 'MCV',
            
            // MONO% - Monocytes,%
            'monocytes,%' => 'MONO%',
            'monocytes' => 'MONO%',
            'mono' => 'MONO%',
            'mono%' => 'MONO%',
            
            // MPV - Mean Platelet Volume
            'mean platelet volume' => 'MPV',
            'mpv' => 'MPV',
            
            // NA+ - Sodium
            'sodium' => 'NA+',
            'na' => 'NA+',
            'na+' => 'NA+',
            
            // NEUTR% - Neutrophils,%
            'neutrophils,%' => 'NEUTR%',
            'neutrophils' => 'NEUTR%',
            'neutr' => 'NEUTR%',
            'neutr%' => 'NEUTR%',
            
            // P - Phosphorous
            'phosphorous' => 'P',
            'p' => 'P',
            
            // PDW - Platelet Distribution Width
            'platelet distribution width' => 'PDW',
            'pdw' => 'PDW',
            
            // PLT - Platelets
            'platelets' => 'PLT',
            'platelet count' => 'PLT',
            'plt' => 'PLT',
            
            // PROT - Total Protein
            'total protein' => 'PROT',
            'prot' => 'PROT',
            
            // RBC - Red Blood Cell
            'red blood cell' => 'RBC',
            'rbc count' => 'RBC',
            'rbc' => 'RBC',
            
            // RDW - Red Cell Distribution Width
            'red cell distribution width' => 'RDW',
            'rdw' => 'RDW',
            
            // TRIG - Triglycerides
            'triglycerides' => 'TRIG',
            'trig' => 'TRIG',
            'tg' => 'TRIG',
            
            // UA - Uric Acid
            'uric acid' => 'UA',
            'ua' => 'UA',
            
            // WBC - White Blood Cell
            'white blood cell' => 'WBC',
            'total wbc count' => 'WBC',
            'wbc count' => 'WBC',
            'wbc' => 'WBC',
            
            // CRP - C-reactive protein
            'c-reactive protein' => 'CRP',
            'c-reactive protein (crp)' => 'CRP',
            'crp' => 'CRP',
            
            // VIT-D - Vitamin D
            'vitamin d' => 'VIT-D',
            'vit-d' => 'VIT-D',
            
            // PTH - Parathyroid hormone
            'parathyroid hormone' => 'PTH',
            'thyroid stimulating hormone' => 'TSH', // TSH is generally used instead of PTH sometimes but PTH is Parathyroid
            'pth' => 'PTH',
            'tsh' => 'TSH',
            
            // APOB - Apolipoprotein B
            'apolipoprotein b' => 'APOB',
            'apob' => 'APOB',
            
            // LDH - Lactate Dehydrogenase
            'lactate dehydrogenase' => 'LDH',
            'ldh' => 'LDH',
            
            // MG+ - Magnesium
            'magnesium' => 'MG+',
            'mg' => 'MG+',
            'mg+' => 'MG+',
            
            // GFR - Glomerular Filtration Rate
            'glomerular filtration rate' => 'GFR',
            'gfr' => 'GFR',
            
            // IGF-1 - Insulin-like Growth Factor-1
            'insulin-like growth factor-1' => 'IGF-1',
            'igf-1' => 'IGF-1',
            
            // C-PEPTIDE - Connecting peptide
            'connecting peptide' => 'C-PEPTIDE',
            'c-peptide' => 'C-PEPTIDE',
        ];
    }

    /**
     * Step 2 – Convert application biomarkers format dynamically to Senoclock expected format.
     */
    public function convertBiomarkersToSenoclockFormat(array $availableBiomarkers, array $extractedBiomarkers = []): array
    {
        $mapping = $this->getSenoclockMapping();

        $extractedMap = [];
        $extractedSenoclockMap = [];
        foreach ($extractedBiomarkers as $extracted) {
            $normName = '';
            if (is_array($extracted) && !empty($extracted['name'])) {
                $normName = strtolower(trim($extracted['name']));
                $extractedMap[$normName] = $extracted;
            } elseif (is_string($extracted)) {
                $normName = strtolower(trim($extracted));
                $extractedMap[$normName] = [
                    'name' => $extracted,
                    'value' => null,
                    'unit' => null,
                    'range' => null,
                ];
            }

            if ($normName !== '') {
                $sKey = $this->findSenoclockKey($normName, $mapping);
                if ($sKey) {
                    $extractedSenoclockMap[$sKey] = $extractedMap[$normName];
                }
            }
        }

        $markers = [];

        foreach ($availableBiomarkers as $biomarker) {
            if (!is_array($biomarker)) {
                continue;
            }

            $biomarkerName = strtolower(trim($biomarker['name'] ?? ''));
            $matchedKeys = [];
            
            // Check if the item itself matches a Senoclock marker directly
            $matchedKey = $this->findSenoclockKey($biomarkerName, $mapping);
            if ($matchedKey) {
                $matchedKeys[] = $matchedKey;
            }

            // Check the nested matched_biomarkers list
            if (!empty($biomarker['matched_biomarkers']) && is_array($biomarker['matched_biomarkers'])) {
                foreach ($biomarker['matched_biomarkers'] as $match) {
                    $matchName = is_array($match) ? ($match['name'] ?? '') : $match;
                    if (!empty($matchName)) {
                        $mKey = $this->findSenoclockKey(strtolower(trim($matchName)), $mapping);
                        if ($mKey) {
                            $matchedKeys[] = $mKey;
                        }
                    }
                }
            }

            $matchedKeys = array_unique($matchedKeys);

            if (empty($matchedKeys) && !empty($biomarkerName)) {
                $sanitizedKey = strtoupper(preg_replace('/[^a-zA-Z0-9]+/', '_', trim($biomarkerName)));
                $sanitizedKey = trim($sanitizedKey, '_');
                if (!empty($sanitizedKey)) {
                    $matchedKeys[] = $sanitizedKey;
                }
            }

            foreach ($matchedKeys as $sKey) {
                if (isset($markers[$sKey])) {
                    continue; // Already processed this Senoclock marker
                }

                $valObj = null;

                // Try to get value directly from extracted map by Senoclock key
                if (isset($extractedSenoclockMap[$sKey])) {
                    $valObj = $this->extractValUnitRange($extractedSenoclockMap[$sKey], $extractedMap);
                } 
                
                // Fallback to original logic if needed
                if (!$valObj) {
                    $valObj = $this->extractValUnitRange($biomarker, $extractedMap);
                }

                if ($valObj) {
                    $markers[$sKey] = $valObj;
                }
            }
        }

        return $markers;
    }

    /**
     * Extracts a flat array of valid Senoclock marker keys from the available_biomarkers structure.
     * Step 3 – Get expected keys from matched_biomarkers.
     *
     * @param array $availableBiomarkers The available_biomarkers array from LabReport
     * @return array List of valid Senoclock keys (e.g. ['HGB', 'WBC'])
     */
    public function getExpectedSenoclockKeys(array $availableBiomarkers): array
    {
        $mapping = $this->getSenoclockMapping();
        $expectedKeys = [];

        foreach ($availableBiomarkers as $biomarker) {
            $matched = is_array($biomarker['matched_biomarkers'] ?? null) ? $biomarker['matched_biomarkers'] : [];
            foreach ($matched as $matchName) {
                if (!empty($matchName)) {
                    $cleanName = strtolower(trim((string)$matchName));
                    $mKey = $this->findSenoclockKey($cleanName, $mapping);
                    if ($mKey) {
                        $expectedKeys[$mKey] = $mKey;
                    } else {
                        // Fallback: If not found in mapping, uppercase it
                        $upperKey = strtoupper(trim((string)$matchName));
                        $expectedKeys[$upperKey] = $upperKey;
                    }
                }
            }
        }

        return array_values($expectedKeys);
    }

    public function findSenoclockKey(string $name, array $mapping): ?string
    {
        $name = strtolower(trim($name));
        if (isset($mapping[$name])) {
            return $mapping[$name];
        }
        foreach ($mapping as $mapKey => $senoKey) {
            if ($mapKey === $name || str_contains($name, $mapKey) || str_contains($mapKey, $name)) {
                return $senoKey;
            }
        }
        return null;
    }

    private function extractValUnitRange(array $biomarker, array $extractedMap): ?array
    {
        $value = null;
        $unit = null;
        $range = null;

        if (!empty($biomarker['matched_biomarkers']) && is_array($biomarker['matched_biomarkers'])) {
            $first = $biomarker['matched_biomarkers'][0] ?? null;
            if (is_array($first)) {
                $value = $first['value'] ?? null;
                $unit = $first['unit'] ?? null;
                $range = $first['reference_range'] ?? $first['range'] ?? null;
            } elseif (is_string($first)) {
                $matchNorm = strtolower(trim($first));
                if (isset($extractedMap[$matchNorm])) {
                    $value = $extractedMap[$matchNorm]['value'] ?? null;
                    $unit = $extractedMap[$matchNorm]['unit'] ?? null;
                    $range = $extractedMap[$matchNorm]['range'] ?? $extractedMap[$matchNorm]['reference_range'] ?? null;
                }
            }
        }

        if ($value === null || $value === '') {
            $value = $biomarker['value'] ?? null;
            $unit = $biomarker['unit'] ?? null;
            $range = $biomarker['range'] ?? $biomarker['reference_range'] ?? null;
        }
        
        if ($value === null || $value === '') {
            $nameNorm = strtolower(trim($biomarker['name'] ?? ''));
            if (isset($extractedMap[$nameNorm])) {
                $value = $extractedMap[$nameNorm]['value'] ?? null;
                $unit = $extractedMap[$nameNorm]['unit'] ?? null;
                $range = $extractedMap[$nameNorm]['range'] ?? $extractedMap[$nameNorm]['reference_range'] ?? null;
            }
        }

        // Always return the structure if requested, or if value exists.
        // But if the user really wants the available biomarkers sent, even if value is null?
        // Let's at least return what we can.
        if ($value !== null && $value !== '') {
            if (is_numeric($value)) {
                $value = str_contains((string)$value, '.') ? (float)$value : (int)$value;
            }
            return [
                'value' => $value,
                'unit' => $unit ?: null,
                'range' => $range ?: null,
            ];
        }

        // If we strictly require value for Senoclock, we could return null. 
        // But let's return it with null if we at least matched it, so the user sees it?
        // Wait, Senoclock API might crash if value is null.
        return null;
    }

    /**
     * Step 3 – Execute the Senoclock Analysis.
     */
    public function executeSenoclockAnalysis(string $fileId, array $payload, string $token): ?array
    {
        $baseUrl = rtrim((string) config('services.senoclock.base_url'), '/');
        $url = "{$baseUrl}/dl-api/file-execute/";

        $response = Http::withoutVerifying()->withToken($token)
            ->post($url, $payload);

        if (!$response->successful()) {
            Log::error("Senoclock AI execute failed: " . $response->body(), ['payload' => $payload]);
            return null;
        }

        return $response->json();
    }

    /**
     * Step 4 – Download generated PDF report.
     */
    public function downloadAndSaveSenoclockReport(string $reportId, string $token): ?string
    {
        $baseUrl = rtrim((string) config('services.senoclock.base_url'), '/');
        $url = "{$baseUrl}/dl-api/report/download/?pdf_report=true&id=" . $reportId;

        $maxAttempts = 12;
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            $attempt++;
            Log::info("Senoclock AI download PDF report attempt {$attempt} of {$maxAttempts}...");
            
            try {
                $response = Http::withoutVerifying()->timeout(30)->withToken($token)->get($url);

                if ($response->successful()) {
                    $contentType = $response->header('Content-Type');
                    if (strpos((string)$contentType, 'application/pdf') !== false) {
                        $fileName = "senoclock_{$reportId}.pdf";
                        $uploadDir = public_path('uploads/senoclock');
                        if (!file_exists($uploadDir)) {
                            @mkdir($uploadDir, 0777, true);
                        }

                        $filePath = $uploadDir . '/' . $fileName;
                        file_put_contents($filePath, $response->body());

                        Log::info("Senoclock AI PDF report downloaded successfully after {$attempt} attempts.");
                        return 'uploads/senoclock/' . $fileName;
                    }
                }
                
                Log::info("Senoclock AI PDF report not ready yet (status: " . $response->status() . "). Waiting 5 seconds...");
            } catch (\Throwable $e) {
                Log::warning("Senoclock AI download attempt {$attempt} failed with exception: " . $e->getMessage());
            }

            if ($attempt < $maxAttempts) {
                sleep(5);
            }
        }

        Log::error("Senoclock AI download PDF report failed: Maximum polling attempts reached or API error.");
        return null;
    }
}
