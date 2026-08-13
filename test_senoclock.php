<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;

$email = config('services.senoclock.email');
$password = config('services.senoclock.password');
$baseUrl = config('services.senoclock.base_url', 'https://api-euc1.senoclock.ai');

echo "Authenticating...\n";
$response = Http::withoutVerifying()->post("{$baseUrl}/dl-api/api-token-auth/", [
    'username' => $email,
    'password' => $password,
]);
$token = $response->json('token');
if (!$token) {
    die("Auth failed\n");
}

echo "Uploading file...\n";
$dummyFile = __DIR__.'/storage/app/dummy.pdf';
file_put_contents($dummyFile, "dummy content");
$uploadResponse = Http::withoutVerifying()->withToken($token)
    ->attach('file', file_get_contents($dummyFile), 'dummy.pdf')
    ->put("{$baseUrl}/dl-api/file-upload/", [
        'process_execute' => 'true',
        'diet_preference' => 'non_veg',
        'preferred_language' => 'en'
    ]);

$senoclockId = $uploadResponse->json('id');
echo "Uploaded ID: $senoclockId\n";

if (!$senoclockId) {
    die("Upload failed: " . $uploadResponse->body() . "\n");
}

echo "Executing...\n";
$executePayload = [
    'id' => $senoclockId,
    'external_id' => '123',
    'dob' => null,
    'age' => 25,
    'gender' => 'male',
    'test_date' => '2023-01-01',
    'markers' => [
        'GLC' => ['range' => '70-100', 'unit' => 'mg/dL', 'value' => 85]
    ],
];
$executeResponse = Http::withoutVerifying()->withToken($token)
    ->post("{$baseUrl}/dl-api/file-execute/", $executePayload);

echo "Execute Response: " . $executeResponse->body() . "\n";

echo "Downloading PDF...\n";
$downloadUrl = "{$baseUrl}/dl-api/report/download/?pdf_report=true&id=" . $senoclockId;
$pdfResponse = Http::withoutVerifying()->withToken($token)->get($downloadUrl);

$contentType = $pdfResponse->header('Content-Type');
echo "Content-Type: $contentType\n";
echo "Response Body Start: " . substr($pdfResponse->body(), 0, 200) . "\n";
