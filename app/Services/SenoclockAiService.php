<?php

namespace App\Services;

use App\Models\AI_Vital;
use App\Models\Constants;
use App\Models\Users;
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

        $url = $this->apiUrl('/rest-auth/login/');

        $response = Http::timeout(30)
            ->acceptJson()
            ->asJson()
            ->post($url, [
                'email' => $email,
                'password' => $password,
            ]);

        if (!$response->successful()) {
            Log::error('Senoclock AI background login failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return null;
        }

        $accessToken = $response->json('access_token');
        if (empty($accessToken)) {
            Log::error('Senoclock AI login response missing access_token', [
                'body' => $response->body(),
            ]);
            return null;
        }

        Cache::put($cacheKey, $accessToken, now()->addHours(12));
        Log::info('Senoclock AI background login successful; access token cached.');

        return $accessToken;
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

    private function mapSex(?int $gender): ?string
    {
        if ($gender === Constants::genderMale) {
            return 'male';
        }

        if ($gender === Constants::genderFemale) {
            return 'female';
        }

        return null;
    }

    private function apiUrl(string $path): string
    {
        $baseUrl = rtrim((string) config('services.senoclock.base_url'), '/');

        return $baseUrl . $path;
    }
}
