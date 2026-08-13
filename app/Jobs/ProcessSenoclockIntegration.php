<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\LabReport;
use App\Models\Users;
use App\Models\Constants;
use App\Services\SenoclockService;
use App\Services\SenoclockAiService;
use Illuminate\Support\Facades\Log;

class ProcessSenoclockIntegration implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $labReportId;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 300;

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
    public function handle(SenoclockService $senoclockService, SenoclockAiService $aiService)
    {
        try {
            $labReport = LabReport::find($this->labReportId);
            if (!$labReport) {
                Log::error("ProcessSenoclockIntegration: LabReport not found", ['lab_report_id' => $this->labReportId]);
                return;
            }

            // Locate the PDF
            $documentPath = public_path($labReport->document_path);
            if (!file_exists($documentPath)) {
                $documentPath = storage_path('app/public/' . ltrim($labReport->document_path ?? '', '/'));
            }
            if (!file_exists($documentPath)) {
                $documentPath = storage_path('app/' . ltrim($labReport->document_path ?? '', '/'));
            }

            if (!file_exists($documentPath)) {
                Log::error("ProcessSenoclockIntegration: Original PDF not found for LabReport #{$labReport->id}");
                return;
            }

            // POST /auth/login
            if (!$senoclockService->authenticate()) {
                Log::error("ProcessSenoclockIntegration: Failed to authenticate with SenoClock API");
                return;
            }

            // PUT /dl-api/file-upload/
            $senoclockId = $senoclockService->uploadDocument($documentPath);
            if (!$senoclockId) {
                Log::error("ProcessSenoclockIntegration: PDF upload failed");
                return;
            }

            // Save senoclock_id
            $labReport->senoclock_id = $senoclockId;
            $labReport->save();

            // Get available biomarkers from analyzeReport response
            $availableBiomarkers = $labReport->available_biomarkers ?? [];
            $analysisResponse = $labReport->analysis_response ?? [];
            $extractedBiomarkers = $analysisResponse['extracted_biomarkers'] ?? [];

            $availableCount = $labReport->available_count ?? count($availableBiomarkers);

            // Case 4: available_count is 0
            if ($availableCount === 0) {
                Log::warning("ProcessSenoclockIntegration: available_count is 0. Aborting execution for LabReport #{$labReport->id}");
                return;
            }

            // 1. Extract ALL markers from the report
            $extractedMarkers = $labReport->markers ?? [];
            if (empty($extractedMarkers)) {
                $extractedMarkers = $aiService->convertBiomarkersToSenoclockFormat($extractedBiomarkers);
            }
            if (empty($extractedMarkers) && !empty($labReport->ocr_text)) {
                $analyzerService = app(\App\Services\LabReportBiomarkerAnalyzerService::class);
                $extractedMarkers = $analyzerService->extractSenoclockMarkersWithOpenAi($labReport->ocr_text);
            }

            // Case 3: No matching markers in extraction
            if (empty($extractedMarkers)) {
                Log::warning("ProcessSenoclockIntegration: No markers extracted from report. Aborting execution for LabReport #{$labReport->id}");
                return;
            }

            // 2. Determine "available" markers based on business logic
            $availableKeys = $aiService->getExpectedSenoclockKeys($availableBiomarkers);

            Log::info('SenoclockService: Available markers from analyzeReport', [
                'available_count' => $availableCount,
                'available_test_count' => count($availableBiomarkers),
                'available_markers' => $availableKeys,
                'available_marker_count' => count($availableKeys),
            ]);

            $mapping = $aiService->getSenoclockMapping();
            $normalizedExtractedMarkers = [];
            foreach ($extractedMarkers as $rawKey => $markerData) {
                $mKey = $aiService->findSenoclockKey($rawKey, $mapping);
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

            // 4. Validate before execution (Do NOT send empty markers if available_count > 0)
            if ($availableCount > 0 && empty($filteredMarkers)) {
                Log::error('SenoclockService: No matching available markers found', [
                    'available_count' => $availableCount,
                    'available_markers' => $availableKeys,
                    'extracted_markers' => array_keys($extractedMarkers),
                ]);
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

            $user = Users::find($labReport->user_id);
            $age = 25;
            $gender = 'male';
            if ($user) {
                $age = $user->dob ? \Carbon\Carbon::parse($user->dob)->age : 25;
                $gender = $this->mapSex($user->gender ?? null);
            }
            $testDate = $labReport->created_at ? $labReport->created_at->format('Y-m-d') : date('Y-m-d');
            $externalId = strval($labReport->user_id);
            $vitals = is_array($labReport->vitals) ? $labReport->vitals : [];

            // POST /dl-api/file-execute/
            $executionSuccess = $senoclockService->executeAlgorithm($senoclockId, $externalId, $age, $gender, $testDate, $senoclockMarkers, $vitals);

            if (!$executionSuccess) {
                Log::error("ProcessSenoclockIntegration: Execution failed for LabReport #{$labReport->id}");
                return;
            }
            
            // Wait before trying to download the generated PDF
            sleep(5);

            // GET /dl-api/report/download/?pdf_report=true&id=senoclock_id
            $destinationDir = public_path('uploads/senoclock');
            $downloadResult = $senoclockService->downloadPdfWithRetry($senoclockId, $destinationDir, $externalId, 12, 5);

            if (!$downloadResult['success']) {
                Log::error("ProcessSenoclockIntegration: PDF download failed", ['error' => $downloadResult['error']]);
                return;
            }

            // Save PDF locally and update senoclock_pdf_path
            $labReport->senoclock_pdf_path = 'uploads/senoclock/' . $downloadResult['path'];
            $labReport->senoclock_status = 'completed';
            $labReport->senoclock_generated_at = now();
            $labReport->save();

            Log::info("ProcessSenoclockIntegration: Completed successfully for LabReport #{$labReport->id}");

            // Automatically trigger the next background API/job (longevityReportPdf)
            try {
                $vital = \App\Models\AI_Vital::where('user_id', $labReport->user_id)
                    ->where('is_longevity', 1)
                    ->orderBy('id', 'desc')
                    ->first();

                if (!$vital) {
                    $vital = \App\Models\AI_Vital::where('user_id', $labReport->user_id)
                        ->orderBy('id', 'desc')
                        ->first();
                }

                if ($vital) {
                    Log::info("ProcessSenoclockIntegration: Triggering longevityReportPdf for AI_Vital #{$vital->id}");
                    $request = new \Illuminate\Http\Request();
                    $request->replace([
                        'user_id' => $labReport->user_id,
                        'report_id' => $vital->id,
                    ]);
                    app(\App\Http\Controllers\v1\NewShenaiCareController::class)->longevityReportPdf($request);
                } else {
                    Log::warning("ProcessSenoclockIntegration: Could not trigger longevityReportPdf, no AI_Vital found for user #{$labReport->user_id}");
                }
            } catch (\Throwable $apiException) {
                Log::error("ProcessSenoclockIntegration: Failed to trigger longevityReportPdf", [
                    'message' => $apiException->getMessage()
                ]);
            }

        } catch (\Throwable $e) {
            Log::error('ProcessSenoclockIntegration: Exception caught', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
        }
    }

    private function mapSex($gender): string
    {
        if ($gender === null || $gender === '') {
            return 'male';
        }
        $genderInt = (int)$gender;
        if ($genderInt === 1) { // 1 = Male typically
            return 'male';
        }
        if ($genderInt === 2) { // 2 = Female typically
            return 'female';
        }
        $genderStr = strtolower(trim((string)$gender));
        if ($genderStr === 'female' || $genderStr === '0' || $genderStr === 'f') {
            return 'female';
        }
        return 'male';
    }
}
