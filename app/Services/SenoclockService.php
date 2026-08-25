<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class SenoclockService
{
    protected $baseUrl;
    protected $email;
    protected $password;
    protected $token;

    public function __construct()
    {
        $this->baseUrl = config('services.senoclock.base_url', 'https://api-euc1.senoclock.ai');
        $this->email = config('services.senoclock.email');
        $this->password = config('services.senoclock.password');
    }

    /**
     * Authenticate with SenoClock
     *
     * @return string|null Token if successful, null otherwise
     */
    public function authenticate(): ?string
    {
        if (!$this->email || !$this->password) {
            Log::error('SenoclockService: Credentials not set in config');
            return null;
        }

        try {
            Log::info('SenoclockService: Attempting authentication', ['email' => $this->email, 'url' => "{$this->baseUrl}/rest-auth/login/"]);
            
            $response = Http::withoutVerifying()->post("{$this->baseUrl}/rest-auth/login/", [
                'email' => $this->email,
                'password' => $this->password,
            ]);

            // Mask sensitive tokens before logging
            $logBody = $response->json();
            if (is_array($logBody)) {
                if (isset($logBody['access_token'])) {
                    $logBody['access_token'] = '***MASKED***';
                }
                if (isset($logBody['refresh_token'])) {
                    $logBody['refresh_token'] = '***MASKED***';
                }
                if (isset($logBody['token'])) {
                    $logBody['token'] = '***MASKED***';
                }
                if (isset($logBody['key'])) {
                    $logBody['key'] = '***MASKED***';
                }
            } else {
                $logBody = '***MASKED RESPONSE BODY***';
            }

            Log::info('SenoclockService: Auth Response', [
                'status' => $response->status(),
                'headers' => $response->headers(),
                'body' => $logBody
            ]);

            if ($response->successful()) {
                $this->token = $response->json('key') ?? $response->json('token') ?? $response->json('access_token');
                return $this->token;
            } else {
                Log::error('SenoclockService: Authentication failed');
            }
        } catch (\Throwable $e) {
            Log::error('SenoclockService: Authentication exception', ['message' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * Upload Document to SenoClock
     *
     * @param string|array $documentPaths
     * @return string|null SenoClock ID if successful, null otherwise
     */
    public function uploadDocument($documentPaths): ?string
    {
        if (!$this->token) {
            Log::error('SenoclockService: Cannot upload, no valid token');
            return null;
        }

        try {
            $url = "{$this->baseUrl}/dl-api/file-upload/";
            $pathsArray = is_array($documentPaths) ? $documentPaths : [$documentPaths];
            Log::info('SenoclockService: Attempting upload', ['paths' => $pathsArray, 'url' => $url]);

            $request = Http::withoutVerifying()->withToken($this->token);
            $attachedFiles = [];
            foreach ($pathsArray as $path) {
                if (file_exists($path)) {
                    $request->attach('file', file_get_contents($path), basename($path));
                    $attachedFiles[] = basename($path);
                } else {
                    Log::warning('SenoclockService: File to attach not found', ['path' => $path]);
                }
            }

            Log::info('SenoclockService: Request body details before sending', [
                'attached_files_count' => count($attachedFiles),
                'attached_filenames' => $attachedFiles,
                'payload_params' => [
                    'process_execute' => 'false',
                    'diet_preference' => 'non_veg',
                    'preferred_language' => 'en'
                ]
            ]);

            $response = $request->put($url, [
                'process_execute' => 'false',
                'diet_preference' => 'non_veg',
                'preferred_language' => 'en'
            ]);

            Log::info('SenoclockService: Upload Response', [
                'status' => $response->status(),
                'headers' => $response->headers(),
                'body' => $response->body()
            ]);

            if ($response->successful()) {
                return $response->json('id');
            }
        } catch (\Throwable $e) {
            Log::error('SenoclockService: Upload exception', ['message' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * Execute Algorithm
     *
     * @param string $senoclockId
     * @param string $externalId Unique identifier for the report
     * @param int $age
     * @param string $gender
     * @param string $testDate
     * @param array $markers
     * @return bool True if successful, False otherwise
     */
    public function executeAlgorithm(string $senoclockId, string $externalId, int $age, string $gender, string $testDate, array $markers, array $vitals = []): bool
    {
        if (empty($this->token)) {
            Log::error('SenoclockService: Cannot execute without a valid token.');
            return false;
        }

        $payload = [
            'id' => $senoclockId,
            'external_id' => $externalId,
            'dob' => null,
            'age' => $age,
            'gender' => $gender,
            'test_date' => $testDate,
        ];

        // Merge vitals into the root payload if provided (before markers)
        if (!empty($vitals)) {
            foreach (['height', 'weight', 'blood_pressure', 'allergies'] as $field) {
                if (isset($vitals[$field]) && $vitals[$field] !== '') {
                    $payload[$field] = $vitals[$field];
                }
            }
        }

        // Add markers at the end
        $payload['markers'] = $markers;

        try {
            $url = "{$this->baseUrl}/dl-api/file-execute/";
            Log::info('SenoclockService: Attempting execution', ['url' => $url, 'payload' => $payload]);

            $response = Http::withoutVerifying()->withToken($this->token)
                ->post($url, $payload);

            Log::info('SenoclockService: Execute Response', [
                'status' => $response->status(),
                'headers' => $response->headers(),
                'body' => $response->body()
            ]);

            if ($response->successful()) {
                $json = $response->json();
                if (($json['status'] ?? '') === 'Ok') {
                    Log::info('SenoclockService: Execute accepted', [
                        'senoclock_id' => $senoclockId,
                        'status' => 'Ok'
                    ]);
                    
                    Log::info('SenoclockService: Waiting for asynchronous report generation', [
                        'senoclock_id' => $senoclockId,
                        'wait_seconds' => 5
                    ]);
                    
                    return true;
                }
            }
        } catch (\Throwable $e) {
            Log::error('SenoclockService: Execute exception', ['message' => $e->getMessage()]);
        }

        return false;
    }

    /**
     * Download PDF with Retry Logic
     *
     * @param string $senoclockId
     * @param string $destinationDir Directory to save the PDF
     * @param int $maxRetries
     * @param int $retryDelay Seconds to wait between retries
     * @return array ['success' => bool, 'path' => string|null, 'error' => string|null]
     */
    public function downloadPdfWithRetry(string $senoclockId, string $destinationDir, ?string $externalId = null, int $maxRetries = 30, int $retryDelay = 5): array
    {
        if (!$this->token) {
            return ['success' => false, 'error' => 'Not authenticated with SenoClock'];
        }

        $query = http_build_query([
            'pdf_report' => 'true',
            'id' => $senoclockId,
        ]);
        $url = "{$this->baseUrl}/dl-api/report/download/?{$query}";
        
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            Log::info("SenoclockService: Download attempt {$attempt}/{$maxRetries}", ['url' => $url]);

            try {
                $response = Http::withoutVerifying()
                    ->withToken($this->token)
                    ->withHeaders(['Accept' => 'application/pdf, application/json, */*'])
                    ->get($url);

                $status = $response->status();
                $contentType = $response->header('Content-Type');
                $body = $response->body();

                // Mask confusing SenoClock transient error in logs
                $logBody = $body;
                if ($status === 409 && str_contains(strtolower($body), 'unable to parse the file')) {
                    $logBody = '{"message":"Report is not generated yet"}';
                }

                Log::info("SenoclockService: Download Response Attempt {$attempt}", [
                    'status' => $status,
                    'content_type' => $contentType,
                    'body_sample' => substr($logBody, 0, 500)
                ]);

                if ($response->successful()) {
                    // Detect if HTML is returned inside 200 OK
                    if (str_contains(strtolower((string)$contentType), 'text/html') || strpos(trim($body), '<!DOCTYPE html>') === 0) {
                        $errorMsg = $this->parseHtmlError($body);
                        Log::warning("SenoclockService: Received HTML instead of PDF on attempt {$attempt}", ['parsed_error' => $errorMsg]);
                        
                        if ($attempt === $maxRetries) {
                            return ['success' => false, 'error' => "SenoClock Error: {$errorMsg}"];
                        }
                    } 
                    // Verify actual PDF (MUST start with %PDF)
                    elseif (strpos(ltrim($body), '%PDF') === 0) {
                        $fileName = "senoclock_{$senoclockId}.pdf";
                        if (!file_exists($destinationDir)) {
                            @mkdir($destinationDir, 0777, true);
                        }
                        
                        $fullPath = rtrim($destinationDir, '/') . '/' . $fileName;
                        file_put_contents($fullPath, $body);
                        
                        Log::info("SenoclockService: Report downloaded successfully", [
                            'status' => $status,
                            'content_type' => $contentType,
                            'size' => strlen($body) . " bytes"
                        ]);
                        return ['success' => true, 'path' => $fileName, 'error' => null];
                    } 
                    // Detect if it's JSON
                    elseif (str_contains(strtolower((string)$contentType), 'application/json')) {
                        $json = $response->json();
                        Log::info("SenoclockService: Report not ready yet", [
                            'status' => $status,
                            'senoclock_id' => $senoclockId,
                            'message' => $json['msg'][0] ?? $json['message'] ?? 'JSON response'
                        ]);
                        
                        if ($attempt === $maxRetries) {
                            return ['success' => false, 'error' => $json['message'] ?? 'SenoClock returned JSON error'];
                        }
                    } 
                    // Unknown content
                    else {
                        Log::warning("SenoclockService: Unknown content received on attempt {$attempt}");
                        if ($attempt === $maxRetries) {
                            return ['success' => false, 'error' => 'Unknown content format returned by SenoClock'];
                        }
                    }
                } else {
                    // Non-200 Response
                    $responseString = $body;
                    $isTransient409 = ($status === 409 && str_contains(strtolower($responseString), 'unable to parse the file'));
                    $isTransient202 = ($status === 202);
                    
                    if ($isTransient409 || $isTransient202) {
                        // Do not log this as an error since it is an expected transient state.
                        // The attempt response was already logged as INFO at the start of the loop.
                    } else {
                        // Check for permanent errors ONLY if it's not the transient 409/202
                        $permanentErrorReason = $this->getPermanentReportGenerationError($responseString);
                        if ($permanentErrorReason) {
                            Log::error("SenoclockService: Permanent error detected ({$permanentErrorReason}). Stopping retries immediately.", [
                                'url' => $url,
                                'senoclock_id' => $senoclockId,
                                'error_body' => substr($responseString, 0, 500)
                            ]);
                            return ['success' => false, 'error' => "SenoClock Permanent Error: " . $responseString];
                        }
                        
                        Log::error("SenoclockService: HTTP {$status} Error on attempt {$attempt}");
                        
                        if ($attempt === $maxRetries) {
                            return ['success' => false, 'error' => 'SenoClock API failed with status ' . $status];
                        }
                    }
                }

            } catch (\Throwable $e) {
                Log::error("SenoclockService: Exception during download attempt {$attempt}", ['message' => $e->getMessage()]);
                if ($attempt === $maxRetries) {
                    return ['success' => false, 'error' => $e->getMessage()];
                }
            }

            // Wait before next retry
            if ($attempt < $maxRetries) {
                // Dynamic backoff strategy: 5s for first 3 attempts, then 10s
                $currentDelay = ($attempt <= 3) ? 5 : 10;
                sleep($currentDelay);
            }
        }

        return ['success' => false, 'error' => 'Max retries exhausted'];
    }

    /**
     * Check if SenoClock returned a permanent error that shouldn't be retried
     */
    private function getPermanentReportGenerationError(string $responseBody): ?string
    {
        $bodyLower = strtolower($responseBody);
        
        $permanentErrors = [
            'minimum 15 markers required' => 'Marker count validation',
            'unsupported or mismatched units found' => 'Unit validation',
            'invalid marker' => 'Marker validation',
            'unsupported marker' => 'Marker validation',
            'invalid unit' => 'Unit validation'
            // "unable to parse the file" is DELIBERATELY omitted because it is a transient error during async generation
        ];

        foreach ($permanentErrors as $errorString => $reason) {
            if (str_contains($bodyLower, $errorString)) {
                return $reason;
            }
        }

        return null;
    }

    /**
     * Parse Django HTML Error Page
     * 
     * @param string $html
     * @return string
     */
    protected function parseHtmlError(string $html): string
    {
        try {
            // First try to extract the title
            preg_match('/<title>(.*?)<\/title>/is', $html, $titleMatches);
            $title = isset($titleMatches[1]) ? trim(str_replace('\n', '', strip_tags($titleMatches[1]))) : '';

            // Then try to extract the exception value
            preg_match('/<table class="meta">.*?<tr>.*?<th>Exception Value:<\/th>.*?<td><pre>(.*?)<\/pre><\/td>/is', $html, $exceptionMatches);
            $exceptionValue = isset($exceptionMatches[1]) ? trim(str_replace('\n', '', strip_tags($exceptionMatches[1]))) : '';

            if ($title && $exceptionValue) {
                return "{$title} - {$exceptionValue}";
            } elseif ($title) {
                return $title;
            }
        } catch (\Throwable $e) {
            // Silently fallback if parsing fails
        }
        
        return 'Unknown HTML Error returned by SenoClock';
    }
}
