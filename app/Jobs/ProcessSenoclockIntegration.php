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
        set_time_limit(300); // Allow up to 5 minutes for SenoClock to generate the PDF

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

            $senoclockService = app(\App\Services\SenoclockService::class);
            
            // Authenticate
            if (!$senoclockService->authenticate()) {
                return;
            }

            // Upload
            $senoclockId = $senoclockService->uploadDocument($documentPath);
            if (!$senoclockId) {
                return;
            }

            // Step 2: Build Senoclock Biomarkers dynamically from the initial AI analysis
            $analysis = $labReport->analysis_response ?? [];
            if (is_string($analysis)) {
                $analysis = json_decode($analysis, true) ?? [];
            }
            $extractedBiomarkers = $analysis['extracted_biomarkers'] ?? [];

            $senoclockMarkers = [];
            foreach ($extractedBiomarkers as $marker) {
                if (is_array($marker) && !empty($marker['name']) && !empty($marker['value'])) {
                    $key = $this->mapToSenoclockKey($marker['name']);
                    if ($key) {
                        $senoclockMarkers[$key] = [
                            'value' => (float) $marker['value'],
                            'unit' => $marker['unit'] ?? '',
                            'range' => $marker['range'] ?? '',
                        ];
                    }
                }
            }

            // If GPT failed to extract markers, we cannot generate a Senoclock report
            if (empty($senoclockMarkers)) {
                Log::error("ProcessSenoclockIntegration: No markers extracted for Senoclock, aborting report generation", ['lab_report_id' => $this->labReportId]);
                $labReport->update(['senoclock_id' => 'FAILED']);
                return;
            }

            // Execute
            $user = User::find($labReport->user_id);
            $age = 25; // Default if not found
            $gender = 'male'; // Default if not found

            if ($user) {
                if (isset($user->dob) && $user->dob) {
                    $age = \Carbon\Carbon::parse($user->dob)->age;
                }
                if (isset($user->gender)) {
                    $genderVal = (string) $user->gender;
                    if ($genderVal === '0' || strtolower($genderVal) === 'male') {
                        $gender = 'male';
                    } elseif ($genderVal === '1' || strtolower($genderVal) === 'female') {
                        $gender = 'female';
                    } else {
                        $gender = strtolower($genderVal);
                    }
                }
            }

            $externalId = 'mulkmed_' . $labReport->id;
            $testDate = $labReport->created_at->format('Y-m-d');
            
            if (!$senoclockService->executeAlgorithm($senoclockId, $externalId, $age, $gender, $testDate, $senoclockMarkers)) {
                return;
            }

            // Store Senoclock ID in database
            $labReport->senoclock_id = $senoclockId;
            $labReport->save();

            // Download (SenoClock takes ~2 minutes to generate the report. 30 retries * 5s = 150 seconds)
            $destinationDir = public_path('uploads');
            $downloadResult = $senoclockService->downloadPdfWithRetry($senoclockId, $destinationDir, null, 30, 5);

            if ($downloadResult['success']) {
                $labReport->senoclock_pdf_path = 'uploads/' . $downloadResult['path'];
                $labReport->save();
                Log::info('ProcessSenoclockIntegration: Successfully completed integration', ['lab_report_id' => $labReport->id]);
            } else {
                Log::error('ProcessSenoclockIntegration: Final download failed', ['error' => $downloadResult['error']]);
            }

        } catch (\Throwable $e) {
            Log::error('ProcessSenoclockIntegration: Exception caught', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
        }
    }
}
