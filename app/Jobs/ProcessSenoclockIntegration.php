<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\LabReport;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProcessSenoclockIntegration implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $labReportId;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($labReportId)
    {
        $this->labReportId = $labReportId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            $labReport = LabReport::find($this->labReportId);
            if (!$labReport || !$labReport->document_path) {
                return;
            }

            $documentPath = storage_path('app/public/' . ltrim($labReport->document_path, '/'));
            if (!file_exists($documentPath)) {
                $documentPath = public_path($labReport->document_path);
            }

            if (!file_exists($documentPath)) {
                Log::error('ProcessSenoclockIntegration: Document not found', ['path' => $documentPath]);
                return;
            }

            // Authenticate with SenoClock
            $token = $this->getSenoclockToken();
            if (!$token) {
                Log::error('ProcessSenoclockIntegration: Failed to authenticate with SenoClock API');
                return;
            }

            $baseUrl = config('services.senoclock.base_url', 'https://api-euc1.senoclock.ai');

            // Step 1: Upload to SenoClock
            $uploadResponse = Http::withoutVerifying()->withToken($token)
                ->attach('file', file_get_contents($documentPath), basename($documentPath))
                ->put("{$baseUrl}/dl-api/file-upload/", [
                    'process_execute' => 'true',
                    'diet_preference' => 'non_veg',
                    'preferred_language' => 'en'
                ]);

            if (!$uploadResponse->successful()) {
                Log::error('ProcessSenoclockIntegration: Failed to upload document', ['error' => $uploadResponse->body()]);
                return;
            }

            $senoclockId = $uploadResponse->json('id');
            if (!$senoclockId) {
                Log::error('ProcessSenoclockIntegration: Invalid response from file-upload API');
                return;
            }

            // Step 2: Build Senoclock Biomarkers using OpenAI (as originally done)
            $analyzer = app(\App\Services\LabReportBiomarkerAnalyzerService::class);
            $ocrText = $labReport->ocr_text ?? '';
            $extractedMarkersPayload = $analyzer->extractSenoclockMarkersWithOpenAi($ocrText);
            
            $senoclockMarkers = $extractedMarkersPayload['markers'] ?? [];

            if (empty($senoclockMarkers)) {
                Log::warning('ProcessSenoclockIntegration: No markers extracted for Senoclock', ['lab_report_id' => $this->labReportId]);
            }

            // Step 3: Execute SenoClock Analysis
            $user = User::find($labReport->user_id);
            $age = 25; // Default if not found
            $gender = 'male'; // Default if not found

            if ($user) {
                if (isset($user->dob) && $user->dob) {
                    $age = \Carbon\Carbon::parse($user->dob)->age;
                }
                if (isset($user->gender)) {
                    $gender = strtolower($user->gender);
                }
            }

            $executePayload = [
                'id' => $senoclockId,
                'external_id' => strval($labReport->user_id),
                'dob' => null,
                'age' => $age,
                'gender' => $gender,
                'test_date' => $labReport->created_at->format('Y-m-d'),
                'markers' => $senoclockMarkers,
            ];

            $executeResponse = Http::withoutVerifying()->withToken($token)
                ->post("{$baseUrl}/dl-api/file-execute/", $executePayload);

            if (!$executeResponse->successful()) {
                Log::error('ProcessSenoclockIntegration: Failed to execute SenoClock API', [
                    'error' => $executeResponse->body(),
                    'payload' => $executePayload
                ]);
                return;
            }

            $executeJson = $executeResponse->json();
            if (($executeJson['status'] ?? '') !== 'Ok') {
                Log::error('ProcessSenoclockIntegration: Execute API did not return Ok', ['response' => $executeJson]);
                return;
            }

            // Store Senoclock ID in database
            $labReport->senoclock_id = $senoclockId;
            $labReport->save();

            // Step 4: Generate Senoclock PDF Report
            $downloadUrl = "{$baseUrl}/dl-api/report/download/?pdf_report=true&id=" . $senoclockId;
            $pdfResponse = Http::withoutVerifying()->withToken($token)->get($downloadUrl);

            if ($pdfResponse->successful()) {
                $fileName = "senoclock_{$senoclockId}.pdf";
                
                $uploadDir = public_path('uploads');
                if (!file_exists($uploadDir)) {
                    @mkdir($uploadDir, 0777, true);
                }

                file_put_contents($uploadDir . '/' . $fileName, $pdfResponse->body());
                
                $localUrl = 'uploads/' . $fileName;
                $labReport->senoclock_pdf_path = $localUrl;
                $labReport->save();
            } else {
                Log::error('ProcessSenoclockIntegration: Failed to download PDF from SenoClock');
            }

        } catch (\Throwable $e) {
            Log::error('ProcessSenoclockIntegration: Exception caught', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
        }
    }

    private function getSenoclockToken()
    {
        $baseUrl = config('services.senoclock.base_url', 'https://api-euc1.senoclock.ai');
        $email = config('services.senoclock.email');
        $password = config('services.senoclock.password');

        if (!$email || !$password) {
            return null;
        }

        try {
            $response = Http::withoutVerifying()->post("{$baseUrl}/rest-auth/login/", [
                'email' => $email,
                'password' => $password,
            ]);

            if ($response->successful()) {
                return $response->json('key') ?? $response->json('token') ?? $response->json('access_token');
            } else {
                Log::error('ProcessSenoclockIntegration: Token auth failed', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
            }
        } catch (\Exception $e) {
            Log::error('ProcessSenoclockIntegration: Token Exception', ['message' => $e->getMessage()]);
        }

        return null;
    }
}
