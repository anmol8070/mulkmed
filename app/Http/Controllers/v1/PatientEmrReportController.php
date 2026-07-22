<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Models\AI_Vital;
use App\Models\Appointments;
use App\Models\Doctors;
use App\Models\DoctorsBySymptoms;
use App\Models\EmrSearchMaster;
use App\Models\GlobalFunction;
use App\Models\JitsiMeeting;
use App\Models\PatientEmrReport;
use App\Models\Users;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;

class PatientEmrReportController extends Controller
{
    /** 1×1 transparent PNG when no signature/stamp file exists on disk */
    private const TINY_PNG_DATA_URI = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    private array $defaultDiagnosisTypes = [];
    private array $columnExistsCache = [];
    private array $userLookupCache = [];

    private function canWriteColumn(string $column): bool
    {
        if (array_key_exists($column, $this->columnExistsCache)) {
            return $this->columnExistsCache[$column];
        }
        $exists = Schema::hasColumn('patient_emr_reports', $column);
        $this->columnExistsCache[$column] = $exists;
        return $exists;
    }

    /**
     * Read a file from disk into a data: URI (DomPDF with isRemoteEnabled=false).
     */
    private function pathToDataUri(string $fullPath): string
    {
        if (!is_readable($fullPath)) {
            return '';
        }
        $binary = @file_get_contents($fullPath);
        if ($binary === false || $binary === '') {
            return '';
        }
        $lower = strtolower($fullPath);
        if (str_ends_with($lower, '.jpg') || str_ends_with($lower, '.jpeg')) {
            $mime = 'image/jpeg';
        } elseif (str_ends_with($lower, '.png')) {
            $mime = 'image/png';
        } elseif (str_ends_with($lower, '.gif')) {
            $mime = 'image/gif';
        } elseif (str_ends_with($lower, '.webp')) {
            $mime = 'image/webp';
        } else {
            $mime = 'image/png';
        }

        return 'data:' . $mime . ';base64,' . base64_encode($binary);
    }

    /**
     * Build a data: URI for files under storage/app/public or public/storage.
     */
    private function localPublicImageDataUri(string $relativePath): string
    {
        $relativePath = trim(str_replace('\\', '/', $relativePath));
        if ($relativePath === '') {
            return '';
        }

        // Accept full URLs and legacy stored values (public/storage/... or public/uploads/...).
        if (preg_match('/^https?:\/\//i', $relativePath) === 1) {
            $parsedPath = parse_url($relativePath, PHP_URL_PATH);
            $relativePath = is_string($parsedPath) ? $parsedPath : $relativePath;
        }

        $relativePath = ltrim($relativePath, '/');
        $uploadsPos = stripos($relativePath, 'uploads/');
        if ($uploadsPos !== false) {
            $relativePath = substr($relativePath, $uploadsPos);
        }
        $relativePath = preg_replace('#^(public/)?storage/#i', '', $relativePath) ?? $relativePath;
        $relativePath = preg_replace('#^public/#i', '', $relativePath) ?? $relativePath;

        foreach ([
            storage_path('app/public/' . $relativePath),
            public_path('storage/' . $relativePath),
            public_path($relativePath),
        ] as $fullPath) {
            $uri = $this->pathToDataUri($fullPath);
            if ($uri !== '') {
                return $uri;
            }
        }

        return '';
    }

    /** Files under Laravel `public/` (e.g. images/no-signature.png). */
    private function publicImageDataUri(string $relativeFromPublic): string
    {
        $rel = ltrim(str_replace('\\', '/', $relativeFromPublic), '/');

        return $this->pathToDataUri(public_path($rel));
    }

    private function validateAppointmentForDoctor(int $appointmentId, int $doctorId): ?Appointments
    {
        return Appointments::where('id', $appointmentId)->first();
    }

    private function getOrCreateEmrReport(int $appointmentId, int $doctorId): PatientEmrReport
    {
        $emr = PatientEmrReport::where('appointment_id', $appointmentId)->first();

        if (!$emr) {
            $emr = new PatientEmrReport();
            $emr->appointment_id = $appointmentId;
            $emr->doctor_id = $doctorId;
            $emr->is_finalized = false;
            $emr->save();
        } else {
            // Keep latest doctor reference on report row.
            $emr->doctor_id = $doctorId;
            $emr->save();
        }

        return $emr;
    }

    private function resolveEffectiveDoctorId(?Appointments $appointment, int $requestedDoctorId): int
    {
        $appointmentDoctorId = (int) ($appointment?->doctor_id ?? 0);
        if ($appointmentDoctorId > 0) {
            return $appointmentDoctorId;
        }

        return $requestedDoctorId;
    }

    /**
     * Generate MRN code from appointment id; fallback to EMR report id.
     */
    private function buildMrnNo(?int $emrId = null, ?int $appointmentId = null): string
    {
        $numeric = (int) ($appointmentId ?? 0);
        if ($numeric <= 0) {
            $numeric = (int) ($emrId ?? 0);
        }
        if ($numeric <= 0) {
            $numeric = 1;
        }

        return 'MMH' . str_pad((string) $numeric, 4, '0', STR_PAD_LEFT);
    }

    private function resolveEmrLang(Request $request): string
    {
        $lang = strtolower(trim((string) $request->input('lang', $request->query('lang', 'en'))));
        if ($lang === '') {
            return 'en';
        }

        $newPath = lang_path('EmrReportTranslations/' . $lang . '.php');
        $legacyPath = lang_path($lang . '/emr_report.php');

        return (is_file($newPath) || is_file($legacyPath)) ? $lang : 'en';
    }

    private function emrPdfLabels(string $lang): array
    {
        $fallback = [];
        $fallbackPath = lang_path('EmrReportTranslations/en.php');
        if (!is_file($fallbackPath)) {
            $fallbackPath = lang_path('en/emr_report.php');
        }
        if (is_file($fallbackPath)) {
            $loaded = include $fallbackPath;
            if (is_array($loaded)) {
                $fallback = $loaded;
            }
        }

        if ($lang === 'en') {
            return $fallback;
        }

        $langPath = lang_path('EmrReportTranslations/' . $lang . '.php');
        if (!is_file($langPath)) {
            $langPath = lang_path($lang . '/emr_report.php');
        }
        if (!is_file($langPath)) {
            return $fallback;
        }

        $loaded = include $langPath;
        if (!is_array($loaded)) {
            return $fallback;
        }

        return array_merge($fallback, $loaded);
    }

    private function shouldUseMpdfHindiTemplate(): bool
    {
        return app()->environment(['local', 'development', 'testing']);
    }

