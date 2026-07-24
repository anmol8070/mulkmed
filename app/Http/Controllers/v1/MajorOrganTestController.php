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
                    'price' => number_format((float) CurrencyHelper::convert($item->price, $currency), 2, '.', ''),
                    'biomarker_count' => count($biomarkers),
                    'biomarkers' => $biomarkers,
                ];
            });

        return response()->json([
            'status' => true,
            'message' => 'Major organ tests fetched successfully',
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
            'data' => [
                'id' => $package->id,
                'title' => $package->title,
                'badge' => $package->badge,
                'description' => $package->description,
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

        $package = null;
        if ($request->filled('package_id')) {
            $package = MajorOrganPackage::where('status', 1)->find($request->package_id);
        } else {
            $package = MajorOrganPackage::where('status', 1)->first();
        }

        if ($selectPackage && !$package) {
            return response()->json([
                'status' => false,
                'message' => 'Package not found.',
            ], 404);
        }

        if ($selectPackage) {
            $tests = MajorOrganTest::where('status', 1)
                ->orderBy('display_order', 'asc')
                ->orderBy('id', 'asc')
                ->get();
        } else {
            $tests = MajorOrganTest::where('status', 1)
                ->whereIn('id', $organTestIds)
                ->orderBy('display_order', 'asc')
                ->orderBy('id', 'asc')
                ->get();
        }

        if ($tests->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No organ tests found for selection.',
            ], 404);
        }

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

        if ($selectPackage) {
            $totalAmount = (float) $package->price;
            $selectionType = 'package';
        } else {
            $totalAmount = (float) $tests->sum(function ($item) {
                return (float) $item->price;
            });
            $selectionType = 'individual';
        }

        $payload = [
            'user_id' => (int) $request->user_id,
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

        $selection = MajorOrganUserSelection::updateOrCreate(
            ['user_id' => (int) $request->user_id, 'status' => 1],
            $payload
        );

        return response()->json([
            'status' => true,
            'message' => 'Selection saved successfully',
            'currency' => CurrencyHelper::getUserCurrency(),
            'data' => $this->formatSelection($selection),
        ]);
    }

    /**
     * Get saved package/organ selection for a user.
     */
    public function getSelection(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $selection = MajorOrganUserSelection::where('user_id', (int) $request->user_id)
            ->where('status', 1)
            ->orderBy('id', 'desc')
            ->first();

        if (!$selection) {
            return response()->json([
                'status' => true,
                'message' => 'No selection found',
                'data' => null,
            ]);
        }

        return response()->json([
            'status' => true,
            'message' => 'Selection fetched successfully',
            'currency' => CurrencyHelper::getUserCurrency(),
            'data' => $this->formatSelection($selection),
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
            'total_amount' => number_format((float) CurrencyHelper::convert($selection->total_amount, $currency), 2, '.', ''),
            'status' => (int) $selection->status,
        ];

        if ($selection->selection_type === 'package' && $selection->package_id) {
            // Package selected: show package + all included biomarkers/organ tests.
            $data['package'] = [
                'id' => $selection->package_id,
                'title' => $selection->package_title,
                'badge' => $selection->package_badge,
                'price' => number_format((float) CurrencyHelper::convert($selection->package_price, $currency), 2, '.', ''),
                'selected' => true,
                'organ_health_check_count' => (int) $selection->organ_health_check_count,
                'total_biomarkers' => (int) $selection->total_biomarkers,
                'summary' => $selection->organ_health_check_count . ' Organ Health Check • ' . $selection->total_biomarkers . ' Biomarkers',
            ];
            $data['selected_organ_tests'] = $selection->selected_organ_tests ?? [];
            $data['selected_biomarkers'] = $selection->selected_biomarkers ?? [];
        } else {
            // Individual selected: show organ tests, hide package.
            $data['selected_organ_tests'] = $selection->selected_organ_tests ?? [];
            $data['selected_biomarkers'] = $selection->selected_biomarkers ?? [];
        }

        return $data;
    }
}
