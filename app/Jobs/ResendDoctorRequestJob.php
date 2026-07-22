<?php

namespace App\Jobs;

use App\Models\ConsultRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\TouristAppointments;
use App\Helpers\EmailHelpers;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ResendDoctorRequestJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $consultId;

    public function __construct($consultId)
    {
        $this->consultId = $consultId;
    }

    public function handle()
    {   
        \Log::info('JOB IS RUNNING');
        $consult = ConsultRequest::with('doctor')->find($this->consultId);

        if (!$consult) return;

        // ✅ Stop if accepted
        if ($consult->status !== 'pending') return;

        // ✅ If 3 minutes completed
            if (now()->greaterThan($consult->expired_at) || $consult->retry_count >= 60) {

                // 1️⃣ Update consult status
                // $consult->status = 'missing'; // expired
                // $consult->save();

                // 2️⃣ Check if any doctor accepted for same appointment
                $anyAccepted = ConsultRequest::where('appointment_id', $consult->appointment_id)
                                ->where('status', 'accepted')
                                ->exists();

                if (!$anyAccepted) {
                    $lockName = 'missed_sms_doctor_timeout_appointment_' . $consult->appointment_id;
                    $lockAcquired = (int) (DB::selectOne('SELECT GET_LOCK(?, 2) AS l', [$lockName])->l ?? 0);
                    if ($lockAcquired !== 1) {
                        \Log::info('Consult missed processing skipped (db lock busy)', [
                            'appointment_id' => $consult->appointment_id,
                            'consult_id' => $consult->id,
                        ]);
                        return;
                    }

                    try {

                    // 3️⃣ Update Appointment status
                    $appointment = TouristAppointments::find($consult->appointment_id);

                    if ($appointment) {
                        $statusUpdated = TouristAppointments::where('id', $appointment->id)
                            ->where('status', '!=', 5)
                            ->update(['status' => 5]); // expired

                        if (!$statusUpdated) {
                            \Log::info('Consult missed SMS skipped (already processed)', [
                                'appointment_id' => $appointment->id,
                                'consult_id' => $consult->id,
                            ]);
                            return;
                        }

                        \Log::info('Consult flow marked missed (doctor timeout)', [
                            'appointment_id' => $appointment->id,
                            'consult_id' => $consult->id,
                        ]);

                        $smsLockKey = 'missed_sms_alert_sent_doctor_timeout_appointment_' . $appointment->id;
                        if (!Cache::add($smsLockKey, now()->toDateTimeString(), now()->addDay())) {
                            \Log::info('Consult missed SMS skipped (cache lock exists)', [
                                'appointment_id' => $appointment->id,
                                'consult_id' => $consult->id,
                            ]);
                            return;
                        }

                        $appointment->loadMissing('tourist');
                        $touristName = trim((string) ($appointment->tourist->first_name ?? ''));
                        if ($touristName === '') {
                        $touristName = trim(
                            ($appointment->tourist->first_name ?? '') . ' ' . ($appointment->tourist->last_name ?? '')
                        );
                        }
                        if ($touristName === '') {
                            $touristName = 'Patient';
                        }

                        $touristCountryCode = \App\Models\GlobalFunction::normalizeCountryCode($appointment->tourist->country_code ?? null);
                        $touristDigits = preg_replace('/\D+/', '', (string) ($appointment->tourist->contact_number ?? $appointment->tourist->phone_number ?? ''));
                        $touristPhone = '';
                        if (!empty($touristCountryCode) && !empty($touristDigits)) {
                            $localNumber = (strpos($touristDigits, $touristCountryCode) === 0)
                                ? substr($touristDigits, strlen($touristCountryCode))
                                : $touristDigits;
                            $touristPhone = '+' . $touristCountryCode . $localNumber;
                        } elseif (!empty($touristDigits)) {
                            $touristPhone = '+' . $touristDigits;
                        }
                        $patientLabel = trim($touristName . ($touristPhone !== '' ? ' ' . $touristPhone : ''));

                        $message = "Dr missed call from {$patientLabel}.";
                        $alertMobiles = ['971522463433', '971569337544'];
                        \Log::info('Consult missed SMS prepared (doctor timeout)', [
                            'appointment_id' => $appointment->id,
                            'tourist_country_code' => $touristCountryCode,
                            'tourist_contact_number' => $touristDigits,
                             'alert_mobiles' => $alertMobiles,
                            'message_preview' => $message,
                        ]);

                        foreach ($alertMobiles as $mobile) {
                            try {
                                \Log::info('Consult missed SMS sending (doctor timeout)', [
                                    'appointment_id' => $appointment->id,
                                    'mobile' => $mobile,
                                    'message_preview' => $message,
                                ]);
                                EmailHelpers::sendSms($mobile, $message);
                                \Log::info('Consult missed SMS sent (doctor timeout)', [
                                    'appointment_id' => $appointment->id,
                                    'mobile' => $mobile,
                                    'message_preview' => $message,
                                ]);
                            } catch (\Throwable $e) {
                                \Log::warning('Missed alert SMS failed (doctor timeout)', [
                                    'mobile' => $mobile,
                                    'appointment_id' => $appointment->id,
                                    'message' => $e->getMessage(),
                                ]);
                            }
                        }
                    }
                    } finally {
                        DB::select('SELECT RELEASE_LOCK(?)', [$lockName]);
                    }
                }

                return;
            }

        // ✅ Stop after 3 minutes
        if (now()->greaterThan($consult->expired_at)) return;

        // ✅ Stop after 60 attempts (3 min / 3 sec)
        if ($consult->retry_count >= 60) return;

        // 🔥 Send Notification Again
        try {
        app('App\Http\Controllers\ConsultController')
            ->sendNotification(
                $consult->doctor->device_token,
                $consult->id,
                null,
                $consult->consult_id,
                now()->format('d-m-Y'),
                now()->format('g:i A'),
                $consult->appointment_id
                );
        } catch (\Throwable $e) {
            \Log::warning('Consult doctor push retry failed, continuing', [
                'consult_id' => $consult->id,
                'appointment_id' => $consult->appointment_id,
                'error' => $e->getMessage(),
            ]);
        }

        // Increase retry
        $consult->increment('retry_count');

        // Schedule next retry after 3 seconds
        self::dispatch($consult->id)
            ->delay(now()->addSeconds(3));
    }
}