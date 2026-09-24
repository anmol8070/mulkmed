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
use App\Models\UserLongevityPlan;
use App\Services\SenoclockAiService;
use App\Mail\AiVitalReportMail;
use App\Models\GlobalFunction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use PDF;
use App\Helpers\CurrencyHelper;
use Illuminate\Support\Str;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\HTMLParserMode;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

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
        $currency = CurrencyHelper::getUserCurrency();

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

        $sections = $this->buildLongevityPriorityAndTriggers(
            is_array($reportData) ? $reportData : [],
            is_array($senoclockData) ? $senoclockData : [],
            is_array($shenAiData) ? $shenAiData : []
        );
        $priorityParameters = $sections['priority_parameters'];
        $clinicalTriggers = $sections['clinical_triggers'];

        $baseUrl = url('/');
        $pdfUrl = $baseUrl . '/api/v1/newshenai-care/longevityReportPdf?user_id=' . $vital->user_id . '&report_id=' . $vital->id;

        $longevityDoctors = $this->getMulkLongevityDoctors();

        $recommendedOrganHealth = $this->buildRecommendedOrganHealthSection($currency);

        // Fetch Longevity Plans
        $longevityPlans = \App\Models\LongevityPlan::where('status', 1)
            ->orderBy('display_order', 'asc')
            ->orderBy('id', 'asc')
            ->get()
            ->map(function ($item) use ($currency) {
                $whatsIncluded = is_array($item->whats_included) ? $item->whats_included : [];
                $benefits = is_array($item->benefits) ? $item->benefits : [];

                return [
                    'id' => $item->id,
                    'title' => $item->title,
                    'subtitle' => $item->subtitle,
                    'description' => $item->description,
                    'currency' => $currency,
                    'price' => number_format((float) CurrencyHelper::convert($item->price, $currency), 2, '.', ''),
                    'image' => !empty($item->image) ? ltrim($item->image, '/') : null,
                    'whats_included' => $whatsIncluded,
                    'benefits' => $benefits,
                ];
            });

        return response()->json([
            'status' => true,
            'message' => 'Latest Longevity Report retrieved successfully.',
            'currency' => $currency,
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
            'recommended_organ_health' => $recommendedOrganHealth,
            'longevity_plans' => [
                'title' => 'Mulk Wellness Retreats and Longevity Plans',
                'section_type' => 'longevity_plans',
                'plans' => $longevityPlans,
            ],
        ], 200);
    }

    /**
     * Recommended Organ Health and Mulk Longevity Panel section as a standalone API.
     * Returns the same payload as the `recommended_organ_health` key of getLatestLongevityReport().
     */
    public function recommendedOrganHealth(Request $request): JsonResponse
    {
        $currency = CurrencyHelper::getUserCurrency();
        $section = $this->buildRecommendedOrganHealthSection($currency);

        return response()->json([
            'status' => true,
            'message' => 'Recommended Organ Health and Mulk Longevity Panel retrieved successfully.',
            'currency' => $currency,
            'recommended_organ_health' => $section,
        ], 200);
    }

    /**
     * Build the Recommended Organ Health and Mulk Longevity Panel section.
     */
    protected function buildRecommendedOrganHealthSection(string $currency): array
    {
        $majorOrganTests = MajorOrganTest::where('status', 1)
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

        // Fetch all active Major Organ Packages (Comprehensive, Basic, etc.)
        $packagesData = MajorOrganPackage::where('status', 1)
            ->orderBy('id', 'asc')
            ->get()
            ->map(function ($pkg) use ($currency, $majorOrganTests) {
                return [
                    'id' => $pkg->id,
                    'title' => $pkg->title,
                    'badge' => $pkg->badge,
                    'description' => $pkg->description,
                    'currency' => $currency,
                    'price' => number_format((float) CurrencyHelper::convert($pkg->price, $currency), 2, '.', ''),
                    'image' => !empty($pkg->image) ? ltrim($pkg->image, '/') : null,
                    'status' => (int) $pkg->status,
                    'organ_health_check_count' => $majorOrganTests->count(),
                    'total_biomarkers' => $majorOrganTests->sum('biomarker_count'),
                    'summary' => $majorOrganTests->count() . ' Organ Health Check • ' . $majorOrganTests->sum('biomarker_count') . ' Biomarkers',
                ];
            })->values();

        return [
            'title' => 'Recommended Organ Health and Mulk Longevity Panel',
            'section_type' => 'recommended_organ_health',
            'package' => $packagesData->first(),
            'packages' => $packagesData,
            'tests' => $majorOrganTests,
        ];
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
     * Upload an AI Vital report PDF file, extract data using OpenAI, and save to ai_vitals table.
     */
    public function uploadAiVitalReport(Request $request, \App\Services\LabReportBiomarkerAnalyzerService $analyzerService): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|integer',
            'report_file' => 'required|file|mimes:pdf',
        ]);

        try {
            $file = $request->file('report_file');
            
            Log::info('Starting AI Vital PDF extraction', [
                'user_id' => $request->user_id,
                'filename' => $file->getClientOriginalName()
            ]);

            // 1. Upload to OpenAI Files API using the existing service
            Log::info('Uploading AI Vital PDF to OpenAI');
            $uploadedPdf = $analyzerService->uploadPdfToOpenAI($file);
            
            Log::info('AI Vital PDF uploaded successfully', [
                'filename' => $uploadedPdf['original_name'],
                'file_id' => $uploadedPdf['file_id']
            ]);

            // 2. Analyze PDF via OpenAI Responses API
            $apiKey = config('services.openai.api_key');
            if (empty($apiKey)) {
                throw new \RuntimeException('OpenAI API key is missing.');
            }

            Log::info('Starting AI Vital OpenAI analysis');

            $prompt = "You are extracting structured data from an AI Vital health report PDF.
Read the ENTIRE uploaded PDF, including every page.
Extract ALL health metrics, vital signs, physical parameters, indices, risk factors, and scores found in the PDF.
Do not calculate, estimate, infer, guess, or hallucinate any value. Preserve the exact numeric or text values from the PDF.

Return valid JSON only.
Structure the JSON as a comprehensive flat object where keys are the metric names (in camelCase).
For the value of each metric, return an object containing the following exact keys:
- \"name\": The original field or biomarker name as it appears in the PDF
- \"result\": The exact result value as a string (preserve all formatting, commas, decimals, e.g. \"1,259.00\", \"111 / 74\", \"46.54\")
- \"unit\": The exact unit as a string (e.g. \"bpm\", \"mmHg\", \"%\", \"-\"). Leave empty if no unit.
- \"normal_range\": The exact normal range as a string (e.g. \"60 - 100\", \"N/A*\", \"SBP 90 - 120, DBP 60 - 70\"). Leave empty if no range.

CRITICAL INSTRUCTION: You MUST include the following specific keys if their corresponding data is found anywhere in the report:
- \"wellnessScore\"
- \"hrvSdnnMs\" (Heart Rate Variability)
- \"bmi\"
- \"basalMetabolicRate\"
- \"totalDailyEnergyExpenditure\"

For all other metrics found (e.g., Blood Pressure, Stress Index, Vascular Age, Heart Rate, etc.), invent an appropriate camelCase key and add it to the JSON.

Rules:
- If a parameter is not present, do not invent it.
- Do not return markdown (e.g., no ```json).
- Do not return explanations outside the JSON.";

            $payload = [
                'model' => config('services.openai.model', 'gpt-4o'),
                'text' => [
                    'format' => ['type' => 'json_object'],
                ],
                'input' => [
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'input_file',
                                'file_id' => $uploadedPdf['file_id'],
                                'detail' => 'high',
                            ],
                            [
                                'type' => 'input_text',
                                'text' => $prompt,
                            ]
                        ],
                    ],
                ],
            ];

            $response = $analyzerService->openAiHttpClient($apiKey)
                ->timeout(240)
                ->post('https://api.openai.com/v1/responses', $payload);

            $responseData = $response->json();
            
            Log::info('OpenAI Responses API completed', [
                'response_id' => $responseData['id'] ?? null,
                'status' => $responseData['status'] ?? null,
            ]);

            if (!$response->successful()) {
                throw new \RuntimeException('OpenAI Responses API failed with status ' . $response->status());
            }

            $outputText = $analyzerService->extractResponsesOutputText($responseData);
            Log::info('AI Vital OpenAI output extracted');
            
            if (empty($outputText)) {
                throw new \RuntimeException('OpenAI returned an empty analysis response.');
            }

            $outputText = trim($outputText);
            $outputText = preg_replace('/^```json\s*/i', '', $outputText);
            $outputText = preg_replace('/\s*```$/', '', $outputText);
            
            $extractedData = json_decode($outputText, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \RuntimeException('OpenAI returned invalid JSON');
            }

            Log::info('AI Vital JSON parsed successfully');

            // Save file locally as well
            $destinationPath = public_path('uploads/ai_vital_senoclock');
            if (!file_exists($destinationPath)) {
                mkdir($destinationPath, 0777, true);
            }
            $fileName = time() . '_' . $file->getClientOriginalName();
            $file->move($destinationPath, $fileName);

            $aiVital = new AI_Vital();
            $aiVital->user_id = $request->input('user_id');
            $aiVital->is_longevity = 1;
            $aiVital->scan_date = now();
            $aiVital->shen_ai = $extractedData;
            $aiVital->report = json_encode($extractedData); // Encode because report is not cast to array
            $aiVital->pdf_file = 'uploads/ai_vital_senoclock/' . $fileName;
            $aiVital->save();

            // Trigger Senoclock AI classification to generate clinical triggers
            try {
                $user = Users::find($aiVital->user_id);
                // Log::info('[uploadAiVitalReport] calling Senoclock processAiVital → trigger-classification', [
                //     'ai_vital_id' => $aiVital->id,
                //     'user_id' => $aiVital->user_id,
                //     'classification_url' => $this->senoclockAiService->getClassificationApiUrl(),
                // ]);
                $this->senoclockAiService->processAiVital($aiVital, $user, $request);
                $aiVital->refresh();
                // Log::info('[uploadAiVitalReport] Senoclock processAiVital finished', [
                //     'ai_vital_id' => $aiVital->id,
                //     'senoclock_ai_response' => $aiVital->senoclock_ai_response,
                // ]);
            } catch (\Throwable $e) {
                Log::error('uploadAiVitalReport Senoclock AI classification trigger error: ' . $e->getMessage());
            }

            $baseUrl = url('/');
            $pdfUrl = $baseUrl . '/api/v1/newshenai-care/longevityReportPdf?user_id=' . $aiVital->user_id . '&report_id=' . $aiVital->id;

            Log::info('AI Vital PDF extraction completed successfully', [
                'extracted_metrics_count' => count($extractedData),
                'keys' => array_keys($extractedData),
            ]);

            return response()->json([
                'status' => true,
                'message' => 'AI Vital report uploaded and processed successfully.',
                'data' => [
                    'ai_vital_id' => $aiVital->id,
                    'file_path' => 'uploads/ai_vital_senoclock/' . $fileName
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error("v1\\NewShenaiCareController: uploadAiVitalReport error: " . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while uploading the report: ' . $e->getMessage()
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
     * Flatten report / senoclock / shen_ai payloads so metric lookups can use root keys.
     */
    protected function flattenLongevityMetrics(array $reportData, array $senoclockData, array $shenAiData): array
    {
        $merged = [];

        foreach ([$reportData, $senoclockData, $shenAiData] as $source) {
            if (!is_array($source) || empty($source)) {
                continue;
            }

            $layers = [$source];
            if (isset($source['data']) && is_array($source['data'])) {
                $layers[] = $source['data'];
            }

            foreach ($layers as $layer) {
                foreach ($layer as $key => $value) {
                    if ($value !== null && $value !== '' && $key !== 'healthIndices' && $key !== 'data') {
                        $merged[$key] = $value;
                    }
                }

                if (isset($layer['healthIndices']) && is_array($layer['healthIndices'])) {
                    foreach ($layer['healthIndices'] as $key => $value) {
                        if ($value !== null && $value !== '') {
                            $merged[$key] = $value;
                        }
                    }
                }
            }
        }

        return $merged;
    }

    /**
     * Build Priority Parameters + top 5 Clinical Triggers for API and PDF (shared).
     * Priority parameters come from the scan's ranked parameters (out-of-range
     * findings), so the API response and the PDF Measurement Results table
     * always show the same rows. The fixed metric set is only a last resort
     * when the scan payload has no ranked parameters at all.
     */
    protected function buildLongevityPriorityAndTriggers(array $reportData, array $senoclockData, array $shenAiData): array
    {
        $mergedData = $this->flattenLongevityMetrics($reportData, $senoclockData, $shenAiData);

        $rankedParams = null;
        foreach ([
            $shenAiData['ranked_parameters'] ?? null,
            $senoclockData['ranked_parameters'] ?? null,
            $senoclockData['data']['ranked_parameters'] ?? null,
            $reportData['ranked_parameters'] ?? null,
            $mergedData['ranked_parameters'] ?? null,
        ] as $candidate) {
            if (!empty($candidate) && is_array($candidate)) {
                $rankedParams = $candidate;
                break;
            }
        }

        $priorityParameters = [];
        if (!empty($rankedParams)) {
            usort($rankedParams, function ($a, $b) {
                return (is_array($a) ? ($a['rank'] ?? 999) : 999) <=> (is_array($b) ? ($b['rank'] ?? 999) : 999);
            });

            foreach ($rankedParams as $param) {
                if (!is_array($param)) {
                    continue;
                }

                $rawName = (string) ($param['parameter_name'] ?? $param['name'] ?? '');
                if ($rawName === '') {
                    continue;
                }

                $resolved = $this->resolveParameterDetails($rawName, $param['input_value'] ?? null, $mergedData);
                $status = trim((string) ($param['status'] ?? 'Normal'));
                if ($status === '') {
                    $status = 'Normal';
                }

                $pct = $param['percentage_out_of_range'] ?? $param['percentage_deviation'] ?? null;
                $pctStr = '-';
                if ($pct !== null && $pct !== '' && is_numeric($pct)) {
                    $pctStr = ((float) $pct > 0 ? '+' : '') . $pct . '%';
                }

                $priorityParameters[] = [
                    'name' => $resolved['name'],
                    'key' => $resolved['key'],
                    'value' => $resolved['value'],
                    'unit' => $this->extractParameterUnit($param, $resolved['key']),
                    'percentage_deviation' => $pctStr,
                    'status' => ucfirst($status),
                    'status_color' => $this->statusColorFromStatus($status),
                ];
            }
        }

        if (empty($priorityParameters)) {
            $priorityParameters = $this->formatPriorityParameters($mergedData);
        }

        $clinicalTriggers = [];
        $triggersSource = [];
        foreach ([
            $senoclockData['data']['trigger'] ?? null,
            $senoclockData['trigger'] ?? null,
            $senoclockData['data']['triggers'] ?? null,
            $senoclockData['triggers'] ?? null,
            $shenAiData['data']['trigger'] ?? null,
            $shenAiData['trigger'] ?? null,
            $shenAiData['data']['triggers'] ?? null,
            $shenAiData['triggers'] ?? null,
            $reportData['data']['trigger'] ?? null,
            $reportData['trigger'] ?? null,
            $mergedData['data']['trigger'] ?? null,
            $mergedData['trigger'] ?? null,
        ] as $candidate) {
            if (!empty($candidate) && is_array($candidate)) {
                $triggersSource = $candidate;
                break;
            }
        }

        if (!empty($triggersSource)) {
            $relatedTriggers = [];
            $allTriggers = [];
            $triggerId = 1;

            foreach ($triggersSource as $trig) {
                if (!is_array($trig)) {
                    continue;
                }

                $matchedConditions = [];
                if (isset($trig['matched_conditions']) && is_array($trig['matched_conditions'])) {
                    foreach ($trig['matched_conditions'] as $mc) {
                        $normalized = $this->normalizeMatchedConditionFields($mc);
                        if ($normalized !== null) {
                            $matchedConditions[] = $normalized;
                        }
                    }
                }

                $cat = strtolower((string) ($trig['trigger_category'] ?? ''));
                $icon = 'wellness.png';
                if (str_contains($cat, 'metabolic') || str_contains($cat, 'insulin')) {
                    $icon = 'insulin.png';
                } elseif (str_contains($cat, 'respiratory')) {
                    $icon = 'respiratory.png';
                }

                $item = [
                    'id' => $triggerId++,
                    'title' => $trig['trigger_name'] ?? 'Trigger',
                    'description' => $trig['trigger_description'] ?? '',
                    'matched_conditions' => $matchedConditions,
                    'associated_organ_health' => $trig['associated_organ_health'] ?? '',
                    'icon' => $icon,
                ];

                $allTriggers[] = $item;

                if ($this->isTriggerRelatedToPriorityParameters($trig, $priorityParameters)) {
                    $relatedTriggers[] = $item;
                }
            }

            $clinicalTriggers = !empty($relatedTriggers)
                ? array_slice($relatedTriggers, 0, 5)
                : array_slice($allTriggers, 0, 5);

            foreach ($clinicalTriggers as $i => &$triggerItem) {
                $triggerItem['id'] = $i + 1;
            }
            unset($triggerItem);
        }

        return [
            'priority_parameters' => $priorityParameters,
            'clinical_triggers' => $clinicalTriggers,
        ];
    }

    /**
     * Normalize AI_Vital JSON fields to arrays for shared builders.
     */
    protected function longevityVitalPayloadArrays(AI_Vital $vital): array
    {
        $toArray = function ($value): array {
            if (empty($value)) {
                return [];
            }
            if (is_string($value)) {
                $decoded = json_decode($value, true);
                return is_array($decoded) ? $decoded : [];
            }
            if (is_object($value)) {
                return json_decode(json_encode($value), true) ?: [];
            }
            return is_array($value) ? $value : [];
        };

        return [
            $toArray($vital->report),
            $toArray($vital->senoclock_ai_response),
            $toArray($vital->shen_ai),
        ];
    }

    /**
     * Same 5 Measurement Results rows as the longevity PDF (page 4).
     */
    protected function formatPriorityParameters(array $data): array
    {
        $wellness = (float) ($this->findMetricValue($data, ['wellnessScore', 'wellness_score', 'Wellness Score']) ?? 0);
        $hrv = (float) ($this->findMetricValue($data, ['hrvSdnnMs', 'hrv', 'hrv_sdnn_ms', 'hrvLnRmssdMs', 'Heart Rate Variability (HRV)', 'HRV']) ?? 0);
        $bmi = (float) ($this->findMetricValue($data, ['bmi', 'Body Mass Index (BMI)', 'BMI']) ?? 0);
        $bmr = (float) ($this->findMetricValue($data, ['basalMetabolicRate', 'bmr', 'BMR (Kcal)', 'BMR', 'Basal Metabolic Rate (BMR)']) ?? 0);
        $tdee = (float) ($this->findMetricValue($data, ['totalDailyEnergyExpenditure', 'tdee', 'TDEE (Kcal)', 'TDEE', 'Total Daily Energy Expenditure (TDEE)']) ?? 0);

        $wellnessVal = round($wellness, 2);
        $wellnessStatus = $wellnessVal >= 70 ? 'Normal' : ($wellnessVal >= 45 ? 'Needs Attention' : 'Low');

        $hrvVal = (int) round($hrv);
        $hrvStatus = $hrvVal >= 70 ? 'Normal' : 'Low';

        $bmiVal = round($bmi, 1);
        if ($bmiVal >= 18.5 && $bmiVal <= 24.9) {
            $bmiStatus = 'Normal';
        } elseif ($bmiVal > 24.9) {
            $bmiStatus = 'High';
        } else {
            $bmiStatus = 'Low';
        }

        return [
            [
                'name' => 'Wellness Score',
                'key' => 'wellnessScore',
                'value' => $wellnessVal,
                'unit' => '-',
                'percentage_deviation' => $this->percentageDeviation($wellnessVal, 47.5),
                'status' => $wellnessStatus,
                'status_color' => $this->statusColorFromStatus($wellnessStatus),
            ],
            [
                'name' => 'HRV (Heart Rate Variability)',
                'key' => 'hrvSdnnMs',
                'value' => $hrvVal,
                'unit' => 'ms',
                'percentage_deviation' => $this->percentageDeviation($hrvVal, 74),
                'status' => $hrvStatus,
                'status_color' => $this->statusColorFromStatus($hrvStatus),
            ],
            [
                'name' => 'BMI',
                'key' => 'bmi',
                'value' => $bmiVal,
                'unit' => '-',
                'percentage_deviation' => $this->percentageDeviation($bmiVal, 25),
                'status' => $bmiStatus,
                'status_color' => $this->statusColorFromStatus($bmiStatus),
            ],
            [
                'name' => 'BMR (Kcal)',
                'key' => 'basalMetabolicRate',
                'value' => round($bmr, 1),
                'unit' => 'Kcal',
                'percentage_deviation' => $this->percentageDeviation($bmr, 1335),
                'status' => 'Normal',
                'status_color' => 'success',
            ],
            [
                'name' => 'TDEE (Kcal)',
                'key' => 'totalDailyEnergyExpenditure',
                'value' => round($tdee, 1),
                'unit' => 'Kcal',
                'percentage_deviation' => $this->percentageDeviation($tdee, 1805),
                'status' => 'Normal',
                'status_color' => 'success',
            ],
        ];
    }

    /**
     * Unit for a ranked parameter: explicit unit, else the trailing unit of the
     * optimal threshold text ("high: >= 20.0 bpm"), else a known metric unit.
     */
    protected function extractParameterUnit(array $param, string $key): string
    {
        $explicit = trim((string) ($param['unit'] ?? ''));
        if ($explicit !== '') {
            return $explicit;
        }

        $threshold = trim((string) ($param['optimal_threshold'] ?? $param['normal_range'] ?? ''));
        if ($threshold !== '' && preg_match('/([a-zA-Z%\/][a-zA-Z%\/\^0-9]*)\s*$/u', $threshold, $matches)) {
            $candidate = trim($matches[1]);
            $reserved = ['high', 'low', 'normal', 'and', 'or', 'to', 'score', 'index'];
            if (!in_array(strtolower($candidate), $reserved, true)) {
                return $candidate;
            }
        }

        $knownUnits = [
            'hrvSdnnMs' => 'ms',
            'basalMetabolicRate' => 'Kcal',
            'totalDailyEnergyExpenditure' => 'Kcal',
            'heartRate' => 'bpm',
            'respiratoryRate' => 'bpm',
            'bloodPressure' => 'mmHg',
            'oxygenSaturation' => '%',
            'bodyFat' => '%',
            'vascularAge' => 'years',
        ];

        return $knownUnits[$key] ?? '-';
    }

    protected function percentageDeviation(float $value, float $target): string
    {
        if ($target == 0.0) {
            return '-';
        }

        $pct = (($value - $target) / $target) * 100;

        return ($pct >= 0 ? '+' : '') . round($pct) . '%';
    }

    protected function statusColorFromStatus(string $status): string
    {
        $lower = strtolower($status);
        if (str_contains($lower, 'needs attention')) {
            return 'warning';
        }
        if (str_contains($lower, 'low') || str_contains($lower, 'high')) {
            return 'danger';
        }

        return 'success';
    }

    protected function findMetricValue(array $data, array $keys): mixed
    {
        $sources = [$data];
        if (isset($data['healthIndices']) && is_array($data['healthIndices'])) {
            $sources[] = $data['healthIndices'];
        }
        if (isset($data['data']) && is_array($data['data'])) {
            $sources[] = $data['data'];
            if (isset($data['data']['healthIndices']) && is_array($data['data']['healthIndices'])) {
                $sources[] = $data['data']['healthIndices'];
            }
        }

        foreach ($sources as $source) {
            foreach ($keys as $key) {
                if (array_key_exists($key, $source) && $source[$key] !== null && $source[$key] !== '') {
                    $extracted = $this->extractScalarValue($source[$key]);
                    if ($extracted !== null && $extracted !== '') {
                        return $extracted;
                    }
                }
            }
        }

        return null;
    }

    protected function extractScalarValue(mixed $val): mixed
    {
        if (is_array($val)) {
            $val = $val['result'] ?? $val['value'] ?? $val['input_value'] ?? $val['val'] ?? reset($val);
        } elseif (is_object($val)) {
            $val = $val->result ?? $val->value ?? $val->input_value ?? $val->val ?? null;
        }

        if (is_array($val) || is_object($val)) {
            return null;
        }

        if ($val === null || $val === '') {
            return null;
        }

        if (is_numeric($val)) {
            return (float) $val;
        }

        if (is_string($val)) {
            $trimmed = trim($val);
            if (preg_match('/^\d+\s*\/\s*\d+$/', $trimmed)) {
                return $trimmed;
            }
            $cleaned = preg_replace('/,/', '', $trimmed);
            if (preg_match('/^-?\d+(\.\d+)?/', $cleaned, $m)) {
                return (float) $m[0];
            }
            return $trimmed;
        }

        return $val;
    }

    protected function resolveParameterDetails(string $paramName, mixed $inputValue, array $mergedData): array
    {
        $nameLower = strtolower(trim($paramName));

        $map = [
            'wellness' => [
                'name' => 'Wellness Score',
                'key' => 'wellnessScore',
                'lookup' => ['wellnessScore', 'wellness_score', 'Wellness Score'],
            ],
            'hrv' => [
                'name' => 'HRV',
                'key' => 'hrvSdnnMs',
                'lookup' => ['hrvSdnnMs', 'hrv', 'hrvLnRmssdMs', 'Heart Rate Variability (HRV)', 'HRV'],
            ],
            'variability' => [
                'name' => 'HRV',
                'key' => 'hrvSdnnMs',
                'lookup' => ['hrvSdnnMs', 'hrv', 'hrvLnRmssdMs', 'Heart Rate Variability (HRV)', 'HRV'],
            ],
            'bmi' => [
                'name' => 'BMI',
                'key' => 'bmi',
                'lookup' => ['bmi', 'Body Mass Index (BMI)', 'BMI'],
            ],
            'body mass' => [
                'name' => 'BMI',
                'key' => 'bmi',
                'lookup' => ['bmi', 'Body Mass Index (BMI)', 'BMI'],
            ],
            'bmr' => [
                'name' => 'BMR (Kcal)',
                'key' => 'basalMetabolicRate',
                'lookup' => ['basalMetabolicRate', 'bmr', 'BMR (Kcal)', 'BMR', 'Basal Metabolic Rate (BMR)'],
            ],
            'basal metabolic' => [
                'name' => 'BMR (Kcal)',
                'key' => 'basalMetabolicRate',
                'lookup' => ['basalMetabolicRate', 'bmr', 'BMR (Kcal)', 'BMR', 'Basal Metabolic Rate (BMR)'],
            ],
            'tdee' => [
                'name' => 'TDEE (Kcal)',
                'key' => 'totalDailyEnergyExpenditure',
                'lookup' => ['totalDailyEnergyExpenditure', 'tdee', 'TDEE (Kcal)', 'TDEE', 'Total Daily Energy Expenditure (TDEE)'],
            ],
            'total daily energy' => [
                'name' => 'TDEE (Kcal)',
                'key' => 'totalDailyEnergyExpenditure',
                'lookup' => ['totalDailyEnergyExpenditure', 'tdee', 'TDEE (Kcal)', 'TDEE', 'Total Daily Energy Expenditure (TDEE)'],
            ],
            'vascular age' => [
                'name' => 'Vascular Age',
                'key' => 'vascularAge',
                'lookup' => ['vascularAge', 'vascular_age', 'Vascular Age'],
            ],
            'stress' => [
                'name' => 'Stress Index',
                'key' => 'stressLevel',
                'lookup' => ['stressLevel', 'stressIndex', 'stress_index', 'stress_level', 'Stress Index', 'Stress Level'],
            ],
            'heart rate' => [
                'name' => 'Heart Rate',
                'key' => 'heartRate',
                'lookup' => ['heartRate', 'heart_rate', 'Heart Rate (HR)', 'hr', 'Heart Rate'],
            ],
            'breathing' => [
                'name' => 'Breathing Rate',
                'key' => 'respiratoryRate',
                'lookup' => ['respiratoryRate', 'respiratory_rate', 'breathingRate', 'breathing_rate', 'Breathing Rate'],
            ],
            'respiratory' => [
                'name' => 'Breathing Rate',
                'key' => 'respiratoryRate',
                'lookup' => ['respiratoryRate', 'respiratory_rate', 'breathingRate', 'breathing_rate', 'Breathing Rate'],
            ],
            'blood pressure' => [
                'name' => 'Blood Pressure',
                'key' => 'bloodPressure',
                'lookup' => ['bloodPressure', 'blood_pressure', 'Blood Pressure', 'bp'],
            ],
            'oxygen' => [
                'name' => 'Oxygen Saturation',
                'key' => 'oxygenSaturation',
                'lookup' => ['oxygenSaturation', 'spo2', 'oxygen_saturation', 'SpO2'],
            ],
            'spo2' => [
                'name' => 'Oxygen Saturation',
                'key' => 'oxygenSaturation',
                'lookup' => ['oxygenSaturation', 'spo2', 'oxygen_saturation', 'SpO2'],
            ],
            'body fat' => [
                'name' => 'Body Fat %',
                'key' => 'bodyFat',
                'lookup' => ['bodyFat', 'body_fat', 'Body Fat %', 'Body Fat'],
            ],
            'cardiac workload' => [
                'name' => 'Cardiac Workload',
                'key' => 'cardiacWorkload',
                'lookup' => ['cardiacWorkload', 'cardiac_workload', 'Cardiac Workload'],
            ],
            'parasympathetic' => [
                'name' => 'Parasympathetic Activity',
                'key' => 'parasympatheticActivity',
                'lookup' => ['parasympatheticActivity', 'parasympathetic_activity', 'Parasympathetic Activity'],
            ],
        ];

        $matchedConfig = null;
        foreach ($map as $keyword => $config) {
            if (str_contains($nameLower, $keyword)) {
                $matchedConfig = $config;
                break;
            }
        }

        $finalName = $matchedConfig['name'] ?? $paramName;
        $finalKey = $matchedConfig['key'] ?? Str::camel($paramName);

        $lookupKeys = $matchedConfig['lookup'] ?? [$paramName, Str::camel($paramName), Str::snake($paramName)];
        $metricVal = $this->findMetricValue($mergedData, $lookupKeys);

        if ($metricVal !== null && $metricVal !== '') {
            $value = is_numeric($metricVal) ? (float) $metricVal : $metricVal;
            if (is_float($value)) {
                $value = round($value, 2);
                if (floor($value) == $value && in_array($finalKey, ['vascularAge', 'heartRate', 'respiratoryRate', 'hrvSdnnMs'])) {
                    $value = (int) $value;
                }
            }
        } else {
            $extractedInput = $this->extractScalarValue($inputValue);
            if ($extractedInput !== null && $extractedInput !== '' && $extractedInput != 1 && $extractedInput != '1') {
                $value = is_numeric($extractedInput) ? round((float) $extractedInput, 2) : $extractedInput;
            } else {
                // No measured value in the scan payload: show a placeholder
                // instead of a made-up number.
                $value = '-';
            }
        }

        return [
            'name' => $finalName,
            'key' => $finalKey,
            'value' => $value,
        ];
    }

    /**
     * Normalize a matched condition into structured fields for PDF/API rendering.
     * Never returns raw JSON/object dumps.
     *
     * @return array{name: string, result: string, unit: string, normal_range: string}|null
     */
    protected function normalizeMatchedConditionFields(mixed $mc): ?array
    {
        if ($mc === null || $mc === '') {
            return null;
        }

        if (is_object($mc)) {
            $mc = json_decode(json_encode($mc), true) ?: [];
        }

        $fallbackName = '';
        $payload = null;

        if (is_string($mc)) {
            $trimmed = trim($mc);
            if ($trimmed === '') {
                return null;
            }

            // "Label { ... }" or "Label {'name': ...}"
            if (preg_match('/^(.*?)\s*(\{[\s\S]*\})$/u', $trimmed, $m)) {
                $fallbackName = trim($m[1]);
                $payload = $this->parseObjectLikeString(trim($m[2]));
            } else {
                $payload = $this->parseObjectLikeString($trimmed);
            }

            // Plain text label only
            if ($payload === null) {
                if ($this->looksLikeRawObjectDump($trimmed)) {
                    return null;
                }
                return [
                    'name' => $trimmed,
                    'result' => '—',
                    'unit' => '—',
                    'normal_range' => '—',
                ];
            }
        } elseif (is_array($mc)) {
            // Shape A: { parameter_name, matched_condition: {...|string} }
            if (array_key_exists('matched_condition', $mc) || array_key_exists('parameter_name', $mc)) {
                $fallbackName = trim((string) ($mc['parameter_name'] ?? $mc['name'] ?? ''));
                $condition = $mc['matched_condition'] ?? null;

                if (is_object($condition)) {
                    $condition = json_decode(json_encode($condition), true);
                }
                if (is_string($condition)) {
                    // Again may be "Label {...}" or pure object string
                    $condTrim = trim($condition);
                    if (preg_match('/^(.*?)\s*(\{[\s\S]*\})$/u', $condTrim, $m)) {
                        if ($fallbackName === '') {
                            $fallbackName = trim($m[1]);
                        }
                        $payload = $this->parseObjectLikeString(trim($m[2]));
                    } else {
                        $payload = $this->parseObjectLikeString($condTrim);
                    }
                    // Plain text matched_condition
                    if ($payload === null && !$this->looksLikeRawObjectDump($condTrim) && $condTrim !== '') {
                        return [
                            'name' => $fallbackName !== '' ? $fallbackName : $condTrim,
                            'result' => $fallbackName !== '' ? $condTrim : '—',
                            'unit' => '—',
                            'normal_range' => '—',
                        ];
                    }
                } elseif (is_array($condition)) {
                    $payload = $condition;
                } else {
                    $payload = $mc;
                }
            } else {
                // Shape B: direct metric object { name, result, unit, normal_range }
                $payload = $mc;
            }
        } else {
            return null;
        }

        if (!is_array($payload)) {
            return null;
        }

        $name = trim((string) ($fallbackName !== '' ? $fallbackName : ($payload['parameter_name'] ?? $payload['name'] ?? '')));
        if ($name === '' && !empty($payload['name'])) {
            $name = trim((string) $payload['name']);
        }

        $resultRaw = $payload['result'] ?? $payload['value'] ?? $payload['input_value'] ?? null;
        $unitRaw = $payload['unit'] ?? null;
        $rangeRaw = $payload['normal_range'] ?? $payload['range'] ?? null;

        $result = $this->displayOrDash($resultRaw);
        $unit = $this->displayOrDash($unitRaw);
        $range = $this->displayOrDash($rangeRaw);

        // Skip completely empty/invalid entries
        if ($name === '' && $result === '—' && $unit === '—' && $range === '—') {
            return null;
        }

        if ($name === '') {
            $name = 'Parameter';
        }

        return [
            'name' => $name,
            'result' => $result,
            'unit' => $unit,
            'normal_range' => $range,
        ];
    }

    /**
     * Parse JSON or Python/JS-style single-quoted object strings into an array.
     */
    protected function parseObjectLikeString(string $text): ?array
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }

        // Double-encoded / standard JSON (retry a few times)
        $current = $text;
        for ($i = 0; $i < 3; $i++) {
            if ($current === '' || (!str_starts_with($current, '{') && !str_starts_with($current, '['))) {
                break;
            }
            $decoded = json_decode($current, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                if (is_array($decoded)) {
                    return $decoded;
                }
                if (is_string($decoded)) {
                    $current = trim($decoded);
                    continue;
                }
            }
            break;
        }

        // Single-quoted dict: {'name': 'bmi', 'result': '25', 'unit': '', 'normal_range': '18.5 - 24.9'}
        if (str_starts_with($text, '{') && str_contains($text, "'")) {
            $asJson = preg_replace('/(?<!\\\\)\'/', '"', $text);
            $decoded = json_decode($asJson, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }

            // Field-by-field extraction (handles awkward quoting)
            $out = [];
            foreach (['name', 'parameter_name', 'result', 'value', 'unit', 'normal_range', 'range'] as $key) {
                if (preg_match('/[\'"]' . preg_quote($key, '/') . '[\'"]\s*:\s*[\'"]([^\'"]*)[\'"]/u', $text, $m)) {
                    $out[$key] = $m[1];
                } elseif (preg_match('/[\'"]' . preg_quote($key, '/') . '[\'"]\s*:\s*([^,}\s][^,}]*)/u', $text, $m)) {
                    $out[$key] = trim($m[1], " \t\n\r\0\x0B'\"");
                }
            }
            if (!empty($out)) {
                return $out;
            }
        }

        // Double-quoted but not valid for other reasons — try field extraction
        if (str_starts_with($text, '{')) {
            $out = [];
            foreach (['name', 'parameter_name', 'result', 'value', 'unit', 'normal_range', 'range'] as $key) {
                if (preg_match('/[\'"]' . preg_quote($key, '/') . '[\'"]\s*:\s*[\'"]([^\'"]*)[\'"]/u', $text, $m)) {
                    $out[$key] = $m[1];
                }
            }
            if (!empty($out)) {
                return $out;
            }
        }

        return null;
    }

    protected function displayOrDash(mixed $value): string
    {
        if ($value === null) {
            return '—';
        }
        $text = trim((string) $value);
        if ($text === '' || $text === '-' || $text === '/' || strtolower($text) === 'n/a' || strtolower($text) === 'null') {
            return '—';
        }
        return $text;
    }

    protected function looksLikeRawObjectDump(string $text): bool
    {
        $text = trim($text);
        return str_contains($text, '{') && (
            str_contains($text, "'name'")
            || str_contains($text, '"name"')
            || str_contains($text, "'result'")
            || str_contains($text, '"result"')
            || str_contains($text, 'normal_range')
        );
    }

    /**
     * @deprecated Use normalizeMatchedConditionFields(); kept for any legacy callers.
     */
    protected function formatMatchedConditionLine(mixed $mc): string
    {
        $normalized = $this->normalizeMatchedConditionFields($mc);
        if ($normalized === null) {
            return '';
        }
        return trim(sprintf(
            '%s — Result: %s, Unit: %s, Normal Range: %s',
            $normalized['name'],
            $normalized['result'],
            $normalized['unit'],
            $normalized['normal_range']
        ));
    }

    protected function decodeJsonValue(mixed $value): mixed
    {
        if (!is_string($value)) {
            return $value;
        }
        return $this->parseObjectLikeString(trim($value)) ?? $value;
    }

    protected function composeConditionParts(string $paramName, mixed $result, mixed $unit, mixed $range): string
    {
        $normalized = $this->normalizeMatchedConditionFields([
            'parameter_name' => $paramName,
            'matched_condition' => [
                'result' => $result,
                'unit' => $unit,
                'normal_range' => $range,
            ],
        ]);
        if ($normalized === null) {
            return $paramName;
        }
        return trim(sprintf(
            '%s — Result: %s, Unit: %s, Normal Range: %s',
            $normalized['name'],
            $normalized['result'],
            $normalized['unit'],
            $normalized['normal_range']
        ));
    }

    protected function scrubJsonFromConditionText(string $text): string
    {
        $normalized = $this->normalizeMatchedConditionFields($text);
        if ($normalized === null) {
            return '';
        }
        return trim(sprintf(
            '%s — Result: %s, Unit: %s, Normal Range: %s',
            $normalized['name'],
            $normalized['result'],
            $normalized['unit'],
            $normalized['normal_range']
        ));
    }

    /**
     * Check if a clinical trigger is related to any parameter present in Priority Parameters.
     */
    protected function isTriggerRelatedToPriorityParameters(array $trig, array $priorityParameters): bool
    {
        // When priority params are unavailable, allow triggers so clinical_triggers is not blank
        if (empty($priorityParameters)) {
            return true;
        }

        $activeKeys = [];
        $activeNames = [];
        foreach ($priorityParameters as $param) {
            if (!empty($param['key'])) {
                $activeKeys[] = strtolower($param['key']);
            }
            if (!empty($param['name'])) {
                $activeNames[] = strtolower($param['name']);
            }
        }

        $conditionStrings = [];
        if (isset($trig['matched_conditions']) && is_array($trig['matched_conditions'])) {
            foreach ($trig['matched_conditions'] as $mc) {
                if (is_array($mc)) {
                    if (!empty($mc['parameter_name'])) {
                        $conditionStrings[] = (string) $mc['parameter_name'];
                    }
                    if (!empty($mc['matched_condition'])) {
                        $conditionStrings[] = (string) $mc['matched_condition'];
                    }
                    if (!empty($mc['name'])) {
                        $conditionStrings[] = (string) $mc['name'];
                    }
                    if (!empty($mc['key'])) {
                        $conditionStrings[] = (string) $mc['key'];
                    }
                } elseif (is_string($mc)) {
                    $conditionStrings[] = $mc;
                }
            }
        }

        if (!empty($conditionStrings)) {
            foreach ($conditionStrings as $condStr) {
                $resolved = $this->resolveParameterDetails($condStr, null, []);
                $resolvedKey = strtolower($resolved['key'] ?? '');
                $resolvedName = strtolower($resolved['name'] ?? '');

                if (in_array($resolvedKey, $activeKeys) || in_array($resolvedName, $activeNames)) {
                    return true;
                }

                $condStrLower = strtolower($condStr);
                foreach ($activeNames as $actName) {
                    if ($actName !== '' && (str_contains($condStrLower, $actName) || str_contains($actName, $condStrLower))) {
                        return true;
                    }
                }
                foreach ($activeKeys as $actKey) {
                    if ($actKey !== '' && (str_contains($condStrLower, $actKey) || str_contains($actKey, $condStrLower))) {
                        return true;
                    }
                }
            }
            return false;
        }

        $triggerText = strtolower(($trig['trigger_name'] ?? '') . ' ' . ($trig['trigger_description'] ?? ''));
        foreach ($activeNames as $actName) {
            if ($actName !== '' && str_contains($triggerText, $actName)) {
                return true;
            }
        }
        foreach ($activeKeys as $actKey) {
            if ($actKey !== '' && str_contains($triggerText, $actKey)) {
                return true;
            }
        }

        return false;
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

        [$reportArr, $senoclockArr, $shenArr] = $this->longevityVitalPayloadArrays($aiVital);
        $sections = $this->buildLongevityPriorityAndTriggers($reportArr, $senoclockArr, $shenArr);
        $data['priorityParameters'] = $sections['priority_parameters'];
        $data['clinicalTriggers'] = $sections['clinical_triggers'];

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
        $reportData = !empty($ai_vital_report->report) ? (is_string($ai_vital_report->report) ? json_decode($ai_vital_report->report) : json_decode(json_encode($ai_vital_report->report))) : new \stdClass();
        $shenAiData = !empty($ai_vital_report->shen_ai) ? (is_string($ai_vital_report->shen_ai) ? json_decode($ai_vital_report->shen_ai) : json_decode(json_encode($ai_vital_report->shen_ai))) : new \stdClass();
        
        $data['senoclock_ai_response'] = !empty($ai_vital_report->senoclock_ai_response) ? (is_string($ai_vital_report->senoclock_ai_response) ? json_decode($ai_vital_report->senoclock_ai_response) : json_decode(json_encode($ai_vital_report->senoclock_ai_response))) : '';
        
        // Normalize report data for the Blade view
        $mapFields = function($source) {
            if (!$source) return new \stdClass();
            $mapped = clone $source;
            
            // Map root-level aliases
            $mapped->heartRate = $source->pulse ?? $source->heartRate ?? null;
            $mapped->respiratoryRate = $source->breathingRate ?? $source->respiratoryRate ?? null;
            $mapped->stressLevel = $source->stressIndex ?? $source->stressLevel ?? null;
            
            // Create healthIndices object if it doesn't exist
            if (!isset($mapped->healthIndices) || !is_object($mapped->healthIndices)) {
                $mapped->healthIndices = new \stdClass();
            }
            
            // Map flat metrics to nested healthIndices
            $mapped->healthIndices->wellnessScore = $source->wellnessScore ?? $mapped->healthIndices->wellnessScore ?? null;
            $mapped->healthIndices->vascularAge = $source->vascularAge ?? $mapped->healthIndices->vascularAge ?? null;
            $mapped->healthIndices->totalCVMortalityRisk = $source->cardiovascularRiskScore ?? $mapped->healthIndices->totalCVMortalityRisk ?? null;
            $mapped->healthIndices->hypertensionRisk = $source->hypertensionRisk ?? $mapped->healthIndices->hypertensionRisk ?? null;
            $mapped->healthIndices->diabetesRisk = $source->diabetesRisk ?? $mapped->healthIndices->diabetesRisk ?? null;
            $mapped->healthIndices->nonAlcoholicFattyLiverDiseaseRisk = $source->fattyLiverDiseaseRisk ?? $mapped->healthIndices->nonAlcoholicFattyLiverDiseaseRisk ?? null;
            
            $mapped->healthIndices->waistToHeightRatio = $source->waistToHeightRatio ?? $mapped->healthIndices->waistToHeightRatio ?? null;
            $mapped->healthIndices->bodyFatPercentage = $source->bodyFatPercentage ?? $mapped->healthIndices->bodyFatPercentage ?? null;
            $mapped->healthIndices->basalMetabolicRate = $source->basalMetabolicRate ?? $mapped->healthIndices->basalMetabolicRate ?? null;
            $mapped->healthIndices->totalDailyEnergyExpenditure = $source->totalDailyEnergyExpenditure ?? $mapped->healthIndices->totalDailyEnergyExpenditure ?? null;
            
            // Nested cvDiseases
            if (!isset($mapped->healthIndices->cvDiseases)) $mapped->healthIndices->cvDiseases = new \stdClass();
            $mapped->healthIndices->cvDiseases->overallRisk = $source->cardiovascularDiseaseRisk ?? $mapped->healthIndices->cvDiseases->overallRisk ?? null;
            
            // Nested hardAndFatalEvents
            if (!isset($mapped->healthIndices->hardAndFatalEvents)) $mapped->healthIndices->hardAndFatalEvents = new \stdClass();
            $mapped->healthIndices->hardAndFatalEvents->hardCVEventRisk = $source->hardAndFatalEventsRisks ?? $mapped->healthIndices->hardAndFatalEvents->hardCVEventRisk ?? null;
            
            return $mapped;
        };

        $data['report'] = $mapFields($reportData);
        $data['shen_ai'] = $mapFields($shenAiData);

        [$reportArr, $senoclockArr, $shenArr] = $this->longevityVitalPayloadArrays($ai_vital_report);
        $sections = $this->buildLongevityPriorityAndTriggers($reportArr, $senoclockArr, $shenArr);
        $data['priorityParameters'] = $sections['priority_parameters'];
        $data['clinicalTriggers'] = $sections['clinical_triggers'];

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

        [$reportArr, $senoclockArr, $shenArr] = $this->longevityVitalPayloadArrays($ai_vital_report);
        $sections = $this->buildLongevityPriorityAndTriggers($reportArr, $senoclockArr, $shenArr);
        $data['priorityParameters'] = $sections['priority_parameters'];
        $data['clinicalTriggers'] = $sections['clinical_triggers'];

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

    /**
     * Download static Blood Age Report v3 PDF (mPDF / A4).
     * GET /api/v1/newshenai-care/downloadBloodAgeReportV3
     */
    public function downloadBloodAgeReportV3(Request $request)
    {
        if (!view()->exists('pages.blood_age_report_v3')) {
            return response()->json([
                'status' => false,
                'message' => 'Blood Age Report template not found.',
            ], 404);
        }

        $inline = $request->boolean('inline');
        return $this->renderBloodAgeReportV3Pdf('blood_age_report_v3.pdf', !$inline);
    }

    /**
     * Legacy writer used when biomarkers were missing.
     * Disabled: blood_age_report_v3 is not used in the Senoclock/lab-report PDF flow.
     */
    public function writeBloodAgeReportV3Pdf(string $destPath): bool
    {
        Log::warning('writeBloodAgeReportV3Pdf skipped — blood_age_report_v3 PDF generation is unused', [
            'dest' => $destPath,
        ]);
        return false;
    }

    /**
     * Render Blood Age Report v3 PDF from the exact HTML preview markup.
     * Preview/download helper only — not used by ProcessSenoclockIntegration.
     */
    protected function renderBloodAgeReportV3Pdf(string $filename = 'blood_age_report_v3.pdf', bool $download = true)
    {
        @ini_set('memory_limit', '512M');
        @ini_set('max_execution_time', '60');

        $tempDir = storage_path('app/blood-age-chrome');
        if (!is_dir($tempDir)) {
            @mkdir($tempDir, 0777, true);
        }

        $viewPath = resource_path('views/pages/blood_age_report_v3.blade.php');
        $cacheKey = 'v3_' . (is_file($viewPath) ? filemtime($viewPath) : '0');
        $cachedPdf = $tempDir . DIRECTORY_SEPARATOR . $cacheKey . '.pdf';

        // Serve cached PDF when the blade template has not changed.
        if (is_file($cachedPdf) && filesize($cachedPdf) > 500) {
            $content = file_get_contents($cachedPdf);
            $disposition = $download ? 'attachment' : 'inline';

            return response($content, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => $disposition . '; filename="' . $filename . '"',
                'Content-Length' => strlen($content),
                'Cache-Control' => 'private, max-age=60',
                'X-Blood-Age-PDF-Cache' => 'HIT',
            ]);
        }

        $html = view('pages.blood_age_report_v3')->render();

        // Chrome prints from a local file:// HTML document. Absolute http://asset
        // URLs would re-hit artisan serve while it is blocked waiting for Chrome
        // (images never load). Rewrite public asset URLs to local file:// paths.
        $html = preg_replace_callback(
            '#(?:https?://[^/"\']+)?/asset/([^"\'\s>]+)#i',
            static function ($matches) {
                $relative = rawurldecode($matches[1]);
                $absolute = public_path('asset/' . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative));
                if (!is_file($absolute)) {
                    return $matches[0];
                }
                return 'file:///' . str_replace('\\', '/', $absolute);
            },
            $html
        );

        // Ensure print CSS is applied for Chromium PDF (exact match to HTML preview).
        if (stripos($html, '@page') === false) {
            $html = str_replace(
                '</head>',
                '<style>@page{size:A4 portrait;margin:0} @media print{html,body{background:#fff!important;padding:0!important;margin:0!important;-webkit-print-color-adjust:exact;print-color-adjust:exact}.page-container{width:210mm!important;min-height:297mm!important;height:297mm!important;margin:0!important;box-shadow:none!important;page-break-after:always;overflow:hidden}.page-container:last-child{page-break-after:auto}}</style></head>',
                $html
            );
        }

        $token = uniqid('preview_', true);
        $htmlPath = $tempDir . DIRECTORY_SEPARATOR . $token . '.html';
        $pdfPath = $tempDir . DIRECTORY_SEPARATOR . $token . '.pdf';
        $outLog = $tempDir . DIRECTORY_SEPARATOR . $token . '.out.log';
        $errLog = $tempDir . DIRECTORY_SEPARATOR . $token . '.err.log';

        file_put_contents($htmlPath, $html);

        $browser = $this->findChromiumExecutable();
        if ($browser === null) {
            @unlink($htmlPath);
            return response()->json([
                'status' => false,
                'message' => 'Chrome/Edge not found. Install Google Chrome or Microsoft Edge to generate an exact HTML PDF preview.',
            ], 500);
        }

        $htmlUri = 'file:///' . str_replace('\\', '/', $htmlPath);
        $chromeArgs = [
            '--headless=new',
            '--disable-gpu',
            '--disable-software-rasterizer',
            '--disable-dev-shm-usage',
            '--no-first-run',
            '--no-default-browser-check',
            '--allow-file-access-from-files',
            '--no-pdf-header-footer',
            '--print-to-pdf-no-header',
            '--run-all-compositor-stages-before-draw',
            '--virtual-time-budget=2000',
            '--print-to-pdf=' . $pdfPath,
            $htmlUri,
        ];

        $stderr = '';
        if (strncasecmp(PHP_OS, 'WIN', 3) === 0) {
            // PowerShell Start-Process is more reliable than proc_open on Windows
            // (avoids hung Chromium locking artisan serve).
            $psArgs = implode(', ', array_map(static function ($arg) {
                return "'" . str_replace("'", "''", $arg) . "'";
            }, $chromeArgs));
            $psBrowser = str_replace("'", "''", $browser);
            $ps = 'powershell -NoProfile -ExecutionPolicy Bypass -Command '
                . '"$p = Start-Process -FilePath \'' . $psBrowser . '\' -ArgumentList @(' . $psArgs . ') -PassThru -WindowStyle Hidden; '
                . 'if (-not $p.WaitForExit(20000)) { Stop-Process -Id $p.Id -Force -ErrorAction SilentlyContinue; '
                . 'Get-CimInstance Win32_Process -Filter \"Name=\'chrome.exe\'\" | '
                . 'Where-Object { $_.CommandLine -match \'print-to-pdf\' } | '
                . 'ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue } }"';
            @exec($ps . ' 2>NUL');
        } else {
            $cmd = array_merge([$browser], $chromeArgs);
            $descriptor = [
                0 => ['pipe', 'r'],
                1 => ['file', $outLog, 'w'],
                2 => ['file', $errLog, 'w'],
            ];
            $process = proc_open($cmd, $descriptor, $pipes, null, null, ['bypass_shell' => true]);
            if (is_resource($process)) {
                fclose($pipes[0]);
                $timeoutSec = 20;
                $start = microtime(true);
                $status = proc_get_status($process);
                while ($status['running']) {
                    if ((microtime(true) - $start) > $timeoutSec) {
                        if (!empty($status['pid'])) {
                            @exec('kill -9 ' . (int) $status['pid'] . ' 2>/dev/null');
                        }
                        proc_terminate($process, 9);
                        break;
                    }
                    usleep(100000);
                    $status = proc_get_status($process);
                }
                proc_close($process);
                if (is_file($errLog)) {
                    $stderr = (string) file_get_contents($errLog);
                }
            }
        }

        $tries = 0;
        while ((!is_file($pdfPath) || filesize($pdfPath) < 500) && $tries < 10) {
            usleep(100000);
            $tries++;
        }

        @unlink($htmlPath);
        @unlink($outLog);
        @unlink($errLog);

        if (!is_file($pdfPath) || filesize($pdfPath) < 500) {
            @unlink($pdfPath);
            Log::error('Blood Age Chrome PDF failed', ['stderr' => $stderr, 'browser' => $browser]);
            // Inline preview: fall back to fast HTML so the browser is not stuck on a blank load.
            if (!$download) {
                return redirect('/preview-blood-age-report-v3-html');
            }
            return response()->json([
                'status' => false,
                'message' => 'Failed to generate PDF from HTML preview (timed out or empty output). Try /preview-blood-age-report-v3-html for a fast layout check.',
                'detail' => trim($stderr) !== '' ? trim($stderr) : 'Empty PDF output',
            ], 500);
        }

        // Persist cache keyed by blade mtime; drop older preview caches.
        @copy($pdfPath, $cachedPdf);
        foreach (glob($tempDir . DIRECTORY_SEPARATOR . 'v3_*.pdf') ?: [] as $oldCache) {
            if ($oldCache !== $cachedPdf) {
                @unlink($oldCache);
            }
        }

        $content = file_get_contents($pdfPath);
        @unlink($pdfPath);

        $disposition = $download ? 'attachment' : 'inline';

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition . '; filename="' . $filename . '"',
            'Content-Length' => strlen($content),
            'Cache-Control' => 'private, max-age=60',
            'X-Blood-Age-PDF-Cache' => 'MISS',
        ]);
    }

    /**
     * Locate Chrome or Edge for headless HTML→PDF printing.
     */
    protected function findChromiumExecutable(): ?string
    {
        $candidates = [
            'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
            'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
            'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
            'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
            '/usr/bin/google-chrome',
            '/usr/bin/google-chrome-stable',
            '/usr/bin/chromium-browser',
            '/usr/bin/chromium',
            '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
            '/Applications/Microsoft Edge.app/Contents/MacOS/Microsoft Edge',
        ];

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Extract inner HTML for one report page after splitting on the opening .page div.
     * (Kept for compatibility with any callers/helpers.)
     */
    protected function extractBloodAgeReportPageInnerHtml(string $chunk): string
    {
        $depth = 1;
        $pos = 0;
        $len = strlen($chunk);

        while ($pos < $len && $depth > 0) {
            $nextOpen = stripos($chunk, '<div', $pos);
            $nextClose = stripos($chunk, '</div>', $pos);
            if ($nextClose === false) {
                break;
            }

            if ($nextOpen !== false && $nextOpen < $nextClose) {
                $depth++;
                $pos = $nextOpen + 4;
                continue;
            }

            $depth--;
            if ($depth === 0) {
                return substr($chunk, 0, $nextClose);
            }
            $pos = $nextClose + 6;
        }

        return rtrim($chunk);
    }

    public function myRetreatPlans(Request $request)
    {
        $userId = $request->query('user_id', $request->input('user_id'));
        if (empty($userId)) {
            return response()->json([
                'status' => false,
                'message' => 'user_id is required.',
            ], 400);
        }

        $filterPlanId = $request->query('plan_id', $request->input('plan_id'));

        $query = UserLongevityPlan::where('user_id', $userId)
            ->where(function ($q) {
                $q->where('status', 1)->orWhere('status', 'active');
            })
            ->orderBy('id', 'desc');
        if (!empty($filterPlanId)) {
            $query->where(function ($q) use ($filterPlanId) {
                $q->where('plan_id', $filterPlanId);
                if (Schema::hasColumn('user_longevity_plans', 'longevity_plan_ids')) {
                    $q->orWhere('longevity_plan_ids', $filterPlanId)
                      ->orWhereRaw("FIND_IN_SET(?, longevity_plan_ids)", [$filterPlanId]);
                }
            });
        }
        $userPlans = $query->get();

        $data = [];
        $processedPlanIds = [];

        foreach ($userPlans as $up) {
            // Check expiry and update if needed
            if (($up->status == 1 || $up->status === 'active') && !empty($up->expiry_date)) {
                $expiryDate = \Carbon\Carbon::parse($up->expiry_date);
                if ($expiryDate->isPast() && !$expiryDate->isToday()) {
                    $up->status = 'expired';
                    $up->save();
                }
            }

            // Extract plan IDs from longevity_plan_ids or plan_id
            $rawPlanIds = !empty($up->longevity_plan_ids) ? $up->longevity_plan_ids : $up->plan_id;
            if ($rawPlanIds) {
                $pids = array_values(array_filter(array_map('trim', explode(',', (string) $rawPlanIds))));
                foreach ($pids as $pid) {
                    if (empty($pid)) continue;
                    $key = $pid . '_' . ($up->id ?? 0);
                    if (in_array($key, $processedPlanIds)) continue;
                    $processedPlanIds[] = $key;

                    $plan = LongevityPlan::find($pid);
                    if ($plan) {
                        $data[] = [
                            'id' => $plan->id,
                            'title' => $plan->title,
                            'subtitle' => $plan->subtitle,
                            'image' => !empty($plan->image) ? GlobalFunction::createMediaUrl($plan->image) : null,
                            'whats_included' => is_string($plan->whats_included) ? json_decode($plan->whats_included, true) : $plan->whats_included,
                            'benefits' => is_string($plan->benefits) ? json_decode($plan->benefits, true) : $plan->benefits,
                            'status' => $up->status == 1 ? 'active' : (string) $up->status,
                            'expiry_date' => $up->expiry_date,
                            'purchased_at' => $up->created_at ? $up->created_at->format('Y-m-d H:i:s') : null,
                        ];
                    }
                }
            }
        }

        // Fallback / supplement from MajorOrganUserSelection if no user_longevity_plans found
        if (empty($data)) {
            $selectionsQuery = \App\Models\MajorOrganUserSelection::where('user_id', $userId)
                ->where('payment_status', 1)
                ->orderBy('id', 'desc');

            $selections = $selectionsQuery->get();
            foreach ($selections as $sel) {
                $rawIds = !empty($sel->longevity_plan_ids) ? $sel->longevity_plan_ids : $sel->plan_id;
                if ($rawIds) {
                    $pids = array_values(array_filter(array_map('trim', explode(',', (string) $rawIds))));
                    foreach ($pids as $pid) {
                        $plan = LongevityPlan::find($pid);
                        if ($plan) {
                            $key = $plan->id . '_sel_' . $sel->id;
                            if (in_array($key, $processedPlanIds)) continue;
                            $processedPlanIds[] = $key;

                            $data[] = [
                                'id' => $plan->id,
                                'title' => $plan->title,
                                'subtitle' => $plan->subtitle,
                                'image' => !empty($plan->image) ? GlobalFunction::createMediaUrl($plan->image) : null,
                                'whats_included' => is_string($plan->whats_included) ? json_decode($plan->whats_included, true) : $plan->whats_included,
                                'benefits' => is_string($plan->benefits) ? json_decode($plan->benefits, true) : $plan->benefits,
                                'status' => 'active',
                                'expiry_date' => null,
                                'purchased_at' => $sel->created_at ? $sel->created_at->format('Y-m-d H:i:s') : null,
                            ];
                        }
                    }
                }
            }
        }

        return response()->json([
            'status' => true,
            'message' => 'Retreat plans fetched successfully.',
            'count' => count($data),
            'plans' => $data,
        ]);
    }
    }
