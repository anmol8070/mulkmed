<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Models\GlobalFunction;
use App\Models\LabReport;
use App\Models\MajorOrganPackage;
use App\Models\MajorOrganTest;
use App\Models\MajorOrganUserSelection;
use App\Jobs\ProcessSenoclockIntegration;
use App\Services\LabReportBiomarkerAnalyzerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Helpers\CurrencyHelper;

class MajorOrganTestController extends Controller
{
    public function list(Request $request)
    {
        $currency = CurrencyHelper::getUserCurrency();
        $tests = MajorOrganTest::where('status', 1)
            ->orderBy('display_order', 'asc')
            ->orderBy('id', 'asc')
            ->get()
            ->map(function ($item) use ($currency) {
                $biomarkers = is_array($item->biomarkers) ? $item->biomarkers : [];

                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'icon' => !empty($item->icon) ? ltrim($item->icon, '/') : null,
                    'currency' => $currency,
                    'price' => number_format((float) CurrencyHelper::convert($item->price, $currency), 2, '.', ''),
                    'biomarker_count' => count($biomarkers),
                    'biomarkers' => $biomarkers,
                ];
            });

        return response()->json([
            'status' => true,
            'message' => 'Major organ tests fetched successfully',
            'currency' => $currency,
            'data' => $tests,
        ]);
    }

    /**
     * AI analysis of an uploaded lab report (image/PDF) against major_organ_tests.
     * Stores document + full analysis response against the user in lab_reports.
     */
    public function analyzeReport(Request $request, LabReportBiomarkerAnalyzerService $analyzer)
    {
        // Increase maximum execution time to 300 seconds (5 minutes) for large images
        set_time_limit(300);

        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer|exists:users,id',
            'height' => 'required|string',
            'weight' => 'required|string',
            'blood_pressure' => 'required|string',
            'allergies' => 'required|string',
            'document' => 'required|array|min:1',
            'document.*' => 'required|file|mimes:pdf,jpeg,jpg,png|max:51200',
            'documents' => 'nullable|array',
            'documents.*' => 'file|mimes:pdf,jpeg,jpg,png|max:51200',
            'ocr_text' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $hasFiles = false;
        if ($request->hasFile('document') || $request->hasFile('documents')) {
            $hasFiles = true;
        }

        if (!$hasFiles && trim((string) $request->input('ocr_text')) === '') {
            return response()->json([
                'status' => false,
                'message' => 'Please upload a lab report document (image/PDF) or provide ocr_text.',
            ], 422);
        }

        $organTests = MajorOrganTest::where('status', 1)
            ->orderBy('display_order', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        if ($organTests->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No active major organ tests found in database.',
                'data' => null,
            ], 404);
        }

        $documentPath = null;
        $documentPaths = [];
        $fileType = null;
        
        $files = [];
        if ($request->hasFile('documents')) {
            $docArray = $request->file('documents');
            $files = array_merge($files, is_array($docArray) ? $docArray : [$docArray]);
        }
        if ($request->hasFile('document')) {
            $docArray = $request->file('document');
            $files = array_merge($files, is_array($docArray) ? $docArray : [$docArray]);
        }
        $files = array_filter($files);

        try {
            foreach ($files as $file) {
                if ($file) {
                    $fileName = $file->getClientOriginalName();
                    $targetDir = public_path('uploads/user_uploaded_senoclock_lab_report');
                    if (!file_exists($targetDir)) {
                        @mkdir($targetDir, 0777, true);
                    }
                    copy($file->getRealPath(), $targetDir . '/' . $fileName);
                    $path = 'uploads/user_uploaded_senoclock_lab_report/' . $fileName;
                    $documentPaths[] = $path;
                    if (!$fileType) {
                        $fileType = strtolower($file->getClientOriginalExtension() ?: '');
                    }
                }
            }

            $analysis = $analyzer->analyzeMultiple(
                $files,
                $request->input('ocr_text'),
                $organTests
            );

            if (!empty($documentPaths)) {
                $analysis['document_path'] = ltrim($documentPaths[0], '/');
                $analysis['document_paths'] = array_map(fn($p) => ltrim($p, '/'), $documentPaths);
            }

            $analysis['height'] = $request->input('height');
            $analysis['weight'] = $request->input('weight');
            $analysis['blood_pressure'] = $request->input('blood_pressure');
            $analysis['allergies'] = $request->input('allergies');

            $labReport = LabReport::create([
                'user_id' => (int) $request->user_id,
                'document_path' => !empty($documentPaths) ? json_encode(array_map(fn($p) => ltrim($p, '/'), $documentPaths)) : null,
                'type' => $fileType,
                'ocr_text' => $analysis['ocr_text'] ?? $request->input('ocr_text'),
                'extraction_source' => $analysis['extraction_source'] ?? null,
                'analysis_response' => $analysis,
                'available_biomarkers' => $analysis['available_biomarkers'] ?? [],
                'missing_biomarkers' => $analysis['missing_biomarkers'] ?? [],
                'available_count' => (int) ($analysis['available_count'] ?? 0),
                'missing_count' => (int) ($analysis['missing_count'] ?? 0),
                'total_count' => (int) ($analysis['total_count'] ?? 0),
                'to_pay' => (float) ($analysis['to_pay'] ?? 0),
                'overall_match_percentage' => $analysis['overall_match_percentage'] ?? null,
                'confidence_score' => $analysis['confidence_score'] ?? null,
                'status' => 1,
            ]);

            $analysis['lab_report_id'] = $labReport->id;
            $analysis['user_id'] = (int) $request->user_id;

            $currency = CurrencyHelper::getUserCurrency();
            $analysis['to_pay'] = CurrencyHelper::convert((float) ($analysis['to_pay'] ?? 0), $currency);

            // Generate Senoclock markers to return in the API response
            $aiService = app(\App\Services\SenoclockAiService::class);
            
            // We pass extracted_biomarkers for BOTH parameters to bypass the artificial 
            // restriction of available_biomarkers (which only contains DB matches).
            $senoclockMarkers = $aiService->convertBiomarkersToSenoclockFormat(
                $analysis['extracted_biomarkers'] ?? [], 
                $analysis['extracted_biomarkers'] ?? []
            );

            if (empty($senoclockMarkers) && !empty($labReport->ocr_text)) {
                $analyzerService = app(\App\Services\LabReportBiomarkerAnalyzerService::class);
                $senoclockMarkers = $analyzerService->extractSenoclockMarkersWithOpenAi($labReport->ocr_text);
            }

            $extractedList = $analysis['extracted_biomarkers'] ?? [];
            $excludedMarkers = [];
            $mappedNames = [];
            
            $mapping = $aiService->getSenoclockMapping();
            foreach ($extractedList as $b) {
                $name = is_array($b) ? ($b['name'] ?? '') : (string) $b;
                $name = trim($name);
                if (empty($name)) continue;

                $key = $aiService->findSenoclockKey($name, $mapping);
                
                if (empty($key)) {
                    $excludedMarkers[] = $name;
                    \Illuminate\Support\Facades\Log::info('SenoClock biomarker mapping detail', [
                        'original_name' => $name,
                        'normalized_name' => strtolower($name),
                        'senoclock_key' => null,
                        'mapped' => false,
                        'reason' => 'No SenoClock mapping exists',
                    ]);
                } else {
                    $mappedNames[] = $name;
                    \Illuminate\Support\Facades\Log::info('SenoClock biomarker mapping detail', [
                        'original_name' => $name,
                        'normalized_name' => strtolower($name),
                        'senoclock_key' => $key,
                        'mapped' => true,
                        'reason' => 'Mapped successfully',
                    ]);
                }
            }

            $extractedCount = count($extractedList);
            $mappedCount = count($senoclockMarkers ?? []);
            $excludedCount = count($excludedMarkers);

            \Illuminate\Support\Facades\Log::info('Final biomarker mapping summary', [
                'openai_extracted_count' => $extractedCount,
                'supported_marker_count' => $mappedCount,
                'final_senoclock_marker_count' => $mappedCount,
                'final_marker_names' => array_keys($senoclockMarkers ?? []),
            ]);

            \Illuminate\Support\Facades\Log::info('SenoClock excluded markers', [
                'excluded_markers' => $excludedMarkers,
            ]);

            \Illuminate\Support\Facades\Log::info('SenoClock mapping validation', [
                'openai_extracted_count' => $extractedCount,
                'mapped_count' => $mappedCount,
                'excluded_count' => $excludedCount,
                'excluded_markers' => $excludedMarkers,
                'mapped_markers' => array_keys($senoclockMarkers ?? []),
            ]);

            \Illuminate\Support\Facades\Log::info('SenoClock biomarker mapping audit', [
                'extracted_count' => $extractedCount,
                'mapped_count' => $mappedCount,
                'mapped_markers' => array_keys($senoclockMarkers ?? []),
                'excluded_count' => $excludedCount,
                'excluded_markers' => $excludedMarkers,
                'unmapped_markers' => $excludedMarkers,
            ]);

            if ($extractedCount !== ($mappedCount + $excludedCount)) {
                $missing = array_diff(
                    array_map(fn($b) => is_array($b) ? ($b['name'] ?? '') : (string) $b, $extractedList),
                    array_merge($mappedNames, $excludedMarkers)
                );
                
                // Note: mappedCount is the unique SenoClock keys. Multiple extracted biomarkers
                // might map to the SAME SenoClock key (e.g. "HDL" and "HDL Cholesterol" -> "HDL").
                // If there are duplicate extractions mapping to the same key, the strict addition might fail.
                // We'll log it for visibility but it might not be a genuine "loss".
                \Illuminate\Support\Facades\Log::error('SenoClock mapping count discrepancy', [
                    'extracted' => $extractedCount,
                    'mapped' => $mappedCount,
                    'excluded' => $excludedCount,
                    'unaccounted_names' => $missing,
                    'note' => 'Discrepancy may occur if multiple extracted names resolve to the same SenoClock key.',
                ]);
            }
            
            $markerCount = count($senoclockMarkers);
            $analysis['marker_count'] = $markerCount;
            $analysis['markers'] = $senoclockMarkers;
            $analysis['status'] = true;
            
            // Save markers to database
            $labReport->markers = $senoclockMarkers;
            $labReport->save();

            // Always generate after PDF upload when markers exist; never dispatch with zero markers.
            if ($markerCount > 0) {
                $labReport->senoclock_status = 'processing';
                $labReport->save();
                ProcessSenoclockIntegration::dispatch($labReport->id);
                \Illuminate\Support\Facades\Log::info('SenoClock background job dispatched', [
                    'lab_report_id' => $labReport->id ?? null,
                    'marker_count' => $markerCount,
                ]);
            } else {
                \Illuminate\Support\Facades\Log::warning('SenoClock job not dispatched because no biomarkers were extracted', [
                    'lab_report_id' => $labReport->id ?? null,
                    'extracted_count' => $extractedCount,
                    'mapped_count' => $markerCount,
                ]);
            }

            return response()->json([
                'status' => true,
                'message' => 'AI analysis completed successfully',
                'currency' => $currency,
                'data' => $analysis,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ], 500);
        }
    }

    private function getSenoclockToken(&$errorResponse = null)
    {
        $baseUrl = config('services.senoclock.base_url', 'https://api-euc1.senoclock.ai');
        $email = config('services.senoclock.email');
        $password = config('services.senoclock.password');

        $response = \Illuminate\Support\Facades\Http::withoutVerifying()->post("{$baseUrl}/rest-auth/login/", [
            'email' => $email,
            'password' => $password
        ]);

        if ($response->successful()) {
            $key = $response->json('key') ?? $response->json('token') ?? $response->json('access_token');
            if ($key) return $key;
            $errorResponse = 'Success but no token found: ' . $response->body();
            return null;
        }

        // Try username if email fails
        $response2 = \Illuminate\Support\Facades\Http::withoutVerifying()->post("{$baseUrl}/rest-auth/login/", [
            'username' => $email,
            'password' => $password
        ]);

        if ($response2->successful()) {
            $key = $response2->json('key') ?? $response2->json('token') ?? $response2->json('access_token');
            if ($key) return $key;
            $errorResponse = 'Success but no token found in response2: ' . $response2->body();
            return null;
        }
        
        $errorResponse = $response->body() . ' | ' . $response2->body();
        return null;
    }

    public function generateSenoclockReport(Request $request, LabReportBiomarkerAnalyzerService $analyzer)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'nullable|integer',
            'senoclock_id' => 'required_without_all:lab_report_id,user_id|string',
            'lab_report_id' => 'required_without_all:senoclock_id,user_id|integer|exists:lab_reports,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $labReport = null;
        $senoclockId = $request->input('senoclock_id');

        if ($request->filled('lab_report_id')) {
            $labReportQuery = LabReport::where('id', $request->lab_report_id);
            if ($request->filled('user_id')) {
                $labReportQuery->where('user_id', $request->user_id);
            }
            $labReport = $labReportQuery->first();
        } elseif ($request->filled('senoclock_id')) {
            $labReport = LabReport::where('senoclock_id', $senoclockId)->first();
        } elseif ($request->filled('user_id')) {
            $completedReports = LabReport::where('user_id', $request->user_id)
                ->whereNotNull('senoclock_pdf_path')
                ->where('senoclock_status', 'completed')
                ->orderBy('id', 'desc')
                ->get();

            if ($completedReports->isNotEmpty()) {
                $data = $completedReports->map(function ($report) {
                    return [
                        'lab_report_id' => $report->id,
                        'senoclock_id' => $report->senoclock_id,
                        'downloads' => '/' . ltrim($report->senoclock_pdf_path, '/'),
                        'download_url' => url("api/v1/majorOrganTests/downloadSenoclockReport/{$report->senoclock_id}"),
                    ];
                });

                return response()->json([
                    'status' => true,
                    'message' => 'SenoClock reports retrieved successfully.',
                    'data' => $data,
                ]);
            }

            $labReport = LabReport::where('user_id', $request->user_id)
                ->orderBy('id', 'desc')
                ->first();
        }

        if (!$labReport) {
            return response()->json([
                'status' => false,
                'message' => 'Lab report not found.',
            ], 404);
        }

        $markerCount = is_array($labReport->markers) ? count($labReport->markers) : 0;
        if ($markerCount === 0) {
            $labReport->ensureDownloadablePdf();
            if (!empty($labReport->senoclock_pdf_path) && file_exists(public_path($labReport->senoclock_pdf_path))) {
                return $this->senoclockDownloadResponse($labReport);
            }
        }

        if (!empty($labReport->senoclock_pdf_path) && file_exists(public_path($labReport->senoclock_pdf_path))) {
            return $this->senoclockDownloadResponse($labReport);
        }

        // Generate the report even if biomarkers are missing from the uploaded PDF.
        // $markerCount = is_array($labReport->markers) ? count($labReport->markers) : 0;
        // if ($markerCount < 16) {
        //     return response()->json([
        //         'status' => false,
        //         'message' => 'SenoClock report cannot be generated yet. At least 16 biomarkers are required.',
        //         'lab_report_id' => $labReport->id,
        //         'marker_count' => $markerCount,
        //     ], 200);
        // }

        if (empty($labReport->senoclock_id)) {
            $isStuckProcessing = $labReport->senoclock_status === 'processing'
                && $labReport->updated_at
                && $labReport->updated_at->lt(now()->subMinutes(5));

            if ($markerCount === 0) {
                \Illuminate\Support\Facades\Log::warning('SenoClock job not dispatched from generateSenoclockReport because no biomarkers were extracted', [
                    'lab_report_id' => $labReport->id,
                    'mapped_count' => $markerCount,
                ]);
            } elseif ($labReport->senoclock_status !== 'processing' || $isStuckProcessing) {
                $labReport->senoclock_status = 'processing';
                $labReport->save();
                ProcessSenoclockIntegration::dispatch($labReport->id);
                \Illuminate\Support\Facades\Log::info('SenoClock background job dispatched from generateSenoclockReport', [
                    'lab_report_id' => $labReport->id,
                    'marker_count' => $markerCount,
                    'stuck_retry' => $isStuckProcessing,
                ]);
            }
        }

        $senoclockId = $labReport->senoclock_id ?: $senoclockId;

        if (empty($senoclockId)) {
            $labReport->ensureDownloadablePdf();
            return $this->senoclockDownloadResponse($labReport);
        }

        try {
            set_time_limit(180);

            $labReport->refresh();
            if (!empty($labReport->senoclock_pdf_path) && file_exists(public_path($labReport->senoclock_pdf_path))) {
                return $this->senoclockDownloadResponse($labReport);
            }

            $senoclockService = app(\App\Services\SenoclockService::class);

            if (!$senoclockService->authenticate()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Failed to authenticate with SenoClock API.',
                    'lab_report_id' => $labReport->id,
                ], 500);
            }

            $destinationDir = public_path('uploads/senoclock_report_generated');
            $downloadResult = $senoclockService->downloadPdfWithRetry($senoclockId, $destinationDir, null, 8, 5);

            if (!$downloadResult['success']) {
                $labReport->refresh();
                $labReport->ensureDownloadablePdf();
                return $this->senoclockDownloadResponse($labReport);
            }

            $labReport->senoclock_pdf_path = 'uploads/senoclock_report_generated/' . $downloadResult['path'];
            $labReport->senoclock_status = 'completed';
            $labReport->senoclock_generated_at = now();
            $labReport->save();

            return $this->senoclockDownloadResponse($labReport);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
                'lab_report_id' => $labReport->id ?? null,
            ], 500);
        }
    }

    private function senoclockDownloadResponse(LabReport $labReport)
    {
        $senoclockId = $labReport->senoclock_id;
        $downloadKey = $senoclockId ?: $labReport->id;
        $pdfPath = $labReport->senoclock_pdf_path;
        $downloads = !empty($pdfPath) ? '/' . ltrim($pdfPath, '/') : '';
        $downloadUrl = url("api/v1/majorOrganTests/downloadSenoclockReport/{$downloadKey}");

        return response()->json([
            'status' => true,
            'message' => 'SenoClock report generated successfully.',
            'data' => [
                'lab_report_id' => $labReport->id,
                'senoclock_id' => $senoclockId,
                'downloads' => $downloads,
                'download_url' => $downloadUrl,
            ],
        ]);
    }

    public function downloadSenoclockReport($id)
    {
        $report = LabReport::where('senoclock_id', $id)->first();
        if (!$report && ctype_digit((string) $id)) {
            $report = LabReport::find($id);
        }

        if ($report) {
            $report->ensureDownloadablePdf();
        }

        if (!$report || empty($report->senoclock_pdf_path) || !file_exists(public_path($report->senoclock_pdf_path))) {
            return response()->json([
                'status' => false,
                'message' => 'Report not found.',
            ], 404);
        }

        return response()->download(public_path($report->senoclock_pdf_path));
    }

    public function package(Request $request)
    {
        $currency = CurrencyHelper::getUserCurrency();
        $package = MajorOrganPackage::where('status', 1)->first();

        if (!$package) {
            return response()->json([
                'status' => true,
                'message' => 'Package not found',
                'data' => null,
            ]);
        }

        $tests = MajorOrganTest::where('status', 1)->get();
        $totalBiomarkers = 0;
        foreach ($tests as $item) {
            $bms = is_array($item->biomarkers) ? $item->biomarkers : [];
            $totalBiomarkers += count($bms);
        }

        return response()->json([
            'status' => true,
            'message' => 'Package fetched successfully',
            'currency' => $currency,
            'data' => [
                'id' => $package->id,
                'title' => $package->title,
                'badge' => $package->badge,
                'description' => $package->description,
                'currency' => $currency,
                'price' => number_format((float) CurrencyHelper::convert($package->price, $currency), 2, '.', ''),
                'image' => !empty($package->image) ? GlobalFunction::createMediaUrl($package->image) : null,
                'status' => (int) $package->status,
                'organ_health_check_count' => $tests->count(),
                'total_biomarkers' => $totalBiomarkers,
                'summary' => $tests->count() . ' Organ Health Check • ' . $totalBiomarkers . ' Biomarkers',
            ],
        ]);
    }

    public function planDetails(Request $request)
    {
        $currency = CurrencyHelper::getUserCurrency();
        $package = MajorOrganPackage::where('status', 1)->first();

        if (!$package) {
            return response()->json([
                'status' => false,
                'message' => 'Package not found',
                'data' => null,
            ]);
        }

        $tests = MajorOrganTest::where('status', 1)
            ->orderBy('display_order', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $totalBiomarkers = 0;
        $includedHealthChecks = $tests->map(function ($item) use (&$totalBiomarkers) {
            $biomarkers = is_array($item->biomarkers) ? $item->biomarkers : [];
            $biomarkerCount = count($biomarkers);
            $totalBiomarkers += $biomarkerCount;

            return [
                'id' => $item->id,
                'name' => $item->name,
                'icon' => !empty($item->icon) ? ltrim($item->icon, '/') : null,
                'biomarker_count' => $biomarkerCount,
                'biomarkers' => $biomarkers,
            ];
        })->values();

        return response()->json([
            'status' => true,
            'message' => 'Plan details fetched successfully',

            'data' => [
                'id' => $package->id,
                'title' => $package->title,
                'badge' => $package->badge,
                'description' => $package->description,
                'currency' => $currency,
                'price' => number_format((float) CurrencyHelper::convert($package->price, $currency), 2, '.', ''),
                'image' => !empty($package->image)
                    ? ltrim($package->image, '/')
                    : null,
                'organ_health_check_count' => $includedHealthChecks->count(),
                'total_biomarkers' => $totalBiomarkers,
                'summary' => $includedHealthChecks->count() . ' Organ Health Check • ' . $totalBiomarkers . ' Biomarkers',
                'included_health_checks' => $includedHealthChecks,
            ],
        ]);
    }

    /**
     * Save package and/or individual organ test selection against user_id.
     *
     * Body:
     * - user_id (required)
     * - select_package: 1/true to select full package (ignores organ_test_ids)
     * - package_id: optional (defaults to active package)
     * - organ_test_ids: [1,2,3] when selecting individual organ checks
     */
    public function saveSelection(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer|exists:users,id',
            'select_package' => 'nullable|boolean',
            'package_id' => 'nullable|integer|exists:major_organ_package,id',
            'organ_test_ids' => 'nullable|array',
            'organ_test_ids.*' => 'integer|exists:major_organ_tests,id',
            'plan_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $selectPackage = filter_var($request->input('select_package', false), FILTER_VALIDATE_BOOLEAN);
        $organTestIds = array_values(array_unique(array_map('intval', $request->input('organ_test_ids', []) ?: [])));

        if (!$selectPackage && empty($organTestIds)) {
            return response()->json([
                'status' => false,
                'message' => 'Select the package or at least one organ health check.',
            ], 422);
        }

        if ($selectPackage) {
            $package = null;
        if ($request->filled('package_id')) {
            $package = MajorOrganPackage::where('status', 1)->find($request->package_id);
        } else {
            $package = MajorOrganPackage::where('status', 1)->first();
        }

            if (!$package) {
            return response()->json([
                'status' => false,
                'message' => 'Package not found.',
            ], 404);
        }

            $tests = MajorOrganTest::where('status', 1)
                ->orderBy('display_order', 'asc')
                ->orderBy('id', 'asc')
                ->get();
            
            $payload = $this->buildSelectionPayload($request->user_id, 'package', $package, $tests, $request->plan_id);
            
            \App\Models\MajorOrganUserSelection::updateOrCreate(
                ['user_id' => (int) $request->user_id, 'status' => 1, 'selection_type' => 'package'],
                $payload
            );
        } else {
            $tests = MajorOrganTest::where('status', 1)->whereIn('id', $organTestIds)->get();
            if ($tests->isNotEmpty()) {
                $payload = $this->buildSelectionPayload($request->user_id, 'individual', null, $tests, $request->plan_id);
                \App\Models\MajorOrganUserSelection::updateOrCreate(
                    ['user_id' => (int) $request->user_id, 'status' => 1, 'selection_type' => 'individual'],
                    $payload
                );
            }
        }

        // Fetch all active selections for this user to return as an array
        $allSelections = \App\Models\MajorOrganUserSelection::where('user_id', (int) $request->user_id)
            ->where('status', 1)
            ->orderBy('id', 'desc')
            ->get();

        $result = $this->formatGroupedSelections($allSelections, CurrencyHelper::getUserCurrency());

        return response()->json([
            'status' => true,
            'message' => 'Selection saved successfully',
            'currency' => CurrencyHelper::getUserCurrency(),
            'data' => $result['data'],
        ]);
    }

    /**
     * Get saved package/organ selection for a user.
     */
    public function getSelection(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer|exists:users,id',
            'selection_type' => 'nullable|string|in:package,individual',
            'package_id' => 'nullable|string',
            'id' => 'nullable|string',
            'organ_test_id' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $currency = CurrencyHelper::getUserCurrency();

        // If selection_type is provided, fetch from master database (not user selections)
        if ($request->filled('selection_type')) {
            if ($request->selection_type === 'package') {
                $query = MajorOrganPackage::where('status', 1);
                
                if ($request->filled('id')) {
                    $ids = array_filter(array_map('intval', explode(',', $request->id)));
                    if (!empty($ids)) {
                        $query->whereIn('id', $ids);
                    }
                }

                $packages = $query->get();
                $allTests = MajorOrganTest::where('status', 1)->orderBy('display_order', 'asc')->orderBy('id', 'asc')->get();
                
                $totalAmountSum = 0;
                $data = $packages->map(function ($package) use ($request, $allTests, &$totalAmountSum) {
                    $payload = $this->buildSelectionPayload($request->user_id, 'package', $package, $allTests, $request->plan_id);
                    $mockModel = new MajorOrganUserSelection($payload);
                    $mockModel->id = $package->id;
                    $totalAmountSum += (float) $mockModel->total_amount;
                    return $this->formatSelection($mockModel);
                });

                return response()->json([
                    'status' => true,
                    'message' => 'Packages fetched successfully',
                    'currency' => $currency,
                    'total_amount' => number_format((float) CurrencyHelper::convert($totalAmountSum, $currency), 2, '.', ''),
                    'data' => $data,
                ]);
            } elseif ($request->selection_type === 'individual') {
                $query = MajorOrganTest::where('status', 1);
                
                if ($request->filled('id')) {
                    $ids = array_filter(array_map('intval', explode(',', $request->id)));
                    if (!empty($ids)) {
                        $query->whereIn('id', $ids);
                    }
                }

                $tests = $query->orderBy('display_order', 'asc')->orderBy('id', 'asc')->get();
                
                $totalAmountSum = 0;
                $data = $tests->map(function ($test) use ($request, &$totalAmountSum) {
                    $collection = collect([$test]);
                    $payload = $this->buildSelectionPayload($request->user_id, 'individual', null, $collection, $request->plan_id);
                    $mockModel = new MajorOrganUserSelection($payload);
                    $mockModel->id = $test->id;
                    $totalAmountSum += (float) $mockModel->total_amount;
                    return $this->formatSelection($mockModel);
                });

                return response()->json([
                    'status' => true,
                    'message' => 'Individual tests fetched successfully',
                    'currency' => $currency,
                    'total_amount' => number_format((float) CurrencyHelper::convert($totalAmountSum, $currency), 2, '.', ''),
                    'data' => $data,
                ]);
            }
        }

        // Fetch user's successful paid selections by default, or filter by requested status/payment_status
        $query = MajorOrganUserSelection::where('user_id', (int) $request->user_id);

        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        } else if ($request->filled('status')) {
            $statuses = explode(',', $request->status);
            $query->whereIn('status', $statuses);
        } else {
            // Default: display only successful paid plan selections
            $query->where('payment_status', 1);
        }

        $selections = $query->orderBy('created_at', 'desc')->get();

        if ($selections->isEmpty()) {
            return response()->json([
                'status' => true,
                'message' => 'No selection found',
                'currency' => $currency,
                'total_amount' => '0.00',
                'data' => [],
            ]);
        }

        $result = $this->formatGroupedSelections($selections, $currency);

        return response()->json([
            'status' => true,
            'message' => 'Selections fetched successfully',
            'currency' => $currency,
            'total_amount' => number_format((float) CurrencyHelper::convert($result['total_amount'], $currency), 2, '.', ''),
            'data' => $result['data'],
        ]);
    }

    protected function formatGroupedSelections($selections, string $currency): array
    {
        $grouped = $selections->groupBy(function ($item) {
            if (!empty($item->order_id)) {
                return 'order_' . $item->order_id;
            }
            $timeKey = $item->created_at ? $item->created_at->format('Y-m-d H:i:s') : 'no_time';
            return 'group_' . $timeKey . '_' . $item->selection_type;
        });

        $totalAmountSum = 0;
        $data = [];

        foreach ($grouped as $groupKey => $groupItems) {
            if ($groupItems->count() === 1) {
                $sel = $groupItems->first();
                $totalAmountSum += (float) $sel->total_amount;
                $data[] = $this->formatSelection($sel);
            } else {
                $first = $groupItems->first();
                $combinedTotalAmount = 0;
                $mergedOrganTests = [];
                $mergedBiomarkers = [];
                $combinedHealthCheckCount = 0;
                $testNames = [];

                foreach ($groupItems as $sel) {
                    $combinedTotalAmount += (float) $sel->total_amount;
                    $formatted = $this->formatSelection($sel);

                    if (!empty($formatted['selected_organ_tests'])) {
                        foreach ($formatted['selected_organ_tests'] as $t) {
                            $mergedOrganTests[] = $t;
                            if (!empty($t['name'])) {
                                $testNames[] = $t['name'];
                            }
                        }
                    }

                    if (!empty($formatted['selected_biomarkers'])) {
                        foreach ($formatted['selected_biomarkers'] as $bm) {
                            $mergedBiomarkers[] = $bm;
                        }
                    }

                    $combinedHealthCheckCount += (int) ($formatted['organ_health_check_count'] ?? 1);
                    if (isset($formatted['total_biomarkers']) && (int) $formatted['total_biomarkers'] > 0) {
                        $combinedBiomarkersCount = max($combinedBiomarkersCount ?? 0, (int) $formatted['total_biomarkers']);
                    }
                }

                $mergedBiomarkers = array_values(array_unique($mergedBiomarkers));
                $totalBiomarkers = max(count($mergedBiomarkers), $combinedBiomarkersCount ?? 0);
                
                if (count($mergedOrganTests) > 0 && $combinedHealthCheckCount < count($mergedOrganTests)) {
                    $combinedHealthCheckCount = count($mergedOrganTests);
                }

                $totalAmountSum += $combinedTotalAmount;
                $convertedPrice = number_format((float) CurrencyHelper::convert($combinedTotalAmount, $currency), 2, '.', '');

                $testNames = array_values(array_unique($testNames));
                if (!empty($testNames)) {
                    $summary = implode(' • ', $testNames);
                } else {
                    $summary = $combinedHealthCheckCount . ' Organ Health Check • ' . $totalBiomarkers . ' Biomarkers';
                }

                $combinedItem = [
                    'id' => $first->id,
                    'user_id' => (int) $first->user_id,
                    'order_id' => $first->order_id,
                    'selection_type' => $first->selection_type,
                    'organ_health_check_count' => (int) $combinedHealthCheckCount,
                    'total_biomarkers' => (int) $totalBiomarkers,
                    'summary' => $summary,
                    'currency' => $currency,
                    'price' => $convertedPrice,
                    'status' => (int) $first->status,
                    'selected_organ_tests' => $mergedOrganTests,
                    'selected_biomarkers' => $mergedBiomarkers,
                    'created_at' => $first->created_at ? $first->created_at->format('Y-m-d H:i:s') : null,
                ];

                if ($first->selection_type === 'package' && $first->package_id) {
                    $combinedItem['package'] = [
                        'id' => $first->package_id,
                        'title' => $first->package_title,
                        'badge' => $first->package_badge,
                        'currency' => $currency,
                        'price' => $convertedPrice,
                        'selected' => true,
                        'organ_health_check_count' => (int) $combinedHealthCheckCount,
                        'total_biomarkers' => (int) $totalBiomarkers,
                        'summary' => $summary,
                    ];
                }

                $data[] = $combinedItem;
            }
        }

        return [
            'total_amount' => $totalAmountSum,
            'data' => $data,
        ];
    }

    protected function formatSelection(MajorOrganUserSelection $selection): array
    {
        $currency = CurrencyHelper::getUserCurrency();

        $organHealthCheckCount = (int) $selection->organ_health_check_count;
        $totalBiomarkers = (int) $selection->total_biomarkers;

        $isPackageSelection = ($selection->selection_type === 'package')
            || !empty($selection->package_id)
            || !empty($selection->package_title)
            || ((float)$selection->total_amount == 599.00 && $selection->selection_type !== 'individual');

        if ($isPackageSelection) {
            $allTests = \App\Models\MajorOrganTest::where('status', 1)->get();
            $totalPkgBiomarkersCount = 0;
            foreach ($allTests as $t) {
                $bms = is_array($t->biomarkers) ? $t->biomarkers : [];
                $totalPkgBiomarkersCount += count($bms);
            }

            $organHealthCheckCount = $allTests->count() > 0 ? $allTests->count() : 10;
            $totalBiomarkers = $totalPkgBiomarkersCount > 0 ? $totalPkgBiomarkersCount : 41;
        }

        $data = [
            'id' => $selection->id,
            'user_id' => (int) $selection->user_id,
            'selection_type' => $selection->selection_type,
            'organ_health_check_count' => $organHealthCheckCount,
            'total_biomarkers' => $totalBiomarkers,
            'summary' => $organHealthCheckCount . ' Organ Health Check • ' . $totalBiomarkers . ' Biomarkers',
            'currency' => $currency,
            'price' => number_format((float) CurrencyHelper::convert($selection->total_amount, $currency), 2, '.', ''),
            'status' => (int) $selection->status,
            'created_at' => $selection->created_at ? $selection->created_at->format('Y-m-d H:i:s') : null,
        ];

        $selectedOrganTests = $selection->selected_organ_tests ?? [];
        if (!empty($selectedOrganTests) && is_array($selectedOrganTests)) {
            $testIds = array_column($selectedOrganTests, 'id');
            $currentTests = \App\Models\MajorOrganTest::whereIn('id', $testIds)->get()->keyBy('id');
            foreach ($selectedOrganTests as &$test) {
                if (isset($currentTests[$test['id']])) {
                    $test['icon'] = !empty($currentTests[$test['id']]->icon) ? ltrim($currentTests[$test['id']]->icon, '/') : null;
                }
            }
        }

        if ($isPackageSelection) {
            $data['package'] = [
                'id' => $selection->package_id ?? 1,
                'title' => $selection->package_title ?? 'Comprehensive Mulk Longitivity Panel 1',
                'badge' => $selection->package_badge ?? 'High Recommendation',
                'currency' => $currency,
                'price' => number_format((float) CurrencyHelper::convert($selection->package_price ?? $selection->total_amount, $currency), 2, '.', ''),
                'selected' => true,
                'organ_health_check_count' => $organHealthCheckCount,
                'total_biomarkers' => $totalBiomarkers,
                'summary' => $organHealthCheckCount . ' Organ Health Check • ' . $totalBiomarkers . ' Biomarkers',
            ];
            $data['selected_organ_tests'] = $selectedOrganTests;
            $data['selected_biomarkers'] = $selection->selected_biomarkers ?? [];
        } else if ($selection->selection_type === 'longevity') {
            if (!empty($selectedOrganTests)) {
                $data['selected_organ_tests'] = $selectedOrganTests;
                $data['selected_biomarkers'] = $selection->selected_biomarkers ?? [];
            } else if ($selection->plan_id) {
                $longevityPlan = \App\Models\LongevityPlan::find($selection->plan_id);
                if ($longevityPlan) {
                    $data['summary'] = $longevityPlan->title;
                    $data['selected_organ_tests'] = [
                        [
                            'id' => $longevityPlan->id,
                            'name' => $longevityPlan->title,
                            'icon' => !empty($longevityPlan->image) ? ltrim($longevityPlan->image, '/') : null,
                            'price' => number_format((float) CurrencyHelper::convert($longevityPlan->price, $currency), 2, '.', ''),
                            'biomarker_count' => 0,
                            'biomarkers' => []
                        ]
                    ];
                    $data['selected_biomarkers'] = [];
                } else {
                    $data['summary'] = 'Longevity Plan (Missing ID: ' . $selection->plan_id . ')';
                    $data['selected_organ_tests'] = [
                        [
                            'id' => $selection->plan_id,
                            'name' => 'Longevity Plan ' . $selection->plan_id,
                            'icon' => null,
                            'price' => number_format((float) CurrencyHelper::convert($selection->total_amount, $currency), 2, '.', ''),
                            'biomarker_count' => 0,
                            'biomarkers' => []
                        ]
                    ];
                    $data['selected_biomarkers'] = [];
                }
            } else {
                $data['selected_organ_tests'] = [];
                $data['selected_biomarkers'] = [];
            }
        } else {
            $data['selected_organ_tests'] = $selectedOrganTests;
            $data['selected_biomarkers'] = $selection->selected_biomarkers ?? [];
        }

        return $data;
    }

    private function buildSelectionPayload($userId, $selectionType, $package, $tests, $planId = null)
    {
        $allBiomarkers = [];
        $selectedOrganTests = $tests->map(function ($item) use (&$allBiomarkers) {
            $biomarkers = is_array($item->biomarkers) ? $item->biomarkers : [];
            foreach ($biomarkers as $biomarker) {
                $allBiomarkers[] = $biomarker;
            }

            return [
                'id' => $item->id,
                'name' => $item->name,
                'icon' => !empty($item->icon) ? ltrim($item->icon, '/') : null,
                'price' => number_format((float) $item->price, 2, '.', ''),
                'biomarker_count' => count($biomarkers),
                'biomarkers' => $biomarkers,
            ];
        })->values()->toArray();

        $allBiomarkers = array_values(array_unique($allBiomarkers));
        $organCount = count($selectedOrganTests);
        $biomarkerCount = count($allBiomarkers);

        $totalAmount = 0;
        if ($selectionType === 'package' && $package) {
            $totalAmount = (float) $package->price;
        } else {
            $totalAmount = (float) $tests->sum(function ($item) {
                return (float) $item->price;
            });
        }

        return [
            'user_id' => (int) $userId,
            'plan_id' => $planId,
            'selection_type' => $selectionType,
            'package_id' => $selectionType === 'individual' ? ($tests->first()->id ?? null) : ($package ? $package->id : null),
            'package_title' => $package ? $package->title : null,
            'package_badge' => $package ? $package->badge : null,
            'package_price' => $package ? (float) $package->price : null,
            'organ_health_check_count' => $organCount,
            'total_biomarkers' => $biomarkerCount,
            'selected_organ_tests' => $selectedOrganTests,
            'selected_biomarkers' => $allBiomarkers,
            'total_amount' => $totalAmount,
            'status' => 1,
        ];
    }

    public function analyzeNative(Request $request, \App\Services\LabReportBiomarkerAnalyzerService $service)
    {
        // Support both 'document' (legacy) and 'files' (new) keys for flexibility
        $request->validate([
            'document' => 'sometimes|array',
            'document.*' => 'file|mimes:pdf',
            'files' => 'sometimes|array',
            'files.*' => 'file|mimes:pdf',
            'prompt' => 'required|string',
        ]);

        try {
            $filesToUpload = $request->file('document') ?: $request->file('files');
            if (empty($filesToUpload)) {
                throw new \Exception('No PDF documents were provided.');
            }

            $filesList = is_array($filesToUpload) ? $filesToUpload : [$filesToUpload];

            $organTests = \App\Models\MajorOrganTest::where('status', 1)
                ->orderBy('display_order', 'asc')
                ->orderBy('id', 'asc')
                ->get();

            $analysis = $service->analyzeMultiple(
                $filesList,
                null,
                $organTests
            );

            return response()->json([
                'success' => true,
                'analysis' => $analysis,
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('analyzeNative error', ['message' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'details' => 'OpenAI upload or analysis error'
            ], 500);
        }
    }
}
