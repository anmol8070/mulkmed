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

            $rep = [];
            if ($aiVital && !empty($aiVital->report)) {
                $rep = $this->parseReport($aiVital->report);
            }
            if ($aiVital && !empty($aiVital->shen_ai)) {
                $rep = array_merge($this->parseReport($aiVital->shen_ai), $rep);
            }

            $age = null;
            $sex = null;

            // Prioritize age and sex extracted directly from the PDF report header
            if (!empty($rep['age']) || !empty($rep['Age'])) {
                $age = (int) ($rep['age'] ?? $rep['Age']);
            }
            if (!empty($rep['sex']) || !empty($rep['Sex']) || !empty($rep['gender']) || !empty($rep['Gender'])) {
                $rawSex = $rep['sex'] ?? $rep['Sex'] ?? $rep['gender'] ?? $rep['Gender'];
                $sex = $this->mapSex($rawSex);
            }

            if ($age === null && $request) {
                $age = $request->input('age');
            }
            if ($sex === null && $request) {
                $sex = $request->input('sex') ?? $request->input('gender');
            }

            if ($age === null && $user) {
                $age = $this->resolveAge($user);
            }
            if ($sex === null && $user) {
                $sex = $this->mapSex($user->gender ?? null);
            }

            $age = ($age !== null && $age !== '') ? (int) $age : null;
            $sex = ($sex !== null && $sex !== '') ? strtolower((string) $sex) : null;

            $accessToken = $this->fetchAccessToken($email, $password);
            if ($accessToken === null) {
                $errorResponse = ['success' => false, 'message' => 'Senoclock AI API Error: Failed to obtain access token or login failed'];
                $aiVital->senoclock_ai_response = $errorResponse;
                $aiVital->save();
                return $errorResponse;
            }

            $payload = $this->buildClassificationPayload($request ?? new Request(), $age, $sex, $aiVital);
            $payload = $this->normalizeClassificationPayload($payload);

            try {
                if (Schema::hasTable('ai_vitals') && Schema::hasColumn('ai_vitals', 'senoclock_ai_request')) {
                    $aiVital->senoclock_ai_request = $payload;
                    $aiVital->save();
                }
            } catch (\Throwable $e) {
                Log::warning('Failed to save senoclock_ai_request column: ' . $e->getMessage());
            }

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

    /**
     * Official SenoClock parameter names and the alternative keys / PDF labels that map to them.
     */
    private const PARAMETER_ALIASES = [
        'Heart Rate (HR)' => ['heartRate', 'heart_rate', 'hr', 'pulse', 'pulseRate', 'pulse_rate', 'Pulse (HR)', 'Pulse'],
        'Blood Pressure' => ['bloodPressure', 'blood_pressure', 'bp'],
        'Heart Rate Variability (HRV)' => ['hrvSdnnMs', 'hrv'],
        'Breathing Rate' => ['respiratoryRate', 'respiratory_rate', 'breathingRate', 'breathing_rate', 'Breathing Rate (BR)'],
        'Stress Index' => ['stressLevel', 'stress_level', 'stressIndex', 'stress_index'],
        'Parasympathetic Activity' => ['parasympatheticActivity', 'parasympathetic_activity'],
        'Cardiac Workload' => ['cardiacWorkload', 'cardiac_workload'],
        'Body Mass Index (BMI)' => ['bmi'],
        'Wellness Score' => ['wellnessScore', 'wellness_score'],
        'Vascular Age' => ['vascularAge', 'vascular_age'],
        'Cardiovascular Disease Risk' => ['cardiovascularDiseaseRisk'],
        'Cardiovascular Risk Score (Framingham FRS)' => ['cardiovascularRiskScore', 'Cardiovascular Risk Score'],
        'Hard and Fatal Events Risks' => ['hardAndFatalEventsRisks', 'hardFatalEventsRisks'],
        'Hypertension Risk' => ['hypertensionRisk'],
        'Diabetes Risk' => ['diabetesRisk'],
        'NAFLD Risk' => ['nafldRisk', 'fattyLiverDiseaseRisk', 'Fatty Liver Disease Risk (NAFLD)', 'Fatty Liver Disease Risk'],
        'Waist-to-Height Ratio (WHtR)' => ['waistToHeightRatio', 'whtr'],
        'Body Fat %' => ['bodyFat', 'body_fat', 'bodyFatPercentage', 'bfp', 'Body Fat Percentage (BFP)', 'Body Fat Percentage'],
        'Body Roundness Index (BRI)' => ['bodyRoundnessIndex', 'bri'],
        'A Body Shape Index (ABSI)' => ['bodyShapeIndex', 'aBodyShapeIndex', 'absi'],
        'Conicity Index (CI)' => ['conicityIndex'],
        'Basal Metabolic Rate (BMR)' => ['basalMetabolicRate', 'bmr'],
        'Total Daily Energy Expenditure (TDEE)' => ['totalDailyEnergyExpenditure', 'tdee'],
    ];

    /**
     * Units agreed with SenoClock, used when the report gives no unit (or "-").
     */
    private const DEFAULT_UNITS = [
        'Stress Index' => 'index',
        'Body Mass Index (BMI)' => 'kg/m^2',
        'Waist-to-Height Ratio (WHtR)' => 'ratio',
        'Body Fat %' => '%',
        'Body Roundness Index (BRI)' => 'index',
        'A Body Shape Index (ABSI)' => 'index',
        'Conicity Index (CI)' => 'index',
    ];

    /**
     * Reference ranges from the Mulk Parameter, Trigger & Organ Health Mapping Framework (Section 2).
     * These override whatever range the uploaded report prints.
     */
    private const REFERENCE_RANGES = [
        'Heart Rate (HR)' => '60 - 100',
        'Heart Rate Variability (HRV)' => '30 - 70',
        'Breathing Rate' => '12 - 20',
        'Stress Index' => '0 - 4',
        'Parasympathetic Activity' => '20 - 40',
        'Cardiac Workload' => '90 - 216',
        'Blood Pressure' => 'SBP 90 - 120, DBP 60 - 80',
        'Body Mass Index (BMI)' => '18.5 - 24.9',
        'Body Fat %' => '7 - 23',
        'Waist-to-Height Ratio (WHtR)' => '0 - 0.5',
        'Body Roundness Index (BRI)' => '0 - 3.85',
        'A Body Shape Index (ABSI)' => '0 - 0.083',
        'Conicity Index (CI)' => '0 - 1.275',
    ];

    private const RISK_LEVEL_PARAMETERS =['Hypertension Risk', 'Diabetes Risk', 'NAFLD Risk'];

    private const PAYLOAD_META_KEYS = ['user_id', 'appointment_id', 'date', 'age', 'sex', 'patient_name'];

    /**
     * Build the exact body agreed with SenoClock:
     * user_id, appointment_id, date (ISO), age, sex, patient_name at root level,
     * and every parameter as {name, result, unit, normal_range} keyed by its official name.
     */
    public function normalizeClassificationPayload(array $payload): array
    {
        $scanDate = $payload['date'] ?? $payload['scan_date'] ?? $payload['scanDate'] ?? null;
        $patientName = $payload['patient_name'] ?? $payload['patientName'] ?? $payload['name'] ?? null;

        $normalized = [];

        if (isset($payload['user_id']) && $payload['user_id'] !== '') {
            $normalized['user_id'] = is_numeric($payload['user_id'])
                ? (str_contains((string) $payload['user_id'], '.') ? (float) $payload['user_id'] : (int) $payload['user_id'])
                : $payload['user_id'];
        }

        $normalized['appointment_id'] = (isset($payload['appointment_id']) && $payload['appointment_id'] !== '')
            ? (is_numeric($payload['appointment_id'])
                ? (str_contains((string) $payload['appointment_id'], '.') ? (float) $payload['appointment_id'] : (int) $payload['appointment_id'])
                : $payload['appointment_id'])
            : 0;

        $isoDate = $this->toIsoDate($scanDate);
        if ($isoDate !== null) {
            $normalized['date'] = $isoDate;
        }

        if (isset($payload['age']) && $payload['age'] !== '') {
            $normalized['age'] = (int) $payload['age'];
        }

        $sex = $this->mapSex($payload['sex'] ?? $payload['gender'] ?? $payload['Sex'] ?? $payload['Gender'] ?? null);
        if ($sex !== null && $sex !== '') {
            $normalized['sex'] = strtolower($sex);
        }

        if (is_string($patientName) && trim($patientName) !== '') {
            $normalized['patient_name'] = trim($patientName);
        }

        $lookup = $this->parameterLookup();

        foreach ($payload as $key => $val) {
            if (in_array($key, self::PAYLOAD_META_KEYS, true)) {
                continue;
            }

            $label = is_array($val) && isset($val['name']) && is_string($val['name']) ? $val['name'] : null;
            $canonical = $lookup[$this->lookupKey((string) $key)]
                ?? ($label !== null ? ($lookup[$this->lookupKey($label)] ?? null) : null);

            // Plain scalar values are only accepted for known parameters; this keeps request-only
            // fields such as dob, scan_date, report_file or lang out of the body.
            if (!is_array($val) && $canonical === null) {
                continue;
            }

            $parameter = $this->toParameterObject($canonical ?? (string) $key, $val, $canonical !== null);
            if ($parameter === null || $this->isInvalidOrEmptyBiomarker($parameter)) {
                continue;
            }

            // Parameters without a normal range (blank, "-", "N/A", "N/A*") are not sent
            if ($parameter['normal_range'] === '') {
                continue;
            }

            if (isset($normalized[$parameter['name']])) {
                continue;
            }

            $normalized[$parameter['name']] = $parameter;
        }

        return $normalized;
    }

    /**
     * @return array<string, string> normalized alias => official parameter name
     */
    private function parameterLookup(): array
    {
        $lookup = [];
        foreach (self::PARAMETER_ALIASES as $official => $aliases) {
            $lookup[$this->lookupKey($official)] = $official;
            foreach ($aliases as $alias) {
                $lookup[$this->lookupKey($alias)] ??= $official;
            }
        }

        return $lookup;
    }

    /**
     * "Heart Rate Variability (HRV)", "heartRateVariabilityHrv" and "heart_rate_variability_hrv"
     * all reduce to the same lookup key.
     */
    private function lookupKey(string $key): string
    {
        return strtolower((string) preg_replace('/[^a-zA-Z0-9]/', '', $key));
    }

    private function toParameterObject(string $key, mixed $val, bool $isOfficial): ?array
    {
        $unit = '';
        $range = '';

        if (is_array($val)) {
            if (array_key_exists('systolic', $val) || array_key_exists('diastolic', $val)) {
                if (!is_scalar($val['systolic'] ?? null) || !is_scalar($val['diastolic'] ?? null)) {
                    return null;
                }
                $result = $val['systolic'] . ' / ' . $val['diastolic'];
                $unit = 'mmHg';
            } else {
                $rawResult = $val['result'] ?? $val['value'] ?? $val['input_value'] ?? null;
                if (is_array($rawResult) || is_object($rawResult) || $rawResult === null) {
                    return null;
                }
                $result = trim((string) $rawResult);
                $unit = is_scalar($val['unit'] ?? null) ? trim((string) $val['unit']) : '';
                $range = is_scalar($val['normal_range'] ?? $val['range'] ?? null)
                    ? trim((string) ($val['normal_range'] ?? $val['range']))
                    : '';
            }
            $name = $isOfficial ? $key : (is_string($val['name'] ?? null) && $val['name'] !== '' ? $val['name'] : $key);
        } else {
            $result = is_bool($val) ? ($val ? 'true' : 'false') : trim((string) $val);
            $name = $key;
        }

        if ($name === 'Blood Pressure') {
            $result = (string) preg_replace('/\s*\/\s*/', ' / ', $result);
            $unit = $unit !== '' ? $unit : 'mmHg';
        }

        if (in_array($name, self::RISK_LEVEL_PARAMETERS, true)
            && preg_match('/\b(very high|high|moderate|medium|low)\b/i', $result, $m)) {
            $result = strtolower($m[1]);
        }

        $range = self::REFERENCE_RANGES[$name] ?? $range;

        if ($range === '-' || strcasecmp($range, 'N/A') === 0 || strcasecmp($range, 'N/A*') === 0) {
            $range = '';
        }

        // "1,380.25" -> "1380.25" (thousands separators only; "112 / 80" is left as is)
        if (preg_match('/^-?\d{1,3}(,\d{3})+(\.\d+)?$/', $result)) {
            $result = str_replace(',', '', $result);
        }

        // Drop trailing zero decimals from results: "36.00" -> "36", "2.10" -> "2.1"
        if (is_string($result) && preg_match('/^-?\d+\.\d+$/', $result)) {
            $result = rtrim(rtrim($result, '0'), '.');
        }

        // Convert numeric results (including 0) to actual int/float numbers
        if (is_numeric($result) && !str_contains((string) $result, '/') && $name !== 'Blood Pressure') {
            $result = str_contains((string) $result, '.') ? (float) $result : (int) $result;
        }

        // Ranges carry numbers only (no "%"), and every number is a decimal: "7 - 23%" -> "7.0 - 23.0"
        $range = trim(str_replace('%', '', $range));
        $range = (string) preg_replace('/(?<![\d.])(\d+)(?![\d.])/', '$1.0', $range);

        if ($unit === '' || $unit === '-') {
            $unit = self::DEFAULT_UNITS[$name] ?? '-';
        }

        return [
            'name' => $name,
            'result' => $result,
            'unit' => $unit,
            'normal_range' => $range,
        ];
    }

    /**
     * Convert report dates such as "20/12/2025, 11:59:22" or "2025-12-20 11:59:22" to ISO 8601 (UTC).
     */
    private function toIsoDate(mixed $date): ?string
    {
        if ($date === null || $date === '' || is_array($date)) {
            return null;
        }

        if ($date instanceof \DateTimeInterface) {
            return \Carbon\Carbon::instance($date)->utc()->format('Y-m-d\TH:i:s\Z');
        }

        $value = trim((string) preg_replace('/\s*,\s*/', ' ', (string) $date));

        foreach (['d/m/Y H:i:s', 'd/m/Y H:i', 'd/m/Y', 'Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d'] as $format) {
            try {
                $parsed = \Carbon\Carbon::createFromFormat('!' . $format, $value, 'UTC');
                if ($parsed !== false && $parsed->format($format) === $value) {
                    return $parsed->format('Y-m-d\TH:i:s\Z');
                }
            } catch (\Throwable $e) {
                // try next format
            }
        }

        try {
            return \Carbon\Carbon::parse($value, 'UTC')->utc()->format('Y-m-d\TH:i:s\Z');
        } catch (\Throwable $e) {
            return (string) $date;
        }
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
        //     // File holds exactly the JSON body POSTed to $url — no wrapper or extra fields
        //     $content = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
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
        //     // File holds exactly the JSON returned by SenoClock — no wrapper or extra fields
        //     $content = is_array($responseBody)
        //         ? json_encode($responseBody, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
        //         : (string) $responseBody;
        //     if ($content === false) { $content = '{}'; }
        //
        //     file_put_contents($publicDir . DIRECTORY_SEPARATOR . "{$nameWithoutExt}_response.json", $content);
        //     file_put_contents($publicDir . DIRECTORY_SEPARATOR . 'senoclock_trigger_classification_response.json', $content);
        // } catch (\Throwable $e) {
        //     Log::warning('Failed to write Senoclock response body file: ' . $e->getMessage());
        // }
    }

    private function buildClassificationPayload(Request $request, ?int $age = null, ?string $sex = null, ?AI_Vital $aiVital = null): array
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

        if ($age === null) {
            $rawAge = $report['age'] ?? $report['Age'] ?? null;
            if ($rawAge !== null && $rawAge !== '') {
                $age = (int) $rawAge;
            }
        }

        if ($sex === null) {
            $rawSex = $report['sex'] ?? $report['Sex'] ?? $report['gender'] ?? $report['Gender'] ?? null;
            if ($rawSex !== null && $rawSex !== '') {
                $sex = strtolower((string) $rawSex);
            }
        }

        $payload = [];

        $userId = $request->user_id ?? $aiVital?->user_id ?? $report['user_id'] ?? null;
        if ($userId !== null && $userId !== '') {
            $payload['user_id'] = is_numeric($userId)
                ? (str_contains((string) $userId, '.') ? (float) $userId : (int) $userId)
                : $userId;
        }

        $appointmentId = $request->appointment_id ?? $aiVital?->appointment_id ?? $report['appointment_id'] ?? null;
        if ($appointmentId !== null && $appointmentId !== '') {
            $payload['appointment_id'] = is_numeric($appointmentId)
                ? (str_contains((string) $appointmentId, '.') ? (float) $appointmentId : (int) $appointmentId)
                : $appointmentId;
        }

        $scanDate = $report['scan_date'] ?? $report['scanDate'] ?? $report['Scan Date'] ?? $request->scan_date ?? $request->date ?? $aiVital?->scan_date ?? null;
        if (!empty($scanDate)) {
            $payload['date'] = (string) $scanDate;
            $payload['scan_date'] = (string) $scanDate;
        }

        if ($age !== null) {
            $payload['age'] = (int) $age;
        }

        if (!empty($sex)) {
            $payload['sex'] = strtolower((string) $sex);
        }

        $patientName = $report['patient_name'] ?? $report['patientName'] ?? $report['name'] ?? $report['Name'] ?? null;
        if (!empty($patientName)) {
            $payload['patient_name'] = (string) $patientName;
        }

        // Merge all extracted biomarker keys from $report preserving full objects
        foreach ($report as $key => $val) {
            if (in_array($key, ['user_id', 'appointment_id', 'date', 'scan_date', 'age', 'sex', 'report_file', 'file', 'email', 'password', 'report', 'payload', 'shen_ai'])) {
                continue;
            }
            if (!$this->isInvalidOrEmptyBiomarker($val)) {
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
                if ($foundVal !== null && !$this->isInvalidOrEmptyBiomarker($foundVal)) {
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

    private function isInvalidOrEmptyBiomarker(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        if (is_array($value)) {
            // Blood pressure object array
            if (isset($value['systolic']) || isset($value['diastolic'])) {
                return false;
            }

            $result = trim((string) ($value['result'] ?? $value['value'] ?? ''));

            // Filter out only if result is empty or explicit "N/A" / "N/A*"
            if ($result === '' || strcasecmp($result, 'N/A') === 0 || strcasecmp($result, 'N/A*') === 0) {
                return true;
            }

            return false;
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '' || strcasecmp($trimmed, 'N/A') === 0 || strcasecmp($trimmed, 'N/A*') === 0) {
                return true;
            }
        }

        return false;
    }

    private function reportValue(array $report, string|array $keys): mixed
    {
        $keys = (array) $keys;
        foreach ($keys as $key) {
            if (array_key_exists($key, $report) && !$this->isInvalidOrEmptyBiomarker($report[$key])) {
                return $this->unwrapMetricValue($report[$key]);
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

    private function mapSex(mixed $gender): ?string
    {
        if ($gender === null || $gender === '') {
            return null;
        }
        $genderInt = is_numeric($gender) ? (int)$gender : null;
        if ($genderInt !== null) {
            if ($genderInt === Constants::genderMale) {
                return 'male';
            }
            if ($genderInt === Constants::genderFemale) {
                return 'female';
            }
        }
        $genderStr = strtolower(trim((string)$gender));
        if ($genderStr === 'female' || $genderStr === 'f') {
            return 'female';
        }
        if ($genderStr === 'male' || $genderStr === 'm') {
            return 'male';
        }
        return $genderStr;
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
            'amylase' => 'AAMY',
            'serum amylase' => 'AAMY',
            'aamy' => 'AAMY',
            
            // AFP - Alpha Fetoprotein
            'alpha fetoprotein' => 'AFP',
            'alpha feto protein' => 'AFP',
            'alpha-fetoprotein' => 'AFP',
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
            'bilirubin direct' => 'BILID',
            'bilirubin, direct' => 'BILID',
            'bilirubin (direct)' => 'BILID',
            'conjugated bilirubin' => 'BILID',
            'bilid' => 'BILID',
            
            // BILIT - Total Bilirubin
            'total bilirubin' => 'BILIT',
            'bilirubin total' => 'BILIT',
            'bilirubin, total' => 'BILIT',
            'bilirubin (total)' => 'BILIT',
            'bilirubin' => 'BILIT',
            'bilit' => 'BILIT',
            
            // BUN - Blood Urea Nitrogen
            'blood urea nitrogen' => 'BUN',
            'urea nitrogen' => 'BUN',
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
            'gamma glutamyl transferase' => 'GGT',
            'gamma glutamyl transpeptidase' => 'GGT',
            'gamma-glutamyl transferase' => 'GGT',
            'ggtp' => 'GGT',
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
            'haematocrit' => 'HCT',
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
            'glycated hemoglobin' => 'HGBA1C',
            'glycated haemoglobin' => 'HGBA1C',
            'glycosylated hemoglobin' => 'HGBA1C',
            'glycosylated haemoglobin' => 'HGBA1C',
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
            'rdw-cv' => 'RDW',
            'rdw cv' => 'RDW',
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
            'white blood cells' => 'WBC',
            'total leucocyte count' => 'WBC',
            'total leukocyte count' => 'WBC',
            'leucocyte count' => 'WBC',
            'leukocyte count' => 'WBC',
            'tlc' => 'WBC',
            'total wbc count' => 'WBC',
            'wbc count' => 'WBC',
            'wbc' => 'WBC',
            
            // CRP - C-reactive protein
            'c-reactive protein' => 'CRP',
            'hs-crp' => 'CRP',
            'hscrp' => 'CRP',
            'high sensitivity crp' => 'CRP',
            'high sensitivity c-reactive protein' => 'CRP',
            'c-reactive protein (crp)' => 'CRP',
            'crp' => 'CRP',
            
            // VIT-D - Vitamin D
            'vitamin d' => 'VIT-D',
            'vit-d' => 'VIT-D',
            
            // PTH - Parathyroid hormone
            'parathyroid hormone' => 'PTH',
            'intact pth' => 'PTH',
            'pth intact' => 'PTH',
            'pth (intact)' => 'PTH',
            'thyroid stimulating hormone' => 'TSH', // TSH is generally used instead of PTH sometimes but PTH is Parathyroid
            'pth' => 'PTH',
            'tsh' => 'TSH',
            
            // APOB - Apolipoprotein B
            'apolipoprotein b' => 'APOB',
            'apo b' => 'APOB',
            'apo-b' => 'APOB',
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
            'egfr' => 'GFR',
            'estimated gfr' => 'GFR',
            'estimated glomerular filtration rate' => 'GFR',
            'gfr' => 'GFR',
            
            // IGF-1 - Insulin-like Growth Factor-1
            'insulin-like growth factor-1' => 'IGF-1',
            'somatomedin c' => 'IGF-1',
            'igf-1' => 'IGF-1',
            
            // C-PEPTIDE - Connecting peptide
            'connecting peptide' => 'C-PEPTIDE',
            'c-peptide' => 'C-PEPTIDE',
        ];
    }

    /**
     * Biomarkers SenoClock requires to generate the report. Without all of them the
     * download endpoint returns HTTP 409 "Minimum required biomarkers are not available".
     */
    public const REQUIRED_SENOCLOCK_BIOMARKERS = [
        'HGBA1C', 'TRIG', 'HDL', 'ALT', 'ALB', 'ALP', 'BILID', 'CHOLT', 'CL', 'CREA',
        'FERR', 'GLC', 'LDL', 'LYMPH%', 'MCV', 'P', 'RDW', 'WBC', 'CRP', 'C-PEPTIDE',
    ];

    /**
     * Display names for the required SenoClock biomarkers.
     */
    public const REQUIRED_SENOCLOCK_BIOMARKER_NAMES = [
        'HGBA1C' => 'HbA1c',
        'TRIG' => 'Triglycerides',
        'HDL' => 'HDL Cholesterol',
        'ALT' => 'Alanine Transaminase (ALT)',
        'ALB' => 'Albumin',
        'ALP' => 'Alkaline Phosphatase',
        'BILID' => 'Direct Bilirubin',
        'CHOLT' => 'Total Cholesterol',
        'CL' => 'Chloride',
        'CREA' => 'Creatinine',
        'FERR' => 'Ferritin',
        'GLC' => 'Glucose',
        'LDL' => 'LDL Cholesterol',
        'LYMPH%' => 'Lymphocytes %',
        'MCV' => 'Mean Corpuscular Volume (MCV)',
        'P' => 'Phosphorus',
        'RDW' => 'Red Cell Distribution Width (RDW)',
        'WBC' => 'White Blood Cell Count (WBC)',
        'CRP' => 'C-Reactive Protein (CRP)',
        'C-PEPTIDE' => 'C-Peptide',
    ];

    /**
     * Every biomarker SenoClock accepts (code => full name), in SenoClock's reference order.
     * Required in full for users who bought the Comprehensive Mulk Longevity plan.
     */
    public const ALL_SENOCLOCK_BIOMARKER_NAMES = [
        'AAMY' => 'Alpha-Amylase',
        'AFP' => 'Alpha Fetoprotein',
        'ALB' => 'Albumin',
        'ALP' => 'Alkaline Phosphatase',
        'ALT' => 'Alanine Transaminase',
        'AST' => 'Aspartate Transaminase',
        'ATLYMPH' => 'Atypical lymphocytes',
        'BASO%' => 'Basophils,%',
        'BILID' => 'Direct Bilirubin',
        'BILIT' => 'Total Bilirubin',
        'BUN' => 'Blood Urea Nitrogen',
        'CA' => 'Calcium',
        'CHOLT' => 'Total Cholesterol',
        'CL' => 'Chloride',
        'CREA' => 'Creatinine',
        'EOS%' => 'Eosinophils,%',
        'ESR' => 'Erythrocyte Sedimentation Rate',
        'FERR' => 'Ferritin',
        'GGT' => 'Gamma-GT',
        'GLOBT' => 'Total Globulin',
        'GLC' => 'Glucose',
        'HCT' => 'Hematocrit',
        'HDL' => 'HDL Cholestrol',
        'HGB' => 'Hemoglobin',
        'HGBA1C' => 'Hemoglobin A1c',
        'IRON' => 'Iron',
        'K+' => 'Potassium',
        'LDL' => 'LDL Cholesterol',
        'LYMPH%' => 'Lymphocytes,%',
        'MCH' => 'Mean Corpuscular Haemoglobin',
        'MCHC' => 'Mean Corpuscular Haemoglobin Concentration',
        'MCV' => 'Mean Corpuscular Volume',
        'MONO%' => 'Monocytes,%',
        'MPV' => 'Mean Platelet Volume',
        'NA+' => 'Sodium',
        'NEUTR%' => 'Neutrophils,%',
        'P' => 'Phosphorous',
        'PDW' => 'Platelet Distribution Width',
        'PLT' => 'Platelets',
        'PROT' => 'Total Protein',
        'RBC' => 'Red Blood Cell',
        'RDW' => 'Red Cell Distribution Width',
        'TRIG' => 'Triglycerides',
        'UA' => 'Uric Acid',
        'WBC' => 'White Blood Cell',
        'CRP' => 'C-reactive protein',
        'VIT-D' => 'Vitamin D',
        'PTH' => 'Parathyroid hormone',
        'APOB' => 'Apolipoprotein B',
        'LDH' => 'Lactate Dehydrogenase',
        'MG+' => 'Magnesium',
        'GFR' => 'Glomerular Filtration Rate',
        'IGF-1' => 'Insulin-like Growth Factor-1',
        'C-PEPTIDE' => 'Connecting peptide',
    ];

    /**
     * Biomarkers a user must have in their lab report, based on the package they bought:
     * Comprehensive plan = all SenoClock biomarkers; Basic plan or no plan = the 20 required ones.
     *
     * @return array{plan: string, package_id: ?int, package_title: ?string, codes: string[]}
     */
    public function getRequiredBiomarkersForUser(int $userId, ?int $packageId = null): array
    {
        $package = null;
        $packageTitle = null;

        if ($packageId) {
            // The plan the app says the user selected.
            $package = \App\Models\MajorOrganPackage::find($packageId);
            $packageTitle = $package->title ?? null;
        } else {
            // Latest paid plan purchase. The app saves plan purchases as "longevity" selections
            // (older ones as "package"), each pointing at the bought package.
            $selection = \App\Models\MajorOrganUserSelection::where('user_id', $userId)
                ->whereIn('selection_type', ['package', 'longevity'])
                ->whereNotNull('package_id')
                ->where('payment_status', 1)
                ->orderBy('id', 'desc')
                ->first();

            if ($selection) {
                $package = \App\Models\MajorOrganPackage::find($selection->package_id);
                $packageTitle = $package->title ?? $selection->package_title;
                $packageId = (int) $selection->package_id;
            }
        }

        if ($packageTitle !== null && stripos($packageTitle, 'comprehensive') !== false) {
            // The 20 core biomarkers first, then the rest in reference order.
            $codes = array_values(array_unique(array_merge(
                self::REQUIRED_SENOCLOCK_BIOMARKERS,
                array_keys(self::ALL_SENOCLOCK_BIOMARKER_NAMES)
            )));

            return ['plan' => 'comprehensive', 'package_id' => $packageId, 'package_title' => $packageTitle, 'codes' => $codes];
        }

        return [
            'plan' => $packageTitle !== null ? 'basic' : 'default',
            'package_id' => $packageTitle !== null ? $packageId : null,
            'package_title' => $packageTitle,
            'codes' => self::REQUIRED_SENOCLOCK_BIOMARKERS,
        ];
    }

    /**
     * Display name for any SenoClock biomarker code.
     */
    public static function biomarkerName(string $code): string
    {
        return self::REQUIRED_SENOCLOCK_BIOMARKER_NAMES[$code] ?? self::ALL_SENOCLOCK_BIOMARKER_NAMES[$code] ?? $code;
    }

    /**
     * Return the required SenoClock biomarkers missing from the given markers
     * (keyed by marker name/code). Markers with a non-numeric value count as missing.
     */
    public function getMissingRequiredSenoclockMarkers(array $markers, ?array $requiredCodes = null): array
    {
        $mapping = $this->getSenoclockMapping();
        $knownKeys = array_flip(array_values($mapping));
        $present = [];

        foreach ($markers as $rawKey => $data) {
            $value = is_array($data) ? ($data['value'] ?? null) : $data;
            if (!is_numeric($value)) {
                continue;
            }
            $rawKey = trim((string) $rawKey);
            $key = isset($knownKeys[strtoupper($rawKey)])
                ? strtoupper($rawKey)
                : $this->findSenoclockKey($rawKey, $mapping);
            if ($key) {
                $present[$key] = true;
            }
        }

        return array_values(array_filter(
            $requiredCodes ?? self::REQUIRED_SENOCLOCK_BIOMARKERS,
            fn($key) => !isset($present[$key])
        ));
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

        // Treat hyphens as spaces on both sides, so normalized names like
        // "c reactive protein" still match the "c-reactive protein" alias.
        foreach ($mapping as $mapKey => $senoKey) {
            $flatKey = str_replace('-', ' ', (string) $mapKey);
            if (!isset($mapping[$flatKey])) {
                $mapping[$flatKey] = $senoKey;
            }
        }
        $name = preg_replace('/\s+/', ' ', str_replace('-', ' ', $name)) ?? $name;
        if (isset($mapping[$name])) {
            return $mapping[$name];
        }

        // Also try without parenthetical aliases: "hemoglobin a1c (hba1c)" → "hemoglobin a1c"
        $nameNoParen = trim(preg_replace('/\s*\([^)]*\)/', '', $name) ?? $name);
        $nameNoParen = preg_replace('/\s+/', ' ', $nameNoParen) ?? $nameNoParen;
        if ($nameNoParen !== '' && $nameNoParen !== $name && isset($mapping[$nameNoParen])) {
            return $mapping[$nameNoParen];
        }

        // Different tests that contain a biomarker's name ("Indirect Bilirubin", "Absolute
        // Neutrophil Count", "Non-HDL", "Urine Protein", "A/G Ratio") must not borrow its key.
        if (preg_match('/\b(indirect|absolute|abs|non hdl|vldl|ratio|urine|urinary)\b|\ba\/g\b/', $name)) {
            return null;
        }

        // Longest contained alias wins, matched on whole words so "ldl" is not found inside
        // "vldl" nor "prot" inside "protein". Skip very short keys ("k","p","ast") for
        // contains-matching so "fasting" does not map to AST and "like" does not map to K+.
        $bestKey = null;
        $bestLen = 0;
        foreach ($mapping as $mapKey => $senoKey) {
            $mapKey = (string) $mapKey;
            $len = strlen($mapKey);
            if ($len < 4) {
                continue;
            }
            $pattern = '/(?<![a-z0-9])' . preg_quote($mapKey, '/') . '(?![a-z0-9])/';
            if (
                preg_match($pattern, $name) ||
                ($nameNoParen !== '' && preg_match($pattern, $nameNoParen))
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
