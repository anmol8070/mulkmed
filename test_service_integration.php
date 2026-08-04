<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Users;
use App\Models\LabReport;
use App\Services\SenoclockAiService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

echo "Running SenoclockAiService processLabReport integration test...\n";

// 1. Mock HTTP calls
Http::fake([
    '*/rest-auth/login/*' => Http::response(['access_token' => 'mock-access-token-12345'], 200),
    '*/dl-api/file-upload/*' => Http::response(['id' => 'mock-file-id-54321'], 200),
    '*/dl-api/file-execute/*' => Http::response(['status' => 'Ok', 'report_id' => 'mock-report-id-99999'], 200),
    '*/dl-api/report/download/*' => Http::response('mock pdf content', 200, ['Content-Type' => 'application/pdf']),
]);

// Ensure config has dummy values so it doesn't skip
config(['services.senoclock.email' => 'test@mulkmed.com']);
config(['services.senoclock.password' => 'secret_password']);

// Create a dummy PDF file in public/uploads for testing
$dummyDir = public_path('uploads');
if (!file_exists($dummyDir)) {
    @mkdir($dummyDir, 0777, true);
}
$dummyPdfPath = $dummyDir . '/test_lab_report.pdf';
file_put_contents($dummyPdfPath, 'dummy original pdf content');

// 2. Setup database test records
$user = new Users();
$user->name = 'John Doe';
$user->email = 'john.doe@example.com';
$user->password = bcrypt('secret');
$user->dob = '1990-05-15';
$user->gender = 1; // Male
$user->save();

$labReport = new LabReport();
$labReport->user_id = $user->id;
$labReport->document_path = 'uploads/test_lab_report.pdf';
$labReport->type = 'pdf';
$labReport->ocr_text = 'Hemoglobin (Hb) 14.5 g/dL range 12-16';
$labReport->analysis_response = [
    'extracted_biomarkers' => [
        [
            'name' => 'Hemoglobin (Hb)',
            'value' => '14.5',
            'unit' => 'g/dL',
            'range' => '12-16'
        ],
        [
            'name' => 'HDL Cholesterol',
            'value' => '50',
            'unit' => 'mg/dL',
            'range' => '40-60'
        ]
    ]
];
$labReport->status = 1;
$labReport->save();

echo "Dummy User ID: {$user->id}\n";
echo "Dummy LabReport ID: {$labReport->id}\n";

// 3. Run the service
$service = app(SenoclockAiService::class);
echo "Processing lab report via SenoclockAiService...\n";
$service->processLabReport($labReport);

// 4. Assertions
$labReport->refresh();

echo "Database Values after execution:\n";
echo "- senoclock_id: " . $labReport->senoclock_id . "\n";
echo "- senoclock_pdf_path: " . $labReport->senoclock_pdf_path . "\n";
echo "- senoclock_status: " . $labReport->senoclock_status . "\n";

$success = true;
if ($labReport->senoclock_id !== 'mock-report-id-99999') {
    echo "FAIL: senoclock_id matches expected\n";
    $success = false;
}
if ($labReport->senoclock_pdf_path !== 'uploads/senoclock_mock-report-id-99999.pdf') {
    echo "FAIL: senoclock_pdf_path matches expected\n";
    $success = false;
}
if ($labReport->senoclock_status !== 'completed') {
    echo "FAIL: senoclock_status should be completed\n";
    $success = false;
}

// Clean up
$user->delete();
$labReport->delete();
@unlink($dummyPdfPath);
@unlink(public_path('uploads/senoclock_mock-report-id-99999.pdf'));

if ($success) {
    echo "ALL TESTS PASSED SUCCESSFULLY!\n";
} else {
    echo "TEST FAILED!\n";
    exit(1);
}
