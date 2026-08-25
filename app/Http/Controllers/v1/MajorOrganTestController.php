<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Models\GlobalFunction;
use App\Models\LabReport;
use App\Models\MajorOrganPackage;
use App\Models\MajorOrganTest;
use App\Models\MajorOrganUserSelection;
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
            
            // Save markers to database
            $labReport->markers = $senoclockMarkers;
            $labReport->save();

            if ($markerCount >= 16) {
                unset($analysis['missing_count']);
                unset($analysis['missing_biomarkers']);
                \App\Jobs\ProcessSenoclockIntegration::dispatch($labReport->id);
                \Illuminate\Support\Facades\Log::info('SenoClock background job dispatched', [
                    'lab_report_id' => $labReport->id ?? null,
                    'marker_count' => $markerCount,
                ]);
            } else {
                \Illuminate\Support\Facades\Log::info('SenoClock background job NOT dispatched', [
                    'reason' => 'marker_count < 16',
                    'marker_count' => $markerCount,
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

        // If ONLY user_id is sent, return all their completed PDFs
        if ($request->has('user_id') && !$request->has('senoclock_id') && !$request->has('lab_report_id')) {
            $reports = \App\Models\LabReport::where('user_id', $request->user_id)
                ->whereNotNull('senoclock_pdf_path')
                ->where('senoclock_status', 'completed')
                ->orderBy('id', 'desc')
                ->get();
                
            $data = $reports->map(function ($report) {
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
                'data' => $data
            ]);
        }

        $senoclockId = $request->senoclock_id;

        if ($request->has('lab_report_id') && !$senoclockId) {
            $labReportQuery = \App\Models\LabReport::where('id', $request->lab_report_id);
            if ($request->has('user_id')) {
                $labReportQuery->where('user_id', $request->user_id);
            }
            $labReport = $labReportQuery->first();
            
            if (!$labReport) {
                return response()->json([
                    'status' => false,
                    'message' => 'Lab report not found.',
                ], 404);
            }
            if ($labReport->senoclock_status === 'failed') {
                return response()->json([
                    'status' => false,
                    'message' => 'SenoClock report generation failed in the background. Please try re-uploading your report.',
                ], 422);
            }
            if (empty($labReport->senoclock_id)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Senoclock report is still generating in the background or not available for this lab report yet.',
                ], 404);
            }
            $senoclockId = $labReport->senoclock_id;

            // If the background job has already downloaded the PDF, return it immediately!
            if (!empty($labReport->senoclock_pdf_path) && file_exists(public_path($labReport->senoclock_pdf_path))) {
                return response()->json([
                    'status' => true,
                    'message' => 'SenoClock report retrieved successfully.',
                    'data' => [
                        'senoclock_id' => $senoclockId,
                        'downloads' => '/' . ltrim($labReport->senoclock_pdf_path, '/'),
                        'download_url' => url("api/v1/majorOrganTests/downloadSenoclockReport/{$senoclockId}"),
                    ]
                ]);
            }
        }

        try {
            set_time_limit(120); // Prevent 30s timeout during retries

            $senoclockService = app(\App\Services\SenoclockService::class);
            
            if (!$senoclockService->authenticate()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Failed to authenticate with SenoClock API.'
                ], 500);
            }

            $destinationDir = public_path('uploads/senoclock_report_generated');
            // Check once (maxRetries=1) to see if it's ready. If not, it's still processing.
            $downloadResult = $senoclockService->downloadPdfWithRetry($senoclockId, $destinationDir, null, 1, 0);
            
            //  if (!$downloadResult['success']) {
            //     return response()->json([
            //         'status' => false,
            //         'message' => 'Lab report uploaded successfully. Senoclock analysis is in progress.',
            //         'lab_report_id' => $labReport->id ?? null
            //     ]);
            // }
            if (!$downloadResult['success']) {
                // Return a completely blank response
                return response('');
            }

            $localUrl = '/' . ltrim('uploads/senoclock_report_generated/' . $downloadResult['path'], '/');

            // Update database if lab report is present
            if ($labReport) {
                $labReport->senoclock_pdf_path = 'uploads/senoclock_report_generated/' . $downloadResult['path'];
                $labReport->senoclock_status = 'completed';
                $labReport->senoclock_generated_at = now();
                $labReport->save();
            }

            return response()->json([
                'status' => true,
                'message' => 'SenoClock report generated successfully.',
                'data' => [
                    'senoclock_id' => $senoclockId,
                    'downloads' => $localUrl,
                    'download_url' => url("api/v1/majorOrganTests/downloadSenoclockReport/{$senoclockId}"),
                ]
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function downloadSenoclockReport($id)
    {
        $report = \App\Models\LabReport::where('senoclock_id', $id)->first();
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
                // 'organ_health_check_count' => $includedHealthChecks->count(),
                // 'total_biomarkers' => $totalBiomarkers,
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
            $existingIndividualSelections = \App\Models\MajorOrganUserSelection::where('user_id', (int) $request->user_id)
                ->where('status', 1)
                ->where('selection_type', 'individual')
                ->get();
            
            $existingTestIds = [];
            foreach ($existingIndividualSelections as $sel) {
                $testArr = $sel->selected_organ_tests ?? [];
                if (!empty($testArr) && isset($testArr[0]['id'])) {
                    $existingTestIds[] = $testArr[0]['id'];
                }
            }

            foreach ($organTestIds as $testId) {
                if (!in_array($testId, $existingTestIds)) {
                    $test = MajorOrganTest::where('status', 1)->where('id', $testId)->get();
                    if ($test->isNotEmpty()) {
                        $payload = $this->buildSelectionPayload($request->user_id, 'individual', null, $test, $request->plan_id);
                        \App\Models\MajorOrganUserSelection::create($payload);
                    }
                }
            }
        }

        // Fetch all active selections for this user to return as an array
        $allSelections = \App\Models\MajorOrganUserSelection::where('user_id', (int) $request->user_id)
            ->where('status', 1)
            ->orderBy('id', 'desc')
            ->get();

        $data = $allSelections->map(function ($sel) {
            return $this->formatSelection($sel);
        });

        return response()->json([
            'status' => true,
            'message' => 'Selection saved successfully',
            'currency' => CurrencyHelper::getUserCurrency(),
            'data' => $data,
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

        // If no selection_type is provided, fetch user's cart (status=1) or purchased (status=2) selections
        $statuses = $request->filled('status') ? explode(',', $request->status) : [1, 2];
        $query = MajorOrganUserSelection::where('user_id', (int) $request->user_id)
            ->whereIn('status', $statuses);
            
        // If no selection_type is provided, fetch user's cart selections

        // $query = MajorOrganUserSelection::where('user_id', (int) $request->user_id)
        //     ->where('status', 2);

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

        $totalAmountSum = 0;
        $data = $selections->map(function ($sel) use (&$totalAmountSum) {
            $totalAmountSum += (float) $sel->total_amount;
            return $this->formatSelection($sel);
        });

        return response()->json([
            'status' => true,
            'message' => 'Selections fetched successfully',
            'currency' => $currency,
            'total_amount' => number_format((float) CurrencyHelper::convert($totalAmountSum, $currency), 2, '.', ''),
            'data' => $data,
        ]);
    }

    protected function formatSelection(MajorOrganUserSelection $selection): array
    {
        $currency = CurrencyHelper::getUserCurrency();
        $data = [
            'id' => $selection->id,
            'user_id' => (int) $selection->user_id,
            'selection_type' => $selection->selection_type,
            'organ_health_check_count' => (int) $selection->organ_health_check_count,
            'total_biomarkers' => (int) $selection->total_biomarkers,
            'summary' => $selection->organ_health_check_count . ' Organ Health Check • ' . $selection->total_biomarkers . ' Biomarkers',
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

        if ($selection->selection_type === 'package' && $selection->package_id) {
            $data['package'] = [
                'id' => $selection->package_id,
                'title' => $selection->package_title,
                'badge' => $selection->package_badge,
                'currency' => $currency,
                'price' => number_format((float) CurrencyHelper::convert($selection->package_price, $currency), 2, '.', ''),
                'selected' => true,
                'organ_health_check_count' => (int) $selection->organ_health_check_count,
                'total_biomarkers' => (int) $selection->total_biomarkers,
                'summary' => $selection->organ_health_check_count . ' Organ Health Check • ' . $selection->total_biomarkers . ' Biomarkers',
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

            $fileMappings = $service->uploadMultiplePdfs($filesToUpload);
            
            $analysis = $service->analyzeUploadedPdfs(
                $fileMappings,
                $request->input('prompt')
            );

            return response()->json([
                'success' => true,
                'files' => $fileMappings,
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
