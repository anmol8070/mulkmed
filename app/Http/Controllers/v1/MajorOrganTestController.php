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
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer|exists:users,id',
            'document' => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp|max:10240',
            'ocr_text' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        if (!$request->hasFile('document') && trim((string) $request->input('ocr_text')) === '') {
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
        $fileType = null;
        $file = $request->file('document');

        try {
            if ($file) {
                $documentPath = GlobalFunction::saveFileAndGivePath($file);
                $fileType = strtolower($file->getClientOriginalExtension() ?: '');
            }

            $analysis = $analyzer->analyze(
                $file,
                $request->input('ocr_text'),
                $organTests
            );

            if ($documentPath) {
                $analysis['document_path'] = ltrim($documentPath, '/');
            }

            $labReport = LabReport::create([
                'user_id' => (int) $request->user_id,
                'document_path' => $documentPath ? ltrim($documentPath, '/') : null,
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

            \App\Jobs\ProcessSenoclockIntegration::dispatch($labReport->id)->afterResponse();

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
            'senoclock_id' => 'required_without:lab_report_id|string',
            'lab_report_id' => 'required_without:senoclock_id|integer|exists:lab_reports,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $senoclockId = $request->senoclock_id;

        if ($request->has('lab_report_id') && !$senoclockId) {
            $labReport = \App\Models\LabReport::find($request->lab_report_id);
            if (!$labReport || empty($labReport->senoclock_id)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Senoclock report is still generating in the background or not available for this lab report yet.',
                ], 404);
            }
            $senoclockId = $labReport->senoclock_id;
        }

        try {
            $authError = null;
            $token = $this->getSenoclockToken($authError);
            if (!$token) {
                return response()->json([
                    'status' => false,
                    'message' => 'Failed to authenticate with SenoClock API.',
                    'error' => $authError,
                ], 500);
            }

            $baseUrl = config('services.senoclock.base_url', 'https://api-euc1.senoclock.ai');

            // Download PDF and save locally
            $downloadUrl = "{$baseUrl}/dl-api/report/download/?pdf_report=true&id=" . $senoclockId;
            $pdfResponse = \Illuminate\Support\Facades\Http::withoutVerifying()->withToken($token)->get($downloadUrl);

            $localUrl = '';
            if ($pdfResponse->successful()) {
                $contentType = $pdfResponse->header('Content-Type');
                if (strpos($contentType, 'application/json') !== false) {
                    return response()->json([
                        'status' => false,
                        'message' => 'SenoClock API returned JSON instead of a PDF. Report might still be processing.',
                        'senoclock_response' => $pdfResponse->json(),
                    ], 500);
                }

                $fileName = "senoclock_{$senoclockId}.pdf";
                
                $uploadDir = public_path('uploads');
                if (!file_exists($uploadDir)) {
                    @mkdir($uploadDir, 0777, true);
                }

                file_put_contents($uploadDir . '/' . $fileName, $pdfResponse->body());
                
                $localUrl = asset('uploads/' . $fileName);
            } else {
                 return response()->json([
                    'status' => false,
                    'message' => 'Failed to download PDF from SenoClock.',
                    'senoclock_error' => $pdfResponse->body()
                ], 500);
            }

            return response()->json([
                'status' => true,
                'message' => 'SenoClock report generated successfully.',
                'data' => [
                    'senoclock_id' => $senoclockId,
                    'download_url' => $localUrl,
                ]
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
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
            
            $payload = $this->buildSelectionPayload($request->user_id, 'package', $package, $tests);
            
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
                        $payload = $this->buildSelectionPayload($request->user_id, 'individual', null, $test);
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
                    $payload = $this->buildSelectionPayload($request->user_id, 'package', $package, $allTests);
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
                    $payload = $this->buildSelectionPayload($request->user_id, 'individual', null, $collection);
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

        // If no selection_type is provided, fetch user's cart selections
        $query = MajorOrganUserSelection::where('user_id', (int) $request->user_id)
            ->where('status', 1);

        $selections = $query->orderBy('id', 'desc')->get();

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
        ];

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
            $data['selected_organ_tests'] = $selection->selected_organ_tests ?? [];
            $data['selected_biomarkers'] = $selection->selected_biomarkers ?? [];
        } else {
            $data['selected_organ_tests'] = $selection->selected_organ_tests ?? [];
            $data['selected_biomarkers'] = $selection->selected_biomarkers ?? [];
        }

        return $data;
    }

    private function buildSelectionPayload($userId, $selectionType, $package, $tests)
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
            'selection_type' => $selectionType,
            'package_id' => $package ? $package->id : null,
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
}
