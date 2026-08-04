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

            $senoclockMarkers = $this->convertBiomarkersToSenoclockFormat($availableBiomarkers, $extractedBiomarkers);
            if (empty($senoclockMarkers)) {
                $senoclockMarkers = $this->convertBiomarkersToSenoclockFormat($extractedBiomarkers);
            }

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
     * Step 2 – Convert application biomarkers format dynamically to Senoclock expected format.
     */
    public function convertBiomarkersToSenoclockFormat(array $availableBiomarkers, array $extractedBiomarkers = []): array
    {
        $mapping = [
            'hdl cholesterol' => 'HDL',
            'ldl cholesterol' => 'LDL',
            'triglycerides' => 'TRIG',
            'blood sugar (fasting)' => 'GLC',
            'blood glucose (fasting)' => 'GLC',
            'hba1c' => 'HGBA1C',
            'sgpt' => 'ALT',
            'sgot' => 'AST',
            'c-reactive protein' => 'CRP',
            'creatinine' => 'CREA',
            'albumin' => 'ALB',
            'ggt' => 'GGT',
            'platelet count' => 'PLT',
            'total wbc count' => 'WBC',
            'sodium' => 'NA+',
            'potassium' => 'K+',
            // Extra standard/common variations
            'hdl' => 'HDL',
            'ldl' => 'LDL',
            'tg' => 'TRIG',
            'trig' => 'TRIG',
            'glucose' => 'GLC',
            'glc' => 'GLC',
            'alt' => 'ALT',
            'ast' => 'AST',
            'crp' => 'CRP',
            'crea' => 'CREA',
            'creatine' => 'CREA',
            'alb' => 'ALB',
            'plt' => 'PLT',
            'wbc' => 'WBC',
            'na+' => 'NA+',
            'k+' => 'K+',
            'na' => 'NA+',
            'k' => 'K+',
        ];

        $extractedMap = [];
        foreach ($extractedBiomarkers as $extracted) {
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
        }

        $markers = [];

        foreach ($availableBiomarkers as $biomarker) {
            if (!is_array($biomarker)) {
                continue;
            }

            $biomarkerName = strtolower(trim($biomarker['name'] ?? ''));
            
            // Check if the item itself matches a Senoclock marker directly
            $matchedKey = $this->findSenoclockKey($biomarkerName, $mapping);
            if ($matchedKey) {
                $valObj = $this->extractValUnitRange($biomarker, $extractedMap);
                if ($valObj) {
                    $markers[$matchedKey] = $valObj;
                }
            }

            // Check the nested matched_biomarkers list
            if (!empty($biomarker['matched_biomarkers']) && is_array($biomarker['matched_biomarkers'])) {
                foreach ($biomarker['matched_biomarkers'] as $match) {
                    if (is_string($match)) {
                        $matchNorm = strtolower(trim($match));
                        $matchedKey = $this->findSenoclockKey($matchNorm, $mapping);
                        if ($matchedKey && isset($extractedMap[$matchNorm])) {
                            $valObj = $this->extractValUnitRange($extractedMap[$matchNorm], $extractedMap);
                            if ($valObj) {
                                $markers[$matchedKey] = $valObj;
                            }
                        }
                    } elseif (is_array($match)) {
                        $matchName = !empty($match['name']) ? strtolower(trim($match['name'])) : $biomarkerName;
                        $matchedKey = $this->findSenoclockKey($matchName, $mapping);
                        if ($matchedKey) {
                            $valObj = $this->extractValUnitRange($match, $extractedMap);
                            if ($valObj) {
                                $markers[$matchedKey] = $valObj;
                            }
                        }
                    }
                }
            }
        }

        return $markers;
    }

    private function findSenoclockKey(string $name, array $mapping): ?string
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
            }
        }

        if ($value === null || $value === '') {
            $value = $biomarker['value'] ?? null;
            $unit = $biomarker['unit'] ?? null;
            $range = $biomarker['range'] ?? $biomarker['reference_range'] ?? null;
        }

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
                        $uploadDir = public_path('uploads');
                        if (!file_exists($uploadDir)) {
                            @mkdir($uploadDir, 0777, true);
                        }

                        $filePath = $uploadDir . '/' . $fileName;
                        file_put_contents($filePath, $response->body());

                        Log::info("Senoclock AI PDF report downloaded successfully after {$attempt} attempts.");
                        return 'uploads/' . $fileName;
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
