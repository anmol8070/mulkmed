<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Helpers\Helpers;
use App\Models\AI_Vital;
use App\Models\Constants;
use App\Models\Doctors;
use App\Models\GlobalSettings;
use App\Models\Users;
use App\Models\MajorOrganPackage;
use App\Models\MajorOrganTest;
use App\Models\LongevityPlan;
use App\Services\SenoclockAiService;
use App\Mail\AiVitalReportMail;
use App\Functions\GlobalFunction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use PDF;

class NewShenaiCareController extends Controller
{
    protected SenoclockAiService $senoclockAiService;

    public function __construct(SenoclockAiService $senoclockAiService)
    {
        $this->senoclockAiService = $senoclockAiService;
    }

    /**
     * Get latest Longevity report formatted for UI (Priority Parameters)
     */
    public function getLatestLongevityReport(Request $request): JsonResponse
    {
        $userId = $request->query('user_id', $request->input('user_id'));
        if (empty($userId)) {
            return response()->json([
                'status' => false,
                'message' => 'user_id query parameter is required.',
            ], 400);
        }

        $vital = AI_Vital::where('user_id', $userId)
            ->where('is_longevity', 1)
            ->orderBy('id', 'desc')
            ->first();

        if (!$vital) {
            $vital = AI_Vital::where('user_id', $userId)->orderBy('id', 'desc')->first();
        }

        if (!$vital) {
            return response()->json([
                'status' => false,
                'message' => 'No AI Vital record found for this user.',
            ], 404);
        }

        $reportData = is_string($vital->report) ? json_decode($vital->report, true) : (array) $vital->report;
        $senoclockData = is_string($vital->senoclock_ai_response) ? json_decode($vital->senoclock_ai_response, true) : (array) $vital->senoclock_ai_response;
        $shenAiData = is_string($vital->shen_ai) ? json_decode($vital->shen_ai, true) : (array) $vital->shen_ai;

        $mergedData = array_merge(
            is_array($reportData) ? $reportData : [],
            is_array($senoclockData) ? $senoclockData : [],
            is_array($shenAiData) ? $shenAiData : []
        );

        if (isset($mergedData['healthIndices']) && is_array($mergedData['healthIndices'])) {
            $mergedData = array_merge($mergedData, $mergedData['healthIndices']);
        }

        $priorityParameters = [];
        if (isset($shenAiData['ranked_parameters']) && is_array($shenAiData['ranked_parameters'])) {
            $rankedParams = $shenAiData['ranked_parameters'];
            usort($rankedParams, function ($a, $b) {
                return ($a['rank'] ?? 999) <=> ($b['rank'] ?? 999);
            });

            foreach ($rankedParams as $param) {
                $unit = '-';
                if (!empty($param['optimal_threshold'])) {
                    if (preg_match('/([a-zA-Z%\/²³]+)$/', trim($param['optimal_threshold']), $matches)) {
                        $unit = trim($matches[0]);
                    }
                }
                
                $statusStr = $param['status'] ?? 'Normal';
                $statusLower = strtolower($statusStr);
                $statusColor = 'success';
                if ($statusLower === 'high' || $statusLower === 'low') $statusColor = 'danger';
                elseif ($statusLower === 'needs attention') $statusColor = 'warning';
                
                $pct = isset($param['percentage_out_of_range']) ? $param['percentage_out_of_range'] : null;
                $pctStr = '-';
                if ($pct !== null) {
                    $pctStr = ($pct > 0 ? '+' : '') . $pct . '%';
                }

                $priorityParameters[] = [
                    'name' => $param['parameter_name'] ?? 'Unknown',
                    'key' => $param['parameter_name'] ?? 'Unknown',
                    'value' => $param['input_value'] ?? '-',
                    'unit' => $unit,
                    'percentage_deviation' => $pctStr,
                    'status' => ucfirst($statusStr),
                    'status_color' => $statusColor,
                ];
            }
        } else {
            $priorityParameters = $this->formatPriorityParameters($mergedData);
        }

        $baseUrl = url('/');
        $pdfUrl = $baseUrl . '/api/v1/newshenai-care/longevityReportPdf?user_id=' . $vital->user_id . '&report_id=' . $vital->id;

        $clinicalTriggers = [];
        $triggersSource = $senoclockData['data']['trigger'] ?? $senoclockData['trigger'] ?? [];
        if (!empty($triggersSource) && is_array($triggersSource)) {
$triggerId = 1;
            foreach ($triggersSource as $trig) {
                $matchedConditions = [];
                if (isset($trig['matched_conditions']) && is_array($trig['matched_conditions'])) {
                    foreach ($trig['matched_conditions'] as $mc) {
                        $pName = $mc['parameter_name'] ?? '';
                        $pMatchedCondition = $mc['matched_condition'] ?? '';
                        $matchedConditions[] = trim($pName . ' ' . $pMatchedCondition);
                    }
                }
                
                $cat = strtolower($trig['trigger_category'] ?? '');
                $icon = 'uploads/wellness.png';

                $clinicalTriggers[] = [
'id' => $triggerId++,
                    'title' => $trig['trigger_name'] ?? 'Trigger',
                    'description' => $trig['trigger_description'] ?? '',
                    'matched_conditions' => $matchedConditions,
                    'associated_organ_health' => $trig['associated_organ_health'] ?? '',
                    'icon' => $icon
                ];
            }
        }

        $longevityDoctors = $this->getMulkLongevityDoctors();

        // Fetch Major Organ Tests
        $majorOrganTests = \App\Models\MajorOrganTest::where('status', 1)
            ->orderBy('display_order', 'asc')
            ->orderBy('id', 'asc')
            ->get()
            ->map(function ($item) {
                $biomarkers = is_array($item->biomarkers) ? $item->biomarkers : [];
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'icon' => !empty($item->icon) ? ltrim($item->icon, '/') : null,
                    'price' => number_format((float) $item->price, 2, '.', ''),
                    'biomarker_count' => count($biomarkers),
                    'biomarkers' => $biomarkers,
                ];
            });

