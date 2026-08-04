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
        set_time_limit(300); // Allow up to 5 minutes for SenoClock to generate the PDF

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
}
