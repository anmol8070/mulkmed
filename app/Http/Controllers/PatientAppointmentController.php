<?php

namespace App\Http\Controllers;

use App\Models\HnHCards;
use App\Models\Users;
use App\Models\Doctor;
use App\Models\GlobalFunction;
use App\Models\GlobalSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\AppointmentDocs;
use App\Models\Appointments;
use App\Models\Constants;
use App\Models\Doctors;
use App\Models\JitsiMeeting;
use App\Models\UserCoupons;
use App\Helpers\EmailHelpers;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
use Illuminate\Support\Str;

class PatientAppointmentController extends Controller
{
    function createRegistration(Request $request)
    {
        return view('patient_appointment.registration');
    }

    function storeRegistration(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fullname' => 'required|string|max:255',
            'gender' => ['required', Rule::in(Constants::genderMale, Constants::genderFemale)],
            'dob' => 'required|date',
            'email' => 'nullable|email|unique:users,identity',
            'username' => 'required|string|unique:users,username',
            'password' => 'required',
            'confirm_password' => 'required|same:password',
            'id_number' => 'nullable',
            'type' => 'required',
        ]);

        // Add custom phone-number check
        $validator->after(function ($validator) use ($request) {
            $exists = Users::where('phone_number', $request->phone_number)
                ->where('country_code', $request->country_code)
                ->exists();

            if ($exists) {
                $validator->errors()->add('phone_number', 'User with this phone number already exists.');
            }
        });

        // If validation fails, auto-redirect with errors
        $validator->validate();

        $user = new Users();
        $user->fullname = $request->fullname;
        $user->gender = (int) $request->gender;
        $user->dob = $request->dob;
        $user->email = $request->email;
        $user->identity = $request->email;
        $user->username = $request->username;
        $user->password = ($request->password);
        $user->ref_id = $request->id_number;
        $user->country_code = $request->country_code;
        $user->phone_number = $request->phone_number;
        $user->type = $request->type ?? null;
        $user->save();

        return redirect()->back()->with('success', 'Account created successfully!');
    }
  
    function createAppointment(Request $request)  
    {
        $patients = Users::select('id', 'fullname', 'phone_number', 'type')->get();

        // Load doctors (active + profile completed)
        $doctors = Doctor::where('status', 1)
                        ->where('is_profile_complete', 1)  
                        ->select('id', 'name')
                        ->get();
        return view('patient_appointment.appointment', compact('patients', 'doctors'));
    }
 
    function storeAppointment(Request $request){
        Log::info('BOOKING_TRACE patientAppointment.storeAppointment hit', [
            'route' => 'web.patientAppointment.storeAppointment',
            'user_id' => $request->user_id,
            'doctor_id' => $request->doctor_id,
            'date' => $request->date,
            'time' => $request->time,
            'host' => $request->getHost(),
        ]);
         $rules = [
            'user_id' => 'required',
            'doctor_id' => 'required',
            'problem' => 'required',
            'date' => 'required',
            'time' => 'required',
            // 'type' => 'required',
            'is_coupon_applied' => [Rule::in(1, 0)],
            // 'user_coupon_id' => 'required',
            'service_amount' => 'required',
            'discount_amount' => 'required',
            'subtotal' => 'required',
            // 'order_summary' => 'required',
            'total_tax_amount' => 'required',
            'payable_amount' => 'required',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }

        $doctor = Doctors::find($request->doctor_id);
        if ($doctor == null) {
            return response()->json(['status' => false, 'message' => "Doctor doesn't exists!"]);
        }
        if ($doctor->on_vacation == 1) {
            return response()->json(['status' => false, 'message' => "this doctor is on vacation!"]);
        }
        if ($doctor->status != Constants::statusDoctorApproved) {
            return response()->json(['status' => false, 'message' => "this doctor is not active!"]);
        }

        $user = Users::find($request->user_id);
        if ($user == null) {
            return response()->json(['status' => false, 'message' => "User doesn't exists!"]);
        }

        $normalizedInputTime = GlobalFunction::normalizeTimeToHis($request->time);
        $headerTimezone = trim((string) $request->header('X-Timezone', ''));
        $inputTimezone = trim((string) $request->input('browser_timezone', ''));
        $timezoneCandidate = $inputTimezone !== '' ? $inputTimezone : $headerTimezone;

        // The admin's input time is interpreted in the timezone of the platform
        // they are booking from, not the browser timezone or any user/doctor
        // country. pt.mulkmed.com → Asia/Dubai, india.mulkmed.com → Asia/Kolkata.
        $adminHost = $request->getHost();
        $adminHostTimezone = GlobalFunction::getAdminTimezoneByHost($adminHost);

        Log::info('BOOKING_TRACE admin timezone payload', [
            'header_timezone' => $headerTimezone,
            'input_timezone' => $inputTimezone,
            'timezone_candidate' => $timezoneCandidate,
            'admin_host' => $adminHost,
            'admin_host_timezone' => $adminHostTimezone,
        ]);

        $utcTimezone = GlobalFunction::getUtcTimezoneValue();
        $userCountryTimezone = GlobalFunction::getTimezoneByCountryCode($user->country_code);
        $doctorCountryTimezone = GlobalFunction::getTimezoneByCountryCode($doctor->country_code);
        $timezoneIdentifiers = \DateTimeZone::listIdentifiers();

        $fallbackTimezoneForAdminInput = in_array($adminHostTimezone, $timezoneIdentifiers, true)
            ? $adminHostTimezone
            : (in_array($userCountryTimezone, $timezoneIdentifiers, true)
            ? $userCountryTimezone
                : (in_array($doctorCountryTimezone, $timezoneIdentifiers, true) ? $doctorCountryTimezone : $utcTimezone));

        // Host-derived timezone is authoritative for admin input. Any explicit
        // header/input timezone is ignored so the admin's typed time always
        // matches the platform region (pt = UAE, india = India).
        $sourceTimezoneForAdminInput = $fallbackTimezoneForAdminInput;
        $utcDateToStore = $request->date;
        $utcTimeToStore = $request->time;

        try {
            if (!empty($normalizedInputTime) && !empty($request->date)) {
                $utcDateTime = Carbon::parse($request->date . ' ' . $normalizedInputTime, $sourceTimezoneForAdminInput)
                    ->setTimezone($utcTimezone);
                $utcDateToStore = $utcDateTime->format('Y-m-d');
                $utcTimeToStore = $utcDateTime->format('Hi');
            }
        } catch (\Throwable $th) {
            Log::warning('BOOKING_TRACE admin local-to-utc conversion failed, storing raw input', [
                'input_date' => $request->date,
                'input_time' => $request->time,
                'source_timezone' => $sourceTimezoneForAdminInput,
                'target_timezone' => $utcTimezone,
                'error' => $th->getMessage(),
            ]);
        }
        Log::info('BOOKING_TRACE admin local-to-utc conversion', [
            'input_date' => $request->date,
            'input_time' => $request->time,
            'normalized_input_time' => $normalizedInputTime,
            'source_timezone' => $sourceTimezoneForAdminInput,
            'fallback_timezone' => $fallbackTimezoneForAdminInput,
            'user_country_timezone' => $userCountryTimezone,
            'doctor_country_timezone' => $doctorCountryTimezone,
            'target_timezone' => $utcTimezone,
            'stored_date' => $utcDateToStore,
            'stored_time' => $utcTimeToStore,
        ]);
        // $user_coupons = UserCoupons::where('id',$request->user_coupon_id)->where('number_of_limits',">",0)->first();

        // if(!($user_coupons))
        // {
        //     return response()->json(['status' => false, 'message' => "Coupon doesn't exists!"]);
        // }
        
        $appointment = new Appointments();

        $appointment->appointment_number = GlobalFunction::generateAppointmentNumber();
        $appointment->completion_otp = rand(1000, 9999);
        $appointment->user_id = $request->user_id;
        $appointment->doctor_id = $request->doctor_id;
        $appointment->status = 1;
        $appointment->date = $utcDateToStore;
        $appointment->time = $utcTimeToStore;
        $appointment->type = $request->type ?? 0;
        $appointment->problem = GlobalFunction::cleanString($request->problem);
        $appointment->order_summary = $request->order_summary;
        $appointment->is_coupon_applied = $request->is_coupon_applied;
        $appointment->service_amount = $request->service_amount;
        $appointment->discount_amount = $request->discount_amount;
        $appointment->subtotal = $request->subtotal;
        $appointment->total_tax_amount = $request->total_tax_amount;
        $appointment->payable_amount = $request->payable_amount;
        $appointment->user_coupon_id = $request->user_coupon_id ?? 0;
        $appointment->payment_status = 1;
        $appointment->admin_billing_type = ($user->type || $request->markInsurance) ? 'Insurance' : null;

        $appointment->save();
        Log::info('BOOKING_TRACE patient appointment saved', [
            'appointment_id' => $appointment->id,
            'appointment_number' => $appointment->appointment_number,
            'date' => $appointment->date,
            'time' => $appointment->time,
        ]);

        $user_coupons = UserCoupons::where('id',$request->user_coupon_id)->update(['number_of_limits' => DB::raw('number_of_limits - 1')]);
        $attachments = [];

        // if ($request->hasFile('document')) {

        //     $document = $request->file('document');
        //     $docs = new AppointmentDocs();
        //     $docs->appointment_id = $appointment->id;
        //     $docs->image = GlobalFunction::saveFileAndGivePath($document);
        //     $docs->is_from_admin = 1;
        //     $docs->save();
            

        //     $attachments[] = GlobalFunction::createMediaUrl($docs->image);

        //     \Mail::to('info@mulkmed.com')->send(new \App\Mail\AppointmentDocumentsMail($user->fullname,$user->phone_number,$user->email,$doctor->name,$doctor->clinic_name,$attachments));
        // }

        if ($request->hasFile('document')) {

            $document = $request->file('document');

            $docs = new AppointmentDocs();
            $docs->appointment_id = $appointment->id;
            $docs->image = GlobalFunction::saveFileAndGivePath($document);
            $docs->is_from_admin = 1;
            $docs->save();
            
    $attachments = [];
            $attachments[] = GlobalFunction::createMediaUrl($docs->image);

    \Log::info('Attachments:', $attachments);

    try {

        \Mail::to('info@mulkmed.com')->send(
            new \App\Mail\AppointmentDocumentsMail(
                $user->fullname,
                $user->phone_number,
                $user->email,
                $doctor->name,
                $doctor->clinic_name,
                $attachments
            )
        );

        \Log::info('Appointment document email sent successfully.');

    } catch (\Throwable $e) {

        \Log::error('Appointment document email failed: ' . $e->getMessage());

    }
        }

        $appId  = env('JITSI_APP_ID');
        $secret = env('JITSI_SECRET');
        $domain = env('JITSI_DOMAIN', 'meet.jit.si'); 
        $room = 'appointment-' . Str::random(10);
        // $jitsiBaseUrl = env('JITSI_URL');
        // $jitsiJwt = env('JWT_TOKEN_JITSI_MEETING');
        // $link = $jitsiBaseUrl . '?roomId=' . $room . '&jwt=' . $jitsiJwt;
        $date = $appointment->date;
        $time = $appointment->time;
        $formattedTime = substr($time, 0, 2) . ':' . substr($time, 2, 2);
        $startDateTime = Carbon::createFromFormat('Y-m-d H:i', $date . ' ' . $formattedTime);
        
        $endDateTime = $startDateTime->copy()->addHour();
        $endTimestamp   = $endDateTime->copy()->setTimezone('UTC')->timestamp;;
        $appointmentdate = Carbon::parse($appointment->date)->format('d-m-Y');
        $appointmentTime = Carbon::createFromFormat('Hi', $appointment->time)->format('g:i A');

        $meeting_link_patient = GlobalFunction::GeneratePatientJitsiMeetingLink($appointment, $room, $endTimestamp);
        $meeting_link_doctor = GlobalFunction::GenerateDoctorJitsiMeetingLink($appointment, $room, $endTimestamp);

        $patient_link = GlobalFunction::CreatePatientLink($appointment, $room, $endTimestamp);
        $doctor_link = GlobalFunction::CreateDoctorLink($appointment, $room, $endTimestamp);

        $patient_link_mail = GlobalFunction::CreatePatientLinkMail($appointment, $room, $endTimestamp);
        $doctor_link_mail = GlobalFunction::CreateDoctorLinkMail($appointment, $room, $endTimestamp);

        $meeting = new JitsiMeeting;
        $meeting->room = $room;
        $meeting->patient_link = $meeting_link_patient;
        $meeting->doctor_link = $meeting_link_doctor;
        $meeting->appointment_id = $appointment->id;
        $meeting->user_id = $appointment->user_id;
        $meeting->doctor_id = $appointment->doctor_id;
        $meeting->start_time = $startDateTime;
        $meeting->end_time = $endDateTime;
        $meeting->save();

        // $path = "meetings/join/".$room;
        // $meeting_link = url($path);
        // $meeting_link_patient =  $link;
        // $meeting_link_doctor =  $link;

        $user = Users::find($appointment->user_id);
        $doctor = Doctors::find($appointment->doctor_id);

        $appointment = Appointments::find($appointment->id) ?? $appointment;
        $normalizedTime = GlobalFunction::normalizeTimeToHis($appointment->time);
        $userTimezone = GlobalFunction::getTimezoneByCountryCode($user->country_code);
        $doctorTimezone = GlobalFunction::getTimezoneByCountryCode($doctor->country_code);
        $baseTimezoneForConversion = GlobalFunction::getUtcTimezoneValue();
        // Internal admin SMS is always read by the UAE/India ops team on the
        // booking portal, so it must reflect the admin's input time. We derive
        // that from the booking host (pt → Asia/Dubai, india → Asia/Kolkata).
        // Patient and doctor SMS continue to use their own country timezones.
        $adminPlatformTimezone = GlobalFunction::getAdminTimezoneByHost($adminHost);

        $userDateForLog = GlobalFunction::convertDateTimeToUserTimezone($appointment->date, $normalizedTime, $userTimezone, 'd-m-Y', $baseTimezoneForConversion);
        $userTimeForLog = GlobalFunction::convertTimeToUserTimezone($normalizedTime, $userTimezone, $appointment->date, 'g:i A', $baseTimezoneForConversion);
        $doctorDateForLog = GlobalFunction::convertDateTimeToUserTimezone($appointment->date, $normalizedTime, $doctorTimezone, 'd-m-Y', $baseTimezoneForConversion);
        $doctorTimeForLog = GlobalFunction::convertTimeToUserTimezone($normalizedTime, $doctorTimezone, $appointment->date, 'g:i A', $baseTimezoneForConversion);
        $adminPlatformDate = GlobalFunction::convertDateTimeToUserTimezone($appointment->date, $normalizedTime, $adminPlatformTimezone, 'd-m-Y', $baseTimezoneForConversion);
        $adminPlatformTime = GlobalFunction::convertTimeToUserTimezone($normalizedTime, $adminPlatformTimezone, $appointment->date, 'g:i A', $baseTimezoneForConversion);
        $userAppointmentDate = $userDateForLog ?? $appointmentdate;
        $userAppointmentTime = $userTimeForLog ?? $appointmentTime;
        $doctorAppointmentDate = $doctorDateForLog ?? $appointmentdate;
        $doctorAppointmentTime = $doctorTimeForLog ?? $appointmentTime;
        Log::info('BOOKING_TRACE patient flow conversion snapshot', [
            'appointment_id' => $appointment->id,
            'raw_date' => $appointment->date,
            'raw_time' => $appointment->time,
            'normalized_time' => $normalizedTime,
            'base_timezone' => $baseTimezoneForConversion,
            'admin_host' => $adminHost,
            'admin_platform_timezone' => $adminPlatformTimezone,
            'user_country_code' => $user->country_code,
            'doctor_country_code' => $doctor->country_code,
            'user_timezone' => $userTimezone,
            'doctor_timezone' => $doctorTimezone,
            'user_converted_date' => $userDateForLog,
            'user_converted_time' => $userTimeForLog,
            'doctor_converted_date' => $doctorDateForLog,
            'doctor_converted_time' => $doctorTimeForLog,
            'admin_platform_date' => $adminPlatformDate,
            'admin_platform_time' => $adminPlatformTime,
        ]);

        \Mail::to($user->email)->send(new \App\Mail\JitsiMeetingLinkPatient($appointment, $doctor, $user, $patient_link_mail));

        $cleanCode = ltrim($user->country_code, '+');
        // Build SMS message (plain text)
//         $message = "Dear {$user->fullname},

// Your appointment has been successfully booked with {$doctor->name} ({$doctor->designation}).

// Appointment Details:
// Doctor: {$doctor->name}
// Specialty: {$doctor->designation}
// Date & Time: {$appointmentdate}, {$appointmentTime}
// Link: {$patient_link_mail}

// Regards,
// Team Mulk Med";     

$message = "Appointment: {$userAppointmentDate} at {$userAppointmentTime}. Join:here{$patient_link_mail}";
                $result = EmailHelpers::sendSms($cleanCode . $user->phone_number, $message);
                Log::info('BOOKING_TRACE patient flow patient sms triggered', [
                    'appointment_id' => $appointment->id,
                    'receiver' => $cleanCode . $user->phone_number,
                    'user_country_code' => $user->country_code,
                    'user_timezone' => $userTimezone,
                    'patient_sms_date' => $userAppointmentDate,
                    'patient_sms_time' => $userAppointmentTime,
                    'message_preview' => $message,
                ]);
                
                $current_date = Carbon::now()->toDateString();
                $current_time = Carbon::now('Asia/Kolkata')->toTimeString();;
//                 $message = "Dear {$doctor->name},

// Payment of AED {$appointment->payable_amount} received for your appointment at Mulk Med on {$current_date} at {$current_time}.

// Thank you for choosing us.

// Regards,
// Team Mulk Med";            
                // $result = EmailHelpers::sendSms($cleanCode . $doctor->phone_number, $message);
                $countryCode = ltrim($doctor->country_code, '+');

                $host = request()->getHost();
                 if ($host === 'india.mulkmed.com') {
//                     $message = "Dear {$doctor->name},
// You have a new appointment booked on {$appointmentdate} at {$appointmentTime}.
// Kindly log in to the Mulk Med app to view details.

// Regards,
// Team Mulk Med";    
                    $message = "New appointment: {$doctorAppointmentDate} at {$doctorAppointmentTime}. View details in Mulk Med App India. ";    
                    $result = EmailHelpers::sendSms($countryCode . $doctor->mobile_number, $message);
                }else{

                     $message = "New appointment: {$doctorAppointmentDate} at {$doctorAppointmentTime}. View details in Mulk Med App UAE. ";    
                    $result = EmailHelpers::sendSms($countryCode . $doctor->mobile_number, $message);

                }        
                Log::info('BOOKING_TRACE patient flow doctor sms triggered', [
                    'appointment_id' => $appointment->id,
                    'receiver' => $countryCode . $doctor->mobile_number,
                    'admin_host' => $host,
                    'doctor_country_code' => $doctor->country_code,
                    'doctor_timezone' => $doctorTimezone,
                    'doctor_sms_date' => $doctorAppointmentDate,
                    'doctor_sms_time' => $doctorAppointmentTime,
                    'message_preview' => $message,
                ]);
                //     $result = EmailHelpers::sendSms($countryCode . $doctor->mobile_number, $message);
                // // }
//                 $message = "Dear Team,
// Patient {$user->fullname} ({$user->identity}, {$user->phone_number}) booked an appointment with {$doctor->name} on {$appointmentdate} at {$appointmentTime}.";

                $adminSmsDate = $adminPlatformDate ?? $userAppointmentDate;
                $adminSmsTime = $adminPlatformTime ?? $userAppointmentTime;
$message ="{$user->fullname} ({$user->phone_number}) booked with {$doctor->name} on {$adminSmsDate} at {$adminSmsTime}. ";
                $message = mb_convert_encoding($message, 'UTF-8', 'auto'); 

                // 971522463433
                $result = EmailHelpers::sendSms( 971522463433 , $message);
                Log::info('BOOKING_TRACE patient flow admin sms triggered', [
                    'appointment_id' => $appointment->id,
                    'receiver' => '971522463433',
                    'admin_host' => $adminHost,
                    'admin_platform_timezone' => $adminPlatformTimezone,
                    'admin_sms_date' => $adminSmsDate,
                    'admin_sms_time' => $adminSmsTime,
                    'message_preview' => $message,
                ]);
                
                
                \Mail::to($doctor->identity)->send(new \App\Mail\JitsiMeetingLinkDoctor($appointment, $doctor, $user, $doctor_link_mail));
                // return $doctor;
                if($doctor->email_2 != null)
                {
                    \Mail::to($doctor->email_2)->send(new \App\Mail\JitsiMeetingLinkDoctor($appointment, $doctor, $user, $doctor_link_mail));
                }
                if($doctor->email_3 != null)
                {
                    \Mail::to($doctor->email_3)->send(new \App\Mail\JitsiMeetingLinkDoctor($appointment, $doctor, $user, $doctor_link_mail));
                }
                if($doctor->email_4 != null)
                {
                    \Mail::to($doctor->email_4)->send(new \App\Mail\JitsiMeetingLinkDoctor($appointment, $doctor, $user, $doctor_link_mail));
                }
                if($doctor->email_5 != null)
                {
                    \Mail::to($doctor->email_5)->send(new \App\Mail\JitsiMeetingLinkDoctor($appointment, $doctor, $user, $doctor_link_mail));
                }

        // Send Push to user
        $title = "Appointment :" . $appointment->appointment_number;
        $message = "Appointment has been placed successfully!";
        $notifyData = [
            'type'=> Constants::notifyAppointment.'',
            'id'=> $appointment->id.''
        ];
        // GlobalFunction::sendPushToUser($title, $message, $user, $notifyData);

        // Send push to doctor
        $title = "New Appointment Request Received";
        $message = "Review the details and accept.";
        $notifyData = [
            'type'=> Constants::notifyAppointment.'',
            'id'=> $appointment->id.''
        ];
        // GlobalFunction::sendPushToDoctor($title, $message, $doctor,$notifyData);

        // Add statement entry
        GlobalFunction::addUserStatementEntry(
            $user->id,
            $appointment->appointment_number,
            $appointment->payable_amount,
            Constants::debit,
            Constants::purchase,
            null,
        );

        $appointment = Appointments::where('id', $appointment->id)->with(['user', 'doctor', 'patient', 'documents'])->first();

        // return GlobalFunction::sendDataResponse(true, 'Appointment placed successfully', $appointment);
        return redirect()
    ->back()
    ->with('success', 'Appointment placed successfully');

    }

    function HnHCards()
    {
        return view('hnh.hnhCards');  
    }  

    function fetchHnHCards(Request $request)
    {
        $totalData =  HnHCards::where('is_deleted', 0)->count();
        $rows = HnHCards::where('is_deleted', 0)->orderBy('id', 'DESC')->get();
        $settings = GlobalSettings::first();

        $result = $rows;

        $columns = array(
            0 => 'id',
            1 => 'name'
        );

        $limit = $request->input('length');
        $start = $request->input('start');
        $order = $columns[$request->input('order.0.column')];
        $dir = $request->input('order.0.dir');

        $totalFiltered = $totalData;
        if (empty($request->input('search.value'))) {
            $result = HnHCards::where('is_deleted', 0)->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
        } else {
            $search = $request->input('search.value');
            $result =  HnHCards::where('is_deleted', 0)
                ->where(function ($query) use ($search) {
                    $query->where('user_name', 'LIKE', "%{$search}%")
                          ->orWhere('card_number', 'LIKE', "%{$search}%")
                          ->orWhere('email', 'LIKE', "%{$search}%")
                          ->orWhere('phone_number', 'LIKE', "%{$search}%");
                })
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
            $totalFiltered = HnHCards::where('is_deleted', 0)
                ->where(function ($query) use ($search) {
                    $query->where('user_name', 'LIKE', "%{$search}%")
                          ->orWhere('card_number', 'LIKE', "%{$search}%")
                          ->orWhere('email', 'LIKE', "%{$search}%")
                          ->orWhere('phone_number', 'LIKE', "%{$search}%");
                })
                ->count();
        }
        // $currency_symbol = Settings::first();
        $data = array();
        foreach ($result as $item) {

           

            $view = '<a href="#" 
                class="mr-2 btn btn-primary text-white edit" 
                data-card_number="' . $item->card_number . '" 
                data-user_name="' . $item->user_name . '" 
                data-phone_number="' . $item->phone_number . '" 
                data-email="' . $item->email . '" 
                data-date_of_birth="' . $item->date_of_birth . '" 
                data-gender="' . $item->gender . '" 
                data-address="' . $item->address . '" 
                data-points="' . $item->points . '" 
                data-emirates_id="' . $item->emirates_id . '" 
                data-payment_status="' . $item->payment_status . '" 
                data-payment_amount="' . $item->payment_amount . '" 
                rel="' . (int)$item->id . '">' . __("View") . '</a>';

            $action = $view;

            $data[] = array(
                $item->card_number,
                $item->user_name,
                $item->email,  
                $item->points,
                $item->phone_number,
                $item->payment_status == 0 ? 'Pending' :($item->payment_status == 1 ? "Paid" : "Failed"),
                $action,
            );  

        }

        $json_data = array(
            "draw"            => intval($request->input('draw')),
            "recordsTotal"    => intval($totalData),
            "recordsFiltered" => $totalFiltered,
            "data"            => $data,
        );
        echo json_encode($json_data);
        exit();
    }
}
