<?php

namespace App\Http\Controllers;

use App\Services\SenoclockAiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SenoclockTestController extends Controller
{
    public function __construct(
        private SenoclockAiService $senoclockAiService
    ) {}

    public function index()
    {
        return view('senoclock.test-classification', [
            'loginApiUrl' => $this->senoclockAiService->getLoginApiUrl(),
            'classificationApiUrl' => $this->senoclockAiService->getClassificationApiUrl(),
        ]);
    }

    public function login(Request $request): JsonResponse
    {
        $apiUrl = $this->senoclockAiService->getLoginApiUrl();
        $email = $request->input('email');
        $password = $request->input('password');

        $result = $this->senoclockAiService->testLogin($email, $password);

        return $this->jsonWithSenoclockApiUrl($result, $apiUrl);
    }

    public function classification(Request $request): JsonResponse
    {
        $apiUrl = $this->senoclockAiService->getClassificationApiUrl();
        $email = $request->input('email');
        $password = $request->input('password');

        $payload = $request->input('payload');
        if (!is_array($payload)) {
            $payload = $request->except(['email', 'password', 'payload']);
        }

        if ($payload === []) {
            return $this->jsonWithSenoclockApiUrl([
                'success' => false,
                'message' => 'Classification payload is required.',
            ], $apiUrl, 422);
        }

        $payload = $this->senoclockAiService->normalizeClassificationPayload($payload);

        if ($payload === []) {
            return $this->jsonWithSenoclockApiUrl([
                'success' => false,
                'message' => 'Classification payload is required.',
            ], $apiUrl, 422);
        }

        $result = $this->senoclockAiService->testClassification($payload, $email, $password);

        return $this->jsonWithSenoclockApiUrl(
            $result,
            $result['api_url'] ?? $apiUrl
        );
    }

    private function jsonWithSenoclockApiUrl(array $result, string $apiUrl, ?int $status = null): JsonResponse
    {
        $result['api_url'] = $result['api_url'] ?? $apiUrl;
        $statusCode = $status ?? ($result['success'] ? 200 : 422);

        return response()
            ->json($result, $statusCode)
            ->header('X-Senoclock-Api-Url', $result['api_url']);
    }
}
