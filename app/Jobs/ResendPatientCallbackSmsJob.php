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

class ResendPatientCallbackSmsJob implements ShouldQueue
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

        if ($consult->status !== 'pending') {
            return;
        }
        if (now()->greaterThan($consult->expired_at) || $consult->retry_count >= 60) {
            return;
        }

        try {
            Log::info('Patient callback notification sending', [
                'consult_id' => $consult->id,
                'appointment_id' => $consult->appointment_id,
                'retry_count' => $consult->retry_count,
            ]);
            app(ConsultController::class)->sendPatientNotificationToUser($consult->id);
            Log::info('Patient callback notification sent', [
                'consult_id' => $consult->id,
                'appointment_id' => $consult->appointment_id,
                'retry_count' => $consult->retry_count,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Patient callback notification failed', [
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
