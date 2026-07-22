<?php

namespace App\Jobs;

use App\Http\Controllers\ConsultController;
use App\Models\ConsultRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ResendPatientDoctorSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $consultId;

    public function __construct($consultId)
    {
        $this->consultId = $consultId;
    }

    public function handle()
    {
        $consult = ConsultRequest::find($this->consultId);
        if (!$consult) {
            return;
        }

        // Stop retries once status changes or cycle expires.
        if ($consult->status !== 'pending') {
            return;
        }
        if (now()->greaterThan($consult->expired_at) || $consult->retry_count >= 60) {
            return;
        }

        try {
            Log::info('Patient consult notification sending to doctor', [
                'consult_id' => $consult->id,
                'appointment_id' => $consult->appointment_id,
                'retry_count' => $consult->retry_count,
            ]);
            app(ConsultController::class)->sendPatientNotificationToDoctor($consult->id);
            Log::info('Patient consult notification sent to doctor', [
                'consult_id' => $consult->id,
                'appointment_id' => $consult->appointment_id,
                'retry_count' => $consult->retry_count,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Patient consult notification failed', [
                'consult_id' => $consult->id,
                'appointment_id' => $consult->appointment_id,
                'retry_count' => $consult->retry_count,
                'error' => $e->getMessage(),
            ]);
        }

        $consult->increment('retry_count');

        self::dispatch($consult->id)->delay(now()->addSeconds(3));
    }
}