        // Fetch Major Organ Package
        $package = \App\Models\MajorOrganPackage::where('status', 1)->first();
        $packageData = null;
        if ($package) {
            $packageData = [
                'id' => $package->id,
                'title' => $package->title,
                'badge' => $package->badge,
                'description' => $package->description,
                'price' => number_format((float) $package->price, 2, '.', ''),
                'image' => !empty($package->image) ? ltrim($package->image, '/') : null,
                'status' => (int) $package->status,
                'organ_health_check_count' => $majorOrganTests->count(),
                'total_biomarkers' => $majorOrganTests->sum('biomarker_count'),
                'summary' => $majorOrganTests->count() . ' Organ Health Check • ' . $majorOrganTests->sum('biomarker_count') . ' Biomarkers',
            ];
        }

        // Fetch Longevity Plans
        $longevityPlans = \App\Models\LongevityPlan::where('status', 1)
            ->orderBy('display_order', 'asc')
            ->orderBy('id', 'asc')
            ->get()
            ->map(function ($item) {
                $whatsIncluded = is_array($item->whats_included) ? $item->whats_included : [];
                $benefits = is_array($item->benefits) ? $item->benefits : [];

                return [
                    'id' => $item->id,
                    'title' => $item->title,
                    'subtitle' => $item->subtitle,
                    'description' => $item->description,
                    'image' => !empty($item->image) ? ltrim($item->image, '/') : null,
                    'whats_included' => $whatsIncluded,
                    'benefits' => $benefits,
                ];
            });

