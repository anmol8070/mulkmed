<?php

namespace App\Jobs;

use App\Models\ConsultRequest;
use App\Models\TouristAppointments;
use App\Models\TouristJitsiMeeting;
use App\Models\Doctors;
use App\Helpers\EmailHelpers;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ResendCustomerJoinJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $consultId;

    public function __construct($consultId)
    {
        $this->consultId = $consultId;
    }

    public function handle()
    {
         \Log::info('Customer JOB IS RUNNING');
        $consult = ConsultRequest::with('appointment.tourist')->find($this->consultId);
        if (!$consult) return;

            \Log::info('send Notification To Customer called', [
                    'status'      => $consult->status,
                    'id'  => $consult->id,
                    'in'  => 'yes'
                    ]);
        
        // ✅ Stop if accepted
        if ($consult->status !== 'pending') return;

                 \Log::info('send Notification To Customer called', [
                    'status'      => $consult->status,
                    'id'  => $consult->id,
                    'in'  => 'not'
                    ]);

        $appointment = $consult->appointment;
        if (!$appointment) return;


        \Log::info('send Notification To Customer called', [
                    'appointment_status'      => $appointment->status,
                    'appointment_id'  => $appointment->id
                    ]);

        // ✅ Stop if customer joined
        // if ($appointment->status != 0 ) return;

        // ✅ Stop after 3 minutes (60 attempts)
        if (now()->greaterThan($consult->expired_at) || $consult->retry_count >= 60) {
            $lockName = 'missed_sms_patient_timeout_appointment_' . $consult->appointment_id;
            $lockAcquired = (int) (DB::selectOne('SELECT GET_LOCK(?, 2) AS l', [$lockName])->l ?? 0);
            if ($lockAcquired !== 1) {
                \Log::info('Consult missed processing skipped (db lock busy)', [
                    'appointment_id' => $consult->appointment_id,
                    'consult_id' => $consult->id,
                ]);
                return;
            }

            try {

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

            \Log::info('Consult flow marked missed (patient timeout)', [
                'appointment_id' => $appointment->id,
                'consult_id' => $consult->id,
            ]);

            $appointment->loadMissing('tourist');
            $doctor = Doctors::find($appointment->doctor_id);

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
            $doctorName = trim($doctor->name ?? '');
            if ($doctorName === '') {
                $doctorName = 'Doctor';
            }
            $message = "{$patientLabel} missed call from {$doctorName}.";

           $smsLockKey = 'missed_sms_alert_sent_patient_timeout_appointment_' . $appointment->id;
            $smsMessagePreviewKey = 'missed_sms_alert_message_patient_timeout_appointment_' . $appointment->id;
            if (!Cache::add($smsLockKey, now()->toDateTimeString(), now()->addDay())) {
                \Log::info('Consult missed SMS skipped (cache lock exists)', [
                    'appointment_id' => $appointment->id,
                    'consult_id' => $consult->id,
                    'alert_mobiles' => ['971522463433', '971569337544'],
                    'message_preview' => Cache::get($smsMessagePreviewKey, $message),
                ]);
                return;
            }

            $alertMobiles = ['971522463433', '971569337544'];
            Cache::put($smsMessagePreviewKey, $message, now()->addDay());
            \Log::info('Consult missed SMS prepared (patient timeout)', [
                'appointment_id' => $appointment->id,
                'tourist_country_code' => $touristCountryCode,
                'tourist_contact_number' => $touristDigits,
                'alert_mobiles' => $alertMobiles,
                'message_preview' => $message,
            ]);

            foreach ($alertMobiles as $mobile) {
                try {
                    \Log::info('Consult missed SMS sending (patient timeout)', [
                        'appointment_id' => $appointment->id,
                        'mobile' => $mobile,
                        'message_preview' => $message,
                    ]);
                    EmailHelpers::sendSms($mobile, $message);
                    \Log::info('Consult missed SMS sent (patient timeout)', [
                        'appointment_id' => $appointment->id,
                        'mobile' => $mobile,
                        'message_preview' => $message,
                    ]);
                } catch (\Throwable $e) {
                    \Log::warning('Missed alert SMS failed (patient timeout)', [
                        'mobile' => $mobile,
                        'appointment_id' => $appointment->id,
                        'message' => $e->getMessage(),
                    ]);
                }
            }

            return;
            } finally {
                DB::select('SELECT RELEASE_LOCK(?)', [$lockName]);
            }
        }

        $touristLink = TouristJitsiMeeting::where('appointment_id', $consult->appointment_id)
                    ->orderBy('id', 'desc')
                    ->value('tourist_link');

         $tourist_link_v2 = TouristJitsiMeeting::where('appointment_id', $consult->appointment_id)
                    ->orderBy('id', 'desc')
                    ->value('tourist_link_v2');            

        // 🔥 Send Notification To Customer
        try {
        app('App\Http\Controllers\ConsultController')
            ->sendNotificationToCustomer(
                $appointment->tourist->device_token,
                $consult->id,
                null,
                $consult->consult_id,
                now()->format('d-m-Y'),
                now()->format('g:i A'),
                $consult->appointment_id,
                "like this link to join the consultation",
                $tourist_link_v2
            );
        } catch (\Throwable $e) {
            \Log::warning('Consult patient push retry failed, continuing', [
                'consult_id' => $consult->id,
                'appointment_id' => $consult->appointment_id,
                'error' => $e->getMessage(),
            ]);
        }

            //  \Log::info('sendNotificationToCustomer called', [
            //         'device_token'      => $appointment->tourist->device_token,
            //         'consult_id'        => $consult->id,
            //         'consult_id'    => $consult->consult_id,
            //         'date'              =>   now()->format('d-m-Y'),
            //         'time'              =>  now()->format('g:i A'),
            //          'appointment_id'              =>   $consult->appointment_id,
            //         'tourist_link_v1'   => $touristLink ?? null,
            //         'tourist_link_v2'   => $tourist_link_v2 ?? null,
            //     ]);

        // Increase retry
        $consult->increment('retry_count');

        // Retry after 3 seconds
        self::dispatch($consult->id)
            ->delay(now()->addSeconds(3));
    }
}