    private function mpdfDownloadFromHtml(string $html, string $filename, string $lang = 'en')
    {
        // Large inline CSS/font payloads can exceed default PCRE limits in mPDF parsing.
        @ini_set('pcre.backtrack_limit', '20000000');
        @ini_set('pcre.recursion_limit', '1000000');

        $tempDir = storage_path('app/mpdf-temp');
        if (!is_dir($tempDir)) {
            @mkdir($tempDir, 0777, true);
        }

        $defaultConfig = (new ConfigVariables())->getDefaults();
        $defaultFontConfig = (new FontVariables())->getDefaults();
        $fontDir = array_merge($defaultConfig['fontDir'], [storage_path('fonts')]);
        $fontData = $defaultFontConfig['fontdata'];
        $fontsPath = storage_path('fonts');
        $hasHindRegular = is_file($fontsPath . DIRECTORY_SEPARATOR . 'Hind-Regular.ttf');
        $hasHindBold = is_file($fontsPath . DIRECTORY_SEPARATOR . 'Hind-Bold.ttf');
        $hasDevaRegular = is_file($fontsPath . DIRECTORY_SEPARATOR . 'NotoSansDevanagari-Regular.ttf');
        $hasDevaBold = is_file($fontsPath . DIRECTORY_SEPARATOR . 'NotoSansDevanagari-Bold.ttf');
        $hasNotoSansRegular = is_file($fontsPath . DIRECTORY_SEPARATOR . 'NotoSans-Regular.ttf');
        $hasNotoSansBold = is_file($fontsPath . DIRECTORY_SEPARATOR . 'NotoSans-Bold.ttf');
        $hasArabicRegular = is_file($fontsPath . DIRECTORY_SEPARATOR . 'NotoSansArabic-Regular.ttf');
        $hasArabicBold = is_file($fontsPath . DIRECTORY_SEPARATOR . 'NotoSansArabic-Bold.ttf');

        if ($hasHindRegular) {
            $fontData['hind'] = ['R' => 'Hind-Regular.ttf'];
            if ($hasHindBold) {
                $fontData['hind']['B'] = 'Hind-Bold.ttf';
            }
        }
        if ($hasDevaRegular) {
            $fontData['notosansdevanagari'] = ['R' => 'NotoSansDevanagari-Regular.ttf'];
            if ($hasDevaBold) {
                $fontData['notosansdevanagari']['B'] = 'NotoSansDevanagari-Bold.ttf';
            }
        }
        if ($hasNotoSansRegular) {
            $fontData['notosans'] = ['R' => 'NotoSans-Regular.ttf'];
            if ($hasNotoSansBold) {
                $fontData['notosans']['B'] = 'NotoSans-Bold.ttf';
            }
        }
        if ($hasArabicRegular) {
            $fontData['notosansarabic'] = ['R' => 'NotoSansArabic-Regular.ttf'];
            if ($hasArabicBold) {
                $fontData['notosansarabic']['B'] = 'NotoSansArabic-Bold.ttf';
            }
            $fontData['notosansarabic']['useOTL'] = 0xFF;
            $fontData['notosansarabic']['useKashida'] = 75;
        }
        $defaultFont = 'dejavusans';
        if ($lang === 'hi') {
            if (isset($fontData['notosansdevanagari'])) {
                $defaultFont = 'notosansdevanagari';
            } elseif (isset($fontData['hind'])) {
                $defaultFont = 'hind';
            }
        } elseif ($lang === 'ur') {
            if (isset($fontData['notosansarabic'])) {
                $defaultFont = 'notosansarabic';
            }
        } elseif ($lang === 'ar') {
            if (isset($fontData['notosansarabic'])) {
                $defaultFont = 'notosansarabic';
            }
        } elseif (isset($fontData['notosans'])) {
            $defaultFont = 'notosans';
        } elseif (isset($fontData['hind'])) {
            $defaultFont = 'hind';
        }

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'tempDir' => $tempDir,
            'fontDir' => $fontDir,
            'fontdata' => $fontData,
            'default_font' => $defaultFont,
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
        ]);

        if (in_array($lang, ['hi', 'ar', 'ur'], true)) {
            $html = $this->sanitizeHtmlForMpdf($html);
        }

        $mpdf->WriteHTML($html);
        $content = $mpdf->Output($filename, \Mpdf\Output\Destination::STRING_RETURN);

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    private function sanitizeHtmlForMpdf(string $html): string
    {
        // mPDF can fail on some fixed-position + calc() combinations from DomPDF-tuned templates.
        $html = preg_replace('#<div class="page-wave-repeat">.*?</div>#is', '', $html) ?? $html;

        // Replace calc width expressions that mPDF may parse as zero in fixed blocks.
        $html = str_replace('width:calc(100% + 20mm);', 'width:100%;', $html);
        $html = str_replace('width: calc(100% + 20mm) !important;', 'width:100% !important;', $html);

        // Downgrade fixed positioning to absolute for stability in mPDF.
        $html = str_replace('position:fixed', 'position:absolute', $html);
        $html = str_replace('position: fixed', 'position: absolute', $html);

        return $html;
    }

    private function normalizeList($items): array
    {
        if (is_null($items)) {
            return [];
        }
        if (is_string($items)) {
            $decoded = json_decode($items, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $items = $decoded;
            } else {
                $items = [$items];
            }
        }
        if (!is_array($items)) {
            return [];
        }

        return collect($items)
            ->map(fn($item) => is_string($item) ? trim($item) : '')
            ->filter(fn($item) => $item !== '')
            ->values()
            ->all();
    }

    private function extractDrugNames(Request $request): array
    {
        if (is_array($request->drug_names) && count($request->drug_names) > 0) {
            return collect($request->drug_names)
                ->map(fn($name) => trim((string) $name))
                ->filter(fn($name) => $name !== '')
                ->values()
                ->all();
        }

        // Backward compatibility for old clients sending single drug_name.
        if ($request->filled('drug_name')) {
            return [trim((string) $request->drug_name)];
        }

        return [];
    }

    /**
     * Keep only storage-relative path for uploaded documents (e.g. uploads/file.pdf).
     */
    private function normalizeUploadsRelativePath(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $value = str_replace('\\', '/', $value);

        // If client sends full URL/path, keep only the uploads/... part.
        $uploadsPos = stripos($value, 'uploads/');
        if ($uploadsPos !== false) {
            return substr($value, $uploadsPos);
        }

        return ltrim($value, '/');
    }

    /**
     * Convert DB/request payload (string|json|array) into normalized relative upload paths.
     *
     * @param mixed $value
     * @return array<int, string>
     */
    private function normalizeUploadsRelativePaths($value): array
    {
        if (is_null($value)) {
            return [];
        }

        $items = [];
        if (is_array($value)) {
            $items = $value;
        } elseif (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return [];
            }
            $decoded = json_decode($trimmed, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $items = $decoded;
            } else {
                $items = [$trimmed];
            }
        } else {
            $items = [(string) $value];
        }

        return collect($items)
            ->map(function ($item): ?string {
                if (is_null($item)) {
                    return null;
                }
                return $this->normalizeUploadsRelativePath((string) $item);
            })
            ->filter(fn($item) => is_string($item) && $item !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Build full clickable URLs for storage-relative upload paths.
     *
     * @param array<int, string> $paths
     * @return array<int, string>
     */
    private function toPublicStorageUrls(array $paths): array
    {
        return collect($paths)
            ->map(function (string $path): string {
                $trimmed = trim($path);
                if ($trimmed === '') {
                    return '';
                }
                if (preg_match('#^https?://#i', $trimmed) === 1) {
                    return $trimmed;
                }
                return url('/storage/' . ltrim($trimmed, '/'));
            })
            ->filter(fn(string $url) => $url !== '')
            ->values()
            ->all();
    }

    /**
     * Store incoming DHPO documents in public/storage/uploads and return merged JSON array for DB.
     */
    private function resolveDhpoPrescriptionDocument(Request $request, $existingValue = null, bool $allowClear = false): ?string
    {
        $existingPaths = $this->normalizeUploadsRelativePaths($existingValue);
        $storedPaths = [];
        $fileKeys = ['dhpo_prescription_document', 'dhpo_prescription_document_file', 'documents', 'document', 'files', 'file'];
        foreach ($fileKeys as $fileKey) {
            if (!$request->hasFile($fileKey)) {
                continue;
            }

            $incomingFiles = $request->file($fileKey);
            if (!is_array($incomingFiles)) {
                $incomingFiles = [$incomingFiles];
            }

            foreach ($incomingFiles as $fileIndex => $incomingFile) {
                if (!($incomingFile instanceof UploadedFile) || !$incomingFile->isValid()) {
                    continue;
                }

                $relativeDir = 'uploads';
                $targetDir = public_path('storage/' . $relativeDir);

                if (!is_dir($targetDir)) {
                    @mkdir($targetDir, 0755, true);
                }

                $originalName = $this->resolveIncomingOriginalFileName($request, $fileKey, (int) $fileIndex, $incomingFile);
                $fileName = basename(str_replace('\\', '/', $originalName));
                $rawClientOriginalName = (string) $incomingFile->getClientOriginalName();
                $nameBeforeSanitize = $fileName;
                // Remove unsafe characters while keeping the original readable filename.
                $fileName = preg_replace('/[^A-Za-z0-9._ -]/', '', (string) $fileName) ?? '';
                if ($fileName === '' || $fileName === '.' || $fileName === '..') {
                    $extension = strtolower((string) $incomingFile->getClientOriginalExtension());
                    $safeExtension = $extension !== '' ? $extension : 'bin';
                    $fileName = 'upload.' . $safeExtension;
                }

                try {
                    $incomingFile->move($targetDir, $fileName);
                    $storedPaths[] = $relativeDir . '/' . $fileName;
                } catch (\Throwable $e) {
                    Log::warning('Failed moving DHPO document to public/storage/uploads, falling back to storage disk.', [
                        'exception' => get_class($e),
                        'message' => $e->getMessage(),
                    ]);

                    $storedPaths[] = $incomingFile->storeAs($relativeDir, $fileName, 'public');
                }
            }
        }

        if (count($storedPaths) > 0) {
            $merged = array_values(array_unique(array_merge($existingPaths, $storedPaths)));
            return json_encode($merged, JSON_UNESCAPED_SLASHES);
        }

        if ($request->exists('dhpo_prescription_document')) {
            $paths = $this->normalizeUploadsRelativePaths($request->input('dhpo_prescription_document'));
            if ($allowClear && count($paths) === 0) {
                return null;
            }
            $merged = array_values(array_unique(array_merge($existingPaths, $paths)));
            return count($merged) > 0 ? json_encode($merged, JSON_UNESCAPED_SLASHES) : null;
        }

        return count($existingPaths) > 0 ? json_encode($existingPaths, JSON_UNESCAPED_SLASHES) : null;
    }

    /**
     * Prefer explicit original filename sent by client for multipart blob uploads.
     */
    private function resolveIncomingOriginalFileName(
        Request $request,
        string $fileKey,
        int $fileIndex,
        UploadedFile $incomingFile
    ): string {
        $candidates = [
            'dhpo_prescription_document_original_name',
            'dhpo_prescription_document_original_names',
            'original_name',
            'original_names',
            'file_name',
            'file_names',
        ];

        foreach ($candidates as $candidateKey) {
            if (!$request->exists($candidateKey)) {
                continue;
            }

            $raw = $request->input($candidateKey);
            if (is_array($raw)) {
                $fromArray = (string) ($raw[$fileIndex] ?? '');
                if (trim($fromArray) !== '') {
                    return $fromArray;
                }
                continue;
            }

            if (is_string($raw) && trim($raw) !== '') {
                // If multiple file fields exist, only accept global scalar for dhpo key.
                if ($fileKey === 'dhpo_prescription_document' || $fileKey === 'dhpo_prescription_document_file') {
                    return $raw;
                }
            }
        }

        return (string) $incomingFile->getClientOriginalName();
    }

    /**
     * Canonical keys stored in patient_emr_reports.vital_details (JSON object).
     */
    private function canonicalVitalDetailKeys(): array
    {
        return [
            'blood_pressure',
            'pulse_rate',
            'breathing_rate',
            'stress_index',
            'bmi',
            'bmi_category',
            'spo2',
            'spo2_air_type',
            'temperature',
            'weight',
            'height',
        ];
    }

    /**
     * Map request/legacy/AI aliases into canonical vital_details keys.
     */
    private function normalizeVitalDetailsArray($raw): array
    {
        if (!is_array($raw)) {
            $raw = [];
        }

        $aliases = [
            'blood_pressure' => ['blood_pressure', 'bloodPressure', 'bp', 'BP', 'blood_pressure_mmhg'],
            'pulse_rate' => ['pulse_rate', 'pulse', 'pulseRate', 'pr', 'heart_rate', 'heartRate', 'hr'],
            'breathing_rate' => ['breathing_rate', 'respiratory_rate', 'respiratoryRate', 'respiration_rate', 'rr', 'breathingRate'],
            'stress_index' => ['stress_index', 'stressIndex', 'stress', 'stress_level', 'stressLevel'],
            'bmi' => ['bmi', 'BMI', 'body_mass_index', 'bodyMassIndex'],
            'bmi_category' => ['bmi_category', 'bmiCategory', 'bmi_status', 'bmiStatus'],
            'spo2' => ['spo2', 'SpO2', 'oxygen_saturation', 'oxygenSaturation', 'o2_sat', 'o2Sat', 'spo'],
            'spo2_air_type' => ['spo2_air_type', 'spo2AirType', 'oxygen_air_type', 'room_air', 'air_type', 'spo2_context'],
            'temperature' => ['temperature', 'temp', 't', 'body_temperature', 'bodyTemperature'],
            'weight' => ['weight', 'wt', 'body_weight', 'bodyWeight'],
            'height' => ['height', 'ht', 'body_height', 'bodyHeight'],
        ];

        $out = [];
        foreach ($aliases as $canonical => $keys) {
            $out[$canonical] = '';
            foreach ($keys as $k) {
                if (!array_key_exists($k, $raw)) {
                    continue;
                }
                $v = $raw[$k];
                if ($v === null || $v === '') {
                    continue;
                }
                $out[$canonical] = is_scalar($v) ? trim((string) $v) : '';
                break;
            }
        }

        return $out;
    }

    private function mergeVitalDetailsPreferIncoming(array $existing, array $incoming): array
    {
        $base = $this->normalizeVitalDetailsArray($existing);
        $new = $this->normalizeVitalDetailsArray($incoming);
        foreach ($this->canonicalVitalDetailKeys() as $key) {
            if (($new[$key] ?? '') !== '') {
                $base[$key] = $new[$key];
            }
        }

        return $base;
    }

    private function aiReportToArray($report): array
    {
        if ($report === null) {
            return [];
        }
        if (is_array($report)) {
            return $report;
        }
        if (is_object($report)) {
            return json_decode(json_encode($report), true) ?? [];
        }

        return [];
    }

    private function pickAiValue(array $r, array $keys)
    {
        foreach ($keys as $k) {
            if (array_key_exists($k, $r) && $r[$k] !== null && $r[$k] !== '') {
                return $r[$k];
            }
        }

        return null;
    }

    private function stressLevelToLabel($value): string
    {
        if (is_string($value) && trim($value) !== '' && !is_numeric(trim($value))) {
            return trim($value);
        }
        $n = (int) round((float) $value);
        $map = [
            0 => 'Relaxed',
            1 => 'Mild',
            2 => 'Moderate',
            3 => 'Moderately Elevated',
            4 => 'Highly Elevated',
        ];

        return $map[$n] ?? (string) $value;
    }

    private function bmiCategoryFromValue(float $bmi): string
    {
        if ($bmi <= 0) {
            return '';
        }
        if ($bmi < 18.5) {
            return 'Underweight';
        }
        if ($bmi < 25) {
            return 'Normal';
        }
        if ($bmi < 30) {
            return 'Overweight';
        }

        return 'Obese';
    }

    /**
     * Fill only empty canonical fields from the latest AI vitals report (camelCase keys).
     */
    private function fillVitalDetailsGapsFromAi(array $vital, $aiReport): array
    {
        $r = $this->aiReportToArray($aiReport);
        if (count($r) === 0) {
            return $vital;
        }

        $empty = fn ($x) => $x === null || $x === '';

        if ($empty($vital['blood_pressure'] ?? '')) {
            $bp = $this->pickAiValue($r, ['bloodPressure', 'blood_pressure']);
            if ($bp !== null) {
                $vital['blood_pressure'] = $this->formatVitalBloodPressure((string) $bp);
            }
        }

        if ($empty($vital['pulse_rate'] ?? '')) {
            $hr = $this->pickAiValue($r, ['heartRate', 'pulse_rate', 'pulse']);
            if ($hr !== null) {
                $vital['pulse_rate'] = $this->appendUnitIfMissing((string) $hr, 'bpm');
            }
        }

        if ($empty($vital['breathing_rate'] ?? '')) {
            $br = $this->pickAiValue($r, ['respiratoryRate', 'breathing_rate', 'respiratory_rate', 'rr']);
            if ($br !== null) {
                $vital['breathing_rate'] = $this->appendUnitIfMissing((string) $br, 'breaths/min');
            }
        }

        if ($empty($vital['stress_index'] ?? '')) {
            $st = $this->pickAiValue($r, ['stressLevel', 'stress_index', 'stressIndex']);
            if ($st !== null) {
                $vital['stress_index'] = $this->stressLevelToLabel($st);
            }
        }

        if ($empty($vital['bmi'] ?? '')) {
            $b = $this->pickAiValue($r, ['bmi', 'BMI']);
            if ($b !== null) {
                $fv = (float) $b;
                $vital['bmi'] = $this->appendUnitIfMissing((string) $b, 'kg/m²');
                if ($empty($vital['bmi_category'] ?? '')) {
                    $cat = $this->bmiCategoryFromValue($fv);
                    if ($cat !== '') {
                        $vital['bmi_category'] = $cat;
                    }
                }
            }
        }

        if ($empty($vital['spo2'] ?? '')) {
            $o = $this->pickAiValue($r, ['spo2', 'SpO2', 'oxygen_saturation', 'o2_sat']);
            if ($o !== null) {
                $vital['spo2'] = $this->appendUnitIfMissing((string) $o, '%', ['%']);
            }
        }

        if ($empty($vital['temperature'] ?? '')) {
            $t = $this->pickAiValue($r, ['temperature', 'temp', 'bodyTemperature']);
            if ($t !== null) {
                $vital['temperature'] = $this->appendTemperatureUnit((string) $t);
            }
        }

        if ($empty($vital['weight'] ?? '')) {
            $w = $this->pickAiValue($r, ['weight', 'wt', 'bodyWeight']);
            if ($w !== null) {
                $vital['weight'] = $this->appendUnitIfMissing((string) $w, 'kg');
            }
        }

        if ($empty($vital['height'] ?? '')) {
            $h = $this->pickAiValue($r, ['height', 'ht', 'bodyHeight']);
            if ($h !== null) {
                $vital['height'] = $this->appendUnitIfMissing((string) $h, 'cm');
            }
        }

        if ($empty($vital['spo2_air_type'] ?? '')) {
            $air = $this->pickAiValue($r, ['spo2_air_type', 'airType', 'roomAir', 'oxygen_air']);
            if ($air !== null) {
                $vital['spo2_air_type'] = (string) $air;
            }
        }

        return $vital;
    }

    private function formatVitalBloodPressure(string $bp): string
    {
        $bp = trim($bp);
        if ($bp === '') {
            return '';
        }
        if (stripos($bp, 'mmhg') !== false || stripos($bp, 'mm hg') !== false) {
            return $bp;
        }

        return $bp . ' mmHg';
    }

    private function appendUnitIfMissing(string $value, string $unit, array $also = []): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        $lower = strtolower($value);
        foreach (array_merge([$unit], $also) as $u) {
            if ($u !== '' && str_contains($lower, strtolower($u))) {
                return $value;
            }
        }

        return $value . ' ' . $unit;
    }

    private function appendTemperatureUnit(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        $lower = strtolower($value);
        if (str_contains($lower, '°f') || str_contains($lower, '°c')
            || str_contains($lower, 'fahrenheit') || str_contains($lower, 'celsius')) {
            return $value;
        }
        if (preg_match('/[°]/u', $value)) {
            return $value;
        }

        return $value . '°F';
    }

    /**
     * Pretty strings for EMR PDF (matches clinical report formatting).
     */
    private function formatVitalDetailsForEmrPdf(array $v): array
    {
        $bp = trim((string) ($v['blood_pressure'] ?? ''));
        if ($bp !== '' && stripos($bp, 'mmhg') === false && stripos($bp, 'mm hg') === false) {
            $bp = $this->formatVitalBloodPressure($bp);
        }

        $pulse = trim((string) ($v['pulse_rate'] ?? ''));
        if ($pulse !== '') {
            $pulse = $this->appendUnitIfMissing($pulse, 'bpm', ['bpm']);
        }

        $breath = trim((string) ($v['breathing_rate'] ?? ''));
        if ($breath !== '') {
            $breath = $this->appendUnitIfMissing($breath, 'breaths/min', ['breath']);
        }

        $spo2Base = trim((string) ($v['spo2'] ?? ''));
        $air = trim((string) ($v['spo2_air_type'] ?? ''));
        $spo2Line = $spo2Base;
        if ($spo2Base !== '' && strpos($spo2Base, '%') === false) {
            $spo2Line = $this->appendUnitIfMissing($spo2Base, '%', ['%']);
        }
        if ($spo2Line !== '' && $air !== '') {
            $spo2Line .= ' (' . $air . ')';
        }

        $temp = trim((string) ($v['temperature'] ?? ''));
        if ($temp !== '') {
            $temp = $this->appendTemperatureUnit($temp);
        }

        $weight = trim((string) ($v['weight'] ?? ''));
        if ($weight !== '') {
            $weight = $this->appendUnitIfMissing($weight, 'kg');
        }

        $height = trim((string) ($v['height'] ?? ''));
        if ($height !== '') {
            $height = $this->appendUnitIfMissing($height, 'cm');
        }

        return [
            'blood_pressure' => $bp !== '' ? $bp : 'N/A',
            'pulse_rate' => $pulse !== '' ? $pulse : 'N/A',
            'breathing_rate' => $breath !== '' ? $breath : 'N/A',
            'spo2' => $spo2Line !== '' ? $spo2Line : 'N/A',
            'temperature' => $temp !== '' ? $temp : 'N/A',
            'weight' => $weight !== '' ? $weight : 'N/A',
            'height' => $height !== '' ? $height : 'N/A',
        ];
    }

    /**
     * After manual snapshot merge, fill empty vitals from AI scan (same appointment).
     */
    private function augmentStoredVitalsWithAi(PatientEmrReport $emr): void
    {
        if (!$this->canWriteColumn('vital_details')) {
            return;
        }
        $current = json_decode($emr->vital_details ?? '{}', true) ?? [];
        $normalized = $this->normalizeVitalDetailsArray($current);
        $ai = $this->getLatestAiVitalsForEmr((int) $emr->appointment_id);
        $filled = $this->fillVitalDetailsGapsFromAi($normalized, $ai['report'] ?? null);
        $emr->vital_details = json_encode($filled);
    }

    private function syncAppointmentAndJitsiMeta(PatientEmrReport $emr): void
    {
        $appointmentId = (int) ($emr->appointment_id ?? 0);
        if ($appointmentId <= 0) {
            return;
        }

        $patientAppointment = Appointments::query()->find($appointmentId);
        $appointmentUserId = (int) ($patientAppointment?->user_id ?? 0);
        if ($appointmentUserId <= 0) {
            $appointmentUserId = (int) (Appointments::query()
                ->where('id', $appointmentId)
                ->value('user_id') ?? 0);
        }

        $appointmentNumber = (string) ($patientAppointment?->appointment_number ?? '');

        $meeting = JitsiMeeting::query()
            ->where('appointment_id', $appointmentId)
            ->latest('id')
            ->first();
        $room = (string) ($meeting?->room ?? '');
        $jitsiId = $meeting?->id;

        if ($appointmentNumber !== '') {
            foreach (['appointment_number', 'appointment_no'] as $col) {
                if ($this->canWriteColumn($col)) {
                    $emr->{$col} = $appointmentNumber;
                }
            }
        }

        if ($room !== '') {
            foreach (['room', 'jitsi_room', 'jitsi_meeting_room'] as $col) {
                if ($this->canWriteColumn($col)) {
                    $emr->{$col} = $room;
                }
            }
        }

        if (!is_null($jitsiId)) {
            foreach (['jitsi_number', 'jitsi_meeting_id'] as $col) {
                if ($this->canWriteColumn($col)) {
                    $emr->{$col} = (int) $jitsiId;
                }
            }
        }

        if ($appointmentUserId > 0 && $this->canWriteColumn('user_id')) {
            $emr->user_id = $appointmentUserId;
        }
    }

    private function emrResponseData(PatientEmrReport $emr): array
    {
        $vitalDecoded = json_decode($emr->vital_details ?? '{}', true) ?? [];
        $dhpoDocuments = $this->normalizeUploadsRelativePaths($emr->dhpo_prescription_document ?? null);
        $appointmentId = (int) ($emr->appointment_id ?? 0);
        $appointmentNumber = '';
        $jitsiRoom = '';
        $jitsiNumber = null;
        if ($appointmentId > 0) {
            $patientAppointment = Appointments::query()->find($appointmentId);
            $appointmentNumber = (string) ($patientAppointment?->appointment_number ?? '');

            $meeting = JitsiMeeting::query()
                ->where('appointment_id', $appointmentId)
                ->latest('id')
                ->first();
            $jitsiRoom = (string) ($meeting?->room ?? '');
            $jitsiNumber = $meeting?->id;
        }

        return [
            'id' => $emr->id,
            'appointment_id' => $emr->appointment_id,
            'doctor_id' => $emr->doctor_id,
            'appointment_number' => $appointmentNumber,
            'jitsi_room' => $jitsiRoom,
            'jitsi_number' => $jitsiNumber,
            'is_finalized' => (bool) $emr->is_finalized,
            'finalized_at' => $emr->finalized_at,
            'vital_details' => Arr::except($this->normalizeVitalDetailsArray($vitalDecoded), ['stress_index', 'bmi', 'bmi_category']),
            'chief_complaints' => $this->normalizeList($emr->chief_complaints),
            'symptoms' => $this->normalizeList($emr->symptoms),
            'allergies' => $this->normalizeList($emr->allergies),
            'history_of_present_illness' => $emr->history_of_present_illness ?? '',
            'diagnosis' => json_decode($emr->diagnosis ?? '[]', true) ?? [],
            'lab_orders' => $this->normalizeList($emr->lab_orders),
            'radiology_orders' => $this->normalizeList($emr->radiology_orders ?? null),
            'dhpo_prescription_document' => $dhpoDocuments,
            'dhpo_prescription_document_urls' => $this->toPublicStorageUrls($dhpoDocuments),
            'dhpo_prescriptions' => json_decode($emr->dhpo_prescriptions ?? '[]', true) ?? [],
            'speciality_hospital_reference' => $emr->speciality_hospital_reference ?? '',
            'follow_up_date' => $emr->follow_up_date ?? null,
        ];
    }

    private function buildCompletionFlags(PatientEmrReport $emr): array
    {
        $vitals = json_decode($emr->vital_details ?? '{}', true);
        $diagnosis = json_decode($emr->diagnosis ?? '[]', true);
        $prescriptions = json_decode($emr->dhpo_prescriptions ?? '[]', true);

        return [
            'vitals' => is_array($vitals) && count(array_filter($vitals, fn($v) => !is_null($v) && $v !== '')) > 0,
            'chief_complaints' => count($this->normalizeList($emr->chief_complaints)) > 0,
            'symptoms' => count($this->normalizeList($emr->symptoms)) > 0,
            'allergies' => count($this->normalizeList($emr->allergies)) > 0,
            'history_of_present_illness' => trim((string) ($emr->history_of_present_illness ?? '')) !== '',
            'icd_diagnosis' => is_array($diagnosis) && count($diagnosis) > 0,
            'lab_orders' => count($this->normalizeList($emr->lab_orders)) > 0,
            'radiology_orders' => count($this->normalizeList($emr->radiology_orders)) > 0,
            'prescriptions' => is_array($prescriptions) && count($prescriptions) > 0,
            'referral' => trim((string) ($emr->speciality_hospital_reference ?? '')) !== '',
            'follow_up' => !is_null($emr->follow_up_date) && (string) $emr->follow_up_date !== '',
        ];
    }

    private function resolveReportFromRequest(Request $request): ?PatientEmrReport
    {
        $reportId = $request->input('report_id');
        $appointmentId = $request->input('appointment_id');

        // Support GET with raw JSON body (Postman users often send body with GET).
        if (is_null($reportId) && is_null($appointmentId)) {
            $decoded = json_decode((string) $request->getContent(), true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $reportId = $decoded['report_id'] ?? null;
                $appointmentId = $decoded['appointment_id'] ?? null;
            }
        }

        if (!is_null($reportId) && $reportId !== '') {
            return PatientEmrReport::where('id', (int) $reportId)->first();
        }

        if (!is_null($appointmentId) && $appointmentId !== '') {
            return PatientEmrReport::where('appointment_id', (int) $appointmentId)->first();
        }

        return null;
    }

    private function resolveDisplayDateTime(?Appointments $appointment, PatientEmrReport $emr): array
    {
        $date = $appointment->appointment_date ?? null;
        $time = $appointment->time ?? ($appointment->appointment_time ?? null);

        $looksLikeInvalidNumericTime = is_string($time) && preg_match('/^\d{3,4}$/', trim($time)) === 1;
        $hasValidTime = is_string($time) && trim($time) !== '' && !$looksLikeInvalidNumericTime;

        if (is_null($date) && $emr->created_at) {
            $date = $emr->created_at->format('Y-m-d');
        }

        return [
            'appointment_date' => $this->normalizeDisplayDate($date),
            'appointment_time' => $time,
        ];
    }

    private function normalizeDisplayDate($date): ?string
    {
        $raw = trim((string) ($date ?? ''));
        if ($raw === '') {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($raw)->format('d/m/Y');
        } catch (\Throwable $exception) {
            return $raw;
        }
    }

    private function normalizeDisplayTime($time, ?PatientEmrReport $emr = null): ?string
    {
        $raw = trim((string) ($time ?? ''));
        if ($raw === '') {
            return null;
        }

        if (preg_match('/^\d{3,4}$/', $raw) === 1) {
            $padded = str_pad($raw, 4, '0', STR_PAD_LEFT);
            $formatted = substr($padded, 0, 2) . ':' . substr($padded, 2, 2);
            try {
                return \Carbon\Carbon::createFromFormat('H:i', $formatted)->format('h:i A');
            } catch (\Throwable $exception) {
                return $raw;
            }
        }

        try {
            return \Carbon\Carbon::parse($raw)->format('h:i A');
        } catch (\Throwable $exception) {
            return $raw;
        }
    }

    private function resolvePatientName(?Appointments $appointment, $user = null): ?string
    {
        $fromUsers = [
            trim((string) ($user?->fullname ?? '')),
            trim((string) ($user?->name ?? '')),
            trim((string) (($user?->first_name ?? '') . ' ' . ($user?->last_name ?? ''))),
        ];

        foreach ($fromUsers as $name) {
            if ($name !== '') {
                return $name;
            }
        }

        $fromAppointment = [
            trim((string) ($appointment?->patient_name ?? '')),
            trim((string) ($appointment?->full_name ?? '')),
            trim((string) ($appointment?->name ?? '')),
        ];

        foreach ($fromAppointment as $name) {
            if ($name !== '') {
                return $name;
            }
        }

        return null;
    }

    private function resolveUserIdFromAppointment(?Appointments $appointment): int
    {
        $userId = (int) ($appointment?->user_id ?? 0);
        if ($userId > 0) {
            return $userId;
        }

        return 0;
    }

    private function resolvePatientUser(?Appointments $appointment): ?Users
    {
        $cacheKey = (string) ($appointment?->id ?? '0');
        if (array_key_exists($cacheKey, $this->userLookupCache)) {
            return $this->userLookupCache[$cacheKey];
        }

        $candidateIds = array_values(array_unique(array_filter([
            (int) ($appointment?->user_id ?? 0),
        ], fn($id) => $id > 0)));

        foreach ($candidateIds as $candidateId) {
            $user = Users::query()->find($candidateId);
            if ($user) {
                return $this->userLookupCache[$cacheKey] = $user;
            }
        }

        return $this->userLookupCache[$cacheKey] = null;
    }

    private function resolveDoctorId(Request $request): int
    {
        if ($request->filled('doctor_id')) {
            return (int) $request->doctor_id;
        }

        $actorType = (string) $request->attributes->get('actor_type', '');
        $actorId = (int) $request->attributes->get('actor_id', 0);
        if ($actorType === 'Doctor' && $actorId > 0) {
            return $actorId;
        }

        return 0;
    }

    private function parseAiVitalsReport($rawReport): ?array
    {
        if (is_array($rawReport)) {
            return $rawReport;
        }
        if (is_object($rawReport)) {
            return (array) $rawReport;
        }
        if (!is_string($rawReport) || trim($rawReport) === '') {
            return null;
        }

        $decoded = json_decode($rawReport, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        return null;
    }

    private function getLatestAiVitalsForEmr(int $appointmentId): ?array
    {
        if ($appointmentId <= 0) {
            return null;
        }

        $appointment = Appointments::query()->find($appointmentId);
        $query = AI_Vital::query()->where('appointment_id', $appointmentId);
        if (!is_null($appointment?->user_id)) {
            $query->where('user_id', (int) $appointment->user_id);
        }

        $aiVital = $query->latest('id')->first();
        if (!$aiVital) {
            return null;
        }

        return [
            'id' => (int) $aiVital->id,
            'user_id' => (int) ($aiVital->user_id ?? 0),
            'appointment_id' => (int) ($aiVital->appointment_id ?? 0),
            'scan_date' => $aiVital->scan_date ?? null,
            'report' => $this->parseAiVitalsReport($aiVital->report ?? null),
        ];
    }

    private function handleException(\Throwable $exception, string $method)
    {
        Log::error('Patient EMR report controller error', [
            'method' => $method,
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
        ]);

        return response()->json([
            'status' => false,
            'message' => 'Internal server error',
        ], 500);
    }

    public function fetch(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'appointment_id' => 'required|integer',
                'doctor_id' => 'required|integer',
            ]);
            if ($validator->fails()) {
                return response()->json(['status' => false, 'message' => Arr::first($validator->errors()->all())]);
            }
            $appointment = $this->validateAppointmentForDoctor((int) $request->appointment_id, (int) $request->doctor_id);
            if (!$appointment) {
                return GlobalFunction::sendSimpleResponse(false, 'Appointment not found for this doctor!');
            }
            $emr = $this->getOrCreateEmrReport((int) $request->appointment_id, (int) $request->doctor_id);
            return GlobalFunction::sendDataResponse(true, 'EMR data fetched successfully', $this->emrResponseData($emr));
        } catch (\Throwable $exception) {
            return $this->handleException($exception, __METHOD__);
        }
    }

    public function getEmrTableData(Request $request)
    {
        try {
            $payload = $request->all();
            if (count($payload) === 0) {
                $decoded = json_decode((string) $request->getContent(), true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $payload = $decoded;
                    $request->merge($decoded);
                }
            }

            $validator = Validator::make($payload, [
                'doctor_id' => 'nullable|integer|min:1',
                'search' => 'nullable|string|max:100',
                'limit' => 'nullable|integer|min:1|max:200',
            ]);
            if ($validator->fails()) {
                return response()->json(['status' => false, 'message' => Arr::first($validator->errors()->all())]);
            }

            $limit = (int) ($payload['limit'] ?? 50);
            $search = mb_strtolower(trim((string) ($payload['search'] ?? '')));
            $doctorId = $this->resolveDoctorId($request);
            if ($doctorId <= 0) {
                return response()->json(['status' => false, 'message' => 'doctor_id is required']);
            }

            $query = PatientEmrReport::latest('id');
            $query->where('doctor_id', $doctorId);

            $rows = $query->limit($limit)->get();
            $appointmentIds = $rows->pluck('appointment_id')->map(fn($id) => (int) $id)->filter(fn($id) => $id > 0)->unique()->values();
            $appointmentsById = collect();
            if ($appointmentIds->isNotEmpty()) {
                $appointmentsById = Appointments::query()
                    ->with(['user'])
                    ->whereIn('id', $appointmentIds->all())
                    ->get()
                    ->keyBy('id');
            }

            $data = $rows->map(function (PatientEmrReport $emr) use ($search, $appointmentsById) {
                $regularAppointment = $appointmentsById->get((int) $emr->appointment_id);
                $user = $regularAppointment?->user;

                $patientName = $this->resolvePatientName($regularAppointment, $user);
                $appointmentNumber = $regularAppointment?->appointment_number ?? null;
                $mrnNo = $this->buildMrnNo((int) ($emr->id ?? 0), (int) ($emr->appointment_id ?? 0));
                $serviceName = $regularAppointment?->speciality_name ?? null;
                $patientType = match ((int) ($regularAppointment?->type ?? -1)) {
                    0 => 'Online',
                    1 => 'Offline',
                    default => 'Online',
                };
                $displayDateTime = $this->resolveDisplayDateTime($regularAppointment, $emr);
                if (!is_null($regularAppointment)) {
                    $displayDateTime['appointment_date'] = $this->normalizeDisplayDate($regularAppointment->date)
                        ?? $displayDateTime['appointment_date'];
                    $displayDateTime['appointment_time'] = $this->normalizeDisplayTime(
                        $regularAppointment->time ?? ($regularAppointment->appointment_time ?? null),
                        $emr
                    );
                }

                if ($search !== '') {
                    $haystack = mb_strtolower(implode(' ', array_filter([
                        (string) $patientName,
                        (string) ($mrnNo ?? ''),
                        (string) ($serviceName ?? ''),
                    ])));
                    if ($haystack === '' || mb_stripos($haystack, $search) === false) {
                        return null;
                    }
                }
                return [
                    'report_id' => $emr->id,
                    'appointment_id' => $emr->appointment_id,
                    'appointment_number' => $appointmentNumber,
                    'patient_name' => $patientName,
                    'mrn_no' => $mrnNo,
                    'patient_type' => $patientType,
                    'service_name' => $serviceName,
                    'appointment_date' => $displayDateTime['appointment_date'],
                    'appointment_time' => $displayDateTime['appointment_time'],
                    'section_status' => $this->buildCompletionFlags($emr),
                    'is_finalized' => (bool) $emr->is_finalized,
                ];
            })->filter()->values();

            return response()->json([
                'status' => true,
                'message' => 'EMR table data fetched successfully',
                'total' => $data->count(),
                'data' => $data,
            ]);
        } catch (\Throwable $exception) {
            return $this->handleException($exception, __METHOD__);
        }
    }

    public function getEmrViewData(Request $request)
    {
        try {
            $payload = $request->all();
            if (count($payload) === 0) {
                $decoded = json_decode((string) $request->getContent(), true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $payload = $decoded;
                }
            }

            $validator = Validator::make($payload, [
                'report_id' => 'sometimes|nullable|integer',
                'appointment_id' => 'sometimes|nullable|integer',
            ]);
            if ($validator->fails()) {
                return response()->json(['status' => false, 'message' => Arr::first($validator->errors()->all())]);
            }
            $hasReportId = !is_null($payload['report_id'] ?? null) && $payload['report_id'] !== '';
            $hasAppointmentId = !is_null($payload['appointment_id'] ?? null) && $payload['appointment_id'] !== '';
            if (!$hasReportId && !$hasAppointmentId) {
                return response()->json(['status' => false, 'message' => 'report_id or appointment_id is required']);
            }

            $emr = $this->resolveReportFromRequest($request);
            if (!$emr) {
                return response()->json(['status' => false, 'message' => 'EMR report not found']);
            }

            $appointment = Appointments::query()->find((int) $emr->appointment_id);
            if (!$appointment) {
                return response()->json(['status' => false, 'message' => 'Appointment not found']);
            }
            $regularAppointment = Appointments::query()->with(['user'])->find((int) $emr->appointment_id);
            $user = $regularAppointment?->user;

            $displayDateTime = $this->resolveDisplayDateTime($appointment, $emr);

            return response()->json([
                'status' => true,
                'message' => 'EMR view data fetched successfully',
                'data' => array_merge($this->emrResponseData($emr), [
                    // Intentionally excluding mrn_no and service_name per requirement.
                    'patient_name' => $this->resolvePatientName($appointment, $user),
                    'patient_type' => match ((int) ($regularAppointment?->type ?? -1)) {
                        0 => 'Online',
                        1 => 'Offline',
                        default => ($appointment->appointment_type ?? 'Online'),
                    },
                    'appointment_date' => $displayDateTime['appointment_date'],
                    'appointment_time' => $displayDateTime['appointment_time'],
                ]),
            ]);
        } catch (\Throwable $exception) {
            return $this->handleException($exception, __METHOD__);
        }
    }

    public function getEmrEditData(Request $request)
    {
        try {
            $payload = $request->all();
            if (count($payload) === 0) {
                $decoded = json_decode((string) $request->getContent(), true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $payload = $decoded;
                }
            }

            $validator = Validator::make($payload, [
                'report_id' => 'sometimes|nullable|integer',
                'appointment_id' => 'sometimes|nullable|integer',
            ]);
            if ($validator->fails()) {
                return response()->json(['status' => false, 'message' => Arr::first($validator->errors()->all())]);
            }
            $hasReportId = !is_null($payload['report_id'] ?? null) && $payload['report_id'] !== '';
            $hasAppointmentId = !is_null($payload['appointment_id'] ?? null) && $payload['appointment_id'] !== '';
            if (!$hasReportId && !$hasAppointmentId) {
                return response()->json(['status' => false, 'message' => 'report_id or appointment_id is required']);
            }

            $emr = $this->resolveReportFromRequest($request);
            if (!$emr) {
                return response()->json(['status' => false, 'message' => 'EMR report not found']);
            }

            return response()->json([
                'status' => true,
                'message' => 'EMR edit data fetched successfully',
                'data' => $this->emrResponseData($emr), // Excludes mrn_no and service_name.
            ]);
        } catch (\Throwable $exception) {
            return $this->handleException($exception, __METHOD__);
        }
    }

    public function updateEmrEditData(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'appointment_id' => 'required|integer',
                'doctor_id' => 'nullable|integer',
                'vital_details' => 'nullable|array',
                'chief_complaints' => 'nullable|array',
                'symptoms' => 'nullable|array',
                'allergies' => 'nullable|array',
                'history_of_present_illness' => 'nullable|string',
                'diagnosis' => 'nullable|array',
                'lab_orders' => 'nullable|array',
                'radiology_orders' => 'nullable|array',
                'dhpo_prescriptions' => 'nullable|array',
                'dhpo_prescription_document' => 'nullable',
                'speciality_hospital_reference' => 'nullable|string|max:2000',
                'follow_up_date' => 'nullable|date',
                'lang' => 'nullable|string|max:10',
            ]);
            if ($validator->fails()) {
                return response()->json(['status' => false, 'message' => Arr::first($validator->errors()->all())]);
            }

            $appointment = Appointments::where('id', (int) $request->appointment_id)->first();
            if (!$appointment) {
                return response()->json(['status' => false, 'message' => 'Appointment not found']);
            }

            $doctorId = $this->resolveEffectiveDoctorId($appointment, (int) ($request->doctor_id ?? 0));
            if ($doctorId <= 0) {
                return response()->json(['status' => false, 'message' => 'doctor_id is required']);
            }

            $emr = $this->getOrCreateEmrReport((int) $request->appointment_id, $doctorId);
            $this->applyEmrSnapshotIfPresent($request, $emr, true);
            $this->augmentStoredVitalsWithAi($emr);

            // Editing should keep record as draft unless finalized explicitly by save-final.
            if ($this->canWriteColumn('is_finalized')) {
                $emr->is_finalized = 0;
            }
            if ($this->canWriteColumn('finalized_at')) {
                $emr->finalized_at = null;
            }

            $emr->save();

            return response()->json([
                'status' => true,
                'message' => 'EMR data updated successfully',
                'data' => $this->emrResponseData($emr),
            ]);
        } catch (\Throwable $exception) {
            return $this->handleException($exception, __METHOD__);
        }
    }

    public function saveDraftGet(Request $request)
    {
        // Support GET clients by merging raw JSON body before reusing saveDraft logic.
        $decoded = json_decode((string) $request->getContent(), true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            // Query params remain highest priority; body fills missing keys.
            $request->merge(array_merge($decoded, $request->all()));
        }

        return $this->saveDraft($request);
    }

    public function getSaveDraftDetail(Request $request)
    {
        try {
            $payload = $request->all();
            if (count($payload) === 0) {
                $decoded = json_decode((string) $request->getContent(), true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $payload = $decoded;
                }
            }

            $validator = Validator::make($payload, [
                'report_id' => 'sometimes|nullable|integer',
                'appointment_id' => 'sometimes|nullable|integer',
            ]);
            if ($validator->fails()) {
                return response()->json(['status' => false, 'message' => Arr::first($validator->errors()->all())]);
            }

            $hasReportId = !is_null($payload['report_id'] ?? null) && $payload['report_id'] !== '';
            $hasAppointmentId = !is_null($payload['appointment_id'] ?? null) && $payload['appointment_id'] !== '';
            if (!$hasReportId && !$hasAppointmentId) {
                return response()->json(['status' => false, 'message' => 'report_id or appointment_id is required']);
            }

            $emr = $this->resolveReportFromRequest($request);
            if (!$emr) {
                return response()->json(['status' => false, 'message' => 'EMR report not found']);
            }

            $data = $this->emrResponseData($emr);
            $appointmentUserId = null;
            $regularAppointment = Appointments::query()
                ->select(['id', 'user_id'])
                ->find((int) $emr->appointment_id);
            if (!is_null($regularAppointment?->user_id)) {
                $appointmentUserId = (int) $regularAppointment->user_id;
            }
            $data['user_id'] = $appointmentUserId;
            $aiVitals = $this->getLatestAiVitalsForEmr((int) $emr->appointment_id);
            if ($aiVitals) {
                if ($appointmentUserId === null && !empty($aiVitals['user_id'])) {
                    $data['user_id'] = (int) $aiVitals['user_id'];
                }
            }

            $data['vital_details'] = $this->fillVitalDetailsGapsFromAi(
                $data['vital_details'] ?? [],
                is_array($aiVitals) ? ($aiVitals['report'] ?? null) : null
            );

            return response()->json([
                'status' => true,
                'message' => 'EMR save draft detail fetched successfully',
                'data' => $data,
            ]);
        } catch (\Throwable $exception) {
            return $this->handleException($exception, __METHOD__);
        }
    }

    public function addDhpoPrescriptionItem(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'appointment_id' => 'required|integer',
                'doctor_id' => 'required|integer',
                'drug_name' => 'nullable|string',
                'drug_names' => 'nullable|array|max:1',
                'drug_names.*' => 'nullable|string|max:255',
                'unit' => 'nullable|string|max:100',
                'frequency' => 'nullable|string|max:100',
                'duration' => 'nullable|string|max:100',
                'total_quantity' => 'nullable|string|max:100',
                'route_of_admin' => 'nullable|string|max:100',
                'special_instruction' => 'nullable|string|max:500',
            ]);
            if ($validator->fails()) {
                return response()->json(['status' => false, 'message' => Arr::first($validator->errors()->all())]);
            }
            if (!$request->has('drug_names') && !$request->filled('drug_name')) {
                return response()->json(['status' => false, 'message' => 'drug_names is required']);
            }

            $appointment = $this->validateAppointmentForDoctor((int) $request->appointment_id, (int) $request->doctor_id);
            if (!$appointment) {
                return GlobalFunction::sendSimpleResponse(false, 'Appointment not found for this doctor!');
            }

            $emr = $this->getOrCreateEmrReport((int) $request->appointment_id, (int) $request->doctor_id);
            $existing = json_decode($emr->dhpo_prescriptions ?? '[]', true);
            if (!is_array($existing)) {
                $existing = [];
            }

            // Store all provided drug names as a single string in one record.
            $drugNames = $this->extractDrugNames($request);

            if (count($drugNames) === 0) {
                return response()->json(['status' => false, 'message' => 'No valid drug names found']);
            }
            if (count($drugNames) > 1) {
                return response()->json(['status' => false, 'message' => 'Only one drug name is allowed per prescription item']);
            }

            $existing[] = [
                'drug_name' => $drugNames[0],
                'unit' => trim((string) ($request->unit ?? '')),
                'frequency' => trim((string) ($request->frequency ?? '')),
                'duration' => trim((string) ($request->duration ?? '')),
                'total_quantity' => trim((string) ($request->total_quantity ?? '')),
                'route_of_admin' => trim((string) ($request->route_of_admin ?? '')),
                'special_instruction' => trim((string) ($request->special_instruction ?? '')),
            ];

            if (!$this->canWriteColumn('dhpo_prescriptions')) {
                return response()->json(['status' => false, 'message' => 'dhpo_prescriptions column not found in DB']);
            }
            $emr->dhpo_prescriptions = json_encode(array_values($existing));
            $emr->save();

            return GlobalFunction::sendDataResponse(true, 'DHPO prescription item added successfully', $this->emrResponseData($emr));
        } catch (\Throwable $exception) {
            return $this->handleException($exception, __METHOD__);
        }
    }

    public function editDhpoPrescriptionItem(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'appointment_id' => 'required|integer',
                'doctor_id' => 'required|integer',
                'index' => 'required|integer|min:0',
                'drug_name' => 'nullable|string|max:1000',
                'drug_names' => 'nullable|array|max:1',
                'drug_names.*' => 'nullable|string|max:255',
                'unit' => 'nullable|string|max:100',
                'frequency' => 'nullable|string|max:100',
                'duration' => 'nullable|string|max:100',
                'total_quantity' => 'nullable|string|max:100',
                'route_of_admin' => 'nullable|string|max:100',
                'special_instruction' => 'nullable|string|max:500',
            ]);
            if ($validator->fails()) {
                return response()->json(['status' => false, 'message' => Arr::first($validator->errors()->all())]);
            }

            $appointment = $this->validateAppointmentForDoctor((int) $request->appointment_id, (int) $request->doctor_id);
            if (!$appointment) {
                return GlobalFunction::sendSimpleResponse(false, 'Appointment not found for this doctor!');
            }

            $emr = $this->getOrCreateEmrReport((int) $request->appointment_id, (int) $request->doctor_id);
            $existing = json_decode($emr->dhpo_prescriptions ?? '[]', true);
            if (!is_array($existing)) {
                $existing = [];
            }

            $idx = (int) $request->index;
            if (!array_key_exists($idx, $existing)) {
                return response()->json(['status' => false, 'message' => 'Prescription index not found']);
            }

            if (!$request->has('drug_names') && !$request->filled('drug_name')) {
                return response()->json(['status' => false, 'message' => 'drug_names is required']);
            }

            $drugNames = $this->extractDrugNames($request);

            if (count($drugNames) === 0) {
                return response()->json(['status' => false, 'message' => 'No valid drug names found']);
            }
            if (count($drugNames) > 1) {
                return response()->json(['status' => false, 'message' => 'Only one drug name is allowed per prescription item']);
            }

            $existing[$idx] = [
                'drug_name' => $drugNames[0],
                'unit' => trim((string) ($request->unit ?? '')),
                'frequency' => trim((string) ($request->frequency ?? '')),
                'duration' => trim((string) ($request->duration ?? '')),
                'total_quantity' => trim((string) ($request->total_quantity ?? '')),
                'route_of_admin' => trim((string) ($request->route_of_admin ?? '')),
                'special_instruction' => trim((string) ($request->special_instruction ?? '')),
            ];

            if (!$this->canWriteColumn('dhpo_prescriptions')) {
                return response()->json(['status' => false, 'message' => 'dhpo_prescriptions column not found in DB']);
            }
            $emr->dhpo_prescriptions = json_encode(array_values($existing));
            $emr->save();

            return GlobalFunction::sendDataResponse(true, 'DHPO prescription item updated successfully', $this->emrResponseData($emr));
        } catch (\Throwable $exception) {
            return $this->handleException($exception, __METHOD__);
        }
    }

    public function deleteDhpoPrescriptionItem(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'appointment_id' => 'required|integer',
                'doctor_id' => 'required|integer',
                'index' => 'nullable|integer|min:0',
                'drug_name' => 'nullable|string|max:255',
            ]);
            if ($validator->fails()) {
                return response()->json(['status' => false, 'message' => Arr::first($validator->errors()->all())]);
            }

            $appointment = $this->validateAppointmentForDoctor((int) $request->appointment_id, (int) $request->doctor_id);
            if (!$appointment) {
                return GlobalFunction::sendSimpleResponse(false, 'Appointment not found for this doctor!');
            }

            $emr = $this->getOrCreateEmrReport((int) $request->appointment_id, (int) $request->doctor_id);
            $existing = json_decode($emr->dhpo_prescriptions ?? '[]', true);
            if (!is_array($existing)) {
                $existing = [];
            }

            if (!is_null($request->index)) {
                $idx = (int) $request->index;
                if (!array_key_exists($idx, $existing)) {
                    return response()->json(['status' => false, 'message' => 'Prescription index not found']);
                }
                unset($existing[$idx]);
            } elseif ($request->filled('drug_name')) {
                $drug = trim((string) $request->drug_name);
                $deleted = false;
                foreach ($existing as $k => $item) {
                    if (strcasecmp(trim((string) ($item['drug_name'] ?? '')), $drug) === 0) {
                        unset($existing[$k]);
                        $deleted = true;
                        break;
                    }
                }
                if (!$deleted) {
                    return response()->json(['status' => false, 'message' => 'Prescription item not found']);
                }
            } else {
                return response()->json(['status' => false, 'message' => 'Provide index or drug_name to delete']);
            }

            if (!$this->canWriteColumn('dhpo_prescriptions')) {
                return response()->json(['status' => false, 'message' => 'dhpo_prescriptions column not found in DB']);
            }
            $emr->dhpo_prescriptions = json_encode(array_values($existing));
            $emr->save();
            return GlobalFunction::sendDataResponse(true, 'DHPO prescription item deleted successfully', $this->emrResponseData($emr));
        } catch (\Throwable $exception) {
            return $this->handleException($exception, __METHOD__);
        }
    }

    public function getDiagnosisTypeDropdown(Request $request)
    {
        try {
            $search = mb_strtolower(trim((string) $request->query('search', '')));
            $types = collect();

            if (Schema::hasTable('emr_search_masters')) {
                $masterStandaloneTypes = EmrSearchMaster::where('is_deleted', 0)
                    ->where('category', 'diagnosis_type')
                    ->pluck('name');

                $masterTypesFromDiagnosis = EmrSearchMaster::where('is_deleted', 0)
                    ->where('category', 'diagnosis')
                    ->whereNotNull('diagnosis_type')
                    ->pluck('diagnosis_type');

                $types = $types->merge($masterStandaloneTypes)->merge($masterTypesFromDiagnosis);
            }

            $items = $types
                ->map(fn($item) => trim((string) $item))
                ->filter(fn($item) => $item !== '')
                // Normalize case first so Principal/principal are treated as one value.
                ->map(fn($item) => mb_convert_case(mb_strtolower($item, 'UTF-8'), MB_CASE_TITLE, 'UTF-8'))
                ->unique(fn($item) => mb_strtolower($item, 'UTF-8'))
                ->filter(fn($item) => $search === '' ? true : mb_stripos($item, $search) !== false)
                ->values()
                ->all();

            return response()->json([
                'status' => true,
                'message' => 'Diagnosis types fetched successfully',
                'total' => count($items),
                'data' => $items,
            ]);
        } catch (\Throwable $exception) {
            return $this->handleException($exception, __METHOD__);
        }
    }

    /**
     * When "Save & Finalize" sends a full snapshot, merge into the report row before locking.
     * Any key omitted is left unchanged (sections already saved via other APIs stay as-is).
     */
    private function applyEmrSnapshotIfPresent(Request $request, PatientEmrReport $emr, bool $allowDocumentClear = false): void
    {
        if ($request->exists('vital_details') && $this->canWriteColumn('vital_details')) {
            $existing = json_decode($emr->vital_details ?? '{}', true) ?? [];
            $incoming = $request->vital_details;
            if (is_array($incoming)) {
                $merged = $this->mergeVitalDetailsPreferIncoming($existing, $incoming);
                $emr->vital_details = json_encode($merged);
            } else {
                $emr->vital_details = (string) $incoming;
            }
        }
        if ($request->exists('chief_complaints') && $this->canWriteColumn('chief_complaints')) {
            $emr->chief_complaints = json_encode($this->normalizeList($request->chief_complaints));
        }
        if ($request->exists('symptoms') && $this->canWriteColumn('symptoms')) {
            $emr->symptoms = json_encode($this->normalizeList($request->symptoms));
        }
        if ($request->exists('allergies') && $this->canWriteColumn('allergies')) {
            $emr->allergies = json_encode($this->normalizeList($request->allergies));
        }
        if ($request->exists('history_of_present_illness') && $this->canWriteColumn('history_of_present_illness')) {
            $emr->history_of_present_illness = trim((string) ($request->history_of_present_illness ?? ''));
        }
        if ($request->exists('diagnosis') && $this->canWriteColumn('diagnosis')) {
            $diag = $request->diagnosis;
            if (is_array($diag)) {
                $clean = collect($diag)
                    ->map(fn($item) => [
                        'type' => trim((string) ($item['type'] ?? '')),
                        'name' => trim((string) ($item['name'] ?? '')),
                    ])
                    ->filter(fn($item) => $item['type'] !== '' && $item['name'] !== '')
                    ->values()
                    ->all();
                $emr->diagnosis = json_encode($clean);
            }
        }
        if ($request->exists('lab_orders') && $this->canWriteColumn('lab_orders')) {
            $emr->lab_orders = json_encode($this->normalizeList($request->lab_orders));
        }
        if ($request->exists('radiology_orders') && $this->canWriteColumn('radiology_orders')) {
            $emr->radiology_orders = json_encode($this->normalizeList($request->radiology_orders));
        }
        if ($request->exists('dhpo_prescriptions') && $this->canWriteColumn('dhpo_prescriptions')) {
            $rx = $request->dhpo_prescriptions;
            $emr->dhpo_prescriptions = is_array($rx) ? json_encode(array_values($rx)) : (string) $rx;
        }
        if (
            (
                $request->exists('dhpo_prescription_document')
                || $request->hasFile('dhpo_prescription_document')
                || $request->hasFile('dhpo_prescription_document_file')
                || $request->hasFile('documents')
                || $request->hasFile('document')
                || $request->hasFile('files')
                || $request->hasFile('file')
            )
            && $this->canWriteColumn('dhpo_prescription_document')
        ) {
            $emr->dhpo_prescription_document = $this->resolveDhpoPrescriptionDocument(
                $request,
                $emr->dhpo_prescription_document ?? null,
                $allowDocumentClear
            );
        }
        if ($request->exists('speciality_hospital_reference') && $this->canWriteColumn('speciality_hospital_reference')) {
            $emr->speciality_hospital_reference = trim((string) ($request->speciality_hospital_reference ?? ''));
        }
        if ($request->exists('follow_up_date') && $this->canWriteColumn('follow_up_date')) {
            $emr->follow_up_date = $request->follow_up_date !== null && $request->follow_up_date !== ''
                ? $request->follow_up_date
                : null;
        }
    }

    public function saveFinal(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'appointment_id' => 'required|integer',
                'doctor_id' => 'nullable|integer',
                'vital_details' => 'nullable|array',
                'chief_complaints' => 'nullable|array',
                'symptoms' => 'nullable|array',
                'allergies' => 'nullable|array',
                'history_of_present_illness' => 'nullable|string',
                'diagnosis' => 'nullable|array',
                'lab_orders' => 'nullable|array',
                'radiology_orders' => 'nullable|array',
                'dhpo_prescriptions' => 'nullable|array',
                'dhpo_prescription_document' => 'nullable',
                'speciality_hospital_reference' => 'nullable|string|max:2000',
                'follow_up_date' => 'nullable|date',
            ]);
            if ($validator->fails()) {
                return response()->json(['status' => false, 'message' => Arr::first($validator->errors()->all())]);
            }
            $appointment = $this->validateAppointmentForDoctor((int) $request->appointment_id, (int) ($request->doctor_id ?? 0));
            if (!$appointment) {
                return GlobalFunction::sendSimpleResponse(false, 'Appointment not found');
            }
            $doctorId = $this->resolveEffectiveDoctorId($appointment, (int) $request->doctor_id);
            if ($doctorId <= 0) {
                return response()->json(['status' => false, 'message' => 'doctor_id is required']);
            }
            $emr = $this->getOrCreateEmrReport((int) $request->appointment_id, $doctorId);
            $this->applyEmrSnapshotIfPresent($request, $emr);
            $this->augmentStoredVitalsWithAi($emr);
            $this->syncAppointmentAndJitsiMeta($emr);
            if ($this->canWriteColumn('is_finalized')) {
                $emr->is_finalized = 1;
            }
            if ($this->canWriteColumn('finalized_at')) {
                $emr->finalized_at = now();
            }
            $emr->save();
            $lang = $this->resolveEmrLang($request);
            $responseData = $this->emrResponseData($emr);
            $responseData['lang'] = $lang;
            $responseData['download_pdf_url'] = route('emr.download-pdf', ['report_id' => $emr->id, 'lang' => $lang]);
            $responseData['download_prescription_pdf_url'] = route('emr.download-prescription-pdf', ['report_id' => $emr->id, 'lang' => $lang]);
            return GlobalFunction::sendDataResponse(true, 'EMR saved and finalized successfully', $responseData);
        } catch (\Throwable $exception) {
            return $this->handleException($exception, __METHOD__);
        }
    }

    public function saveDraft(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'appointment_id' => 'required|integer',
                'doctor_id' => 'nullable|integer',
                'vital_details' => 'nullable|array',
                'chief_complaints' => 'nullable|array',
                'symptoms' => 'nullable|array',
                'allergies' => 'nullable|array',
                'history_of_present_illness' => 'nullable|string',
                'diagnosis' => 'nullable|array',
                'lab_orders' => 'nullable|array',
                'radiology_orders' => 'nullable|array',
                'dhpo_prescriptions' => 'nullable|array',
                'dhpo_prescription_document' => 'nullable',
                'speciality_hospital_reference' => 'nullable|string|max:2000',
                'follow_up_date' => 'nullable|date',
                'lang' => 'nullable|string|max:10',
            ]);
            if ($validator->fails()) {
                return response()->json(['status' => false, 'message' => Arr::first($validator->errors()->all())]);
            }

            $appointment = $this->validateAppointmentForDoctor((int) $request->appointment_id, (int) ($request->doctor_id ?? 0));
            if (!$appointment) {
                return GlobalFunction::sendSimpleResponse(false, 'Appointment not found');
            }

            $doctorId = $this->resolveEffectiveDoctorId($appointment, (int) $request->doctor_id);
            if ($doctorId <= 0) {
                return response()->json(['status' => false, 'message' => 'doctor_id is required']);
            }
            $emr = $this->getOrCreateEmrReport((int) $request->appointment_id, $doctorId);
            $this->applyEmrSnapshotIfPresent($request, $emr);
            $this->augmentStoredVitalsWithAi($emr);
            $this->syncAppointmentAndJitsiMeta($emr);
            if ($this->canWriteColumn('is_finalized')) {
                $emr->is_finalized = 0;
            }
            if ($this->canWriteColumn('finalized_at')) {
                $emr->finalized_at = null;
            }
            $emr->save();
            $lang = $this->resolveEmrLang($request);
            $responseData = $this->emrResponseData($emr);
            $responseData['lang'] = $lang;
            $responseData['download_pdf_url'] = route('emr.download-pdf', ['report_id' => $emr->id, 'lang' => $lang]);
            $responseData['download_prescription_pdf_url'] = route('emr.download-prescription-pdf', ['report_id' => $emr->id, 'lang' => $lang]);
            return GlobalFunction::sendDataResponse(true, 'This report is currently saved as a draft. Please save and finalize.', $responseData);
        } catch (\Throwable $exception) {
            return $this->handleException($exception, __METHOD__);
        }
    }

    public function getSymptomDropdown(Request $request)
    {
        try {
            $search = trim((string) $request->query('search', ''));
            $query = DoctorsBySymptoms::query()
                ->select('problem')
                ->where('is_deleted', 0)
                ->whereNotNull('problem');
            if ($search !== '') {
                $query->where('problem', 'like', '%' . $search . '%');
            }
            $items = $query->orderBy('problem')->limit(30)->pluck('problem')->unique()->values();
            return GlobalFunction::sendDataResponse(true, 'Symptoms fetched successfully', $items);
        } catch (\Throwable $exception) {
            return $this->handleException($exception, __METHOD__);
        }
    }

    public function getChiefComplaintDropdown(Request $request)
    {
        try {
            return $this->getPastListDropdown($request, 'chief_complaint', 'Chief complaints fetched successfully');
        } catch (\Throwable $exception) {
            return $this->handleException($exception, __METHOD__);
        }
    }

    public function getAllergyDropdown(Request $request)
    {
        try {
            return $this->getPastListDropdown($request, 'allergy', 'Allergies fetched successfully');
        } catch (\Throwable $exception) {
            return $this->handleException($exception, __METHOD__);
        }
    }

    public function getDrugNameDropdown(Request $request)
    {
        try {
            $search = mb_strtolower(trim((string) $request->query('search', '')));
            // Keep dropdown clean: default source is EMR master data only.
            // Historical prescriptions are included only when include_history=1.
            $includeHistory = filter_var($request->query('include_history', false), FILTER_VALIDATE_BOOLEAN);
            $drugNames = new Collection();
            if (Schema::hasTable('emr_search_masters')) {
                $masterItems = EmrSearchMaster::where('is_deleted', 0)
                    ->whereIn('category', ['drug_name', 'drug', 'drugs'])
                    ->pluck('name')
                    ->map(fn($item) => trim((string) $item))
                    ->filter(fn($item) => $item !== '')
                    ->values();
                $drugNames = $drugNames->merge($masterItems);
            }

            // Include already-used prescription drugs from saved EMR reports as fallback.
            if ($includeHistory && Schema::hasTable('patient_emr_reports') && Schema::hasColumn('patient_emr_reports', 'dhpo_prescriptions')) {
                $historicalItems = PatientEmrReport::query()
                    ->whereNotNull('dhpo_prescriptions')
                    ->pluck('dhpo_prescriptions')
                    ->flatMap(function ($raw) {
                        $decoded = json_decode((string) $raw, true);
                        if (!is_array($decoded)) {
                            return [];
                        }

                        return collect($decoded)->flatMap(function ($row) {
                            $name = trim((string) (is_array($row) ? ($row['drug_name'] ?? '') : ''));
                            if ($name === '') {
                                return [];
                            }

                            // Existing records may store multiple names in single string: "A, B, C".
                            return collect(explode(',', $name))
                                ->map(fn($part) => trim((string) $part))
                                ->filter(fn($part) => $part !== '')
                                ->values();
                        });
                    })
                    ->values();

                $drugNames = $drugNames->merge($historicalItems);
            }

            $items = $drugNames->unique()
                ->filter(fn($item) => $search === '' ? true : mb_stripos($item, $search) !== false)
                ->values()
                ->take(50)
                ->all();

            return response()->json([
                'status' => true,
                'message' => 'Drug names fetched successfully',
                'total' => count($items),
                'data' => $items,
            ]);
        } catch (\Throwable $exception) {
            return $this->handleException($exception, __METHOD__);
        }
    }

    public function getLabOrderDropdown(Request $request)
    {
        try {
            $search = mb_strtolower(trim((string) $request->query('search', '')));
            $flatten = new Collection();

            if (Schema::hasTable('emr_search_masters')) {
                $masterItems = EmrSearchMaster::where('is_deleted', 0)
                    ->where('category', 'lab_order')
                    ->pluck('name')
                    ->map(fn($item) => trim((string) $item))
                    ->filter(fn($item) => $item !== '')
                    ->values();
                $flatten = $flatten->merge($masterItems);
            }

            $items = $flatten->unique()
                ->filter(fn($item) => $search === '' ? true : mb_stripos($item, $search) !== false)
                ->values()
                ->take(50)
                ->all();

            if (!in_array('Other', $items, true)) {
                array_unshift($items, 'Other');
            } else {
                $items = array_values(array_unique(array_merge(['Other'], $items)));
            }

            return response()->json([
                'status' => true,
                'message' => 'Lab orders fetched successfully',
                'total' => count($items),
                'data' => $items,
            ]);
        } catch (\Throwable $exception) {
            return $this->handleException($exception, __METHOD__);
        }
    }

    public function getRadiologyOrderDropdown(Request $request)
    {
        try {
            $search = mb_strtolower(trim((string) $request->query('search', '')));
            $flatten = new Collection();

            if (Schema::hasTable('emr_search_masters')) {
                $masterItems = EmrSearchMaster::where('is_deleted', 0)
                    ->where('category', 'radiology_order')
                    ->pluck('name')
                    ->map(fn($item) => trim((string) $item))
                    ->filter(fn($item) => $item !== '')
                    ->values();
                $flatten = $flatten->merge($masterItems);
            }

            $items = $flatten->unique()
                ->filter(fn($item) => $search === '' ? true : mb_stripos($item, $search) !== false)
                ->values()
                ->take(50)
                ->all();

            if (!in_array('Other', $items, true)) {
                array_unshift($items, 'Other');
            } else {
                $items = array_values(array_unique(array_merge(['Other'], $items)));
            }

            return response()->json([
                'status' => true,
                'message' => 'Radiology orders fetched successfully',
                'total' => count($items),
                'data' => $items,
            ]);
        } catch (\Throwable $exception) {
            return $this->handleException($exception, __METHOD__);
        }
    }

    private function getPastListDropdown(Request $request, string $category, string $message)
    {
        $search = mb_strtolower(trim((string) $request->query('search', '')));
        $flatten = new Collection();

        if (Schema::hasTable('emr_search_masters')) {
            $masterItems = EmrSearchMaster::where('is_deleted', 0)
                ->where('category', $category)
                ->pluck('name')
                ->map(fn($item) => trim((string) $item))
                ->filter(fn($item) => $item !== '')
                ->values();
            $flatten = $flatten->merge($masterItems);
        }

        $items = $flatten->unique()
            ->filter(fn($item) => $search === '' ? true : mb_stripos($item, $search) !== false)
            ->values()
            ->take(30)
            ->all();

        return GlobalFunction::sendDataResponse(true, $message, $items);
    }

    public function getDiagnosisDropdown(Request $request)
    {
        try {
            $search = mb_strtolower(trim((string) $request->query('search', '')));
            $selectedType = trim((string) $request->query('type', ''));
            $options = [];

            if (Schema::hasTable('emr_search_masters')) {
                $masterQuery = EmrSearchMaster::where('is_deleted', 0)
                    ->where('category', 'diagnosis');
                if ($selectedType !== '') {
                    $masterQuery->where('diagnosis_type', $selectedType);
                }
                $options = $masterQuery
                    ->pluck('name')
                    ->map(fn($item) => trim((string) $item))
                    ->filter(fn($item) => $item !== '')
                    ->values()
                    ->all();
            }

            $items = collect($options)->unique()
                ->filter(fn($item) => $search === '' ? true : mb_stripos($item, $search) !== false)
                ->values()
                ->take(30)
                ->all();

            // Keep "Other" option on top for UI custom add.
            // If type is selected, show previous DB values + Other.
            if (!in_array('Other', $items, true)) {
                array_unshift($items, 'Other');
            } else {
                $items = array_values(array_unique(array_merge(['Other'], $items)));
            }

            return response()->json([
                'status' => true,
                'message' => 'Diagnosis options fetched successfully',
                'selected_type' => $selectedType !== '' ? $selectedType : null,
                'total' => count($items),
                'data' => $items,
            ]);
        } catch (\Throwable $exception) {
            return $this->handleException($exception, __METHOD__);
        }
    }

    /**
     * Download the EMR report as a PDF.
     *
     * GET /api/v1/emr/download-pdf?report_id=X
     * GET /api/v1/emr/download-pdf?appointment_id=X
     */
    public function downloadEmrReport(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'report_id' => 'sometimes|nullable|integer',
                'appointment_id' => 'sometimes|nullable|integer',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => false, 'message' => Arr::first($validator->errors()->all())]);
            }

            $hasReportId = filled($request->input('report_id'));
            $hasAppointmentId = filled($request->input('appointment_id'));

            if (!$hasReportId && !$hasAppointmentId) {
                return response()->json(['status' => false, 'message' => 'report_id or appointment_id is required']);
            }

            /** @var PatientEmrReport|null $emr */
            $emr = $this->resolveReportFromRequest($request);

            if (!$emr) {
                return response()->json(['status' => false, 'message' => 'EMR report not found']);
            }

            $regularAppointment = Appointments::query()
                ->with(['user', 'doctor'])
                ->find((int) ($emr->appointment_id ?? 0));
            if (!$regularAppointment) {
                return response()->json(['status' => false, 'message' => 'Appointment not found']);
            }
            $appointment = $regularAppointment;
            $doctor = $regularAppointment->doctor;
            $user = $regularAppointment?->user;

            $patientName = $this->resolvePatientName($appointment, $user) ?? 'N/A';

            $consultDate = null;
            if (!empty($regularAppointment?->date)) {
                $consultDate = \Carbon\Carbon::parse($regularAppointment->date)->format('m/d/Y');
            } elseif ($appointment?->appointment_date) {
                $consultDate = \Carbon\Carbon::parse($appointment->appointment_date)->format('m/d/Y');
            } elseif ($emr->created_at) {
                $consultDate = $emr->created_at->format('m/d/Y');
            } else {
                $consultDate = date('m/d/Y');
            }

            $vitalDetailsRaw = json_decode($emr->vital_details ?? '{}', true) ?? [];
            $vitalNormalized = $this->normalizeVitalDetailsArray($vitalDetailsRaw);
            $aiVitalsRow = $this->getLatestAiVitalsForEmr((int) $emr->appointment_id);
            $vitalFilled = $this->fillVitalDetailsGapsFromAi($vitalNormalized, $aiVitalsRow['report'] ?? null);
            $vitalDetails = $this->formatVitalDetailsForEmrPdf($vitalFilled);
            $chiefComplaints = $this->normalizeList($emr->chief_complaints);
            $symptoms = $this->normalizeList($emr->symptoms);
            $allergies = $this->normalizeList($emr->allergies);
            $historyText = $emr->history_of_present_illness ?? '';
            $diagnosisRaw = json_decode($emr->diagnosis ?? '[]', true) ?? [];
            $diagnosis = collect(is_array($diagnosisRaw) ? $diagnosisRaw : [])
                ->map(function ($item) {
                    $row = is_array($item) ? $item : [];

                    return [
                        'type' => (string) ($row['type'] ?? ($row['label'] ?? 'Diagnosis')),
                        'code' => (string) ($row['code'] ?? ($row['icd_code'] ?? '')),
                        'name' => (string) ($row['name'] ?? ($row['title'] ?? '')),
                    ];
                })
                ->values()
                ->all();
            $labOrders = $this->normalizeList($emr->lab_orders);
            $radiologyOrders = $this->normalizeList($emr->radiology_orders);
            $prescriptionsRaw = json_decode($emr->dhpo_prescriptions ?? '[]', true) ?? [];
            $prescriptions = collect(is_array($prescriptionsRaw) ? $prescriptionsRaw : [])
                ->map(function ($item) {
                    $row = is_array($item) ? $item : [];

                    return [
                        'drug_name' => (string) ($row['drug_name'] ?? ''),
                        'unit' => (string) ($row['unit'] ?? ''),
                        'frequency' => (string) ($row['frequency'] ?? ''),
                        'duration' => (string) ($row['duration'] ?? ''),
                        'total_quantity' => (string) ($row['total_quantity'] ?? ''),
                        'route_of_admin' => (string) ($row['route_of_admin'] ?? ''),
                        'special_instruction' => (string) ($row['special_instruction'] ?? ''),
                    ];
                })
                ->values()
                ->all();
            $referral = $emr->speciality_hospital_reference ?? '';
            $followUpDate = $emr->follow_up_date ? \Carbon\Carbon::parse($emr->follow_up_date)->format('d M Y') : null;

            $doctorId = (int) ($emr->doctor_id ?? ($regularAppointment?->doctor_id ?? ($appointment?->doctor_id ?? 0)));
            $doctorRow = null;
            if ($doctorId > 0) {
                $doctorRow = Doctors::query()->find($doctorId);
            }

            $doctorName = $doctorRow?->name ?? ($doctor?->name ?? 'N/A');
            $doctorRegNo = $doctorRow?->dha_registration_number ?? ($doctor?->dha_registration_number ?? 'N/A');
            $doctorSignature = $doctorRow?->digital_signature ?? ($doctor?->digital_signature ?? null);
            $doctorStamp = $doctorRow?->doctor_seal
                ?? ($doctor?->doctor_seal ?? ($doctor?->doctor_stamp ?? ($doctor?->stamp ?? null)));
            // $patientGender = $user?->gender ?? ($appointment?->gender ?? null);
            $mrnNo = $this->buildMrnNo((int) ($emr->id ?? 0), (int) ($emr->appointment_id ?? 0));
            $patientAge = 'N/A';
            $dobValue = $user?->dob
                ?? ($user?->date_of_birth ?? null)
                ?? ($user?->birth_date ?? null)
                ?? ($appointment?->dob ?? null)
                ?? ($appointment?->date_of_birth ?? null);
            if (!empty($dobValue)) {
                try {
                    $patientAge = (string) \Carbon\Carbon::parse($dobValue)->age;
                } catch (\Throwable $e) {
                    $patientAge = 'N/A';
                }
            }
            $genderValue = $user?->gender ?? ($appointment?->gender ?? null);

            $patientGender = match ($genderValue) {
                1 => 'Male',
                2 => 'Female',
                0 => '-',
                default => '-',
            };

            $data = compact(
                'emr',
                'appointment',
                'doctor',
                'patientName',
                'consultDate',
                'vitalDetails',
                'chiefComplaints',
                'symptoms',
                'allergies',
                'historyText',
                'diagnosis',
                'labOrders',
                'radiologyOrders',
                'prescriptions',
                'referral',
                'followUpDate',
                'doctorName',
                'doctorRegNo',
                'doctorSignature',
                'doctorStamp',
                'patientGender',
                'mrnNo',
                'patientAge',
                
            );

            $filename = 'EMR_Report_' . $mrnNo . '_' . date('Ymd') . '.pdf';

            if (!extension_loaded('gd')) {
                return response()->json([
                    'status' => false,
                    'message' => 'PDF generation requires the PHP GD extension (DomPDF decodes PNG/JPEG with it). '
                        . 'In php.ini enable: extension=gd (on Windows also ensure php_gd.dll is present next to php.exe), then restart PHP / your web server.',
                ], 500);
            }

            $storagePath = str_replace('/', DIRECTORY_SEPARATOR, storage_path('app/public'));
            $data['storagePath'] = $storagePath;
            $data['emrLogoSrc'] = $this->localPublicImageDataUri('uploads/prescription_logo.png');
            $lang = $this->resolveEmrLang($request);
            $data['labels'] = $this->emrPdfLabels($lang);
            $data['lang'] = $lang;

            $emrSignatureSrc = '';
            $signatureCandidates = [];
            if (!empty($doctorSignature)) {
                $rawSignature = trim((string) $doctorSignature);
                $signatureCandidates[] = $rawSignature;
                $signatureCandidates[] = ltrim($rawSignature, '/\\');
                if (!str_contains($rawSignature, '/')) {
                    $signatureCandidates[] = 'uploads/' . ltrim($rawSignature, '/\\');
                }
                $signatureCandidates[] = 'public/' . ltrim($rawSignature, '/\\');
                $signatureCandidates[] = 'public/storage/' . ltrim($rawSignature, '/\\');
            }
            foreach (array_values(array_unique(array_filter($signatureCandidates))) as $candidate) {
                $emrSignatureSrc = $this->localPublicImageDataUri($candidate);
                if ($emrSignatureSrc !== '') {
                    break;
                }
            }
            if ($emrSignatureSrc === '') {
                $emrSignatureSrc = $this->publicImageDataUri('images/no-signature.png');
            }
            if ($emrSignatureSrc === '') {
                $emrSignatureSrc = self::TINY_PNG_DATA_URI;
            }
            $data['emrSignatureSrc'] = $emrSignatureSrc;

            $emrStampSrc = '';
            if (!empty($doctorStamp)) {
                $emrStampSrc = $this->localPublicImageDataUri(ltrim((string) $doctorStamp, '/\\'));
            }
            if ($emrStampSrc === '') {
                $emrStampSrc = $this->localPublicImageDataUri('uploads/mulkmed_prescription_stamp.png');
            }
            if ($emrStampSrc === '') {
                $emrStampSrc = self::TINY_PNG_DATA_URI;
            }
            $data['emrStampSrc'] = $emrStampSrc;

            $emrPatientPhotoSrc = '';
            if (!empty($user?->profile_image)) {
                $emrPatientPhotoSrc = $this->localPublicImageDataUri(ltrim((string) $user->profile_image, '/\\'));
            }
            $data['emrPatientPhotoSrc'] = $emrPatientPhotoSrc;

            set_time_limit(180);
            ini_set('max_execution_time', '180');

            $html = view('pages.emr_report_mpdf', $data)->render();
            return $this->mpdfDownloadFromHtml($html, $filename, $lang);
        } catch (\Throwable $exception) {
            return $this->handleException($exception, __METHOD__);
        }
    }

    /**
     * Download prescription PDF for an EMR appointment.
     *
     * GET /api/v1/emr/download-prescription-pdf?report_id=X
     * GET /api/v1/emr/download-prescription-pdf?appointment_id=X
     */
    public function downloadPrescriptionPdf(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'report_id' => 'sometimes|nullable|integer',
                'appointment_id' => 'sometimes|nullable|integer',
            ]);
            if ($validator->fails()) {
                return response()->json(['status' => false, 'message' => Arr::first($validator->errors()->all())]);
            }

            $hasReportId = filled($request->input('report_id'));
            $hasAppointmentId = filled($request->input('appointment_id'));
            if (!$hasReportId && !$hasAppointmentId) {
                return response()->json(['status' => false, 'message' => 'report_id or appointment_id is required']);
            }

            /** @var PatientEmrReport|null $emr */
            $emr = $this->resolveReportFromRequest($request);
            if (!$emr) {
                return response()->json(['status' => false, 'message' => 'EMR report not found']);
            }

            $appointment = Appointments::query()
                ->with(['user', 'doctor', 'prescription'])
                ->find((int) $emr->appointment_id);
            if (!$appointment) {
                return response()->json(['status' => false, 'message' => 'Appointment not found']);
            }

            $prescription = $appointment->prescription;
            if (!$prescription) {
                $emrRx = json_decode($emr->dhpo_prescriptions ?? '[]', true);
                $emrDiagnosis = json_decode($emr->diagnosis ?? '[]', true);

                if (!is_array($emrRx) || count($emrRx) === 0) {
                    return response()->json(['status' => false, 'message' => 'Prescription not found for this appointment']);
                }

                $addMedicine = collect($emrRx)->map(function ($rx) {
                    $instruction = trim((string) ($rx['special_instruction'] ?? ''));
                    $mealTime = stripos($instruction, 'before') !== false ? 0 : 1;
                    $frequency = trim((string) ($rx['frequency'] ?? ''));
                    $duration = trim((string) ($rx['duration'] ?? ''));

                    return [
                        'drugCode' => trim((string) ($rx['route_of_admin'] ?? '')),
                        'title' => trim((string) ($rx['drug_name'] ?? '')),
                        'mealTime' => $mealTime,
                        // Keep legacy key for old template consumers.
                        'dosage' => $duration !== '' ? $duration : $frequency,
                        'quantity' => trim((string) ($rx['total_quantity'] ?? '')),
                        'notes' => $instruction,
                        // Provide explicit keys used by current prescription templates.
                        'unit' => trim((string) ($rx['unit'] ?? '')),
                        'frequency' => $frequency,
                        'duration' => $duration,
                        'total_quantity' => trim((string) ($rx['total_quantity'] ?? '')),
                        'route_of_admin' => trim((string) ($rx['route_of_admin'] ?? '')),
                        'special_instruction' => $instruction,
                    ];
                })->values()->all();

                $diagnosis = [];
                if (is_array($emrDiagnosis) && count($emrDiagnosis) > 0) {
                    $diagnosis = collect($emrDiagnosis)->map(function ($d) {
                        return [
                            'title' => trim((string) ($d['type'] ?? 'Diagnosis')),
                            'icd' => ['-'],
                            'description' => [trim((string) ($d['name'] ?? '-'))],
                        ];
                    })->values()->all();
                }

                $prescription = [
                    'medicine' => json_encode([
                        'erx' => $appointment->appointment_number ?? '',
                        'diagnosis' => $diagnosis,
                        'addMedicine' => $addMedicine,
                        'notes' => '',
                    ]),
                    'created_at' => $emr->created_at,
                    'appointment' => [
                        'appointment_number' => $appointment->appointment_number ?? '',
                        'doctor' => [
                            'name' => $appointment->doctor->name ?? '',
                            'dha_registration_number' => $appointment->doctor->dha_registration_number ?? '',
                            'digital_signature' => $appointment->doctor->digital_signature ?? null,
                        ],
                    ],
                ];
            }

            $regularAppointment = Appointments::query()
                ->with(['user'])
                ->find((int) ($appointment->id ?? 0));
            $patientUser = $regularAppointment?->user;
            $fullName = $this->resolvePatientName($appointment, $patientUser) ?? 'N/A';
            $user = [
                'fullname' => $fullName,
                'gender' => $patientUser?->gender,
                'dob' => $patientUser?->dob,
                'ref_id' => $patientUser?->ref_id
                    ?? ($patientUser?->emirates_id ?? ''),
            ];

            $data = [
                'user' => $user,
                'prescription' => $prescription,
            ];
            $mrnNo = $this->buildMrnNo((int) ($emr->id ?? 0), (int) ($appointment->id ?? 0));
            $data['mrnNo'] = $mrnNo;
            $lang = $this->resolveEmrLang($request);
            $data['lang'] = $lang;
            $data['labels'] = $this->emrPdfLabels($lang);

            if (!extension_loaded('gd')) {
                return response()->json([
                    'status' => false,
                    'message' => 'PDF generation requires the PHP GD extension. Please enable extension=gd and restart PHP.',
                ], 500);
            }

            $doctorSignature = (string) ($prescription['appointment']['doctor']['digital_signature'] ?? '');
            $signatureSrc = '';
            if ($doctorSignature !== '') {
                $signatureSrc = $this->localPublicImageDataUri(ltrim($doctorSignature, '/\\'));
            }
            if ($signatureSrc === '') {
                $signatureSrc = $this->publicImageDataUri('images/no-signature.png');
            }
            if ($signatureSrc === '') {
                $signatureSrc = self::TINY_PNG_DATA_URI;
            }

            $stampSrc = $this->localPublicImageDataUri('uploads/mulkmed_prescription_stamp.png');
            if ($stampSrc === '') {
                $stampSrc = self::TINY_PNG_DATA_URI;
            }

            $logoSrc = $this->localPublicImageDataUri('uploads/prescription_logo.png');
            if ($logoSrc === '') {
                $logoSrc = self::TINY_PNG_DATA_URI;
            }

            $watermarkSrc = $this->localPublicImageDataUri('uploads/mulkmed_presciption_watermark.png');
            if ($watermarkSrc === '') {
                $watermarkSrc = self::TINY_PNG_DATA_URI;
            }

            $profilePhotoSrc = '';
            $profileImage = $user['profile_image'] ?? null;
            if (!empty($profileImage)) {
                $profilePhotoSrc = $this->localPublicImageDataUri(ltrim((string) $profileImage, '/\\'));
            }

            $data['prescriptionPdfImages'] = [
                'logo' => $logoSrc,
                'watermark' => $watermarkSrc,
                'signature' => $signatureSrc,
                'stamp' => $stampSrc,
                'profile' => $profilePhotoSrc,
            ];

            set_time_limit(180);
            ini_set('max_execution_time', '180');

            $filename = 'Prescription_' . $mrnNo . '_' . date('Ymd') . '.pdf';
            $pdf = Pdf::loadView('pages.prescription', $data)
                ->setPaper('a4', 'portrait')
                ->setOptions([
                    'dpi' => 96,
                    'isRemoteEnabled' => false,
                    'isHtml5ParserEnabled' => true,
                    'defaultFont' => 'NotoSans',
                    'enable_php' => false,
                    'enable_javascript' => false,
                ]);

            if (in_array($lang, ['hi', 'ar', 'ur'], true)) {
                $html = view('pages.prescription_mpdf', $data)->render();
                return $this->mpdfDownloadFromHtml($html, $filename, $lang);
            }

            return $pdf->download($filename);
        } catch (\Throwable $exception) {
            return $this->handleException($exception, __METHOD__);
        }
    }
}
