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
use Illuminate\Support\Facades\Schema;

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

            $uploadedFileName = null;
            if ($aiVital && !empty($aiVital->pdf_file)) {
                $uploadedFileName = basename($aiVital->pdf_file);
            } elseif ($request && $request->hasFile('report_file')) {
                $uploadedFileName = $request->file('report_file')->getClientOriginalName();
            } elseif ($request && $request->input('report_file')) {
                $uploadedFileName = basename((string) $request->input('report_file'));
            }

            // Debug body logging (commented out)
            // $this->logExactTriggerClassificationBody($payload, [
            //     'source' => 'processAiVital',
            //     'ai_vital_id' => $aiVital->id,
            //     'user_id' => $aiVital->user_id,
            //     'age' => $age,
            //     'sex' => $sex,
            //     'uploaded_file_name' => $uploadedFileName,
            // ]);

            $responseBody = $this->triggerClassification($accessToken, $payload, $email, $password, false, $uploadedFileName);
            
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

        // Debug body logging (commented out)
        // $this->logExactTriggerClassificationBody($payload, [
        //     'source' => 'testClassification',
        // ]);

        $responseBody = $this->triggerClassification($accessToken, $payload, $email, $password);

        if ($responseBody === null || isset($responseBody['error'])) {
            return [
                'success' => false,
                'message' => 'Classification request failed.',
                'status' => $responseBody['status'] ?? 500,
                'body' => $responseBody['message'] ?? $responseBody,
                'payload' => $payload,
                'api_url' => $classificationUrl,
            ];
        }

        return [
            'success' => true,
            'message' => 'Classification completed successfully.',
            'data' => $responseBody,
            'api_url' => $classificationUrl,
            'request_body' => $payload,
        ];
    }

    public function normalizeClassificationPayload(array $payload): array
    {
        $aliases = [
            'heartRate' => 'Heart Rate (HR)',
            'respiratoryRate' => 'Breathing Rate',
            'stressLevel' => 'Stress Index',
            'bmi' => 'Body Mass Index (BMI)',
            'wellnessScore' => 'Wellness Score',
            'hrvSdnnMs' => 'Heart Rate Variability (HRV)',
            'basalMetabolicRate' => 'Basal Metabolic Rate (BMR)',
            'totalDailyEnergyExpenditure' => 'Total Daily Energy Expenditure (TDEE)',
            'vascularAge' => 'Vascular Age',
            'bodyFat' => 'Body Fat %',
            'cardiacWorkload' => 'Cardiac Workload',
            'parasympatheticActivity' => 'Parasympathetic Activity',
        ];

        foreach ($aliases as $from => $to) {
            if (isset($payload[$from]) && !isset($payload[$to])) {
                $payload[$to] = $payload[$from];
            } elseif (isset($payload[$to]) && !isset($payload[$from])) {
                $payload[$from] = $payload[$to];
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
            'wellnessScore',
            'hrvSdnnMs',
            'bmi',
            'basalMetabolicRate',
            'totalDailyEnergyExpenditure',
            'heartRate',
            'vascularAge',
        ];

        foreach ($numericKeys as $key) {
            if (!array_key_exists($key, $payload)) {
                continue;
            }

            // Do not cast if value is a biomarker object array
            if (is_array($payload[$key])) {
                continue;
            }

            $payload[$key] = $this->castNumericValue($payload[$key]);
        }

        return array_filter(
            $payload,
            static fn ($value) => $value !== null && $value !== ''
        );
    }

    private function unwrapMetricValue(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        if (array_key_exists('systolic', $value) || array_key_exists('diastolic', $value)) {
            return $value;
        }

        return $value['result'] ?? $value['value'] ?? $value['input_value'] ?? $value;
    }

    private function normalizeBloodPressure(mixed $bloodPressure): ?array
    {
        if ($bloodPressure === null || $bloodPressure === '') {
            return null;
        }

        if (is_array($bloodPressure)) {
            if (isset($bloodPressure['systolic']) || isset($bloodPressure['diastolic'])) {
                return [
                    'systolic' => $this->castNumericValue($bloodPressure['systolic'] ?? null),
                    'diastolic' => $this->castNumericValue($bloodPressure['diastolic'] ?? null),
                ];
            }
            $bloodPressure = $bloodPressure['result'] ?? $bloodPressure['value'] ?? null;
        }

        if (!is_string($bloodPressure) && !is_numeric($bloodPressure)) {
            return null;
        }

        if (preg_match('/(\d+)\s*\/\s*(\d+)/', trim((string) $bloodPressure), $matches)) {
            return [
                'systolic' => (int) $matches[1],
                'diastolic' => (int) $matches[2],
            ];
        }

        return null;
    }

    private function castNumericValue(mixed $value): mixed
    {
        $value = $this->unwrapMetricValue($value);

        if (is_int($value) || is_float($value)) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            $cleaned = str_replace(',', '', trim($value));
            if (is_numeric($cleaned)) {
                return str_contains($cleaned, '.') ? (float) $cleaned : (int) $cleaned;
            }
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

    private function triggerClassification(string $accessToken, array $payload, string $email = '', string $password = '', bool $isRetry = false, ?string $uploadedFileName = null): ?array
    {
        $url = $this->apiUrl('/dl-api/mulkmed/trigger-classification/');

        // Debug exact body logging (commented out)
        // $bodyJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        // Log::info('===== SENO CLOCK TRIGGER-CLASSIFICATION EXACT REQUEST BODY START =====');
        // Log::info('URL: ' . $url);
        // Log::info('METHOD: POST');
        // Log::info('Authorization: Bearer ' . substr($accessToken, 0, 12) . '...(redacted)');
        // Log::info($bodyJson !== false ? $bodyJson : '{}');
        // Log::info('===== SENO CLOCK TRIGGER-CLASSIFICATION EXACT REQUEST BODY END =====');
        // $this->writeExactBodyToFile($payload, $url, $isRetry, $uploadedFileName);

        $response = Http::timeout(60)
            ->acceptJson()
            ->asJson()
            ->withToken($accessToken)
            ->post($url, $payload);

        // Debug response logging (commented out)
        // $responseJson = json_encode($response->json() ?? $response->body(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        // Log::info('===== SENO CLOCK TRIGGER-CLASSIFICATION RESPONSE START =====');
        // Log::info('STATUS: ' . $response->status());
        // Log::info($responseJson !== false ? $responseJson : $response->body());
        // Log::info('===== SENO CLOCK TRIGGER-CLASSIFICATION RESPONSE END =====');

        // $this->writeExactResponseToFile($response->json() ?? $response->body(), $response->status(), $uploadedFileName);

        if ($response->status() === 401 && !$isRetry && !empty($email) && !empty($password)) {
            Log::info('Senoclock AI token expired, auto-refreshing token and retrying...');
            $newToken = $this->fetchAccessToken($email, $password, true);
            if ($newToken) {
                return $this->triggerClassification($newToken, $payload, $email, $password, true, $uploadedFileName);
            }
        }

        if (!$response->successful()) {
            Log::error('Senoclock AI classification failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return ['error' => true, 'status' => $response->status(), 'message' => $response->body()];
        }

        return $response->json() ?? [];
    }

    /**
     * Log exact JSON body sent to Senoclock trigger-classification.
     * (Commented out)
     */
    private function logExactTriggerClassificationBody(array $payload, array $meta = []): void
    {
        // $bodyJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        // Log::info('===== SENO CLOCK PREPARED BODY (before HTTP) START =====');
        // Log::info('meta: ' . json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        // Log::info('api_url: ' . $this->getClassificationApiUrl());
        // Log::info($bodyJson !== false ? $bodyJson : '{}');
        // Log::info('===== SENO CLOCK PREPARED BODY (before HTTP) END =====');
    }

    /**
     * Always write the exact request body to ONE dedicated JSON file based on uploaded report name.
     * (Commented out)
     */
    private function writeExactBodyToFile(array $payload, string $url, bool $isRetry = false, ?string $uploadedFileName = null): void
    {
        // try {
        //     $publicDir = public_path('uploads/ai_vital_senoclock');
        //     if (!is_dir($publicDir)) {
        //         @mkdir($publicDir, 0777, true);
        //     }
        //
        //     $fileNameOnly = $uploadedFileName ? basename($uploadedFileName) : 'uploaded_report.pdf';
        //     $nameWithoutExt = pathinfo($fileNameOnly, PATHINFO_FILENAME);
        //
        //     $formattedData = [
        //         'api_url' => $url,
        //         'method' => 'POST',
        //         'uploaded_file_name' => $fileNameOnly,
        //         'request_body' => $payload,
        //     ];
        //
        //     $content = json_encode($formattedData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        //     if ($content === false) { $content = '{}'; }
        //
        //     file_put_contents($publicDir . DIRECTORY_SEPARATOR . "{$nameWithoutExt}_body.json", $content);
        //     file_put_contents($publicDir . DIRECTORY_SEPARATOR . 'senoclock_trigger_classification_body.json', $content);
        // } catch (\Throwable $e) {
        //     Log::warning('Failed to write Senoclock request body file: ' . $e->getMessage());
        // }
    }

    /**
     * Always write the exact response body to ONE dedicated JSON file based on uploaded report name.
     * (Commented out)
     */
    private function writeExactResponseToFile(mixed $responseBody, int $statusCode, ?string $uploadedFileName = null): void
    {
        // try {
        //     $publicDir = public_path('uploads/ai_vital_senoclock');
        //     if (!is_dir($publicDir)) {
        //         @mkdir($publicDir, 0777, true);
        //     }
        //
        //     $fileNameOnly = $uploadedFileName ? basename($uploadedFileName) : 'uploaded_report.pdf';
        //     $nameWithoutExt = pathinfo($fileNameOnly, PATHINFO_FILENAME);
        //
        //     $content = json_encode([
        //         'received_at' => date('c'),
        //         'status_code' => $statusCode,
        //         'uploaded_file_name' => $fileNameOnly,
        //         'response' => $responseBody,
        //     ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        //     if ($content === false) { $content = '{}'; }
        //
        //     file_put_contents($publicDir . DIRECTORY_SEPARATOR . "{$nameWithoutExt}_response.json", $content);
        //     file_put_contents($publicDir . DIRECTORY_SEPARATOR . 'senoclock_trigger_classification_response.json', $content);
        // } catch (\Throwable $e) {
        //     Log::warning('Failed to write Senoclock response body file: ' . $e->getMessage());
        // }
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

        if ($aiVital && !empty($aiVital->shen_ai)) {
            $shen = $this->parseReport($aiVital->shen_ai);
            $looksLikeClassification = isset($shen['ranked_parameters'])
                || isset($shen['trigger'])
                || isset($shen['triggers'])
                || isset($shen['data']['ranked_parameters']);
            if (!$looksLikeClassification) {
                $report = array_merge($shen, $report);
            }
        }

        $payload = [
            'user_id' => (string) ($request->user_id ?? $aiVital?->user_id ?? ''),
            'appointment_id' => (string) ($request->appointment_id ?? $aiVital?->appointment_id ?? '0'),
            'date' => $request->date ?? $request->scan_date ?? $aiVital?->scan_date ?? date('Y-m-d H:i:s'),
            'age' => $age,
            'sex' => $sex,
        ];

        // Merge all extracted biomarker keys from $report preserving full objects
        foreach ($report as $key => $val) {
            if (in_array($key, ['user_id', 'appointment_id', 'date', 'scan_date', 'age', 'sex', 'report_file', 'file', 'email', 'password', 'report', 'payload', 'shen_ai'])) {
                continue;
            }
            if ($val !== null && $val !== '') {
                $payload[$key] = $val;
            }
        }

        // Aliases for standard keys if missing
        $aliasMap = [
            'heartRate' => ['heart_rate', 'Heart Rate (HR)', 'hr'],
            'bloodPressure' => ['blood_pressure', 'Blood Pressure', 'bp'],
            'oxygenSaturation' => ['spo2', 'oxygen_saturation', 'SpO2'],
            'temperature' => ['temp'],
            'respiratoryRate' => ['respiratory_rate', 'breathingRate', 'breathing_rate', 'Breathing Rate', 'Breathing Rate (BR)'],
            'stressLevel' => ['stress_level', 'stressIndex', 'stress_index', 'Stress Index'],
            'bmi' => ['Body Mass Index (BMI)'],
            'hrvSdnnMs' => ['hrv', 'Heart Rate Variability (HRV)'],
            'parasympatheticActivity' => ['parasympathetic_activity', 'Parasympathetic Activity'],
            'cardiacWorkload' => ['cardiac_workload', 'Cardiac Workload'],
            'wellnessScore' => ['wellness_score', 'Wellness Score'],
            'vascularAge' => ['vascular_age', 'Vascular Age'],
            'bodyFat' => ['body_fat', 'Body Fat %'],
            'basalMetabolicRate' => ['bmr', 'BMR', 'Basal Metabolic Rate (BMR)'],
            'totalDailyEnergyExpenditure' => ['tdee', 'TDEE', 'Total Daily Energy Expenditure (TDEE)'],
        ];

        foreach ($aliasMap as $primaryKey => $altKeys) {
            if (!isset($payload[$primaryKey])) {
                $foundVal = $this->reportValue($report, $altKeys);
                if ($foundVal !== null) {
                    $payload[$primaryKey] = $foundVal;
                }
            }
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

    private function flattenReportMetrics(array $report): array
    {
        $flat = $report;
        foreach (['healthIndices', 'data'] as $nestKey) {
            if (!isset($report[$nestKey]) || !is_array($report[$nestKey])) {
                continue;
            }
            foreach ($report[$nestKey] as $key => $value) {
                if ($value === null || $value === '' || $key === 'healthIndices') {
                    continue;
                }
                if (!isset($flat[$key]) || $flat[$key] === null || $flat[$key] === '') {
                    $flat[$key] = $value;
                }
            }
            if (isset($report[$nestKey]['healthIndices']) && is_array($report[$nestKey]['healthIndices'])) {
                foreach ($report[$nestKey]['healthIndices'] as $key => $value) {
                    if ($value === null || $value === '') {
                        continue;
                    }
                    if (!isset($flat[$key]) || $flat[$key] === null || $flat[$key] === '') {
                        $flat[$key] = $value;
                    }
                }
            }
        }

        return $flat;
    }

    private function reportValue(array $report, string|array $keys): mixed
    {
        $keys = (array) $keys;
        foreach ($keys as $key) {
            if (array_key_exists($key, $report) && $report[$key] !== null && $report[$key] !== '') {
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
                'external_id' => (string) $labReport->user_id,
                'dob' => $dob,
                'age' => $age,
                'gender' => $gender,
                'test_date' => $testDate,
                'height' => $analysisResponse['height'] ?? null,
                'weight' => $analysisResponse['weight'] ?? null,
                'blood_pressure' => $analysisResponse['blood_pressure'] ?? null,
                'allergies' => $analysisResponse['allergies'] ?? null,
                'markers' => (object) $senoclockMarkers,
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

        $payload = [
            'process_execute' => 'true',
            'diet_preference' => 'non_veg',
            'preferred_language' => 'en'
        ];

        Log::info("Senoclock AI upload sending payload to {$url}", [
            'file_name' => basename($filePath),
            'file_size_bytes' => filesize($filePath),
            'payload' => $payload
        ]);

        $response = Http::withoutVerifying()->withToken($token)
            ->attach('file', file_get_contents($filePath), basename($filePath))
            ->put($url, $payload);

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
            
            // AST - Aspartate Transaminase
            'aspartate transaminase' => 'AST',
            'aspartate aminotransferase' => 'AST',
            'aspartate aminotransferase (ast)' => 'AST',
            'sgot' => 'AST',
            'sgot ast' => 'AST',
            'ast' => 'AST',
            
            // ALT - Alanine Transaminase  
            'alanine transaminase' => 'ALT',
            'alanine aminotransferase' => 'ALT',
            'alanine transaminase (alt)' => 'ALT',
            'sgpt' => 'ALT',
            'sgpt alt' => 'ALT',
            'alt' => 'ALT',
            
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
            'fasting glucose' => 'GLC',
            'glucose fasting' => 'GLC',
            'blood sugar' => 'GLC',
            'blood sugar (fasting)' => 'GLC',
            'blood glucose' => 'GLC',
            'blood glucose (fasting)' => 'GLC',
            'glc' => 'GLC',
            
            // HCT - Hematocrit
            'hematocrit' => 'HCT',
            'hematocrit (pcv)' => 'HCT',
            'pcv' => 'HCT',
            'hct' => 'HCT',
            
            // HDL - HDL Cholestrol
            'hdl cholestrol' => 'HDL',
            'hdl cholesterol' => 'HDL',
            'hdl cholesterol (good)' => 'HDL',
            'hdl-c' => 'HDL',
            'high density lipoprotein' => 'HDL',
            'high-density lipoprotein' => 'HDL',
            'hdl' => 'HDL',
            
            // HGB - Hemoglobin
            'hemoglobin' => 'HGB',
            'haemoglobin' => 'HGB',
            'hemoglobin (hb)' => 'HGB',
            'haemoglobin (hb)' => 'HGB',
            'hb' => 'HGB',
            'hgb' => 'HGB',
            
            // HGBA1C - Hemoglobin A1c (must be checked before plain "hemoglobin" via longest-match)
            'hemoglobin a1c' => 'HGBA1C',
            'haemoglobin a1c' => 'HGBA1C',
            'hemoglobin a1c (hba1c)' => 'HGBA1C',
            'glycohemoglobin' => 'HGBA1C',
            'hba1c' => 'HGBA1C',
            'hgba1c' => 'HGBA1C',
            
            // IRON - Iron
            'iron' => 'IRON',
            'iron, serum' => 'IRON',
            
            // K+ - Potassium
            'potassium' => 'K+',
            'potassium, serum' => 'K+',
            'k' => 'K+',
            'k+' => 'K+',
            
            // LDL - LDL Cholesterol
            'ldl cholesterol' => 'LDL',
            'ldl cholesterol (bad)' => 'LDL',
            'ldl-c' => 'LDL',
            'low density lipoprotein' => 'LDL',
            'low-density lipoprotein' => 'LDL',
            'ldl' => 'LDL',
            
            // LYMPH% - Lymphocytes,%
            'lymphocytes,%' => 'LYMPH%',
            'lymphocytes' => 'LYMPH%',
            'lymph' => 'LYMPH%',
            'lymph%' => 'LYMPH%',
            
            // MCH - Mean Corpuscular Haemoglobin
            'mean corpuscular haemoglobin' => 'MCH',
            'mean corpuscular hemoglobin' => 'MCH',
            'mean corpuscular hemoglobin (mch)' => 'MCH',
            'mch' => 'MCH',
            
            // MCHC - Mean Corpuscular Haemoglobin Concentration
            'mean corpuscular haemoglobin concentration' => 'MCHC',
            'mean corpuscular hemoglobin concentration' => 'MCHC',
            'mean corpuscular hb conc' => 'MCHC',
            'mean corpuscular hb conc. (mchc)' => 'MCHC',
            'mchc' => 'MCHC',
            
            // MCV - Mean Corpuscular Volume
            'mean corpuscular volume' => 'MCV',
            'mean corpuscular volume (mcv)' => 'MCV',
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
            'sodium, serum' => 'NA+',
            'na' => 'NA+',
            'na+' => 'NA+',
            
            // NEUTR% - Neutrophils,%
            'neutrophils,%' => 'NEUTR%',
            'neutrophils' => 'NEUTR%',
            'neutr' => 'NEUTR%',
            'neutr%' => 'NEUTR%',
            
            // P - Phosphorous
            'phosphorous' => 'P',
            'phosphorus' => 'P',
            'phosphorous, inorganic' => 'P',
            'phosphorus, inorganic' => 'P',
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

        foreach ($extractedBiomarkers as $extracted) {
            if (is_array($extracted) && !empty($extracted['name'])) {
                $normName = strtolower(trim($extracted['name']));
                if (!isset($extractedMap[$normName])) {
                    $extractedMap[$normName] = $extracted;
                }
            } elseif (is_string($extracted)) {
                $normName = strtolower(trim($extracted));
                if (!isset($extractedMap[$normName])) {
                    $extractedMap[$normName] = [
                        'name' => $extracted,
                        'value' => null,
                        'unit' => null,
                        'range' => null,
                    ];
                }
            }
        }

        $markers = [];

        foreach ($availableBiomarkers as $biomarker) {
            if (!is_array($biomarker)) {
                continue;
            }

            // A 'biomarker' here actually represents a MajorOrganTest (e.g. 'Liver Function Test')
            // with a list of 'matched_biomarkers' (e.g. ['ALT', 'AST', 'ALP']).
            $testMatchedBiomarkers = [];
            if (!empty($biomarker['matched_biomarkers']) && is_array($biomarker['matched_biomarkers'])) {
                $testMatchedBiomarkers = $biomarker['matched_biomarkers'];
            } else {
                // Fallback if the biomarker array is just a single item
                $testMatchedBiomarkers = [$biomarker['name'] ?? ''];
            }

            foreach ($testMatchedBiomarkers as $match) {
                $matchName = is_array($match) ? ($match['name'] ?? '') : (string) $match;
                $matchName = trim($matchName);
                if ($matchName === '') {
                    continue;
                }

                $sKey = $this->findSenoclockKey($matchName, $mapping);
                if (!$sKey) {
                    continue;
                }

                if (empty($sKey) || isset($markers[$sKey])) {
                    continue;
                }

                $matchNorm = strtolower($matchName);
                if (isset($extractedMap[$matchNorm])) {
                    $valObj = $this->extractValUnitRange($extractedMap[$matchNorm], $extractedMap);
                    if ($valObj) {
                        $markers[$sKey] = $valObj;
                    }
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
        if ($name === '') {
            return null;
        }

        // Exact match first.
        if (isset($mapping[$name])) {
            return $mapping[$name];
        }

        // Also try without parenthetical aliases: "hemoglobin a1c (hba1c)" → "hemoglobin a1c"
        $nameNoParen = trim(preg_replace('/\s*\([^)]*\)/', '', $name) ?? $name);
        $nameNoParen = preg_replace('/\s+/', ' ', $nameNoParen) ?? $nameNoParen;
        if ($nameNoParen !== '' && $nameNoParen !== $name && isset($mapping[$nameNoParen])) {
            return $mapping[$nameNoParen];
        }

        // Longest contained alias wins. Skip very short keys ("k","p","ast") for contains-matching
        // so "fasting" does not map to AST and "like" does not map to K+.
        $bestKey = null;
        $bestLen = 0;
        foreach ($mapping as $mapKey => $senoKey) {
            $mapKey = (string) $mapKey;
            $len = strlen($mapKey);
            if ($len < 4) {
                continue;
            }
            if (
                str_contains($name, $mapKey) ||
                ($nameNoParen !== '' && str_contains($nameNoParen, $mapKey))
            ) {
                if ($len > $bestLen) {
                    $bestLen = $len;
                    $bestKey = $senoKey;
                }
            }
        }

        return $bestKey;
    }

    private function extractValUnitRange(array $biomarker, array $extractedMap): ?array
    {
        $value = $biomarker['value'] ?? null;
        $unit = $biomarker['unit'] ?? null;
        $range = $biomarker['range'] ?? $biomarker['reference_range'] ?? null;
        
        if ($value === null || $value === '') {
            $nameNorm = strtolower(trim($biomarker['name'] ?? ''));
            if (isset($extractedMap[$nameNorm])) {
                $value = $extractedMap[$nameNorm]['value'] ?? null;
                $unit = $extractedMap[$nameNorm]['unit'] ?? null;
                $range = $extractedMap[$nameNorm]['range'] ?? $extractedMap[$nameNorm]['reference_range'] ?? null;
            }
        }

        if ($value !== null && $value !== '') {
            // SenoClock blood-age algo requires numeric values.
            if (!is_numeric($value)) {
                return null;
            }
            $value = str_contains((string) $value, '.') ? (float) $value : (int) $value;

            if ($unit) {
                // Fix greek letters and superscripts BEFORE stripping non-ascii
                $unit = str_replace(['μ', 'µ'], 'u', $unit);
                $unit = str_replace(
                    ['⁰', '¹', '²', '³', '⁴', '⁵', '⁶', '⁷', '⁸', '⁹', 'Â³', 'Â²'],
                    ['^0', '^1', '^2', '^3', '^4', '^5', '^6', '^7', '^8', '^9', '^3', '^2'],
                    $unit
                );
                $unit = str_replace('mmA3', 'mm^3', $unit);

                $unit = preg_replace('/[^\x20-\x7E]/', '', $unit); // Strip remaining non-ASCII

                // Normalize spacing and common OCR errors for cell counts (PLT, RBC, WBC)
                $unit = str_ireplace(['x10^', '*10^', 'x 10^', '10*'], '10^', $unit);
                $unit = str_ireplace(['/ L', '/ l'], '/L', $unit);
                $unit = str_ireplace(['/ uL', '/ ul', '/u l'], '/uL', $unit);

                // Senoclock strict unit conversions for identical cell counts
                $unit = str_ireplace('10^3/uL', '10^9/L', $unit);
                $unit = str_ireplace('10^6/uL', '10^12/L', $unit);
                $unit = str_ireplace('10^3/mm^3', '10^9/L', $unit);
                $unit = str_ireplace('10^6/mm^3', '10^12/L', $unit);
            }

            $result = [
                'value' => $value,
                'unit' => $unit ?: '',
            ];

            // Only send simple numeric ranges like the valid SenoClock example ("45-999", "4.0 - 6.0").
            // Narrative ranges ("Desirable: < 200", "Up to 41") break the blood age algo.
            $normalizedRange = $this->normalizeSenoclockRange($range);
            if ($normalizedRange !== null) {
                $result['range'] = $normalizedRange;
            }

            return $result;
        }

        return null;
    }

    /**
     * Keep only simple min-max ranges acceptable to SenoClock file-execute.
     */
    private function normalizeSenoclockRange(mixed $range): ?string
    {
        if ($range === null) {
            return null;
        }

        $range = trim((string) $range);
        if ($range === '') {
            return null;
        }

        // Normalize dashes
        $range = str_replace(['–', '—', '−'], '-', $range);

        // Accept "4.0 - 6.0" / "45-999" / "0.20 - 5"
        if (preg_match('/^\d+(\.\d+)?\s*-\s*\d+(\.\d+)?$/', $range)) {
            return preg_replace('/\s*-\s*/', ' - ', $range);
        }

        // Extract first two numbers from patterns like "13.0 - 17.0 (M) / 12.0 - 15.0 (F)"
        if (preg_match('/(\d+(?:\.\d+)?)\s*-\s*(\d+(?:\.\d+)?)/', $range, $m)) {
            return $m[1] . ' - ' . $m[2];
        }

        return null;
    }

    /**
     * Validates and normalizes markers before sending to Senoclock File Execute
     */
    public function validateSenoClockMarkers(array $markers, array $availableKeys): array
    {
        $validMarkers = [];
        $excluded = [];

        foreach ($markers as $key => $data) {
            if (!in_array($key, $availableKeys, true)) {
                $excluded[] = "{$key} (not in available keys)";
                continue;
            }

            if (!isset($data['value']) || $data['value'] === '') {
                $excluded[] = "{$key} (missing value)";
                continue;
            }

            $normalized = [
                'value' => is_numeric($data['value']) ? (str_contains((string)$data['value'], '.') ? (float)$data['value'] : (int)$data['value']) : $data['value'],
                'unit' => is_string($data['unit'] ?? null) ? trim($data['unit']) : '',
            ];

            if (isset($data['range']) && is_string($data['range']) && trim($data['range']) !== '') {
                $normalized['range'] = trim($data['range']);
            }

            $validMarkers[$key] = $normalized;
        }

        if (!empty($excluded)) {
            Log::info("SenoclockService: Excluded markers during final validation", ['excluded' => $excluded]);
        }

        return $validMarkers;
    }

    /**
     * Step 3 – Execute the Senoclock Analysis.
     */
    public function executeSenoclockAnalysis(string $fileId, array $payload, string $token): ?array
    {
        $baseUrl = rtrim((string) config('services.senoclock.base_url'), '/');
        $url = "{$baseUrl}/dl-api/file-execute/";

        Log::info('SenoclockService: Request details', [
            'url' => $url,
            'application_now' => now()->toDateTimeString(),
            'application_timezone' => config('app.timezone'),
            'application_today' => now()->toDateString(),
            'utc_now' => now()->utc()->toDateTimeString(),
            'utc_today' => now()->utc()->toDateString(),
            'test_date' => $payload['test_date'] ?? null,
            'test_date_is_today' => isset($payload['test_date'])
                ? $payload['test_date'] === now()->toDateString()
                : null,
            'test_date_is_future' => isset($payload['test_date'])
                ? $payload['test_date'] > now()->toDateString()
                : null,
            'marker_count' => isset($payload['markers'])
                ? count($payload['markers'])
                : 0,
            'marker_names' => isset($payload['markers'])
                ? array_keys($payload['markers'])
                : [],
        ]);

        $response = Http::withoutVerifying()->withToken($token)
            ->post($url, $payload);

        Log::info('SenoclockService: Response details', [
            'http_status' => $response->status(),
            'successful' => $response->successful(),
            'failed' => $response->failed(),
            'response_body' => $response->body(),
            'response_json' => $response->json(),
        ]);

        if (!$response->successful()) {
            if ($response->status() === 400) {
                Log::error('SenoclockService: API validation failure', [
                    'http_status' => $response->status(),
                    'test_date_sent' => $payload['test_date'] ?? null,
                    'application_today' => now()->toDateString(),
                    'utc_today' => now()->utc()->toDateString(),
                    'response_body' => $response->body(),
                    'response_json' => $response->json(),
                    'marker_count' => isset($payload['markers'])
                        ? count($payload['markers'])
                        : 0,
                ]);
            } else {
                Log::error("Senoclock AI execute failed: " . $response->body(), ['payload' => $payload]);
            }
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

        $maxAttempts = 30;
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
                        $uploadDir = public_path('uploads/senoclock_report_generated');
                        if (!file_exists($uploadDir)) {
                            @mkdir($uploadDir, 0777, true);
                        }

                        $filePath = $uploadDir . '/' . $fileName;
                        file_put_contents($filePath, $response->body());

                        Log::info("Senoclock AI PDF report downloaded successfully after {$attempt} attempts.");
                        return 'uploads/senoclock_report_generated/' . $fileName;
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