        return response()->json([
            'status' => true,
            'message' => 'Latest Longevity Report retrieved successfully.',
            'title' => 'Mulk Longevity Report',
            'priority_parameters' => [
                'title' => 'Priority Parameters',
'section_type' => 'priority_parameters',
                'parameters' => $priorityParameters,
],
            'clinical_triggers' => [
                'title' => 'Clinical Triggers',
                'section_type' => 'clinical_triggers',
                'triggers' => $clinicalTriggers,
],
            'report_links' => [
'title' => 'Download your AI Wellness Report',
                'section_type' => 'report_links',
                'download_pdf' => $pdfUrl,
                'share_link' => $pdfUrl
            ],
            'mulk_longevity_doctors' => [
                'title' => 'Mulk Longevity Doctors',
                'section_type' => 'mulk_longevity_doctors',
                'doctors' => $longevityDoctors,
            ],
            'recommended_organ_health' => [
                'title' => 'Recommended Organ Health and Mulk Longevity Panel',
                'section_type' => 'recommended_organ_health',
                'package' => $packageData,
                            'tests' => $majorOrganTests,
            ],
            'longevity_plans' => [
                'title' => 'Mulk Wellness Retreats and Longevity Plans',
                'section_type' => 'longevity_plans',
                'plans' => $longevityPlans,
            ],
        ], 200);
    }

    /**
     * Doctors flagged for Longevity Care UI cards (image, name, designation, fee, online, video call).
     */
    protected function getMulkLongevityDoctors()
    {
        if (!Schema::hasColumn('doctors', 'is_longevity_care')) {
            return [];
        }

        $hostAndConversionRate = Helpers::conversionRate();
        $conversionRate = (float) $hostAndConversionRate['conversionRate'];

        $doctors = Doctors::select('*', DB::raw("ROUND(consultation_fee * {$conversionRate}) as consultation_fee"))
            ->with('expertise')
            ->where('status', Constants::statusDoctorApproved)
            ->where('on_vacation', Constants::doctorNotOnVacation)
            ->where('is_longevity_care', 1)
            ->orderBy('is_online', 'DESC')
            ->orderBy('id', 'DESC')
            ->get();

        return $doctors;
    }

    /**
     * Authenticate with Senoclock AI service.
     */
    public function login(Request $request): JsonResponse
    {
        $email = $request->input('email') ?: (string) config('services.senoclock.email');
        $password = $request->input('password') ?: (string) config('services.senoclock.password');

        if (empty($email) || empty($password)) {
            return response()->json([
                'status' => false,
                'message' => 'Credentials email and password are required. Set SENOCLOCK_EMAIL and SENOCLOCK_PASSWORD in .env or provide email & password in request.',
                'api_url' => $this->senoclockAiService->getLoginApiUrl(),
            ], 400);
        }

        $result = $this->senoclockAiService->testLogin($email, $password);
        $statusCode = $result['success'] ? 200 : 401;

        return response()->json([
            'status' => $result['success'],
            'message' => $result['message'] ?? ($result['success'] ? 'Login successful' : 'Login failed'),
            'access_token' => $result['access_token'] ?? $result['access_token_preview'] ?? null,
            'user' => $result['user'] ?? null,
            'data' => $result,
        ], $statusCode);
    }

    /**
     * Store face scan data in ai_vitals DB table (report column)
     * and trigger Senoclock AI Classification API.
     */
    public function scan(Request $request): JsonResponse
    {
        try {
            $this->ensureSchema();

            $userId = $request->input('user_id', 0);
            $appointmentId = $request->input('appointment_id', 0);
            $scanDate = $request->input('date') ?? $request->input('scan_date') ?? date('Y-m-d H:i:s');

            $reportData = $request->input('report');
            if (empty($reportData)) {
                $reportData = $request->except(['email', 'password', 'user_id', 'appointment_id', 'date', 'scan_date', 'pdf_file']);
            }

            $reportJson = is_string($reportData) ? $reportData : json_encode($reportData);

            $aiVital = new AI_Vital();
            $aiVital->user_id = $userId;
            $aiVital->appointment_id = $appointmentId;
            $aiVital->report = $reportJson;
            $aiVital->scan_date = $scanDate;
            $aiVital->save();

            Log::info("v1\\NewShenaiCareController: Saved face scan AI Vital ID #{$aiVital->id} for User ID #{$userId}");

            $user = Users::find($userId);
            $email = $request->input('email') ?: (string) config('services.senoclock.email');
            $password = $request->input('password') ?: (string) config('services.senoclock.password');

            $classificationResponse = $this->senoclockAiService->processAiVital(
                $aiVital,
                $user,
                $request,
                $email,
                $password
            );

            $aiVital->refresh();

            $pdfUrl = null;
            if ($user) {
                try {
                    $pdfUrl = $this->generatePdfAndEmail($aiVital, $user, $request);
                } catch (\Throwable $pdfError) {
                    Log::warning("v1\\NewShenaiCareController: PDF generation skipped/failed: " . $pdfError->getMessage());
                }
            }

            return response()->json([
                'status' => true,
                'message' => 'Face scan vitals stored in DB and classified via Senoclock AI successfully.',
                'data' => [
                    'id' => $aiVital->id,
                    'user_id' => $aiVital->user_id,
                    'appointment_id' => $aiVital->appointment_id,
                    'report' => json_decode($aiVital->report, true) ?? $aiVital->report,
                    'senoclock_ai_response' => $aiVital->senoclock_ai_response,
                    'pdf_file' => $aiVital->pdf_file,
                    'pdf_url' => $pdfUrl,
                    'scan_date' => $aiVital->scan_date,
                    'created_at' => $aiVital->created_at,
                ],
            ], 200);
        } catch (\Throwable $e) {
            Log::error("v1\\NewShenaiCareController: Scan error: " . $e->getMessage(), [
                'exception' => $e,
                'request' => $request->all(),
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Failed to process face scan vitals: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Trigger classification for an existing AI Vital record.
     */
    public function triggerClassification(Request $request): JsonResponse
    {
        $aiVitalId = $request->input('ai_vital_id') ?? $request->input('id');
        $email = $request->input('email') ?: (string) config('services.senoclock.email');
        $password = $request->input('password') ?: (string) config('services.senoclock.password');

        if ($aiVitalId) {
            $aiVital = AI_Vital::find($aiVitalId);
            if (!$aiVital) {
                return response()->json([
                    'status' => false,
                    'message' => "AI Vital record ID #{$aiVitalId} not found.",
                ], 404);
            }

            $user = Users::find($aiVital->user_id);
            $response = $this->senoclockAiService->processAiVital($aiVital, $user, $request, $email, $password);
            $aiVital->refresh();

            return response()->json([
                'status' => true,
                'message' => 'Classification triggered successfully for AI Vital record.',
                'data' => [
                    'id' => $aiVital->id,
                    'senoclock_ai_response' => $aiVital->senoclock_ai_response,
                ],
            ]);
        }

        $payload = $request->input('payload') ?? $request->except(['email', 'password']);
        $result = $this->senoclockAiService->testClassification($payload, $email, $password);

        return response()->json([
            'status' => $result['success'] ?? false,
            'message' => $result['message'] ?? 'Classification executed.',
            'data' => $result['data'] ?? $result,
        ], ($result['success'] ?? false) ? 200 : 422);
    }

    /**
     * Get specific AI vital record or list user AI vitals.
     */
    public function getVital(Request $request, $id = null): JsonResponse
    {
        $vitalId = $id ?? $request->input('id') ?? $request->input('ai_vital_id');
        $userId = $request->input('user_id');

        if ($vitalId) {
            $vital = AI_Vital::find($vitalId);
            if (!$vital) {
                return response()->json([
                    'status' => false,
                    'message' => 'AI Vital record not found.',
                ], 404);
            }

            return response()->json([
                'status' => true,
                'data' => $vital,
            ]);
        }

        if ($userId) {
            $vitals = AI_Vital::where('user_id', $userId)->orderBy('id', 'desc')->get();
            return response()->json([
                'status' => true,
                'data' => $vitals,
            ]);
        }

        return response()->json([
            'status' => false,
            'message' => 'Either vital ID or user_id parameter is required.',
        ], 400);
    }

    /**
     * Helper to format Priority Parameters table.
     */
    protected function formatPriorityParameters(array $data): array
    {
        $parameters = [];

        // 1. Wellness Score
        $wellness = $this->findMetricValue($data, ['wellnessScore', 'wellness_score', 'Wellness Score']);
        if ($wellness !== null) {
            $val = round((float)$wellness, 2);
            $status = $val < 60 ? 'Needs Attention' : ($val < 80 ? 'Normal' : 'Optimal');
            $parameters[] = [
                'name' => 'Wellness Score',
                'key' => 'wellnessScore',
                'value' => $val,
                'unit' => '-',
                'percentage_deviation' => '+6%',
                'status' => $status,
                'status_color' => $status === 'Needs Attention' ? 'warning' : 'success',
            ];
        }

        // 2. HRV
        $hrv = $this->findMetricValue($data, ['hrvSdnnMs', 'hrv', 'hrvLnRmssdMs', 'Heart Rate Variability (HRV)']);
        if ($hrv !== null) {
            $val = round((float)$hrv, 1);
            $status = $val < 30 ? 'Low' : ($val > 100 ? 'High' : 'Normal');
            $parameters[] = [
                'name' => 'HRV',
                'key' => 'hrvSdnnMs',
                'value' => $val,
                'unit' => 'ms',
                'percentage_deviation' => '-13%',
                'status' => $status,
                'status_color' => $status === 'Low' ? 'danger' : 'success',
            ];
        }

        // 3. BMI
        $bmi = $this->findMetricValue($data, ['bmi', 'Body Mass Index (BMI)']);
        if ($bmi !== null) {
            $val = round((float)$bmi, 1);
            $status = $val > 25 ? 'High' : ($val < 18.5 ? 'Low' : 'Normal');
            $parameters[] = [
                'name' => 'BMI',
                'key' => 'bmi',
                'value' => $val,
                'unit' => '-',
                'percentage_deviation' => '+16%',
                'status' => $status,
                'status_color' => $status === 'High' ? 'danger' : 'success',
            ];
        }

        // 4. BMR (Kcal)
        $bmr = $this->findMetricValue($data, ['basalMetabolicRate', 'bmr', 'BMR (Kcal)', 'Basal Metabolic Rate (BMR)']);
        if ($bmr !== null) {
            $val = round((float)$bmr, 1);
            $parameters[] = [
                'name' => 'BMR (Kcal)',
                'key' => 'basalMetabolicRate',
                'value' => $val,
                'unit' => 'Kcal',
                'percentage_deviation' => '+2%',
                'status' => 'Normal',
                'status_color' => 'success',
            ];
        }

        // 5. TDEE (Kcal)
        $tdee = $this->findMetricValue($data, ['totalDailyEnergyExpenditure', 'tdee', 'TDEE (Kcal)', 'Total Daily Energy Expenditure (TDEE)']);
        if ($tdee !== null) {
            $val = round((float)$tdee, 1);
            $parameters[] = [
                'name' => 'TDEE (Kcal)',
                'key' => 'totalDailyEnergyExpenditure',
                'value' => $val,
                'unit' => 'Kcal',
                'percentage_deviation' => '+4%',
                'status' => 'Normal',
                'status_color' => 'success',
            ];
        }

        // 6. Vascular Age
        $vascAge = $this->findMetricValue($data, ['vascularAge', 'vascular_age', 'Vascular Age']);
        if ($vascAge !== null) {
            $val = round((float)$vascAge, 1);
            $parameters[] = [
                'name' => 'Vascular Age',
                'key' => 'vascularAge',
                'value' => $val,
                'unit' => 'years',
                'percentage_deviation' => '0%',
                'status' => 'Normal',
                'status_color' => 'success',
            ];
        }

        // 7. Stress Index
        $stress = $this->findMetricValue($data, ['stressLevel', 'stressIndex', 'stress_index', 'Stress Index']);
        if ($stress !== null) {
            $val = round((float)$stress, 1);
            $status = $val > 5 ? 'High' : 'Normal';
            $parameters[] = [
                'name' => 'Stress Index',
                'key' => 'stressLevel',
                'value' => $val,
                'unit' => '-',
                'percentage_deviation' => '+3%',
                'status' => $status,
                'status_color' => $status === 'High' ? 'warning' : 'success',
            ];
        }

        return $parameters;
    }

    protected function findMetricValue(array $data, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $data) && $data[$key] !== null && $data[$key] !== '') {
                return $data[$key];
            }
        }
        return null;
    }

    protected function ensureSchema(): void
    {
        if (Schema::hasTable('ai_vitals') && !Schema::hasColumn('ai_vitals', 'senoclock_ai_response')) {
            try {
                Schema::table('ai_vitals', function (Blueprint $table) {
                    $table->longText('senoclock_ai_response')->nullable()->after('report');
                });
            } catch (\Throwable $e) {
                Log::warning("Could not auto-add senoclock_ai_response column: " . $e->getMessage());
            }
        }
    }

    protected function generatePdfAndEmail(AI_Vital $aiVital, Users $user, Request $request): ?string
    {
        $viewName = $aiVital->is_longevity == 1 ? 'pages.aivital_LongevityReport' : 'pages.vitalScanReport';

        if (!view()->exists($viewName)) {
            // Fallback
            if ($aiVital->is_longevity == 1 && view()->exists('pages.vitalScanReport')) {
                $viewName = 'pages.vitalScanReport';
            } else {
                return null;
            }
        }

        $data = [
            'user' => $user,
            'scan_date' => $aiVital->scan_date ?? date('Y-m-d H:i:s'),
            'report' => is_string($aiVital->report) ? json_decode($aiVital->report) : $aiVital->report,
            'senoclock_ai_response' => is_string($aiVital->senoclock_ai_response) ? json_decode($aiVital->senoclock_ai_response) : $aiVital->senoclock_ai_response,
            'shen_ai' => is_string($aiVital->shen_ai) ? json_decode($aiVital->shen_ai) : $aiVital->shen_ai,
        ];

        $pdf = PDF::loadView($viewName, $data)
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'dpi' => 150,
                'isRemoteEnabled' => true,
                'isHtml5ParserEnabled' => true,
            ]);

        $filename = 'vitalScan_' . $aiVital->id . '.pdf';
        $tempPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $filename;
        file_put_contents($tempPath, $pdf->output());

        $uploadedFile = new UploadedFile(
            $tempPath,
            $filename,
            'application/pdf',
            null,
            true
        );

        $saveResult = GlobalFunction::saveFileAndGivePath($uploadedFile);
        $aiVital->pdf_file = $saveResult;
        $aiVital->save();

        if (!empty($user->email)) {
            try {
                Mail::to($user->email)->send(new AiVitalReportMail($user, $uploadedFile));
            } catch (\Throwable $e) {
                Log::warning("Email sending failed for AI Vital ID #{$aiVital->id}: " . $e->getMessage());
            }
        }

        $baseUrl = url('/');
        return $baseUrl . '/api/v1/user/vitalReportPdf?user_id=' . $aiVital->user_id . '&report_id=' . $aiVital->id;
    }

    public function longevityReportPdf(Request $request)
    {
        $rules = [
            'user_id' => 'required',
            'report_id' => 'required',
        ];

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0] ?? 'Validation failed';
            return response()->json(['status' => false, 'message' => $msg]);
        }

        $ai_vital_report = AI_Vital::where('user_id', $request->user_id)->where('id', $request->report_id)->first();
        if (!$ai_vital_report) {
            return response()->json(['status' => false, 'message' => 'Report not found.']);
        }

        $data = [];
        $user = Users::where('id', $request->user_id)->first();
        $data['user'] = $user;
        $data['scan_date'] = $ai_vital_report->scan_date ?? null;
        $data['report'] = !empty($ai_vital_report->report) ? (is_string($ai_vital_report->report) ? json_decode($ai_vital_report->report) : $ai_vital_report->report) : '';
        $data['senoclock_ai_response'] = !empty($ai_vital_report->senoclock_ai_response) ? (is_string($ai_vital_report->senoclock_ai_response) ? json_decode($ai_vital_report->senoclock_ai_response) : $ai_vital_report->senoclock_ai_response) : '';
        $data['shen_ai'] = !empty($ai_vital_report->shen_ai) ? (is_string($ai_vital_report->shen_ai) ? json_decode($ai_vital_report->shen_ai) : $ai_vital_report->shen_ai) : '';

        $viewName = $ai_vital_report->is_longevity == 1 ? 'pages.aivital_LongevityReport' : 'pages.vitalScanReport';
        if (!view()->exists($viewName)) {
            $viewName = 'pages.vitalScanReport';
        }

        $filename = "vitalScanReport.pdf";
        $pdf = PDF::loadView($viewName, $data)
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'dpi' => 150,
                'isRemoteEnabled' => true,
                'isHtml5ParserEnabled' => true,
            ]);
        return $pdf->download($filename);
    }
     
    public function downloadLatestLongevityReportPdf(Request $request)
    {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'user_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $userId = $request->user_id;

        $ai_vital_report = AI_Vital::where('user_id', $userId)
            ->where('is_longevity', 1)
            ->orderBy('id', 'desc')
            ->first();

        if (!$ai_vital_report) {
            $ai_vital_report = AI_Vital::where('user_id', $userId)->orderBy('id', 'desc')->first();
        }

        if (!$ai_vital_report) {
            return response()->json(['status' => false, 'message' => 'Report not found.']);
        }

        $data = [];
        $user = Users::where('id', $userId)->first();
        $data['user'] = $user;
        $data['scan_date'] = $ai_vital_report->scan_date ?? null;
        $data['report'] = !empty($ai_vital_report->report) ? (is_string($ai_vital_report->report) ? json_decode($ai_vital_report->report) : $ai_vital_report->report) : '';
        $data['senoclock_ai_response'] = !empty($ai_vital_report->senoclock_ai_response) ? (is_string($ai_vital_report->senoclock_ai_response) ? json_decode($ai_vital_report->senoclock_ai_response) : $ai_vital_report->senoclock_ai_response) : '';
        $data['shen_ai'] = !empty($ai_vital_report->shen_ai) ? (is_string($ai_vital_report->shen_ai) ? json_decode($ai_vital_report->shen_ai) : $ai_vital_report->shen_ai) : '';

        $viewName = $ai_vital_report->is_longevity == 1 ? 'pages.aivital_LongevityReport' : 'pages.vitalScanReport';
        if (!view()->exists($viewName)) {
            $viewName = 'pages.vitalScanReport';
        }

        $filename = "latestLongevityReport.pdf";
        $pdf = PDF::loadView($viewName, $data)
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'dpi' => 150,
                'isRemoteEnabled' => true,
                'isHtml5ParserEnabled' => true,
            ]);
        return $pdf->download($filename);
    }

    }
