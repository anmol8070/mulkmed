<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\LabReport;
use App\Models\User;
use App\Services\SenoclockAiService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProcessSenoclockIntegration implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $labReportId;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($labReportId)
    {
        $this->labReportId = $labReportId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(SenoclockAiService $service)
    {
        try {
            $labReport = LabReport::find($this->labReportId);
            if (!$labReport) {
                Log::error("ProcessSenoclockIntegration: LabReport not found", ['lab_report_id' => $this->labReportId]);
                return;
            }

            $service->processLabReport($labReport);

        } catch (\Throwable $e) {
            Log::error('ProcessSenoclockIntegration: Exception caught', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
        }
    }

    private function getSenoclockToken()
    {
        $baseUrl = config('services.senoclock.base_url', 'https://api-euc1.senoclock.ai');
        $email = config('services.senoclock.email');
        $password = config('services.senoclock.password');

        if (!$email || !$password) {
            return null;
        }

        try {
            $response = Http::withoutVerifying()->post("{$baseUrl}/rest-auth/login/", [
                'email' => $email,
                'password' => $password,
            ]);

            if ($response->successful()) {
                return $response->json('key') ?? $response->json('token') ?? $response->json('access_token');
            } else {
                Log::error('ProcessSenoclockIntegration: Token auth failed', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
            }
        } catch (\Exception $e) {
            Log::error('ProcessSenoclockIntegration: Token Exception', ['message' => $e->getMessage()]);
        }

        return null;
    }
}
