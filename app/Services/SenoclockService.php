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

            Log::info('SenoclockService: Auth Response', [
                'status' => $response->status(),
                'headers' => $response->headers(),
                'body' => $response->body()
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
     * @param string $documentPath
     * @return string|null SenoClock ID if successful, null otherwise
     */
    public function uploadDocument(string $documentPath): ?string
    {
        if (!$this->token) {
            Log::error('SenoclockService: Cannot upload, no valid token');
            return null;
        }

        try {
            $url = "{$this->baseUrl}/dl-api/file-upload/";
            Log::info('SenoclockService: Attempting upload', ['path' => $documentPath, 'url' => $url]);

            $response = Http::withoutVerifying()->withToken($this->token)
                ->attach('file', file_get_contents($documentPath), basename($documentPath))
                ->put($url, [
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
    public function executeAlgorithm(string $senoclockId, string $externalId, int $age, string $gender, string $testDate, array $markers): bool
    {
        if (!$this->token) {
            Log::error('SenoclockService: Cannot execute, no valid token');
            return false;
        }

        $payload = [
            'id' => $senoclockId,
            'external_id' => $externalId,
            'dob' => null,
            'age' => $age,
            'gender' => $gender,
            'test_date' => $testDate,
            'markers' => $markers,
        ];

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
    public function downloadPdfWithRetry(string $senoclockId, string $destinationDir, ?string $externalId = null, int $maxRetries = 5, int $retryDelay = 5): array
    {
        if (!$this->token) {
            return ['success' => false, 'error' => 'Not authenticated with SenoClock'];
        }

        $url = "{$this->baseUrl}/dl-api/report/download/?pdf_report=true&id={$senoclockId}";
        
        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            Log::info("SenoclockService: Download attempt {$attempt}/{$maxRetries}", ['url' => $url]);

            try {
                $response = Http::withoutVerifying()->withToken($this->token)->get($url);
                $status = $response->status();
                $contentType = $response->header('Content-Type');
                $body = $response->body();

                Log::info("SenoclockService: Download Response Attempt {$attempt}", [
                    'status' => $status,
                    'content_type' => $contentType,
                    'body_sample' => substr($body, 0, 500)
                ]);

                if ($response->successful()) {
                    // Detect if HTML is returned inside 200 OK
                    if (str_contains(strtolower($contentType), 'text/html') || strpos(trim($body), '<!DOCTYPE html>') === 0) {
                        $errorMsg = $this->parseHtmlError($body);
                        Log::warning("SenoclockService: Received HTML instead of PDF on attempt {$attempt}", ['parsed_error' => $errorMsg]);
                        
                        if ($attempt === $maxRetries) {
                            return ['success' => false, 'error' => "SenoClock Error: {$errorMsg}"];
                        }
                    } 
                    // Detect if it's JSON
                    elseif (str_contains(strtolower($contentType), 'application/json')) {
                        Log::warning("SenoclockService: Received JSON on attempt {$attempt}", ['json' => $response->json()]);
                        
                        if ($attempt === $maxRetries) {
                            return ['success' => false, 'error' => $response->json('message') ?? 'SenoClock returned JSON error'];
                        }
                    } 
                    // Verify actual PDF
                    elseif (strpos(trim($body), '%PDF') === 0) {
                        $fileName = "senoclock_{$senoclockId}.pdf";
                        if (!file_exists($destinationDir)) {
                            @mkdir($destinationDir, 0777, true);
                        }
                        
                        $fullPath = rtrim($destinationDir, '/') . '/' . $fileName;
                        file_put_contents($fullPath, $body);
                        
                        Log::info("SenoclockService: PDF successfully downloaded and saved", ['path' => $fullPath]);
                        return ['success' => true, 'path' => $fileName, 'error' => null];
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
                    if (str_contains(strtolower($contentType), 'text/html') || strpos(trim($body), '<!DOCTYPE html>') === 0) {
                        $errorMsg = $this->parseHtmlError($body);
                        Log::error("SenoclockService: HTTP {$status} HTML Error on attempt {$attempt}", ['parsed_error' => $errorMsg]);
                        
                        if ($attempt === $maxRetries) {
                            return ['success' => false, 'error' => "SenoClock Error: {$errorMsg}"];
                        }
                    } else {
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
                sleep($retryDelay);
            }
        }

        return ['success' => false, 'error' => 'Max retries exhausted'];
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
