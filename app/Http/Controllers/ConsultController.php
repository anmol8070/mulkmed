<?php

namespace App\Http\Controllers;

use App\Models\ConsultRequest;
use App\Models\Doctor;
use App\Jobs\ResendDoctorRequestJob;
use App\Jobs\ResendCustomerJoinJob;
use App\Jobs\ResendPatientDoctorSmsJob;
use App\Jobs\ResendPatientCallbackSmsJob;
use Illuminate\Http\Request;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Kreait\Firebase\Messaging\AndroidConfig;
use Kreait\Firebase\Messaging\ApnsConfig;
use App\Models\Doctors;
use App\Models\TouristList;
use App\Models\TouristAppointments;
use App\Models\Appointments;
use App\Models\TouristJitsiMeeting;
use App\Models\GlobalFunction;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use DB;
use App\Models\Constants;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Exception\Messaging\NotFound;
use App\Helpers\EmailHelpers;
use App\Models\JitsiMeeting;
use App\Models\Users;

class ConsultController extends Controller
{
    // Send New Request
    public function sendRequest_old($turistId)
    {  
        try {

                 $turistId; 
                $doctor = Doctor::select('id', 'device_token')
                            ->where('travel_visible',1)->where('status', 1)->where('device_token', '!=', null)->get();


                $appointment = new TouristAppointments();

                $appointment->appointment_number = GlobalFunction::generateAppointmentNumber();
                $appointment->tourist_id        = $turistId;
                $appointment->doctor_id         = 0; // No doctor assigned yet
                $appointment->status            = 0;
                $appointment->date              = now('UTC')->toDateString();
                $appointment->time              = now()->utc()->format('Hi');
                $appointment->problem           = '';
                $appointment->save();            


                $appId  = env('JITSI_APP_ID');
                $secret = env('JITSI_SECRET');
                $domain = env('JITSI_DOMAIN', 'meet.jit.si'); 
                $room = 'appointment-' . Str::random(10);

                $date = $appointment->date;
                $time = $appointment->time;
                $formattedTime = substr($time, 0, 2) . ':' . substr($time, 2, 2);
                $startDateTime = Carbon::createFromFormat('Y-m-d H:i', $date . ' ' . $formattedTime);
                
                $endDateTime = $startDateTime->copy()->addHour();
                $endTimestamp   = $endDateTime->copy()->setTimezone('UTC')->timestamp;
                $appointmentdate = Carbon::parse($appointment->date)->format('d-m-Y');
                $appointmentTime = Carbon::createFromFormat('Hi', $appointment->time)->format('g:i A');

                $meeting_link_tourist = GlobalFunction::GenerateTouristJitsiMeetingLink($appointment, $room, $endTimestamp);
             
                $tourist_link = GlobalFunction::CreateTouristLinkV2($appointment, $room, $endTimestamp);
               
                $tourist_link_mail = GlobalFunction::CreateTouristLinkMail($appointment, $room, $endTimestamp);

                $meeting                    = new TouristJitsiMeeting;
                $meeting->room              = $room;
                $meeting->tourist_link      = $meeting_link_tourist;
                $meeting->doctor_link       = null; // No doctor link yet
                $meeting->appointment_id    = $appointment->id;
                $meeting->tourist_id        = $appointment->tourist_id;
                $meeting->doctor_id         = 0; // No doctor assigned yet
                $meeting->start_time        = $startDateTime;
                $meeting->end_time          = $endDateTime;
                $meeting->save();            

                foreach ($doctor as $doc) {

               
                    $consult = ConsultRequest::create([
                        'doctor_id'     => $doc->id,
                        'consult_id'    => $turistId,
                        'status'        => 'pending',
                        'retry_count'   => 0,
                        'appointment_id' => $appointment->id,
                        'room'          => $room,
                        'expired_at'    => now()->addMinutes(3)
                    ]);

                        try {
                             $imageUrl = null;
                            // First Send
                            $this->sendNotification($doc->device_token,$consult->id,$imageUrl,$turistId , $appointmentdate, $appointmentTime,$appointment->id);
                        } catch (\Throwable $th) {
                            // return $th->getMessage();
                        }
                  
                    // Schedule retry after 30 sec
                    dispatch(new ResendDoctorRequestJob($consult->id))
                        // ->delay(now()->addSeconds(30));
                        ->delay(now()->addSeconds(3));
                   
                }     

                return response()->json(['status' => true, 'message' => 'Request Sent','meeting_link_tourist' => $meeting_link_tourist, 'tourist_link' => $tourist_link, 'appointmentdate' => $appointmentdate, 'appointmentTime' => $appointmentTime]);

        //     } catch (\Exception $e) {
        //         return response()->json(['status' => false, 'message' => $e->getMessage()], 500); 
        // }
    } catch (\Throwable $e) {
            Log::error('ConsultController@sendRequest failed', [
                'tourist_id' => $turistId,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            $payload = ['status' => false, 'message' => $e->getMessage()];
            if (config('app.debug')) {
                $payload['debug'] = [
                    'exception' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ];
            }

            return response()->json($payload, 500);
        }
    }

    public function sendRequest($turistId)
    {
        try {
             Log::info('sendRequest called for tourist_id: ' . $turistId);

            // 🔥 STEP 1: Get limited doctors (IMPORTANT)
            $doctors = Doctor::select('id', 'device_token')
                ->where('travel_visible', 1)
                ->where('status', 1)
                ->whereNotNull('device_token')
                ->limit(70) // 🔥 keep small for speed
                ->get();

            // 🔥 STEP 2: Create appointment
            $appointment = new TouristAppointments();
            $appointment->appointment_number = GlobalFunction::generateAppointmentNumber();
            $appointment->tourist_id = $turistId;
            $appointment->doctor_id = 0;
            $appointment->status = 0;
            $appointment->date = now('UTC')->toDateString();
            $appointment->time = now()->utc()->format('Hi');
            $appointment->problem = '';
            $appointment->save();

            // 🔥 STEP 3: Meeting setup
            $room = 'appointment-' . \Str::random(10);

            $date = $appointment->date;
            $time = $appointment->time;

            $formattedTime = substr($time, 0, 2) . ':' . substr($time, 2, 2);

            $startDateTime = \Carbon\Carbon::createFromFormat('Y-m-d H:i', $date . ' ' . $formattedTime);
            $endDateTime = $startDateTime->copy()->addHour();
            $endTimestamp = $endDateTime->copy()->setTimezone('UTC')->timestamp;

            $appointmentdate = \Carbon\Carbon::parse($appointment->date)->format('d-m-Y');
            $appointmentTime = \Carbon\Carbon::createFromFormat('Hi', $appointment->time)->format('g:i A');

            $meeting_link_tourist = GlobalFunction::GenerateTouristJitsiMeetingLink($appointment, $room, $endTimestamp);
            $tourist_link = GlobalFunction::CreateTouristLinkV2($appointment, $room, $endTimestamp);

            // 🔥 STEP 4: Save meeting
            $meeting = new TouristJitsiMeeting();
            $meeting->room = $room;
            $meeting->tourist_link = $meeting_link_tourist;
            $meeting->doctor_link = null;
            $meeting->appointment_id = $appointment->id;
            $meeting->tourist_id = $appointment->tourist_id;
            $meeting->doctor_id = 0;
            $meeting->start_time = $startDateTime;
            $meeting->end_time = $endDateTime;
            $meeting->save();

            // 🔥 STEP 5: Create consult + send notification AFTER RESPONSE
            foreach ($doctors as $doc) {

                $consult = ConsultRequest::create([
                    'doctor_id'     => $doc->id,
                    'consult_id'    => $turistId,
                    'status'        => 'pending',
                    'retry_count'   => 0,
                    'appointment_id'=> $appointment->id,
                    'room'          => $room,
                    'expired_at'    => now()->addMinutes(3)
                ]);

                // ✅ MAGIC LINE (FAST RESPONSE)
                dispatch(function () use ($doc, $consult, $turistId, $appointmentdate, $appointmentTime, $appointment) {

                    try {
                        $imageUrl = null;

                        app(\App\Http\Controllers\ConsultController::class)
                            ->sendNotification(
                                $doc->device_token,
                                $consult->id,
                                $imageUrl,
                                $turistId,
                                $appointmentdate,
                                $appointmentTime,
                                $appointment->id
                            );

                            \Log::error('Notification sended to doctor_id: ' . $doc->id . ' for consult_id: ' . $consult->id);

                    } catch (\Throwable $e) {
                        \Log::error('Notification Error: ' . $e->getMessage());
                    }

                 

                })->afterResponse(); // 🔥 IMPORTANT

                   // Schedule retry after 30 sec
                    dispatch(new ResendDoctorRequestJob($consult->id))
                        ->delay(now()->addSeconds(3));
                        // ->delay(now()->addSeconds(3))->afterResponse();

            }

            // 🔥 STEP 6: RETURN RESPONSE IMMEDIATELY
            return response()->json([
                'status' => true,
                'message' => 'Request Sent',
                'meeting_link_tourist' => $meeting_link_tourist,
                'tourist_link' => $tourist_link,
                'appointmentdate' => $appointmentdate,
                'appointmentTime' => $appointmentTime
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
    }
    }

    // Send patient appointment request only to booked doctor after 3m 3s
    public function sendPatientRequest($appointmentId)
    {
        try {
            $appointment = Appointments::find($appointmentId);
            if (!$appointment) {
                return response()->json(['status' => false, 'message' => 'Appointment not found'], 404);
            }

            $doctor = Doctors::find($appointment->doctor_id);
            if (!$doctor) {
                return response()->json(['status' => false, 'message' => "Doctor doesn't exists!"]);
            }
            if ($doctor->on_vacation == 1) {
                return response()->json(['status' => false, 'message' => 'This doctor is on vacation!']);
            }
            if ($doctor->status != Constants::statusDoctorApproved) {
                return response()->json(['status' => false, 'message' => 'This doctor is not active!']);
            }

            $patient = Users::find($appointment->user_id);
            if (!$patient) {
                return response()->json(['status' => false, 'message' => 'Patient not found'], 404);
            }

            $date = $appointment->date;
            $time = $appointment->time;
            $formattedTime = substr($time, 0, 2) . ':' . substr($time, 2, 2);
            $startDateTime = Carbon::createFromFormat('Y-m-d H:i', $date . ' ' . $formattedTime);
            $endDateTime = $startDateTime->copy()->addHour();
            $endTimestamp = $endDateTime->copy()->setTimezone('UTC')->timestamp;

            $meeting = JitsiMeeting::where('appointment_id', $appointment->id)->latest('id')->first();
            if ($meeting) {
                $room = $meeting->room;
                $doctorLink = $meeting->doctor_link;
            } else {
                $room = 'appointment-' . Str::random(10);
                $meeting_link_patient = GlobalFunction::GeneratePatientJitsiMeetingLink($appointment, $room, $endTimestamp);
                $meeting_link_doctor = GlobalFunction::GenerateDoctorJitsiMeetingLink($appointment, $room, $endTimestamp);
                $doctorLink = GlobalFunction::CreateDoctorLink($appointment, $room, $endTimestamp);

                $meeting = new JitsiMeeting();
                $meeting->room = $room;
                $meeting->patient_link = $meeting_link_patient;
                $meeting->doctor_link = $meeting_link_doctor;
                $meeting->appointment_id = $appointment->id;
                $meeting->user_id = $appointment->user_id;
                $meeting->doctor_id = $appointment->doctor_id;
                $meeting->start_time = $startDateTime;
                $meeting->end_time = $endDateTime;
                $meeting->save();
            }

            if (empty($doctorLink)) {
                $doctorLink = GlobalFunction::CreateDoctorLink($appointment, $room, $endTimestamp);
            }

            $consult = ConsultRequest::create([
                'doctor_id' => $doctor->id,
                'consult_id' => $patient->id,
                'status' => 'pending',
                'retry_count' => 0,
                'appointment_id' => $appointment->id,
                'room' => $room,
                'expired_at' => now()->addMinutes(3),
            ]);

            $doctorCountryCode = ltrim((string) ($doctor->country_code ?? ''), '+');
            $doctorMobile = preg_replace('/\D+/', '', (string) ($doctor->mobile_number ?? $doctor->phone_number ?? ''));
            $doctorMsisdn = $doctorCountryCode . $doctorMobile;

            if (!empty($doctorMsisdn)) {
                // Temporarily disabled 3-minute retry cycle (every 3 sec).
                // dispatch(new ResendPatientDoctorSmsJob($consult->id))
                //     ->delay(now()->addSeconds(3));

                // One-time notification also disabled for now.
                // dispatch(function () use ($consult) {
                //     try {
                //         app(\App\Http\Controllers\ConsultController::class)
                //             ->sendPatientNotificationToDoctor($consult->id);
                //     } catch (\Throwable $e) {
                //         \Log::warning('Patient consult one-time notification failed', [
                //             'consult_id' => $consult->id,
                //             'error' => $e->getMessage(),
                //         ]);
                //     }
                // })->afterResponse();
            }

            return response()->json([
                'status' => true,
                'message' => 'Patient request scheduled for doctor SMS',
                'appointment_id' => $appointment->id,
                'doctor_id' => $doctor->id,
                'consult_request_id' => $consult->id,
                'room' => $room,
            ]);
        } catch (\Throwable $e) {
            Log::error('ConsultController@sendPatientRequest failed', [
                'appointment_id' => $appointmentId,
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);

            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // Accept Request
    public function accept(Request $request)
    {       
        $id = $request->consult_request_id;
        $doctor_id = $request->doctor_id;

        $consult = ConsultRequest::findOrFail($id);

        if ($consult->status == 'accepted') {
            return response()->json(['status' => false, 'message' => 'Request already accepted'], 400);
        }

        if ($consult->status == 'rejected') {
            return response()->json(['status' => false, 'message' => 'Request already rejected'], 400);
        }
        
        $doctor = Doctors::find($doctor_id);
        if ($doctor == null) {
            return response()->json(['status' => false, 'message' => "Doctor doesn't exists!"]);
        }
        if ($doctor->on_vacation == 1) {
            return response()->json(['status' => false, 'message' => "this doctor is on vacation!"]);
        }
        if ($doctor->status != Constants::statusDoctorApproved) {
            return response()->json(['status' => false, 'message' => "this doctor is not active!"]);
        }

        $tourist = TouristList::find($consult->consult_id);
        if ($tourist == null) {
            return response()->json(['status' => false, 'message' => "tourist doesn't exists!"]);
        }

        $appointment = TouristAppointments::find($consult->appointment_id);
        $appointment->doctor_id         = $doctor_id;
        $appointment->status            = 1;
        $appointment->save();

        $consult->update(['status' => 'accepted','doctor_id' => $doctor_id]);
        
        try {
                $consult_appointment = ConsultRequest::where('appointment_id', $consult->appointment_id)
                    ->update([
                        'status' => 'accepted'
                    ]);
            } catch (\Throwable $th) {
                // Handle error if needed
            }
        
        $appId  = env('JITSI_APP_ID');
        $secret = env('JITSI_SECRET');
        $domain = env('JITSI_DOMAIN', 'meet.jit.si'); 
        $room = 'appointment-' . Str::random(10);

        $date = $appointment->date;
        $time = $appointment->time;
        $formattedTime = substr($time, 0, 2) . ':' . substr($time, 2, 2);
        $startDateTime = Carbon::createFromFormat('Y-m-d H:i', $date . ' ' . $formattedTime);
        
        $endDateTime = $startDateTime->copy()->addHour();
        $endTimestamp   = $endDateTime->copy()->setTimezone('UTC')->timestamp;
        $appointmentdate = Carbon::parse($appointment->date)->format('d-m-Y');
        $appointmentTime = Carbon::createFromFormat('Hi', $appointment->time)->format('g:i A');


        $meeting_link_doctor = GlobalFunction::GenerateTouristDoctorJitsiMeetingLink($appointment, $consult->room, $endTimestamp);

        $doctor_link = GlobalFunction::CreateTouristDoctorLink($appointment, $consult->room, $endTimestamp);

        $doctor_link_mail = GlobalFunction::CreateTouristDoctorLinkMail($appointment, $consult->room, $endTimestamp);

        $meeting                    = TouristJitsiMeeting::where('room', $consult->room)->first(); ;
        $meeting->doctor_link       = $meeting_link_doctor;
        $meeting->doctor_id         = $appointment->doctor_id;
        $meeting->save();

        $tourist = TouristList::find($appointment->tourist_id);
                
        if ($tourist && $tourist->number_of_consultation > 0) {
                 $tourist->decrement('number_of_consultation', 1);
        }

        if ($consult && $consult->status == 'accepted') {

            try {
    
                Http::post('http://127.0.0.1:3001/notify', [
                    'user_id' => $consult->consult_id, // 👈 patient id
                    'type' => 'doctor_accepted',
                    'message' => 'Doctor accepted your request'
                ]);
                    //code...
            } catch (\Throwable $th) {
                //throw $th;
            }  
        }

        return response()->json(['status' => true, 
                                'message' => 'Accepted',
                                'meeting_link_doctor' => $meeting_link_doctor, 
                                'doctor_link' => $doctor_link, 
                                'appointmentdate' => $appointmentdate,
                                'appointmentTime' => $appointmentTime,
                                'room' => $consult->room]);
        


        return response()->json(['status' => true, 'message' => 'Request accepted successfully']);
    }

    // FCM Send Function
    public function sendNotification($token,$consultId=null,$imageUrl = null,$touristId = null, $appointmentdate = null, $appointmentTime = null,$appointmentID = null )
    {
        $messaging = app('firebase.messaging');
   
        if (!$token) return;

        $turist = TouristList::find($touristId);
        $name = $turist ? $turist?->first_name . ' ' . $turist?->last_name : 'A tourist';

        // Format appointment date convert to 20 jan 2024
        $appointmentdate = Carbon::parse($appointmentdate)->format('d M Y');
        $appointmentTime = $appointmentTime;
        // get day from appointment date
        $appointmentDay = Carbon::parse($appointmentdate)->format('l');


        $androidConfig = AndroidConfig::fromArray([
            'notification' => [
                'image' => $imageUrl,
                'sound' => 'default',
                'channel_id' => 'consult_channel'
            ],
        ]);

        // ✅ iOS (APNs) Config
            $apnsConfig = ApnsConfig::fromArray([
                'headers' => [
                    'apns-priority' => '10',
                ],
                'payload' => [
                    'aps' => [
                        'alert' => [
                            'title' => "New Consultation Request - $name. To Accept Please Click Here",
                            'body' => 'Please accept the request',
                        ],
                        'sound' => 'ringtone.wav', // ⚠️ iOS me extension zaroor
                        'badge' => 1,
                        'content-available' => 1
                    ],
                ],
            ]);

        $message = CloudMessage::withTarget('token', $token)
            ->withNotification(Notification::create(
                "New Consultation Request - $name . To Accept Please Click Here",
                'Please accept the request',
                $imageUrl
            ))
            ->withAndroidConfig($androidConfig)
            ->withApnsConfig($apnsConfig) // 🔥 ye add kiya
            ->withData([
                'type' => 'consult_request',
                'consult_request_id' => (string) $consultId,  // 🔥 ID send
                'image_url' => $imageUrl,
                'sound' => 'ringtone',
                'name' => $name,
                'appointment_date' => $appointmentdate,
                'appointment_time' => $appointmentTime,
                'appointment_day' => $appointmentDay,
                'appointment_id'  => $appointmentID,
            ]);

        // $messaging->send($message);

        try {
        $messaging->send($message);
        } catch (NotFound $e) {
            // Token belongs to a different/unregistered Firebase project.
            Log::warning('FCM NotFound: token not registered', [
                'token_prefix' => substr((string) $token, 0, 10),
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);

            // Best-effort cleanup to prevent repeated failures.
            try {
                Doctors::where('device_token', $token)->update(['device_token' => null]);
            } catch (\Throwable $ignore) {
            }
        }
    }

    public function rejoinConsult($consultId)
    {
        $consult = ConsultRequest::with('appointment')->find($consultId);
        if (!$consult) return response()->json(['status' => false]);
        if (!$consult->appointment) {
            \Log::warning('rejoinConsult skipped: consult has no appointment relation', [
                'consult_id' => $consultId,
                'appointment_id' => $consult->appointment_id ?? null,
            ]);
            return response()->json(['status' => false, 'message' => 'Appointment not found for consult']);
        }

        $appointment = TouristAppointments::find($consult->appointment->id);
        if (!$appointment) {
            \Log::warning('rejoinConsult skipped: appointment missing', [
                'consult_id' => $consultId,
                'appointment_id' => $consult->appointment->id,
            ]);
            return response()->json(['status' => false, 'message' => 'Appointment does not exist']);
        }
        $appointment->status            = 1;
        // $appointment->date              = now()->toDateString();
        // $appointment->time              = now()->format('Hi');
        $appointment->date              = now('UTC')->toDateString();
        $appointment->time              = now()->utc()->format('Hi');
        $appointment->doctor_id         = $consult->doctor_id;
        $appointment->save();

        // return  $appointment->tourist->device_token;

        $appId  = env('JITSI_APP_ID');
        $secret = env('JITSI_SECRET');
        $domain = env('JITSI_DOMAIN', 'meet.jit.si'); 
        $room = 'appointment-' . Str::random(10);

        // dd($secret);

        $date = $appointment->date;
        $time = $appointment->time;
        $formattedTime = substr($time, 0, 2) . ':' . substr($time, 2, 2);
        $startDateTime = Carbon::createFromFormat('Y-m-d H:i', $date . ' ' . $formattedTime);
        
        $endDateTime = $startDateTime->copy()->addHour();
        $endTimestamp   = $endDateTime->copy()->setTimezone('UTC')->timestamp;
        $appointmentdate = Carbon::parse($appointment->date)->format('d-m-Y');
        $appointmentTime = Carbon::createFromFormat('Hi', $appointment->time)->format('g:i A');


         $meeting_link_doctor   = GlobalFunction::GenerateTouristDoctorJitsiMeetingLink($appointment, $room, $endTimestamp);
         $doctor_link           = GlobalFunction::CreateTouristDoctorLinkV2($appointment, $room, $endTimestamp);
         $touristLink           = GlobalFunction::GenerateTouristJitsiMeetingLink($appointment, $room, $endTimestamp);
         $tourist_link          = GlobalFunction::CreateTouristLinkV2($appointment, $room, $endTimestamp);


        

        // Save new meeting
                $meeting                    = new TouristJitsiMeeting;
                $meeting->room              = $room;
                $meeting->tourist_link      = $touristLink;
                $meeting->tourist_link_v2   = $tourist_link;
                $meeting->doctor_link       = $meeting_link_doctor; // No doctor link yet
                $meeting->appointment_id    = $appointment->id;
                $meeting->tourist_id        = $appointment->tourist_id;
                $meeting->doctor_id         = $consult->doctor_id; // No doctor assigned yet
                $meeting->start_time        = $startDateTime;
                $meeting->end_time          = $endDateTime;
                $meeting->save(); 

        // Reset 3 min logic
        $consult->retry_count = 0;
        $consult->expired_at = now()->addMinutes(3);
        $consult->status = 1; // pending again
        $consult->save();

        $appointment->status = 1; // waiting for customer
        $appointment->doctor_id = $consult->doctor_id;
        $appointment->save();
       
        // ✅ MAGIC LINE (FAST RESPONSE)
                dispatch(function () use ($consult, $appointment, $touristLink, $tourist_link) {
        try {
                $imageUrl = null;
                // First Send
                $this->sendNotificationToCustomer(
                $appointment->tourist->device_token,
                $consult->id,
                null,
                $consult->consult_id,
                now()->format('d-m-Y'),
                now()->format('g:i A'),
                $consult->appointment_id,
                $touristLink,
                $tourist_link);

            } catch (\Throwable $th) {
                            // return $th->getMessage();
            }
                  })->afterResponse(); // 🔥 IMPORTANT       
        

        // 🔥 Start customer 3 min cycle
        dispatch(new ResendCustomerJoinJob($consult->id))
                            ->delay(now()->addSeconds(3));
                            // ->afterResponse();

        return response()->json([
            'status' => true,
            'doctor_link' => $doctor_link,
            'meeting_link_doctor' =>$meeting_link_doctor,
        ]);
    }

    // Doctor callback flow for patient appointment
    public function rejoinPatientConsult($id)
    {
        // Route uses {userId}. Prefer user-based lookup first.
        // Fallback to consult_request_id only for backward compatibility.
            $consult = ConsultRequest::where('consult_id', $id)
                ->orderByDesc('id')
                ->first();
        if (!$consult) {
            $consult = ConsultRequest::find($id);
        }

        if (!$consult) {
            return response()->json(['status' => false, 'message' => 'Consult request not found for this user'], 404);
        }

        $appointment = Appointments::find($consult->appointment_id);
        if (!$appointment) {
            return response()->json(['status' => false, 'message' => 'Appointment not found'], 404);
        }

        $doctor = Doctors::find($appointment->doctor_id);
        if (!$doctor) {
            return response()->json(['status' => false, 'message' => "Doctor doesn't exists!"], 404);
        }

        $patient = Users::find($appointment->user_id);
        if (!$patient) {
            return response()->json(['status' => false, 'message' => 'Patient not found'], 404);
        }

        $date = $appointment->date;
        $time = $appointment->time;
        $formattedTime = substr($time, 0, 2) . ':' . substr($time, 2, 2);
        $startDateTime = Carbon::createFromFormat('Y-m-d H:i', $date . ' ' . $formattedTime);
        $endDateTime = $startDateTime->copy()->addHour();
        $endTimestamp = $endDateTime->copy()->setTimezone('UTC')->timestamp;
        $room = 'appointment-' . Str::random(10);

        $meeting_link_doctor = GlobalFunction::GenerateDoctorJitsiMeetingLink($appointment, $room, $endTimestamp);
        $doctor_link = GlobalFunction::CreateDoctorLink($appointment, $room, $endTimestamp);
        $meeting_link_patient = GlobalFunction::GeneratePatientJitsiMeetingLink($appointment, $room, $endTimestamp);
        $patient_link = GlobalFunction::CreatePatientLink($appointment, $room, $endTimestamp);

        $meeting = new JitsiMeeting();
        $meeting->room = $room;
        $meeting->patient_link = $meeting_link_patient;
        $meeting->doctor_link = $meeting_link_doctor;
        $meeting->appointment_id = $appointment->id;
        $meeting->user_id = $appointment->user_id;
        $meeting->doctor_id = $appointment->doctor_id;
        $meeting->start_time = $startDateTime;
        $meeting->end_time = $endDateTime;
        $meeting->save();

        $consult->retry_count = 0;
        $consult->expired_at = now()->addMinutes(3);
        $consult->status = 'pending';
        $consult->room = $room;
        $consult->save();

        // Temporarily disabled 3-minute retry cycle (every 3 sec).
        // dispatch(new ResendPatientCallbackSmsJob($consult->id))
        //     ->delay(now()->addSeconds(3));

        // One-time notification also disabled for now.
        // dispatch(function () use ($consult) {
        //     try {
        //         app(\App\Http\Controllers\ConsultController::class)
        //             ->sendPatientNotificationToUser($consult->id);
        //     } catch (\Throwable $e) {
        //         \Log::warning('Patient callback one-time notification failed', [
        //             'consult_id' => $consult->id,
        //             'error' => $e->getMessage(),
        //         ]);
        //     }
        // })->afterResponse();

        return response()->json([
            'status' => true,
            'message' => 'Patient callback request sent',
            'consult_request_id' => $consult->id,
            'appointment_id' => $appointment->id,
            'doctor_link' => $doctor_link,
            'meeting_link_doctor' => $meeting_link_doctor,
            'patient_link' => $patient_link,
        ]);
    }

    public function customerJoined($appointmentId)
    {
        $appointment = TouristAppointments::find($appointmentId);

        if (!$appointment) {
            return response()->json([
                'status' => false,
                'message' => 'Appointment not found'
            ], 404);
        }

        $consult = ConsultRequest::where('appointment_id', $appointmentId)
                ->where('doctor_id', $appointment->doctor_id)
                ->first();

        $consult->status = 'accepted';
        $consult->save();

        $appointment->status = 1; // joined
        $appointment->save();


        try {
                $consult_appointment = ConsultRequest::where('appointment_id', $appointmentId)
                    ->update([
                        'status' => 'accepted'
                    ]);
            } catch (\Throwable $th) {
                // Handle error if needed
            }

        try {

            Http::post('http://127.0.0.1:3001/notify', [
                'user_id' => $appointment->doctor_id,
                'type' => 'patient_accepted',
                'message' => 'Patient accepted your request'
            ]);

        } catch (\Throwable $th) {
            //throw $th;
        }


        return response()->json(['status' => true,'message'=>"Tourist join link"]);
    }

    public function sendNotificationToCustomer($token,$consultId=null,$imageUrl = null,$touristId = null, $appointmentdate = null, $appointmentTime = null,$appointmentID = null,$touristLink=null,$tourist_link=null )
    {
        $messaging = app('firebase.messaging');
   
        if (!$token) return;

        $turist = TouristList::find($touristId);
        $name = $turist ? $turist?->first_name . ' ' . $turist?->last_name : 'A tourist';

        // Format appointment date convert to 20 jan 2024
        $appointmentdate = Carbon::parse($appointmentdate)->format('d M Y');
        $appointmentTime = $appointmentTime;
        // get day from appointment date
        $appointmentDay = Carbon::parse($appointmentdate)->format('l');


        $androidConfig = AndroidConfig::fromArray([
            'notification' => [
                'image' => $imageUrl,
                'sound' => 'default',
                'channel_id' => 'consult_channel'
            ],
        ]);

        // ✅ iOS Config add kiya
        $apnsConfig = ApnsConfig::fromArray([
            'headers' => [
                'apns-priority' => '10',
            ],
            'payload' => [
                'aps' => [
                    'alert' => [
                        'title' => 'Live video Consultation',
                        'body' => 'Our doctor is ready to start the video consultation.',
                    ],
                    'sound' => 'default', // ⚠️ important for iOS
                    'badge' => 1,
                    'content-available' => 1,
                    'mutable-content' => 1
                ],
            ],
        ]);

        $message = CloudMessage::withTarget('token', $token)
            ->withNotification(Notification::create(
                'Live video Consultation',
                'Our doctor is ready to start the video consultation.',
                $imageUrl
            ))
            ->withAndroidConfig($androidConfig)
            ->withApnsConfig($apnsConfig) // 🔥 ye add kiya
            ->withData([
                'type' => 'consult_request',
                'consult_request_id' => (string) $consultId,  // 🔥 ID send
                'image_url' => $imageUrl,
                'sound' => 'ringtone',
                'name' => $name,
                'appointment_date' => $appointmentdate,
                'appointment_time' => $appointmentTime,
                'appointment_day' => $appointmentDay,
                'appointment_id'  => $appointmentID,
                // 'touristLink'    => $touristLink,
                'touristv2link'   =>$tourist_link
            ]);

        // $messaging->send($message);
        try {
        $messaging->send($message);
        } catch (NotFound $e) {
            Log::warning('FCM NotFound: customer token not registered', [
                'token_prefix' => substr((string) $token, 0, 10),
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);

            try {
                TouristList::where('device_token', $token)->update(['device_token' => null]);
            } catch (\Throwable $ignore) {
            }
        }
    }

    public function sendPatientNotificationToDoctor($consultId)
    {
        $consult = ConsultRequest::find($consultId);
        if (!$consult) return;

        $appointment = Appointments::find($consult->appointment_id);
        if (!$appointment) return;

        $doctor = Doctors::find($consult->doctor_id);
        $patient = Users::find($appointment->user_id);
        if (!$doctor || !$patient) return;
        if (empty($doctor->device_token)) return;

        $formattedTime = substr((string) $appointment->time, 0, 2) . ':' . substr((string) $appointment->time, 2, 2);
        $startDateTime = Carbon::createFromFormat('Y-m-d H:i', $appointment->date . ' ' . $formattedTime);
        $endTimestamp = $startDateTime->copy()->addHour()->setTimezone('UTC')->timestamp;
        $doctorLink = GlobalFunction::CreateDoctorLink($appointment, $consult->room, $endTimestamp);

        $patientName = trim((string) ($patient->fullname ?? 'Patient'));
        $patientCountryCode = ltrim((string) ($patient->country_code ?? ''), '+');
        $patientPhone = preg_replace('/\D+/', '', (string) ($patient->phone_number ?? ''));
        $patientMsisdn = trim($patientCountryCode . $patientPhone);
        $patientLabel = $patientMsisdn !== '' ? "{$patientName} (+{$patientMsisdn})" : $patientName;

        $messaging = app('firebase.messaging');
        $title = "New Consultation Request - {$patientLabel} . To Accept Please Click Here";
        $body = 'Please accept the request';

        $androidConfig = AndroidConfig::fromArray([
            'notification' => [
                'sound' => 'default',
                'channel_id' => 'consult_channel'
            ],
        ]);

        $apnsConfig = ApnsConfig::fromArray([
            'headers' => ['apns-priority' => '10'],
            'payload' => [
                'aps' => [
                    'alert' => ['title' => $title, 'body' => $body],
                    'sound' => 'default',
                    'badge' => 1,
                    'content-available' => 1
                ],
            ],
        ]);

        $message = CloudMessage::withTarget('token', $doctor->device_token)
            ->withNotification(Notification::create($title, $body))
            ->withAndroidConfig($androidConfig)
            ->withApnsConfig($apnsConfig)
            ->withData([
                'type' => 'consult_request',
                'consult_request_id' => (string) $consult->id,
                'appointment_id' => (string) $appointment->id,
                'doctor_link' => (string) $doctorLink,
            ]);

        try {
            $messaging->send($message);
        } catch (NotFound $e) {
            Log::warning('FCM NotFound: patient doctor token not registered', [
                'doctor_id' => $doctor->id,
                'message' => $e->getMessage(),
            ]);
            Doctors::where('id', $doctor->id)->update(['device_token' => null]);
        }
    }

    public function sendPatientNotificationToUser($consultId)
    {
        $consult = ConsultRequest::find($consultId);
        if (!$consult) return;

        $appointment = Appointments::find($consult->appointment_id);
        if (!$appointment) return;

        $patient = Users::find($appointment->user_id);
        if (!$patient || empty($patient->device_token)) return;

        $formattedTime = substr((string) $appointment->time, 0, 2) . ':' . substr((string) $appointment->time, 2, 2);
        $startDateTime = Carbon::createFromFormat('Y-m-d H:i', $appointment->date . ' ' . $formattedTime);
        $endTimestamp = $startDateTime->copy()->addHour()->setTimezone('UTC')->timestamp;
        $patientLink = GlobalFunction::CreatePatientLink($appointment, $consult->room, $endTimestamp);

        $patientName = trim((string) ($patient->fullname ?? 'Patient'));
        $messaging = app('firebase.messaging');
        $title = "New Consultation Request - {$patientName} . To Accept Please Click Here";
        $body = 'Please join consultation';

        $androidConfig = AndroidConfig::fromArray([
            'notification' => [
                'sound' => 'default',
                'channel_id' => 'consult_channel'
            ],
        ]);

        $apnsConfig = ApnsConfig::fromArray([
            'headers' => ['apns-priority' => '10'],
            'payload' => [
                'aps' => [
                    'alert' => ['title' => $title, 'body' => $body],
                    'sound' => 'default',
                    'badge' => 1,
                    'content-available' => 1
                ],
            ],
        ]);

        $message = CloudMessage::withTarget('token', $patient->device_token)
            ->withNotification(Notification::create($title, $body))
            ->withAndroidConfig($androidConfig)
            ->withApnsConfig($apnsConfig)
            ->withData([
                'type' => 'consult_request',
                'consult_request_id' => (string) $consult->id,
                'appointment_id' => (string) $appointment->id,
                'patient_link' => (string) $patientLink,
            ]);

        try {
            $messaging->send($message);
        } catch (NotFound $e) {
            Log::warning('FCM NotFound: patient token not registered', [
                'user_id' => $patient->id,
                'message' => $e->getMessage(),
            ]);
            Users::where('id', $patient->id)->update(['device_token' => null]);
        }
    }

    public function appointmentCompleted($appointmentId)
    {    
        $appointment = TouristAppointments::find($appointmentId);
        // $appointment = Appointments::find($appointmentId);
        $appointment = Appointments::find($appointmentId);

        if (!$appointment) {
            return response()->json([
                'status' => false,
                'message' => 'Appointment not found'
            ], 404);
        }

        $appointment->status = 2; // completed
        $appointment->save();

        return response()->json([
            'status' => true,
            'message' => 'Appointment marked as completed'
        ]);
    }



    public function checkDoctorAccepted($tourist_id)
    {
        
       $get_appointment_data = ConsultRequest::where('consult_id', $tourist_id)->orderByDesc('id')->first();

        if(!$get_appointment_data){

         return response()->json([
                'status' => true,
                'is_doctor_accepted' => 0,
                // 'doctor_status' => $consult->status,
                // 'consult_id' => $get_appointment_data->id
            ]);
        }

        $consult = ConsultRequest::select('appointment_id','consult_id','status','id')
            ->where('consult_id', $tourist_id)
            ->where('status', 'accepted')
            ->where('appointment_id',$get_appointment_data->appointment_id)
            ->groupBy('appointment_id','consult_id','status','id')
            ->orderByDesc('id')
            ->first();

            if (!$consult) {
                return response()->json([
                    'status' => true,
                    'is_doctor_accepted' => 0,
                    'message' => 'No request found',
                    'consult_id' => $get_appointment_data->id
                ]);
            }

            if ($consult->status == 'accepted') {
                return response()->json([
                    'status' => true,
                    'is_doctor_accepted' => 1,
                    'doctor_status' => 'accepted',
                    'consult_id' => $consult->id
                ]);
            }

            return response()->json([
                'status' => true,
                'is_doctor_accepted' => 0,
                'doctor_status' => $consult->status,
                'consult_id' => $consult->id
            ]);
    }

    public function checkPatientAccepted($consult_request_id)
    {
        
        $consult = ConsultRequest::select('appointment_id','consult_id','status','id')
            ->where('id', $consult_request_id)
            ->where('status', 'accepted')
            ->first();

            if (!$consult) {
                return response()->json([
                    'status' => true,
                    'is_patient_accepted' => 0,
                    // 'message' => 'No request found'
                ]);
            }

            if ($consult->status == 'accepted') {
                return response()->json([
                    'status' => true,
                    'is_patient_accepted' => 1,
                    // 'patient_status' => 'accepted'

                ]);
            }

            return response()->json([
                'status' => true,
                'is_patient_accepted' => 0,
                // 'patient_status' => $consult->status
            ]);
    }

     public function cancelRequestByConsultId($consultId)
    {
        $latestConsult = ConsultRequest::where('consult_id', $consultId)
            ->orderByDesc('id')
            ->first();

        if (!$latestConsult) {
            return response()->json([
                'status' => false,
                'message' => 'No consult request found for this consult_id'
            ], 404);
        }

        $updatedRows = ConsultRequest::where('appointment_id', $latestConsult->appointment_id)
            // ->where('status', 'pending')
            ->update([
                'status' => 'rejected',
                'updated_at' => now(),
            ]);

        if ($updatedRows === 0) {
            return response()->json([
                'status' => false,
                'message' => 'No pending consult request found to cancel'
            ], 400);
        }

        return response()->json([
            'status' => true,
            'message' => 'Consult request cancelled successfully',
            'consult_id' => (int) $consultId,
            'appointment_id' => $latestConsult->appointment_id,
            'cancelled_rows' => $updatedRows
            ]);
    }

}