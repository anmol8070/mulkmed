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

    /** Upload, execute and poll until the PDF is downloaded (original behaviour). */
    public const MODE_FULL = 'full';
    /** Upload, execute and make one download attempt; queue MODE_DOWNLOAD if the PDF is not ready. */
    public const MODE_START = 'start';
    /** Only poll for and save the PDF of an already executed report. */
    public const MODE_DOWNLOAD = 'download';

    protected $labReportId;

    protected string $mode;

    /** Outcome of a MODE_START run, returned in the analyzeReport response. */
    public array $result = [];

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
    public function __construct($labReportId, string $mode = self::MODE_FULL)
    {
        $this->labReportId = $labReportId;
        $this->mode = $mode;
    }

    private function fail(?LabReport $labReport, string $message, bool $markFailed = true): void
    {
        $this->result = array_merge($this->result, ['status' => 'failed', 'message' => $message]);
        if ($labReport && $markFailed) {
            $labReport->senoclock_status = 'failed';
            $labReport->save();
        }
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

            $testDateRaw = $labReport && $labReport->created_at ? $labReport->created_at->format('Y-m-d') : date('Y-m-d');
            
            Log::info('ProcessSenoclockIntegration: Starting', [
                'lab_report_id' => $this->labReportId,
                'user_id' => $labReport ? $labReport->user_id : null,
                'test_date' => $testDateRaw,
                'application_now' => now()->toDateTimeString(),
                'application_timezone' => config('app.timezone'),
                'application_today' => now()->toDateString(),
                'utc_now' => now()->utc()->toDateTimeString(),
                'utc_today' => now()->utc()->toDateString(),
            ]);

            if (!$labReport) {
                Log::error("ProcessSenoclockIntegration: LabReport not found", ['lab_report_id' => $this->labReportId]);
                $this->fail(null, 'Lab report not found');
                return;
            }

            if ($this->mode === self::MODE_DOWNLOAD) {
                if (!$senoclockService->authenticate()) {
                    Log::error("ProcessSenoclockIntegration: Failed to authenticate with SenoClock API");
                    $this->fail($labReport, 'Failed to authenticate with SenoClock API');
                    return;
                }
                $this->downloadAndComplete($labReport, $senoclockService, (string) $labReport->senoclock_id, strval($labReport->user_id), 30);
                return;
            }

            // Locate the PDFs
            $documentPaths = [];
            
            // Collect paths from analysis_response if available, otherwise fallback to single document_path
            $analysisResponse = $labReport->analysis_response ?? [];
            if (!empty($analysisResponse['document_paths'])) {
                foreach ($analysisResponse['document_paths'] as $path) {
                    $fullPath = public_path($path);
                    if (!file_exists($fullPath)) {
                        $fullPath = storage_path('app/public/' . ltrim($path, '/'));
                    }
                    if (!file_exists($fullPath)) {
                        $fullPath = storage_path('app/' . ltrim($path, '/'));
                    }
                    if (file_exists($fullPath)) {
                        $documentPaths[] = $fullPath;
                    }
                }
            } else {
                $singlePath = public_path($labReport->document_path);
                if (!file_exists($singlePath)) {
                    $singlePath = storage_path('app/public/' . ltrim($labReport->document_path ?? '', '/'));
                }
                if (!file_exists($singlePath)) {
                    $singlePath = storage_path('app/' . ltrim($labReport->document_path ?? '', '/'));
                }
                if (file_exists($singlePath)) {
                    $documentPaths[] = $singlePath;
                }
            }

            if (empty($documentPaths)) {
                Log::error("ProcessSenoclockIntegration: Original PDFs not found for LabReport #{$labReport->id}");
                $this->fail($labReport, 'Original lab report PDF not found', false);
                return;
            }

            // POST /auth/login
            if (!$senoclockService->authenticate()) {
                Log::error("ProcessSenoclockIntegration: Failed to authenticate with SenoClock API");
                $this->fail($labReport, 'Failed to authenticate with SenoClock API', false);
                return;
            }

            // PUT /dl-api/file-upload/
            $senoclockId = $senoclockService->uploadDocument($documentPaths);
            if (!$senoclockId) {
                Log::error("ProcessSenoclockIntegration: PDF upload failed");
                $this->fail($labReport, 'SenoClock PDF upload failed', false);
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

            // Do not fail the report when biomarker count is 0.
            // Case 4: available_count is 0 — continue if extracted markers exist
            // if ($availableCount === 0 && empty($extractedBiomarkers) && empty($labReport->markers)) {
            //     Log::warning("ProcessSenoclockIntegration: available_count is 0. Aborting execution for LabReport #{$labReport->id}");
            //     $labReport->senoclock_status = 'failed';
            //     $labReport->save();
            //     return;
            // }

            // 1. Extract ALL markers from the report
            $extractedMarkers = $labReport->markers ?? [];
            if (empty($extractedMarkers)) {
                $extractedMarkers = $aiService->convertBiomarkersToSenoclockFormat($extractedBiomarkers);
            }
            if (empty($extractedMarkers) && !empty($labReport->ocr_text)) {
                $analyzerService = app(\App\Services\LabReportBiomarkerAnalyzerService::class);
                $extractedMarkers = $analyzerService->extractSenoclockMarkersWithOpenAi($labReport->ocr_text);
            }

            // Case 3: No matching markers in extraction — still continue, do not set status false
            // if (empty($extractedMarkers)) {
            //     Log::warning("ProcessSenoclockIntegration: No markers extracted from report. Aborting execution for LabReport #{$labReport->id}");
            //     return;
            // }

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

            // 3. Do not filter by availableKeys. Send all extracted markers directly!
            $filteredMarkers = $normalizedExtractedMarkers;

            $filteredMarkerCount = count($filteredMarkers);

            Log::info('SenoclockService: Final marker filtering', [
                'extracted_marker_count' => count($extractedMarkers),
                'normalized_extracted_markers' => array_keys($normalizedExtractedMarkers),
                'filtered_marker_count' => count($filteredMarkers),
                'sent_markers' => array_keys($filteredMarkers),
            ]);

            // Generate the report even if biomarkers are missing from the uploaded PDF.
            // APP BUSINESS RULE: Minimum 16 markers required for report generation
            // if ($filteredMarkerCount < 16) {
            //     Log::warning('SenoclockService: Insufficient markers for report generation', [
            //         'available_marker_count' => count($availableKeys),
            //         'extracted_marker_count' => count($extractedMarkers),
            //         'filtered_marker_count' => $filteredMarkerCount,
            //         'required_marker_count' => 16,
            //         'sent_markers' => array_keys($filteredMarkers),
            //     ]);
            //     return; // Stop execution
            // }

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

            // Zero biomarkers: do NOT generate report.
            if (count($senoclockMarkers) === 0) {
                Log::warning('ProcessSenoclockIntegration: Zero biomarkers — skipping report generation', [
                    'lab_report_id' => $labReport->id,
                ]);
                $this->fail($labReport, 'No biomarkers available to send to SenoClock');
                return;
            }

            // Drop non-numeric values (e.g. MPV="Normal") — blood age algo rejects them.
            $invalidMarkers = [];
            foreach ($senoclockMarkers as $key => $data) {
                $value = is_array($data) ? ($data['value'] ?? null) : null;
                if (!is_numeric($value)) {
                    $invalidMarkers[$key] = $value;
                    unset($senoclockMarkers[$key]);
                }
            }
            if (!empty($invalidMarkers)) {
                Log::warning('ProcessSenoclockIntegration: Removed non-numeric markers before SenoClock execute', [
                    'lab_report_id' => $labReport->id,
                    'removed' => $invalidMarkers,
                    'remaining_count' => count($senoclockMarkers),
                ]);
            }

            // SenoClock needs these specific biomarkers (other extracted markers are optional).
            // Abort before execute/download — otherwise download keeps returning HTTP 409.
            $missingRequired = $aiService->getMissingRequiredSenoclockMarkers($senoclockMarkers);
            if (!empty($missingRequired)) {
                Log::warning('ProcessSenoclockIntegration: Required biomarkers missing — skipping execute/download', [
                    'lab_report_id' => $labReport->id,
                    'missing_required_biomarkers' => $missingRequired,
                    'sent_markers' => array_keys($senoclockMarkers),
                ]);
                $this->fail($labReport, 'Required biomarkers are missing');
                $this->result['missing_biomarkers'] = $missingRequired;
                return;
            }

            $user = Users::find($labReport->user_id);
            $age = 25;
            $gender = 'male';
            if ($user) {
                $age = $user->dob ? \Carbon\Carbon::parse($user->dob)->age : 25;
                $gender = $this->mapSex($user->gender ?? null);
            }
            
            $originalTestDate = $labReport->created_at ? $labReport->created_at->format('Y-m-d') : date('Y-m-d');
            $senoclockTestDate = $originalTestDate;
            
            if ($originalTestDate >= now()->toDateString()) {
                $senoclockTestDate = now()->subDay()->toDateString();
                Log::warning('SenoclockService: Test date normalized for API', [
                    'original_test_date' => $originalTestDate,
                    'senoclock_test_date' => $senoclockTestDate,
                    'reason' => 'SenoClock requires test_date to be strictly earlier than today',
                ]);
            }

            $externalId = strval($labReport->user_id);
            $vitals = [];
            $analysisResponse = $labReport->analysis_response ?? [];
            foreach (['height', 'weight', 'blood_pressure', 'allergies'] as $field) {
                if (isset($analysisResponse[$field])) {
                    $vitals[$field] = $analysisResponse[$field];
                }
            }

            Log::info('ProcessSenoclockIntegration: Preparing payload', [
                'lab_report_id' => $this->labReportId,
                'test_date' => $senoclockTestDate,
                'marker_count' => count($senoclockMarkers),
                'marker_names' => array_keys($senoclockMarkers),
            ]);

            // Log exact payload safely
            Log::info("SenoclockService: Executing Senoclock Analysis", [
                'id' => $senoclockId,
                'external_id' => $externalId,
                'age' => $age,
                'gender' => $gender,
                'test_date' => $senoclockTestDate,
                'vitals' => $vitals,
                'markers' => $senoclockMarkers,
            ]);

            // POST /dl-api/file-execute/
            $executionSuccess = $senoclockService->executeAlgorithm($senoclockId, $externalId, $age, $gender, $senoclockTestDate, $senoclockMarkers, $vitals);

            if (!$executionSuccess) {
                Log::error('ProcessSenoclockIntegration: SenoClock execution failed', [
                    'lab_report_id' => $this->labReportId,
                    'test_date' => $senoclockTestDate ?? $originalTestDate,
                    'marker_count' => count($senoclockMarkers),
                    'response_status' => null, // Note: handled inside executeAlgorithm
                ]);
                Log::error("ProcessSenoclockIntegration: Execution failed for LabReport #{$this->labReportId}");
                $this->fail($labReport, 'SenoClock execution failed');
                $this->result['senoclock_id'] = $senoclockId;
                $this->result['execute_payload'] = $senoclockService->lastExecutePayload;
                $this->result['execute_response'] = $senoclockService->lastExecuteResponse;
                return;
            }

            Log::info('ProcessSenoclockIntegration: SenoClock execution successful', [
                'lab_report_id' => $this->labReportId,
                'test_date' => $senoclockTestDate,
                'marker_count' => count($senoclockMarkers),
            ]);

            $this->result = [
                'status' => 'processing',
                'message' => 'SenoClock execution accepted',
                'senoclock_id' => $senoclockId,
                'execute_payload' => $senoclockService->lastExecutePayload,
                'execute_response' => $senoclockService->lastExecuteResponse,
            ];

            // Wait before trying to download the generated PDF
            sleep(5);

            // MODE_START makes one download attempt so the caller can show its response,
            // then hands the remaining polling to a queued MODE_DOWNLOAD job.
            $maxAttempts = $this->mode === self::MODE_START ? 1 : 30;
            $this->downloadAndComplete($labReport, $senoclockService, $senoclockId, $externalId, $maxAttempts);
        } catch (\Throwable $e) {
            Log::error('ProcessSenoclockIntegration: Exception caught', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            $this->result = array_merge($this->result, ['status' => 'failed', 'message' => $e->getMessage()]);
        }
    }

    /**
     * GET /dl-api/report/download/ — save the PDF and mark the report completed.
     * In MODE_START a "not generated yet" answer queues a MODE_DOWNLOAD job instead of failing.
     */
    private function downloadAndComplete(LabReport $labReport, SenoclockService $senoclockService, string $senoclockId, string $externalId, int $maxAttempts): void
    {
        $destinationDir = public_path('uploads/senoclock_report_generated');
        $downloadResult = $senoclockService->downloadPdfWithRetry($senoclockId, $destinationDir, $externalId, $maxAttempts, 5);
        $this->result['senoclock_id'] = $senoclockId;
        $this->result['download_response'] = $senoclockService->lastDownloadResponse;

        if (!$downloadResult['success']) {
            $notReady = ($senoclockService->lastDownloadResponse['status'] ?? null) === 202;
            if ($this->mode === self::MODE_START && $notReady) {
                self::dispatch($labReport->id, self::MODE_DOWNLOAD);
                Log::info('ProcessSenoclockIntegration: Report not ready, download job queued', [
                    'lab_report_id' => $labReport->id,
                    'senoclock_id' => $senoclockId,
                ]);
                $this->result['status'] = 'processing';
                $this->result['message'] = 'SenoClock report is being generated';
                return;
            }

            Log::error("ProcessSenoclockIntegration: PDF download failed", ['error' => $downloadResult['error']]);
            $this->fail($labReport, $downloadResult['error'] ?? 'SenoClock PDF download failed');
            return;
        }

        // Save PDF locally and update senoclock_pdf_path
        $labReport->senoclock_pdf_path = 'uploads/senoclock_report_generated/' . $downloadResult['path'];
        $labReport->senoclock_status = 'completed';
        $labReport->senoclock_generated_at = now();
        $labReport->save();

        $this->result['status'] = 'completed';
        $this->result['message'] = 'SenoClock report generated successfully';
        $this->result['downloads'] = '/' . ltrim($labReport->senoclock_pdf_path, '/');

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

                // Temporarily increase memory limit for DOMPDF generation
                ini_set('memory_limit', '1024M');
                app(\App\Http\Controllers\v1\NewShenaiCareController::class)->longevityReportPdf($request);
            } else {
                Log::warning("ProcessSenoclockIntegration: Could not trigger longevityReportPdf, no AI_Vital found for user #{$labReport->user_id}");
            }
        } catch (\Throwable $apiException) {
            Log::error("ProcessSenoclockIntegration: Failed to trigger longevityReportPdf", [
                'message' => $apiException->getMessage()
            ]);
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
