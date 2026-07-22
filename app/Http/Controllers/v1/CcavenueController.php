<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Models\AppointmentDocs;
use App\Models\Appointments;
use App\Models\BestOfferCart;
use App\Models\BestOfferPlanPayments;
use App\Models\Constants;
use App\Models\DoctorPlans;
use App\Models\Doctors;
use App\Models\GlobalFunction;
use App\Models\TouristCards;
use App\Models\SeniorCards;
use App\Models\Users;
use App\Models\Coupons;
use App\Models\UserPlan;
use App\Models\JitsiMeeting;
use App\Models\HnHCards;
use App\Models\AIVitalScanMisa;
use App\Models\BestOfferPlanOrders;
use App\Models\UserCoupons;
use Illuminate\Http\Request;
use App\Helpers\EmailHelpers;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Helpers\Crypto;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class CcavenueController extends Controller
{
   function addAppointmentWithCoupon(Request $request)
    {
        Log::info('BOOKING_TRACE v1.addAppointmentWithCoupon hit', [
            'route' => 'api.v1.addAppointmentWithCoupon',
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
            'type' => 'required',
            'is_coupon_applied' => [Rule::in(1, 0)],
            'user_coupon_id' => 'required',
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
        $user_coupons = UserCoupons::where('id',$request->user_coupon_id)->where('number_of_limits',">",0)->first();

        if(!($user_coupons))
        {
            return response()->json(['status' => false, 'message' => "Coupon doesn't exists!"]);
        }

        if (GlobalFunction::isDoctorSlotBooked($request->doctor_id, $request->date, $request->time)) {
            return response()->json(['status' => false, 'message' => 'This slot is already booked. Please choose another time.']);
        }

        $appointment = new Appointments();

        $appointment->appointment_number = GlobalFunction::generateAppointmentNumber();
        $appointment->completion_otp = rand(1000, 9999);
        $appointment->user_id = $request->user_id;
        $appointment->doctor_id = $request->doctor_id;
        $appointment->status = 1;
        $appointment->date = $request->date;
        $appointment->time = $request->time;
        $appointment->type = $request->type;
        $appointment->problem = GlobalFunction::cleanString($request->problem);
        // $appointment->order_summary = $request->order_summary;
        $appointment->is_coupon_applied = $request->is_coupon_applied;
        $appointment->service_amount = $request->service_amount;
        $appointment->discount_amount = $request->discount_amount;
        $appointment->subtotal = $request->subtotal;
        $appointment->total_tax_amount = $request->total_tax_amount;
        $appointment->payable_amount = $request->payable_amount;
        $appointment->user_coupon_id = $request->user_coupon_id;
        $appointment->payment_status = 1;
        $appointment->save();

        $user_coupons = UserCoupons::where('id',$request->user_coupon_id)->update(['number_of_limits' => DB::raw('number_of_limits - 1')]);
        if ($request->has('documents')) {
            foreach ($request->documents as $document) {
                $docs = new AppointmentDocs();
                $docs->appointment_id = $appointment->id;
                $docs->image = GlobalFunction::saveFileAndGivePath($document);
                $docs->save();
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
        $appointmentTime = GlobalFunction::formatTimeForDisplay($appointment->time);

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

        $appointmentTimeForConversion = GlobalFunction::normalizeTimeToHis($appointment->time) ?? '00:00:00';
        $userTimezone = GlobalFunction::getTimezoneByCountryCode($user->country_code);
        $doctorTimezone = GlobalFunction::getTimezoneByCountryCode($doctor->country_code);
        $baseTimezoneForConversion = GlobalFunction::getUtcTimezoneValue();

        $userAppointmentDate = GlobalFunction::convertDateTimeToUserTimezone($appointment->date, $appointmentTimeForConversion, $userTimezone, 'd-m-Y', $baseTimezoneForConversion) ?? $appointmentdate;
        $userAppointmentTime = GlobalFunction::convertTimeToUserTimezone($appointmentTimeForConversion, $userTimezone, $appointment->date, 'g:i A', $baseTimezoneForConversion) ?? $appointmentTime;

        $doctorAppointmentDate = GlobalFunction::convertDateTimeToUserTimezone($appointment->date, $appointmentTimeForConversion, $doctorTimezone, 'd-m-Y', $baseTimezoneForConversion) ?? $appointmentdate;
        $doctorAppointmentTime = GlobalFunction::convertTimeToUserTimezone($appointmentTimeForConversion, $doctorTimezone, $appointment->date, 'g:i A', $baseTimezoneForConversion) ?? $appointmentTime;
        Log::info('Ccavenue booking conversion snapshot (flow-1)', [
            'appointment_id' => $appointment->id,
            'raw_date' => $appointment->date,
            'raw_time' => $appointment->time,
            'normalized_time' => $appointmentTimeForConversion,
            'base_timezone' => $baseTimezoneForConversion,
            'user_country_code' => $user->country_code,
            'doctor_country_code' => $doctor->country_code,
            'user_timezone' => $userTimezone,
            'doctor_timezone' => $doctorTimezone,
            'user_converted_date' => $userAppointmentDate,
            'user_converted_time' => $userAppointmentTime,
            'doctor_converted_date' => $doctorAppointmentDate,
            'doctor_converted_time' => $doctorAppointmentTime,
        ]);

        \Mail::to($user->email)->send(new \App\Mail\JitsiMeetingLinkPatient($appointment, $doctor, $user, $patient_link_mail));

        $cleanCode = GlobalFunction::normalizeCountryCode($user->country_code) ?? ltrim((string) $user->country_code, '+');
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
                Log::info('BOOKING_TRACE v1 flow-1 user sms triggered', [
                    'appointment_id' => $appointment->id,
                    'receiver' => $cleanCode . $user->phone_number,
                    'message_preview' => $message,
                ]);
                
                // $current_date = Carbon::now()->toDateString();
                // $current_time = Carbon::now('Asia/Kolkata')->toTimeString();;
//                 $message = "Dear {$doctor->name},

// Payment of AED {$appointment->payable_amount} received for your appointment at Mulk Med on {$current_date} at {$current_time}.

// Thank you for choosing us.

// Regards,
// Team Mulk Med";            
                // $result = EmailHelpers::sendSms($cleanCode . $doctor->phone_number, $message);
                // \Mail::to($doctor->identity)->send(new \App\Mail\JitsiMeetingLinkPatient($meeting_link));
                $countryCode = GlobalFunction::normalizeCountryCode($doctor->country_code) ?? ltrim((string) $doctor->country_code, '+');

                $host = request()->getHost();
                if ($host === 'india.mulkmed.com') {
//                     $message = "Dear {$doctor->name},
// You have a new appointment booked on {$appointmentdate} at {$appointmentTime}.
// Kindly log in to the Mulk Med app to view details.

// Regards,
// Team Mulk Med";       
                  $message = "New appointment: {$doctorAppointmentDate} at {$doctorAppointmentTime}. View details in Mulk Med App India. ";    
                    $result = EmailHelpers::sendSms($countryCode . $doctor->mobile_number, $message);
                    Log::info('BOOKING_TRACE v1 flow-1 doctor sms triggered', [
                        'appointment_id' => $appointment->id,
                        'receiver' => $countryCode . $doctor->mobile_number,
                        'message_preview' => $message,
                    ]);
                }else{

                     $message = "New appointment: {$doctorAppointmentDate} at {$doctorAppointmentTime}. View details in Mulk Med App UAE. ";    
                    $result = EmailHelpers::sendSms($countryCode . $doctor->mobile_number, $message);
                    Log::info('BOOKING_TRACE v1 flow-1 doctor sms triggered', [
                        'appointment_id' => $appointment->id,
                        'receiver' => $countryCode . $doctor->mobile_number,
                        'message_preview' => $message,
                    ]);

                }         
                //     $result = EmailHelpers::sendSms($countryCode . $doctor->mobile_number, $message);
                // }

//                 $message = "Dear Team,
// Patient {$user->fullname} ({$user->identity}, {$user->phone_number}) booked an appointment with {$doctor->name} on {$appointmentdate} at {$appointmentTime}.";  
 
$message ="{$user->fullname} ({$user->phone_number}) booked with {$doctor->name} on {$userAppointmentDate} at {$userAppointmentTime}. ";         
                $result = EmailHelpers::sendSms( 971522463433 , $message);
                Log::info('BOOKING_TRACE v1 flow-1 admin sms triggered', [
                    'appointment_id' => $appointment->id,
                    'receiver' => '971522463433',
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

        return GlobalFunction::sendDataResponse(true, 'Appointment placed successfully', $appointment);
    }
    public function initiateAppointmentPayment(Request $request)
    {
        try {
            Log::info('BOOKING_TRACE v1.ccavenue.initiateAppointmentPayment hit', [
                'route' => 'api.v1.ccavenue.initiate',
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
                'type' => 'required',
                'order_summary' => 'required',
                'is_coupon_applied' => [Rule::in(1, 0)],
                'service_amount' => 'required',
                'discount_amount' => 'required',
                'subtotal' => 'required',
                'total_tax_amount' => 'required',
                'payable_amount' => 'required',
                'amount' => 'required'
            ];

            $validator = Validator::make($request->all(), $rules);
            if ($validator->fails()) {
                $messages = $validator->errors()->all();
                $msg = $messages[0];
                return response()->json(['status' => false, 'message' => $msg]);
            }                                   

            $user = Users::find($request->user_id);
            if ($user == null) {
                return response()->json(['status' => false, 'message' => "User doesn't exists!"]);
            }

            $doctor = Doctors::with('category')->where('id', $request->doctor_id)->first();
            if ($doctor == null) {
                return response()->json(['status' => false, 'message' => "Doctor doesn't exists!"]);
            }
            if ($doctor->on_vacation == 1) {
                return response()->json(['status' => false, 'message' => "this doctor is on vacation!"]);
            }
            if ($doctor->status != Constants::statusDoctorApproved) {
                return response()->json(['status' => false, 'message' => "this doctor is not active!"]);
            }


            $doctor_plan = null;
            if($request->has('plan_id') && $request->plan_id != 0){
                $doctor_plan = DoctorPlans::where('id', $request->plan_id)->where('is_deleted', 0)->first();
                if($doctor_plan == null){
                    return response()->json(['status' => false, 'message' => "this plan is invalid!"]);
                }
            }
            // return $doctor_plan;
            $this->cancelUnpaidPendingAppointmentsForUserSlot($request);
            $is_already_booked = $this->isAlreadyBooked($request);
            if ($is_already_booked > 0) {
                return response()->json([
                    'status'  => false,
                    'message' => "This doctor already has an appointment during this time!"
                ]);
            }

            // if($is_already_booked > 0){
            //     return response()->json(['status' => false, 'message' => "This doctor already have an appointment on this time!"]);
            // }
           
            $final_doctor_charges = $this->getFinalDoctorCharges($doctor, $doctor_plan ?? null, $request->coupon_id ?? null);

            $order_id = 'ccavenue_' . uniqid();

            $appointment = new Appointments();
            $appointment->appointment_number = GlobalFunction::generateAppointmentNumber();
            $appointment->completion_otp = rand(1000, 9999);
            $appointment->user_id = $request->user_id;
            $appointment->doctor_id = $request->doctor_id;
            $appointment->date = $request->date;
            $appointment->time = $request->time;
            $appointment->type = $request->type;
            $appointment->speciality_name = $doctor->category ? $doctor->category->title:'';

            $appointment->problem = GlobalFunction::cleanString($request->problem);
            $orderSummary = $request->order_summary;
            if (is_array($orderSummary) || is_object($orderSummary)) {
                $orderSummary = json_encode($orderSummary);
            }
            $appointment->order_summary = $orderSummary;
            $appointment->is_coupon_applied = $request->is_coupon_applied;

            $appointment->service_amount = $final_doctor_charges['base_price'];
            $appointment->discount_amount = $final_doctor_charges['discount'];
            $appointment->subtotal = $request->subtotal;
            $appointment->total_tax_amount = $request->total_tax_amount;
            $appointment->payable_amount = $final_doctor_charges['final_price'];
            $appointment->order_id = $order_id;
            $appointment->status = Constants::orderPlacedPending;
            $appointment->payment_status = Constants::appointmentPaymentPendingStatus;

            $amount = $final_doctor_charges['final_price'];

            if($request->has('user_coupon_id'))
            {
                $user_coupon = UserCoupons::where('id',$request->user_coupon_id);
                if($user_coupon)
                {
                    $appointment->service_amount = $final_doctor_charges['base_price'];
                    $appointment->discount_amount = $final_doctor_charges['final_price'];
                    $appointment->payable_amount = $final_doctor_charges['final_price'];
                    $appointment->user_coupon_id = $request->user_coupon_id;
                    $amount = 0;
                }
            }
            // save appointment
            $appointment->save();

            if ($request->is_coupon_applied == 1) {
                $appointment->coupon_title = $request->coupon_title;
                // add coupon to used coupon
                $discounts = explode(',', $user->coupons_used);
                array_push($discounts, $request->coupon_id);
                $user->coupons_used = implode(',', $discounts);
            }

            // $amount = $request->amount;


            if ($request->has('documents')) {
                foreach ($request->documents as $document) {
                    $docs = new AppointmentDocs();
                    $docs->appointment_id = $appointment->id;
                    $docs->image = GlobalFunction::saveFileAndGivePath($document);
                    $docs->save();
                }
            }
            

            if($doctor_plan != null){
                $user_plan = new UserPlan();
                $user_plan->user_id = $request->user_id;
                $user_plan->plan_id = $doctor_plan->id;
                $user_plan->plan_name = $doctor_plan->plan_name;
                $user_plan->original_price = $doctor_plan->original_price;
                $user_plan->discount = $doctor_plan->discount;
                $user_plan->discount_type = $doctor_plan->discount_type;
                $user_plan->final_price = $final_doctor_charges['final_price'];
                $user_plan->hh_price = $doctor_plan->hh_price;
                $user_plan->consultations_total = $doctor_plan->number_of_consultations;
                $user_plan->consultations_used = 0;
                $user_plan->number_of_days = $doctor_plan->number_of_days;
                $user_plan->consultation_text = $doctor_plan->consultation_text;
                // $user_plan->valid_from = Carbon::today();
                // $user_plan->valid_to = Carbon::today()->addDays($doctor_plan->number_of_days);
                $user_plan->status = 'active';
                $user_plan->save();

                $appointment->user_plan_id = $user_plan->id;
                $appointment->save();
            }

            $baseUrl = request()->getSchemeAndHttpHost();

            // $redirectUrl = $baseUrl . '/v2/api/v1/payment-response';
            // $cancelUrl   = $baseUrl . '/v2/api/v1/payment-cancel';
            $redirectUrl = $baseUrl . '/api/v1/payment-response';
            $cancelUrl   = $baseUrl . '/api/v1/payment-cancel';
            $data = [
                "merchant_id" => env('CCAVENUE_MERCHANT_ID'),
                "order_id" => $order_id,
                "currency" => "AED",
                "amount" => $amount,
                "merchant_param5" => Constants::appointmentPaymentType,
                "redirect_url" => $redirectUrl,
                "cancel_url" => $cancelUrl,
                "language" => "EN",
                "appointment_id" => $appointment->id
            ];

            // return $data;

            $merchant_data = "";
            foreach ($data as $key => $value) {
                if (is_array($value) || is_object($value)) {
                    $value = json_encode($value);
                }
                $merchant_data .= $key . '=' . $value . '&';
            }
            

           $encrypted_data = Crypto::encrypt($merchant_data, env('CCAVENUE_WORKING_KEY'));

            $payment_url = env('CCAVENUE_BASE_URL') . "=$encrypted_data&access_code=" . env('CCAVENUE_ACCESS_CODE');
            // $payment_url =  url('/api/v1/user/ccavenue/appointmentSuccess?order_id=' . $order_id);

            return response()->json([
                'status' => true,
                'payment_url' => $payment_url,
                'appointment_id' => $appointment->id,
                // 'already_appointment' => $is_already_booked
                // 'final_doctor_charge' => $final_doctor_charge
            ]);
        }

        catch (\Throwable $e) {
            // Log unexpected errors
            Log::error('Error occured', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['status' => false, 'message' => $e->getMessage()]);
        }
        
    }

    public function appointmentSuccess(Request $request)
    {
        $appointment = Appointments::where('order_id', $request->order_id)->first();        
        $appointment->payment_status = Constants::appointmentPaymentSuccessStatus;
        $appointment->status = Constants::orderAccepted;
        $appointment->save();

        if($appointment->user_plan_id != 0){
            $doctor_plan = DoctorPlans::select('doctor_plans.*')->join('user_plans','user_plans.plan_id','doctor_plans.id')
                            ->where('user_plans.id',$appointment->user_plan_id)->first();
            if($doctor_plan->number_of_consultations > 1){
                $user_coupon = new UserCoupons();
                $user_coupon->user_id = $appointment->user_id;
                $user_coupon->plan_id = $doctor_plan->id;
                $user_coupon->coupon_code = strtoupper(Str::random(6));
                $user_coupon->number_of_limits = $doctor_plan->number_of_consultations - 1;
                $appointment_date = Carbon::createFromFormat('Y-m-d', $appointment->date);
                $user_coupon->save();
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
        $appointmentTime = GlobalFunction::formatTimeForDisplay($appointment->time);

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

        $user = Users::find($appointment->user_id);
        $doctor = Doctors::find($appointment->doctor_id);

        $appointment = Appointments::find($appointment->id) ?? $appointment;

        $appointmentTimeForConversion = GlobalFunction::normalizeTimeToHis($appointment->time) ?? '00:00:00';
        $userTimezone = GlobalFunction::getTimezoneByCountryCode($user->country_code);
        $doctorTimezone = GlobalFunction::getTimezoneByCountryCode($doctor->country_code);
        $baseTimezoneForConversion = GlobalFunction::getUtcTimezoneValue();

        $userAppointmentDate = GlobalFunction::convertDateTimeToUserTimezone($appointment->date, $appointmentTimeForConversion, $userTimezone, 'd-m-Y', $baseTimezoneForConversion) ?? $appointmentdate;
        $userAppointmentTime = GlobalFunction::convertTimeToUserTimezone($appointmentTimeForConversion, $userTimezone, $appointment->date, 'g:i A', $baseTimezoneForConversion) ?? $appointmentTime;

        $doctorAppointmentDate = GlobalFunction::convertDateTimeToUserTimezone($appointment->date, $appointmentTimeForConversion, $doctorTimezone, 'd-m-Y', $baseTimezoneForConversion) ?? $appointmentdate;
        $doctorAppointmentTime = GlobalFunction::convertTimeToUserTimezone($appointmentTimeForConversion, $doctorTimezone, $appointment->date, 'g:i A', $baseTimezoneForConversion) ?? $appointmentTime;
        Log::info('Ccavenue booking conversion snapshot (flow-2)', [
            'appointment_id' => $appointment->id,
            'raw_date' => $appointment->date,
            'raw_time' => $appointment->time,
            'normalized_time' => $appointmentTimeForConversion,
            'base_timezone' => $baseTimezoneForConversion,
            'user_country_code' => $user->country_code,
            'doctor_country_code' => $doctor->country_code,
            'user_timezone' => $userTimezone,
            'doctor_timezone' => $doctorTimezone,
            'user_converted_date' => $userAppointmentDate,
            'user_converted_time' => $userAppointmentTime,
            'doctor_converted_date' => $doctorAppointmentDate,
            'doctor_converted_time' => $doctorAppointmentTime,
        ]);

        \Mail::to($user->email)->send(new \App\Mail\JitsiMeetingLinkPatient($appointment, $doctor, $user, $patient_link_mail));

        $cleanCode = GlobalFunction::normalizeCountryCode($user->country_code) ?? ltrim((string) $user->country_code, '+');
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
                Log::info('BOOKING_TRACE v1 flow-2 user sms triggered', [
                    'appointment_id' => $appointment->id,
                    'receiver' => $cleanCode . $user->phone_number,
                    'message_preview' => $message,
                ]);
                
                // $current_date = Carbon::now()->toDateString();
                // $current_time = Carbon::now('Asia/Kolkata')->toTimeString();;
//                 $message = "Dear {$doctor->name},

// Payment of AED {$appointment->payable_amount} received for your appointment at Mulk Med on {$current_date} at {$current_time}.

// Thank you for choosing us.

// Regards,
// Team Mulk Med";            
                // $result = EmailHelpers::sendSms($cleanCode . $user->phone_number, $message);
                // \Mail::to($doctor->identity)->send(new \App\Mail\JitsiMeetingLinkPatient($meeting_link));

                  $countryCode = GlobalFunction::normalizeCountryCode($doctor->country_code) ?? ltrim((string) $doctor->country_code, '+');

                $host = request()->getHost();
                if ($host === 'india.mulkmed.com') {
//                     $message = "Dear {$doctor->name},
// You have a new appointment booked on {$appointmentdate} at {$appointmentTime}.
// Kindly log in to the Mulk Med app to view details.

// Regards,
// Team Mulk Med";    

                    $message = "New appointment: {$doctorAppointmentDate} at {$doctorAppointmentTime}. View details in Mulk Med App India. ";    
                    $result = EmailHelpers::sendSms($countryCode . $doctor->mobile_number, $message);
                    Log::info('BOOKING_TRACE v1 flow-2 doctor sms triggered', [
                        'appointment_id' => $appointment->id,
                        'receiver' => $countryCode . $doctor->mobile_number,
                        'message_preview' => $message,
                    ]);
                }else{

                     $message = "New appointment: {$doctorAppointmentDate} at {$doctorAppointmentTime}. View details in Mulk Med App UAE. ";    
                    $result = EmailHelpers::sendSms($countryCode . $doctor->mobile_number, $message);
                    Log::info('BOOKING_TRACE v1 flow-2 doctor sms triggered', [
                        'appointment_id' => $appointment->id,
                        'receiver' => $countryCode . $doctor->mobile_number,
                        'message_preview' => $message,
                    ]);

                }

//                 $message = "Dear Team,
// Patient {$user->fullname} ({$user->identity}, {$user->phone_number}) booked an appointment with {$doctor->name} on {$appointmentdate} at {$appointmentTime}.";  
                
$message ="{$user->fullname} ({$user->phone_number}) booked with {$doctor->name} on {$userAppointmentDate} at {$userAppointmentTime}. ";
                $result = EmailHelpers::sendSms( 971522463433 , $message);
                Log::info('BOOKING_TRACE v1 flow-2 admin sms triggered', [
                    'appointment_id' => $appointment->id,
                    'receiver' => '971522463433',
                    'message_preview' => $message,
                ]);
                \Mail::to($doctor->identity)->send(new \App\Mail\JitsiMeetingLinkDoctor($appointment, $doctor, $user, $doctor_link_mail));
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
                // $amount    = $responseData['amount'] ?? null;
                // $currency  = $responseData['currency'] ?? null;
                // $pay_mode  = $responseData['payment_mode'] ?? null;

                return response()->json([
                    'status' => "success",
                    'data'   => [
                        'order_id'     => $request->order_id,
                        'amount'       => $appointment->payable_amount,
                        'currency'     => "AED",
                        // 'payment_mode' => $pay_mode,
                        'meeting_link' => $meeting_link_patient,
                        // 'meeting_doctor' => $meeting_link_doctor
                    ]
                ]);
            

    }

    public function initiatePaymentHnH(Request $request)
    {
        try {
            $rules = [
                'user_id' => 'required',
                'user_name' => 'required',
                'email' => 'required|email',
                'phone_number' => 'required',
                'date_of_birth' => 'required',
                // 'gender' => [Rule::in(Constants::genderMale, Constants::genderFemale)],
                // 'address' => 'required'
            ];

            $validator = Validator::make($request->all(), $rules);
            if ($validator->fails()) {
                $messages = $validator->errors()->all();
                $msg = $messages[0];
                return response()->json(['status' => false, 'message' => $msg]);
            }                                   

            $user = Users::find($request->user_id);
            if ($user == null) {
                return response()->json(['status' => false, 'message' => "User doesn't exists!"]);
            }

            $order_id = 'ccavenue_' . uniqid();

            $hnh_card = new HnHCards();
            $hnh_card->user_id = $request->user_id;
            $hnh_card->user_name = $request->user_name;
            $hnh_card->phone_number = $request->phone_number;
            $hnh_card->email = $request->email;
            $hnh_card->id_number = $request->id_number ?? null;
            $hnh_card->date_of_birth = $request->date_of_birth;
            $hnh_card->gender = $request->gender;
            $hnh_card->address = $request->address ?? null;
            $hnh_card->emirates_id = $request->emiratesId ?? null;
            $hnh_card->order_id = $order_id;
            $hnh_card->payment_status = 0;
            $hnh_card->payment_amount = 150; // static for now
             $hnh_card->valid_till = now()->addYear()->format('Y-m-d H:i:s');
            $hnh_card->save();

            // initial 45 AED 
            // $amount = $hnh_card->balance_aed; // static for now
            $amount = 1; // static for now

            $baseUrl = request()->getSchemeAndHttpHost();

            $redirectUrl = $baseUrl . '/v2/api/v1/payment-response';
            $cancelUrl   = $baseUrl . '/v2/api/v1/payment-cancel';

            $baseTimezoneForConversion = GlobalFunction::getUtcTimezoneValue();
            Cache::put('booking_base_timezone_' . $order_id, $baseTimezoneForConversion, now()->addHours(24));

            $data = [
                "billing_name" => $request->user_name,
                "billing_email" => $request->email,
                "billing_address" => $request->address ?? '',
                "merchant_id" => env('CCAVENUE_MERCHANT_ID'),
                "order_id" => $order_id,
                "currency" => "AED",
                "amount" => $amount,
                "merchant_param5" => Constants::HnHPaymentType,
                "redirect_url" => $redirectUrl,
                "cancel_url" => $cancelUrl,
                "language" => "EN",
            ];

            // return $data;

            $merchant_data = "";
            foreach ($data as $key => $value) {
                $merchant_data .= $key . '=' . $value . '&';
            }
            

            $encrypted_data = Crypto::encrypt($merchant_data, env('CCAVENUE_WORKING_KEY'));

            // $payment_url = env('CCAVENUE_BASE_URL') . "=$encrypted_data&access_code=" . env('CCAVENUE_ACCESS_CODE');
            $payment_url =  url('/api/v1/user/ccavenue/appointmentSuccess?order_id=' . $order_id);

            return response()->json([
                'status' => true,
                'payment_url' => $payment_url,
                'card_details' => $hnh_card,
                // 'final_doctor_charge' => $final_doctor_charge
            ]);
        }

        catch (\Throwable $e) {
            // Log unexpected errors
            Log::error('Error ', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return ['status' => false, 'message' => $e->getMessage()];
        }
        
    }

    public function initiatePaymentTouristCard(Request $request)
    {
        try {
            $rules = [
                'user_id' => 'required',
                'user_name' => 'required',
                'email' => 'required|email',
                'phone_number' => 'required',
                'date_of_birth' => 'required',
                // 'gender' => [Rule::in(Constants::genderMale, Constants::genderFemale)],
                // 'address' => 'required'
            ];

            $validator = Validator::make($request->all(), $rules);
            if ($validator->fails()) {
                $messages = $validator->errors()->all();
                $msg = $messages[0];
                return response()->json(['status' => false, 'message' => $msg]);
            }                                   

            $user = Users::find($request->user_id);
            if ($user == null) {
                return response()->json(['status' => false, 'message' => "User doesn't exists!"]);
            }

            // if more than age 79 
            $payable_amount = 499;

            $dob = Carbon::parse($request->date_of_birth);
            $age = $dob->age;
            $visit_visa_validity = $request->visit_visa_validity;

            if($age <= 70)
            {
                if($visit_visa_validity == 30){
                    $payable_amount = 80;
                }

                else if($visit_visa_validity == 60){
                    $payable_amount = 120;
                }

                else{
                    $payable_amount = 149;
                }
            }

            // else if($age >= 71 && $age <= 79)
            else if($age >= 71)
            {
                if($visit_visa_validity == 30){
                    $payable_amount = 250;
                }

                else if($visit_visa_validity == 60){
                    $payable_amount = 400;
                }

                else{
                    $payable_amount = 499;
                }
            }

            $passport_doc = null;
            if($request->hasFile('passport_document')){

                $passport_doc = GlobalFunction::saveFileAndGivePath($request->passport_document);
            }

            $order_id = 'ccavenue_' . uniqid();

            $hnh_card = new TouristCards();
            $hnh_card->user_id = $request->user_id;
            $hnh_card->user_name = $request->user_name;
            $hnh_card->phone_number = $request->phone_number;
            $hnh_card->email = $request->email;
            $hnh_card->id_number = $request->id_number ?? null;
            $hnh_card->date_of_birth = $request->date_of_birth;
            $hnh_card->gender = $request->gender;
            $hnh_card->address = $request->address ?? null;
            $hnh_card->emirates_id = $request->emiratesId ?? null;
            $hnh_card->order_id = $order_id;
            $hnh_card->payment_status = 0;
            // $hnh_card->payment_amount = 45; // static for now
            $hnh_card->payment_amount = $payable_amount;
            $hnh_card->passport_number = $request->passport_number ?? null;
            $hnh_card->passport_document = $passport_doc;
            $hnh_card->travelling_from_country = $request->travelling_from_country ?? null;
            $hnh_card->travelling_from_date = $request->travelling_from_date ?? null;
            $hnh_card->visit_visa_validity = $visit_visa_validity;

            if ($hnh_card->travelling_from_date) {
                // Compute validity based on travelling_from_date
                $hnh_card->valid_till = Carbon::parse($hnh_card->travelling_from_date)
                    ->addDays($hnh_card->visit_visa_validity)
                    ->addDays(10); // buffer period
            } else {
                // fallback if travelling_from_date isn’t available
                $hnh_card->valid_till = now()
                    ->addDays($hnh_card->visit_visa_validity)
                    ->addDays(10);
            }

            $hnh_card->save();

            // initial 45 AED 
            // $amount = $hnh_card->balance_aed; // static for now

            $baseUrl = request()->getSchemeAndHttpHost();

            $redirectUrl = $baseUrl . '/v2/api/v1/payment-response';
            $cancelUrl   = $baseUrl . '/v2/api/v1/payment-cancel';

            $data = [
                "billing_name" => $request->user_name,
                "billing_email" => $request->email,
                "billing_address" => $request->address ?? '',
                "merchant_id" => env('CCAVENUE_MERCHANT_ID'),
                "order_id" => $order_id,
                "currency" => "AED",
                "amount" => $payable_amount,
                "merchant_param5" => Constants::TouristCardPaymentType,
                "redirect_url" => $redirectUrl,
                "cancel_url" => $cancelUrl,
                "language" => "EN",
            ];

            // return $data;

            $merchant_data = "";
            foreach ($data as $key => $value) {
                $merchant_data .= $key . '=' . $value . '&';
            }
            

            $encrypted_data = Crypto::encrypt($merchant_data, env('CCAVENUE_WORKING_KEY'));

            $payment_url = env('CCAVENUE_BASE_URL') . "=$encrypted_data&access_code=" . env('CCAVENUE_ACCESS_CODE');

            return response()->json([
                'status' => true,
                'payment_url' => $payment_url,
                'card_details' => $hnh_card,
                // 'final_doctor_charge' => $final_doctor_charge
            ]);
        }

        catch (\Throwable $e) {
            // Log unexpected errors
            Log::error('Error ', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return ['status' => false, 'message' => $e->getMessage()];
        }
        
    }

    public function initiatePaymentSeniorCard(Request $request)
    {
        try {
            $rules = [
                'user_id' => 'required',
                'user_name' => 'required',
                'email' => 'required|email',
                'phone_number' => 'required',
                'date_of_birth' => 'required',
                // 'gender' => [Rule::in(Constants::genderMale, Constants::genderFemale)],
                // 'address' => 'required'
            ];

            $validator = Validator::make($request->all(), $rules);
            if ($validator->fails()) {
                $messages = $validator->errors()->all();
                $msg = $messages[0];
                return response()->json(['status' => false, 'message' => $msg]);
            }                                   

            $user = Users::find($request->user_id);
            if ($user == null) {
                return response()->json(['status' => false, 'message' => "User doesn't exists!"]);
            }

            $order_id = 'ccavenue_' . uniqid();

            $hnh_card = new SeniorCards();
            $hnh_card->user_id = $request->user_id;
            $hnh_card->user_name = $request->user_name;
            $hnh_card->phone_number = $request->phone_number;
            $hnh_card->email = $request->email;
            $hnh_card->id_number = $request->id_number ?? null;
            $hnh_card->date_of_birth = $request->date_of_birth;
            $hnh_card->gender = $request->gender;
            $hnh_card->address = $request->address ?? null;
            $hnh_card->emirates_id = $request->emiratesId ?? null;
            $hnh_card->order_id = $order_id;
            $hnh_card->payment_status = 0;
            $hnh_card->payment_amount = 1500; // static for now
             $hnh_card->valid_till = now()->addYear()->format('Y-m-d H:i:s');
            $hnh_card->save();

            // initial 45 AED 
            // $amount = $hnh_card->balance_aed; // static for now
            $amount = 1500; // static for now

            $baseUrl = request()->getSchemeAndHttpHost();
            $redirectUrl = $baseUrl . '/api/v1/payment-response';
            $cancelUrl   = $baseUrl . '/api/v1/payment-cancel';
            // $redirectUrl = $baseUrl . '/v2/api/v1/payment-response';
            // $cancelUrl   = $baseUrl . '/v2/api/v1/payment-cancel';
            $data = [
                "billing_name" => $request->user_name,
                "billing_email" => $request->email,
                "billing_address" => $request->address ?? '',
                "merchant_id" => env('CCAVENUE_MERCHANT_ID'),
                "order_id" => $order_id,
                "currency" => "AED",
                "amount" => $amount,
                "merchant_param5" => Constants::SeniorCardPaymentType,
                "redirect_url" => $redirectUrl,
                "cancel_url" => $cancelUrl,
                "language" => "EN",
            ];

            // return $data;

            $merchant_data = "";
            foreach ($data as $key => $value) {
                $merchant_data .= $key . '=' . $value . '&';
            }
            

            $encrypted_data = Crypto::encrypt($merchant_data, env('CCAVENUE_WORKING_KEY'));

            $payment_url = env('CCAVENUE_BASE_URL') . "=$encrypted_data&access_code=" . env('CCAVENUE_ACCESS_CODE');

            return response()->json([
                'status' => true,
                'payment_url' => $payment_url,
                'card_details' => $hnh_card,
                // 'final_doctor_charge' => $final_doctor_charge
            ]);
        }

        catch (\Throwable $e) {
            // Log unexpected errors
            Log::error('Error ', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return ['status' => false, 'message' => $e->getMessage()];
        }
        
    }

    function successAIVitalScan(Request $request)
    {
        $responseData = $request;
        $status = 'Success';
        if($responseData['merchant_param5'] == Constants::CCAvenueHnHPaymentType){
            $hnhCard = HnHCards::where('order_id', $responseData['order_id'])->first();
            if($hnhCard){
                if($status == 'Success'){
                    $hnhCard->payment_status = Constants::HnHPaymentSuccessStatus;
                    $hnhCard->save();
                }

                else if($status == 'Failure'){
                    $hnhCard->payment_status = Constants::HnHPaymentFailureStatus;
                    $hnhCard->save();
                }
            }

            return response()->json([
                'status' => $status,
                'data'   => [
                    'order_id'     => $order_id ?? 0,
                    'amount'       => $responseData['amount'],
                    'currency'     => "AED",
                    // 'payment_mode' => $responseData['payment_mode'],
                    'hnh_card_details' => $hnhCard
                ]
            ]);
        }

        else if(($responseData['merchant_param5'] == Constants::CCAvenueAIVitalScanPaymentType) || ($responseData['merchant_param5'] == Constants::CCAvenueAIVitalScanBeforePaymentType) || ($responseData['merchant_param5'] == Constants::CCAvenueMesaBeforeChatPayment)){
            $ai_vital_misa = AIVitalScanMisa::where('order_id', $responseData['order_id'])->first();
            if($ai_vital_misa){
                if($status == 'Success'){
                    $ai_vital_misa->payment_status = Constants::AIVitalsPaymentSuccessStatus;
                    $ai_vital_misa->payment_type = $responseData['merchant_param5'];
                    $ai_vital_misa->save();
                }

                else if($status == 'Failure'){
                    $ai_vital_misa->payment_status = Constants::AIVitalsPaymentFailureStatus;
                    $ai_vital_misa->payment_type = $responseData['merchant_param5'];
                    $ai_vital_misa->save();
                }
            }

            return response()->json([
                'status' => $status,
                'data'   => [
                    'order_id'     => $responseData['order_id'],
                    'amount'       => $responseData['amount'],
                    'currency'     => "AED",
                    'payment_mode' => "Credit Card",
                    // 'AI_Vitals_scan_details' => $ai_vital_misa
                ]
            ]);
        }
    }

    function initiatePaymentAIVitalScan(Request $request)
    {
         try {
            $rules = [
                'user_id' => 'required',
            ];

            $validator = Validator::make($request->all(), $rules);
            if ($validator->fails()) {
                $messages = $validator->errors()->all();
                $msg = $messages[0];
                return response()->json(['status' => false, 'message' => $msg]);
            }                                   

            $user = Users::find($request->user_id);
            if ($user == null) {
                return response()->json(['status' => false, 'message' => "User doesn't exists!"]);
            }

            $order_id = 'ccavenue_' . uniqid();

            // 5 AED static for now
            $amount = 5;

            $ai_vital_misa = new AIVitalScanMisa();
            $ai_vital_misa->user_id = $request->user_id;
            $ai_vital_misa->order_id = $order_id;
            $ai_vital_misa->report_from = $request->report_from;
            $ai_vital_misa->payment_status = 0;
            $ai_vital_misa->payment_amount = $amount;
            $ai_vital_misa->scan_date = $request->date;
            $ai_vital_misa->save();

            $baseUrl = request()->getSchemeAndHttpHost();

            // $redirectUrl = $baseUrl . '/v2/api/v1/payment-response';
            // $cancelUrl   = $baseUrl . '/v2/api/v1/payment-cancel';
            $redirectUrl = $baseUrl . '/api/v1/payment-response';
            $cancelUrl   = $baseUrl . '/api/v1/payment-cancel';
            $data = [
                "merchant_id" => env('CCAVENUE_MERCHANT_ID'),
                "order_id" => $order_id,
                "currency" => "AED",
                "amount" => $amount,
                "merchant_param5" => Constants::CCAvenueAIVitalScanPaymentType,
                "redirect_url" => $redirectUrl,
                "cancel_url" => $cancelUrl,
                "language" => "EN",
            ];

            // return $data;

            $merchant_data = "";
            foreach ($data as $key => $value) {
                $merchant_data .= $key . '=' . $value . '&';
            }
            

            $encrypted_data = Crypto::encrypt($merchant_data, env('CCAVENUE_WORKING_KEY'));

            $payment_url = env('CCAVENUE_BASE_URL') . "=$encrypted_data&access_code=" . env('CCAVENUE_ACCESS_CODE');
            // $payment_url = url('/api/v1/user/ccavenue/successAIVitalScan?order_id=' . $order_id . '&merchant_param5=' . Constants::CCAvenueAIVitalScanPaymentType . '&amount=' .$amount);

            return response()->json([
                'status' => true,
                'payment_url' => $payment_url,
                // 'final_doctor_charge' => $final_doctor_charge
            ]);
        }

        catch (\Throwable $e) {
            // Log unexpected errors
            Log::error('Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return ['status' => false, 'message' => $e->getMessage()];
        }
    }

    function initiatePaymentAIVitalScanBefore(Request $request)
    {
         try {
            $rules = [
                'user_id' => 'required',
            ];

            $validator = Validator::make($request->all(), $rules);
            if ($validator->fails()) {
                $messages = $validator->errors()->all();
                $msg = $messages[0];
                return response()->json(['status' => false, 'message' => $msg]);
            }                                   

            $user = Users::find($request->user_id);
            if ($user == null) {
                return response()->json(['status' => false, 'message' => "User doesn't exists!"]);
            }

            $order_id = 'ccavenue_' . uniqid();

            // 5 AED static for now
            $amount = 2;

            $ai_vital_misa = new AIVitalScanMisa();
            $ai_vital_misa->user_id = $request->user_id;
            $ai_vital_misa->order_id = $order_id;
            $ai_vital_misa->report_from = $request->report_from;
            $ai_vital_misa->payment_status = 0;
            $ai_vital_misa->payment_amount = $amount;
            $ai_vital_misa->scan_date = $request->date;
            $ai_vital_misa->save();

            $baseUrl = request()->getSchemeAndHttpHost();

            // $redirectUrl = $baseUrl . '/v2/api/v1/payment-response';
            // $cancelUrl   = $baseUrl . '/v2/api/v1/payment-cancel';

            $redirectUrl = $baseUrl . '/api/v1/payment-response';
            $cancelUrl   = $baseUrl . '/api/v1/payment-cancel';

            $data = [
                "merchant_id" => env('CCAVENUE_MERCHANT_ID'),
                "order_id" => $order_id,
                "currency" => "AED",
                "amount" => $amount,
                "merchant_param5" => Constants::CCAvenueAIVitalScanBeforePaymentType,
                "redirect_url" => $redirectUrl,
                "cancel_url" => $cancelUrl,
                "language" => "EN",
            ];

            // return $data;

            $merchant_data = "";
            foreach ($data as $key => $value) {
                $merchant_data .= $key . '=' . $value . '&';
            }
            

            $encrypted_data = Crypto::encrypt($merchant_data, env('CCAVENUE_WORKING_KEY'));

            $payment_url = env('CCAVENUE_BASE_URL') . "=$encrypted_data&access_code=" . env('CCAVENUE_ACCESS_CODE');
            // $payment_url = url('/api/v1/user/ccavenue/successAIVitalScan?order_id=' . $order_id . '&merchant_param5=' . Constants::CCAvenueAIVitalScanBeforePaymentType . '&amount=' .$amount);


            return response()->json([
                'status' => true,
                'payment_url' => $payment_url,
                // 'final_doctor_charge' => $final_doctor_charge
            ]);
        }

        catch (\Throwable $e) {
            // Log unexpected errors
            Log::error('Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return ['status' => false, 'message' => $e->getMessage()];
        }
    }

    function initiatePaymentMesaBeforeChat(Request $request)
    {
         try {
            $rules = [
                'user_id' => 'required',
            ];

            $validator = Validator::make($request->all(), $rules);
            if ($validator->fails()) {
                $messages = $validator->errors()->all();
                $msg = $messages[0];
                return response()->json(['status' => false, 'message' => $msg]);
            }                                   

            $user = Users::find($request->user_id);
            if ($user == null) {
                return response()->json(['status' => false, 'message' => "User doesn't exists!"]);
            }

            $order_id = 'ccavenue_' . uniqid();

            // 5 AED static for now
            $amount = 2;

            $ai_vital_misa = new AIVitalScanMisa();
            $ai_vital_misa->user_id = $request->user_id;
            $ai_vital_misa->order_id = $order_id;
            $ai_vital_misa->report_from = $request->report_from;
            $ai_vital_misa->payment_status = 0;
            $ai_vital_misa->payment_amount = $amount;
            $ai_vital_misa->scan_date = $request->date;
            $ai_vital_misa->save();

            $baseUrl = request()->getSchemeAndHttpHost();

            // $redirectUrl = $baseUrl . '/v2/api/v1/payment-response';
            // $cancelUrl   = $baseUrl . '/v2/api/v1/payment-cancel';

            $redirectUrl = $baseUrl . '/api/v1/payment-response';
            $cancelUrl   = $baseUrl . '/api/v1/payment-cancel';


            $data = [
                "merchant_id" => env('CCAVENUE_MERCHANT_ID'),
                "order_id" => $order_id,
                "currency" => "AED",
                "amount" => $amount,
                "merchant_param5" => Constants::CCAvenueMesaBeforeChatPayment,
                "redirect_url" => $redirectUrl,
                "cancel_url" => $cancelUrl,
                "language" => "EN",
            ];

            // return $data;

            $merchant_data = "";
            foreach ($data as $key => $value) {
                $merchant_data .= $key . '=' . $value . '&';
            }
            

            $encrypted_data = Crypto::encrypt($merchant_data, env('CCAVENUE_WORKING_KEY'));

            $payment_url = env('CCAVENUE_BASE_URL') . "=$encrypted_data&access_code=" . env('CCAVENUE_ACCESS_CODE');
            // $payment_url = url('/api/v1/user/ccavenue/successAIVitalScan?order_id=' . $order_id . '&merchant_param5=' . Constants::CCAvenueMesaBeforeChatPayment . '&amount=' .$amount);


            return response()->json([
                'status' => true,
                'payment_url' => $payment_url,
                // 'final_doctor_charge' => $final_doctor_charge
            ]);
        }

        catch (\Throwable $e) {
            // Log unexpected errors
            Log::error('Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return ['status' => false, 'message' => $e->getMessage()];
        }
    }

    function initiatePaymentBestOffers(Request $request){
        try {
            $rules = [
                'user_id' => 'required',
                 'is_coupon_applied' => [Rule::in(1, 0)],
            ];

            $validator = Validator::make($request->all(), $rules);
            if ($validator->fails()) {
                $messages = $validator->errors()->all();
                $msg = $messages[0];
                return response()->json(['status' => false, 'message' => $msg]);
            }                                   

            $user = Users::find($request->user_id);
            if ($user == null) {
                return response()->json(['status' => false, 'message' => "User doesn't exists!"]);
            }

            $order_id = 'ccavenue_' . uniqid();


            $cart = BestOfferCart::where('user_id', $request->user_id)->get();

            if ($cart->isEmpty()) {  
                return response()->json([
                    'status' => false,
                    'message' => 'Cart not found',
                    'data' => null
                ], 200); 
            }  

             
            // total charge
            $total_charge = 0;
         
            foreach ($cart as $key => $cart_item) {
                $bestOfferPlanOrders = new BestOfferPlanOrders();
                $bestOfferPlanOrders->user_id = $request->user_id;
                $bestOfferPlanOrders->offer_id = $cart_item->offer_id;
                $bestOfferPlanOrders->order_id = $order_id;
                $bestOfferPlanOrders->quantity = $cart_item->quantity;
                $bestOfferPlanOrders->total_price = $cart_item->price;
                $bestOfferPlanOrders->image = $cart_item->image;
                $bestOfferPlanOrders->offer_name = $cart_item->offer_name;
            
                $bestOfferPlanOrders->benefits = $cart_item->benefits;
                $bestOfferPlanOrders->price_description = $cart_item->price_description;
                $bestOfferPlanOrders->description = $cart_item->description;
                $bestOfferPlanOrders->status = 0;

                $bestOfferPlanOrders->ar_offer_name = $cart_item->ar_offer_name;
                $bestOfferPlanOrders->fr_offer_name = $cart_item->fr_offer_name;
                $bestOfferPlanOrders->hi_offer_name = $cart_item->hi_offer_name;
                $bestOfferPlanOrders->ur_offer_name = $cart_item->ur_offer_name;

                $bestOfferPlanOrders->price_description = $cart_item->price_description;
                $bestOfferPlanOrders->ar_price_description = $cart_item->ar_price_description;
                $bestOfferPlanOrders->fr_price_description = $cart_item->fr_price_description;
                $bestOfferPlanOrders->hi_price_description = $cart_item->hi_price_description;
                $bestOfferPlanOrders->ur_price_description = $cart_item->ur_price_description;

                $bestOfferPlanOrders->description = $cart_item->description;
                $bestOfferPlanOrders->ar_description = $cart_item->ar_description;
                $bestOfferPlanOrders->fr_description = $cart_item->fr_description;
                $bestOfferPlanOrders->hi_description = $cart_item->hi_description;
                $bestOfferPlanOrders->ur_description = $cart_item->ur_description;
    
                $bestOfferPlanOrders->benefits = $cart_item->benefits;
                $bestOfferPlanOrders->ar_benefits = $cart_item->ar_benefits;
                $bestOfferPlanOrders->fr_benefits = $cart_item->fr_benefits;
                $bestOfferPlanOrders->hi_benefits = $cart_item->hi_benefits;
                $bestOfferPlanOrders->ur_benefits = $cart_item->ur_benefits;
                
                $bestOfferPlanOrders->save();               

                $total_charge += $cart_item->price*$cart_item->quantity; 
            }

            // coupon calculation
            $coupon_discount = 0;
            
            if($request->has('coupon_id')){
                $coupon = Coupons::find($request->coupon_id);

                $percent = $coupon->percentage;
                $flat = $coupon->flat;  

                $calculatedDiscount = ($total_charge * $percent) / 100;
                $coupon_discount = $calculatedDiscount + $flat;
                if($coupon_discount > $coupon->max_discount_amount){
                    $coupon_discount = $coupon->max_discount_amount;
                }
            }  
  
            $total_charge = $total_charge - $coupon_discount;

            $payment = new BestOfferPlanPayments();
            $payment->order_id = $order_id;
            $payment->user_id = $request->user_id;
            $payment->payment_status = 0;
            $payment->total_payable = $total_charge;
            $payment->is_coupon_applied = $request->is_coupon_applied ?? 0;
            $payment->coupon_id = $request->coupon_id ?? 0;
            $payment->save();

            $baseUrl = request()->getSchemeAndHttpHost();

            // $redirectUrl = $baseUrl . '/v2/api/v1/payment-response';
            // $cancelUrl   = $baseUrl . '/v2/api/v1/payment-cancel'; 
            
            $redirectUrl = $baseUrl . '/api/v1/payment-response';
            $cancelUrl   = $baseUrl . '/api/v1/payment-cancel'; 

            $data = [
                "merchant_id" => env('CCAVENUE_MERCHANT_ID'),
                "order_id" => $order_id,
                "currency" => "AED",
                "amount" => $total_charge,
                "merchant_param5" => Constants::CCAvenueBestOffersPaymentPaymentType,
                "redirect_url" => $redirectUrl,
                "cancel_url" => $cancelUrl,
                "language" => "EN",
            ];

            // return $data;

            $merchant_data = "";
            foreach ($data as $key => $value) {
                $merchant_data .= $key . '=' . $value . '&';
            }
            

            $encrypted_data = Crypto::encrypt($merchant_data, env('CCAVENUE_WORKING_KEY'));

            // $payment_url = env('CCAVENUE_BASE_URL') . "=$encrypted_data&access_code=" . env('CCAVENUE_ACCESS_CODE');
            $payment_url = env('CCAVENUE_BASE_URL') . "=$encrypted_data&access_code=" . env('CCAVENUE_ACCESS_CODE');;

            return response()->json([
                'status' => true,
                'payment_url' => $payment_url,
                // 'final_doctor_charge' => $final_doctor_charge
            ]);
        }

        catch (\Throwable $e) {
            // Log unexpected errors
            Log::error('Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return ['status' => false, 'message' => $e->getMessage()];
        }
    }

    function bestOffersPaymentResponse(Request $request){


        try{

            $workingKey = env('CCAVENUE_WORKING_KEY');
            $encResponse = $request->input('encResp');


            if(!$encResponse)
            {
                return response('No enResp', 400);
            }

            // Decrypt CCAvenue response
            $rcvdString = Crypto::decrypt($encResponse, $workingKey);
            parse_str($rcvdString, $responseData);

            // status check
            $status = $responseData['order_status'] ?? 'Unknown';
            $order_id = $responseData['order_id'] ?? null;
            
            if($responseData['merchant_param5'] == Constants::CCAvenueBestOffersPaymentPaymentType){
                
                $payment = BestOfferPlanPayments::where('order_id', $responseData['order_id'])->first();
                if($payment){
                    if($status == 'Success'){
                        $payment->payment_status = Constants::successPaymentStatus;
                        $payment->save();

                        BestOfferPlanOrders::where('order_id', $order_id)->update(['status'=> 1, 'purchased_at'=>now()->utc()]);

                        BestOfferCart::where('user_id', $payment->user_id)->delete();
                    }
  
                    else if($status == 'Failure'){
                        $payment->payment_status = Constants::failurePaymentStatus;
                        $payment->save();
                    }  
                }
  
                return response()->json([  
                    'status' => $status,
                    'data'   => [
                        'order_id'     => $order_id,
                        'amount'       => $responseData['amount'],
                        'currency'     => $responseData['currency'],  
                        'payment_mode' => $responseData['payment_mode'],  
                        'payment_details' => $payment
                    ]
                ]);
            
            }
        }
        catch (\Throwable $e) {
            // Log unexpected errors
            Log::error('Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return ['status' => false, 'message' => $e->getMessage()];
        }
    }

    function isAlreadyBooked($request)
    {
        $bookedSlotTimes = GlobalFunction::getActiveBookedSlotTimesForDoctorDate(
            $request->doctor_id,
            $request->date
        );

        return GlobalFunction::isDoctorSlotTimeBooked($request->time, $bookedSlotTimes) ? 1 : 0;
    }

    private function cancelUnpaidPendingAppointmentsForUserSlot($request): void
    {
        $normalizedDate = GlobalFunction::normalizeDateToYmd($request->date);
        if ($normalizedDate === null) {
            return;
        }

        Appointments::where('doctor_id', $request->doctor_id)
            ->whereDate('date', $normalizedDate)
            ->where('user_id', $request->user_id)
            ->where(function ($query) {
                $query->whereNull('payment_status')
                    ->orWhere('payment_status', '!=', Constants::appointmentPaymentSuccessStatus);
            })
            ->whereNotIn('status', [
                Constants::orderCancelled,
                Constants::orderDeclined,
                Constants::orderCompleted,
            ])
            ->get()
            ->each(function ($appointment) use ($request) {
                $bookedTimes = [];
                $normalizedTime = GlobalFunction::normalizeTimeToHi($appointment->time);
                if ($normalizedTime !== null) {
                    $bookedTimes[$normalizedTime] = true;
                }

                if (GlobalFunction::isDoctorSlotOverlappingAppointment($request->time, $bookedTimes)) {
                    $appointment->status = Constants::orderCancelled;
                    $appointment->save();
                }
            });
    }

    function getFinalDoctorCharges($doctor, $doctor_plan, $coupon_id)
    {
        $total_charge = 0;

        // doctor offline type 
        if($doctor_plan){
            // percent
            if($doctor_plan->discount_type == 'percent'){
                $total_charge = $doctor_plan->original_price - (($doctor_plan->original_price * $doctor_plan->discount) / 100);
            }

            else{
                $total_charge = $doctor_plan->original_price - $doctor_plan->discount;
            }
        }

        // doctor online type
        else{
            $total_charge = $doctor->consultation_fee;
        }

        $base_price = $total_charge;

        // coupon calculation
        $coupon_discount = 0;
        
        if($coupon_id){
            $coupon = Coupons::find($coupon_id);

            $percent = $coupon->flat;
            $max = $coupon->flat;

            // $calculatedDiscount = ($total_charge * $percent) / 100;
            // $coupon_discount = min($calculatedDiscount, $max);
            $coupon_discount = $max;
        }

        $total_charge = $total_charge - $coupon_discount;

        return [
            'base_price' => $base_price,
            'discount' => $coupon_discount,
            'final_price' => $total_charge
        ];
    }

    function ccavenue_payment_response(Request $request)
    {

        try{
            Log::info('BOOKING_TRACE v1.ccavenue.payment_response hit', [
                'route' => 'api.v1.payment-response',
                'host' => $request->getHost(),
            ]);

            $workingKey = env('CCAVENUE_WORKING_KEY');
            $encResponse = $request->input('encResp');

            $meeting_link = '';

            if(!$encResponse)
            {
                return response('No enResp', 400);
            }

            // Decrypt CCAvenue response
            $rcvdString = Crypto::decrypt($encResponse, $workingKey);
            parse_str($rcvdString, $responseData);

            // status check
            $status = $responseData['order_status'] ?? 'Unknown';
            $order_id = $responseData['order_id'] ?? null;

            if($responseData['merchant_param5'] == Constants::CCAvenueHnHPaymentType){
                $hnhCard = HnHCards::where('order_id', $responseData['order_id'])->first();
                if($hnhCard){
                    if($status == 'Success'){
                        $hnhCard->payment_status = Constants::HnHPaymentSuccessStatus;
                        $hnhCard->purchased_at = now()->format('Y-m-d H:i:s');
                        $hnhCard->save();

                        $user = Users::where('id', $hnhCard->user_id)->first();
                        if(isset($user))
                        {
                            \Mail::to($user->email)->send(new \App\Mail\SendWelcomeHnHMail($user->username, $user->fullname, $hnhCard->image));
                        }
                    }

                    else if($status == 'Failure'){
                        $hnhCard->payment_status = Constants::HnHPaymentFailureStatus;
                        $hnhCard->save();
                    }
                }

                return response()->json([
                    'status' => $status,
                    'data'   => [
                        'order_id'     => $order_id,
                        'amount'       => $responseData['amount'],
                        'currency'     => $responseData['currency'],  
                        'payment_mode' => $responseData['payment_mode'],
                        'hnh_card_details' => $hnhCard
                    ]
                ]);
            }

            else if($responseData['merchant_param5'] == Constants::CCAvenueTouristCardPaymentType){
                $touristCard = TouristCards::where('order_id', $responseData['order_id'])->first();
                if($touristCard){
                    if($status == 'Success'){
                        $touristCard->payment_status = Constants::successPaymentStatus;

                        $now = now();

                        $touristCard->purchased_at = $now;
                            
                        $touristCard->save();

                        $user = Users::where('id', $touristCard->user_id)->first();
                        if(isset($user))
                        {
                            \Mail::to($user->email)->send(new \App\Mail\SendWelcomeTouristMail($user->username, $user->fullname, $touristCard->image));
                        }
                    }

                    else if($status == 'Failure'){
                        $touristCard->payment_status = Constants::failurePaymentStatus;
                        $touristCard->save();
                    }
                }

                return response()->json([
                    'status' => $status,
                    'data'   => [
                        'order_id'     => $order_id,
                        'amount'       => $responseData['amount'],
                        'currency'     => $responseData['currency'],  
                        'payment_mode' => $responseData['payment_mode'],
                        'tourist_card_details' => $touristCard
                    ]
                ]);
            }

            else if($responseData['merchant_param5'] == Constants::CCAvenueSeniorCardPaymentType){
                $seniorCard = SeniorCards::where('order_id', $responseData['order_id'])->first();
                if($seniorCard){
                    if($status == 'Success'){
                        $seniorCard->payment_status = Constants::successPaymentStatus;
                        $seniorCard->purchased_at = now()->format('Y-m-d H:i:s');
                        $seniorCard->save();

                        $user = Users::where('id', $seniorCard->user_id)->first();
                        if(isset($user))
                        {
                            \Mail::to($user->email)->send(new \App\Mail\SendWelcomeSeniorMail($user->username, $user->fullname, $seniorCard->image));
                        }
                    }

                    else if($status == 'Failure'){
                        $seniorCard->payment_status = Constants::failurePaymentStatus;
                        $seniorCard->save();
                    }
                }

                return response()->json([
                    'status' => $status,
                    'data'   => [
                        'order_id'     => $order_id,
                        'amount'       => $responseData['amount'],
                        'currency'     => $responseData['currency'],  
                        'payment_mode' => $responseData['payment_mode'],
                        'senior_card_details' => $seniorCard
                    ]
                ]);
            }

            else if(($responseData['merchant_param5'] == Constants::CCAvenueAIVitalScanPaymentType) || ($responseData['merchant_param5'] == Constants::CCAvenueAIVitalScanBeforePaymentType) || ($responseData['merchant_param5'] == Constants::CCAvenueMesaBeforeChatPayment)){
                $ai_vital_misa = AIVitalScanMisa::where('order_id', $responseData['order_id'])->first();
                if($ai_vital_misa){
                    if($status == 'Success'){
                        $ai_vital_misa->payment_status = Constants::AIVitalsPaymentSuccessStatus;
                        $ai_vital_misa->payment_type = $responseData['merchant_param5'];
                        $ai_vital_misa->save();
                    }

                    else if($status == 'Failure'){
                        $ai_vital_misa->payment_status = Constants::AIVitalsPaymentFailureStatus;
                        $ai_vital_misa->payment_type = $responseData['merchant_param5'];
                        $ai_vital_misa->save();
                    }
                }

                return response()->json([
                    'status' => $status,
                    'data'   => [
                        'order_id'     => $order_id,
                        'amount'       => $responseData['amount'],
                        'currency'     => $responseData['currency'],
                        'payment_mode' => $responseData['payment_mode'],
                        // 'AI_Vitals_scan_details' => $ai_vital_misa
                    ]
                ]);
            }

            else if($responseData['merchant_param5'] == Constants::CCAvenueBestOffersPaymentPaymentType){
                return $this->bestOffersPaymentResponse($request);
            }

            $appointment = Appointments::where('order_id', $order_id)->first();

            if($appointment->payment_status != Constants::appointmentPaymentPendingStatus){
                 return response()->json([
                'status' => false,
                'message' => "payment already made !",
                ]);
            }

            // update status and save jitsi link
            if ($status === 'Success' && $order_id) {

                // Appointments::where('order_id', $order_id)->update(['payment_status' => Constants::appointmentPaymentSuccessStatus]);
                $appointment->payment_status = Constants::appointmentPaymentSuccessStatus;
                $appointment->status = Constants::orderAccepted;
                $appointment->save();

                if($appointment->user_plan_id != 0){
                    $doctor_plan = DoctorPlans::select('doctor_plans.*')->join('user_plans','user_plans.plan_id','doctor_plans.id')
                                    ->where('user_plans.id',$appointment->user_plan_id)->first();
                    if($doctor_plan->number_of_consultations > 1){
                        $user_coupon = new UserCoupons();
                        $user_coupon->user_id = $appointment->user_id;
                        $user_coupon->plan_id = $doctor_plan->id;
                        $user_coupon->coupon_code = strtoupper(Str::random(6));
                        $user_coupon->number_of_limits = $doctor_plan->number_of_consultations - 1;
                        $appointment_date = Carbon::createFromFormat('Y-m-d', $appointment->date);
                        $user_coupon->save();
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
                $appointmentTime = GlobalFunction::formatTimeForDisplay($appointment->time);

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

                $appointmentTimeForConversion = GlobalFunction::normalizeTimeToHis($appointment->time) ?? '00:00:00';
                $userTimezone = GlobalFunction::getTimezoneByCountryCode($user->country_code);
                $doctorTimezone = GlobalFunction::getTimezoneByCountryCode($doctor->country_code);

                $baseTimezoneForConversion = Cache::get('booking_base_timezone_' . $order_id, GlobalFunction::getUtcTimezoneValue());
                $userAppointmentDate = GlobalFunction::convertDateTimeToUserTimezone($appointment->date, $appointmentTimeForConversion, $userTimezone, 'd-m-Y', $baseTimezoneForConversion) ?? $appointmentdate;
                $userAppointmentTime = GlobalFunction::convertTimeToUserTimezone($appointmentTimeForConversion, $userTimezone, $appointment->date, 'g:i A', $baseTimezoneForConversion) ?? $appointmentTime;

                $doctorAppointmentDate = GlobalFunction::convertDateTimeToUserTimezone($appointment->date, $appointmentTimeForConversion, $doctorTimezone, 'd-m-Y', $baseTimezoneForConversion) ?? $appointmentdate;
                $doctorAppointmentTime = GlobalFunction::convertTimeToUserTimezone($appointmentTimeForConversion, $doctorTimezone, $appointment->date, 'g:i A', $baseTimezoneForConversion) ?? $appointmentTime;
                Log::info('Ccavenue booking conversion snapshot (flow-3)', [
                    'appointment_id' => $appointment->id,
                    'raw_date' => $appointment->date,
                    'raw_time' => $appointment->time,
                    'normalized_time' => $appointmentTimeForConversion,
                    'base_timezone' => $baseTimezoneForConversion,
                    'user_country_code' => $user->country_code,
                    'doctor_country_code' => $doctor->country_code,
                    'user_timezone' => $userTimezone,
                    'doctor_timezone' => $doctorTimezone,
                    'user_converted_date' => $userAppointmentDate,
                    'user_converted_time' => $userAppointmentTime,
                    'doctor_converted_date' => $doctorAppointmentDate,
                    'doctor_converted_time' => $doctorAppointmentTime,
                ]);

                \Mail::to($user->email)->send(new \App\Mail\JitsiMeetingLinkPatient($appointment, $doctor, $user, $patient_link_mail));

                $cleanCode = GlobalFunction::normalizeCountryCode($user->country_code) ?? ltrim((string) $user->country_code, '+');
                // Build SMS message (plain text)
//                 $message = "Dear {$user->fullname},

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
                Log::info('BOOKING_TRACE v1 flow-3 user sms triggered', [
                    'appointment_id' => $appointment->id,
                    'receiver' => $cleanCode . $user->phone_number,
                    'message_preview' => $message,
                ]);
                
                // $current_date = Carbon::now()->toDateString();
                // $current_time = Carbon::now('Asia/Kolkata')->toTimeString();;
//                 $message = "Dear {$doctor->name},

// Payment of AED {$appointment->payable_amount} received for your appointment at Mulk Med on {$current_date} at {$current_time}.

// Thank you for choosing us.

// Regards,
// Team Mulk Med";            
                // $result = EmailHelpers::sendSms($cleanCode . $user->phone_number, $message);
                // \Mail::to($doctor->identity)->send(new \App\Mail\JitsiMeetingLinkPatient($meeting_link));
                $countryCode = GlobalFunction::normalizeCountryCode($doctor->country_code) ?? ltrim((string) $doctor->country_code, '+');

                $host = request()->getHost();
                if ($host === 'india.mulkmed.com') {
//                     $message = "Dear {$doctor->name},
// You have a new appointment booked on {$appointmentdate} at {$appointmentTime}.
// Kindly log in to the Mulk Med app to view details.

// Regards,
// Team Mulk Med";    
                $message = "New appointment: {$doctorAppointmentDate} at {$doctorAppointmentTime}. View details in Mulk Med App India. ";    
                    $result = EmailHelpers::sendSms($countryCode . $doctor->mobile_number, $message);
                    Log::info('BOOKING_TRACE v1 flow-3 doctor sms triggered', [
                        'appointment_id' => $appointment->id,
                        'receiver' => $countryCode . $doctor->mobile_number,
                        'message_preview' => $message,
                    ]);
                }else{

                     $message = "New appointment: {$doctorAppointmentDate} at {$doctorAppointmentTime}. View details in Mulk Med App UAE. ";    
                    $result = EmailHelpers::sendSms($countryCode . $doctor->mobile_number, $message);
                    Log::info('BOOKING_TRACE v1 flow-3 doctor sms triggered', [
                        'appointment_id' => $appointment->id,
                        'receiver' => $countryCode . $doctor->mobile_number,
                        'message_preview' => $message,
                    ]);

                }        
                //     $result = EmailHelpers::sendSms($countryCode . $doctor->mobile_number, $message);
                // }
                // $message = "Dear Team,
// Patient {$user->fullname} ({$user->identity}, {$user->phone_number}) booked an appointment with {$doctor->name} on {$appointmentdate} at {$appointmentTime}."; 
               $message ="{$user->fullname} ({$user->phone_number}) booked with {$doctor->name} on {$userAppointmentDate} at {$userAppointmentTime}. ";           
                $result = EmailHelpers::sendSms( 971522463433 , $message);
                Log::info('BOOKING_TRACE v1 flow-3 admin sms triggered', [
                    'appointment_id' => $appointment->id,
                    'receiver' => '971522463433',
                    'message_preview' => $message,
                ]);
                \Mail::to($doctor->identity)->send(new \App\Mail\JitsiMeetingLinkDoctor($appointment, $doctor, $user, $doctor_link_mail));
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
                $amount    = $responseData['amount'] ?? null;
                $currency  = $responseData['currency'] ?? null;
                $pay_mode  = $responseData['payment_mode'] ?? null;

                return response()->json([
                    'status' => $status,
                    'data'   => [
                        'order_id'     => $order_id,
                        'amount'       => $amount,
                        'currency'     => $currency,
                        'payment_mode' => $pay_mode,
                        'meeting_link' => $meeting_link_patient,
                        // 'meeting_doctor' => $meeting_link_doctor
                    ]
                ]);
            }

            else if($status == 'Failure' && $order_id)
            {
                Appointments::where('order_id', $order_id)->update(['payment_status' => Constants::appointmentPaymentFailureStatus]);
                $amount    = $responseData['amount'] ?? null;
                $currency  = $responseData['currency'] ?? null;
                $pay_mode  = $responseData['payment_mode'] ?? null;

                return response()->json([
                    'status' => $status,
                    'data'   => [
                        'order_id'     => $order_id,
                        'amount'       => $amount,
                        'currency'     => $currency,
                        'payment_mode' => $pay_mode,
                        // 'meeting_doctor' => $meeting_link_doctor
                    ]
                ]);
            }

            
        }

        catch (\Throwable $e) {
            // Log unexpected errors
            Log::error('Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return ['status' => false, 'message' => $e->getMessage()];
        }

    }

    function ccavenue_payment_cancel(Request $request){

        try{
            $workingKey = env('CCAVENUE_WORKING_KEY');
            $encResponse = $request->input('encResp');

            if(!$encResponse)
            {
                return response('No enResp', 400);
            }

            // Decrypt CCAvenue response
            $rcvdString = Crypto::decrypt($encResponse, $workingKey);
            parse_str($rcvdString, $responseData);

            // Example: basic status check
            $status = $responseData['order_status'] ?? 'Unknown';
            $order_id = $responseData['order_id'] ?? null;

            if($responseData['merchant_param5'] == Constants::CCAvenueHnHPaymentType){
                $hnhCard = HnHCards::where('order_id', $responseData['order_id'])->first();
                if($hnhCard){
                    if($status == 'Aborted'){
                        $hnhCard->payment_status = Constants::HnHPaymentAbortedStatus;
                        $hnhCard->save();
                    }

                   
                }

                return response()->json([
                'status' => $status,
                'data'   => [
                    'order_id'     => $order_id,
                    'amount'       => $responseData['amount'],
                    'currency'     => $responseData['currency'],
                    'payment_mode' => $responseData['payment_mode'],
                ]
            ]);
            }

            else if(($responseData['merchant_param5'] == Constants::CCAvenueAIVitalScanPaymentType) || ($responseData['merchant_param5'] == Constants::AIVitalScanPaymentBeforeType)){
                $ai_vital_misa = AIVitalScanMisa::where('order_id', $responseData['order_id'])->first();
                if($ai_vital_misa){
                   
                    if($status == 'Aborted'){
                        $ai_vital_misa->payment_status = Constants::AIVitalsPaymentAbortedStatus;
                        $ai_vital_misa->payment_type = $responseData['merchant_param5'];
                        $ai_vital_misa->save();
                    }
                }

                return response()->json([
                    'status' => $status,
                    'data'   => [
                        'order_id'     => $order_id,
                        'amount'       => $responseData['amount'],
                        'currency'     => $responseData['currency'],
                        'payment_mode' => $responseData['payment_mode'],
                        // 'AI_Vitals_scan_details' => $ai_vital_misa
                    ]
                ]);
            }

            $appointment = Appointments::where('order_id', $order_id)->first();

            if($appointment->payment_status != Constants::appointmentPaymentPendingStatus){
                 return response()->json([
                'status' => false,
                'message' => "payment already made !",
                ]);
            }

            // update status
            if ($status === 'Aborted' && $order_id) {
                $appointment->payment_status = Constants::appointmentPaymentAbortedStatus;
                $appointment->save();
            }

            $amount    = $responseData['amount'] ?? null;
            $currency  = $responseData['currency'] ?? null;
            $pay_mode  = $responseData['payment_mode'] ?? null;

            return response()->json([
                'status' => $status,
                'data'   => [
                    'order_id'     => $order_id,
                    'amount'       => $amount,
                    'currency'     => $currency,
                    'payment_mode' => $pay_mode,
                ]
            ]);
        }

        catch (\Throwable $e) {
            // Log unexpected errors
            Log::error('Error Occured', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return ['status' => false, 'message' => $e->getMessage()];
        }
    }

    function ccavenue_payment_webhook(Request $request)
    {
        $workingKey = env('CCAVENUE_WORKING_KEY');
        $encResponse = $request->input('encResp');

        if(!$encResponse)
        {
            return response('No enResp', 400);
        }

        // Decrypt CCAvenue response
        $rcvdString = Crypto::decrypt($encResponse, $workingKey);
        parse_str($rcvdString, $responseData);

        // Example: basic status check
        $status = $responseData['order_status'] ?? 'Unknown';
        $order_id = $responseData['order_id'] ?? null;

        $appointment = Appointments::where('order_id', $order_id)->first();

        if($appointment->payment_status != Constants::appointmentPaymentPendingStatus){
                return response()->json([
            'status' => false,
            'message' => "payment already made !",
            ]);
        }

        if($responseData['merchant_param5'] == 'hnhpayment'){
                $hnhCard = HnHCards::where('order_id', $responseData['order_id'])->first();
                if($hnhCard){
                    if($status == 'Success'){
                        $hnhCard->payment_status = Constants::HnHPaymentSuccessStatus;
                        $hnhCard->save();
                    }

                    else if($status == 'Failure'){
                        $hnhCard->payment_status = Constants::HnHPaymentFailureStatus;
                        $hnhCard->save();
                    }
                }

                return response()->json([
                'status' => $status,
                'data'   => [
                    'order_id'     => $order_id,
                    'amount'       => $responseData['amount'],
                    'currency'     => $responseData['currency'],
                    'payment_mode' => $responseData['payment_mode'],
                    'hnh_card_details' => $hnhCard
                ]
            ]);
            }


        // update status
        if ($status === 'Success' && $order_id) {
            Appointments::where('order_id', $order_id)->update(['payment_status' => Constants::appointmentPaymentSuccessStatus]);
        }

        else if($status == 'Failure' && $order_id)
        {
            Appointments::where('order_id', $order_id)->update(['payment_status' => Constants::appointmentPaymentFailureStatus]);
        }

        // update status
        if ($status === 'Aborted' && $order_id) {
            Appointments::where('order_id', $order_id)->update(['payment_status' => Constants::appointmentPaymentAbortedStatus]);
        }

        return response('OK', 200);
    }

    function convertInrToAed($amountInINR)
    {
        // Cache key 
        $cacheKey = 'inr_to_aed_rate';

        // Try to get from cache, else fetch and store
        $rate = Cache::remember($cacheKey, now()->addHours(6), function () {
            $response = Http::get('https://open.er-api.com/v6/latest/INR');

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['rates']['AED'])) {
                    return $data['rates']['AED'];
                }
            }

            return null; // If API fails, store null so we can handle it outside
        });

        if (!$rate) {
            throw new \Exception("Unable to fetch INR to AED conversion rate.");
        }

        // Apply buffer (1% less AED per INR to protect against drops)
        // $bufferPercent = 1; 
        // $rateWithBuffer = $rate * (1 - $bufferPercent / 100);

        // Return rounded AED amount
        // return round($amountInINR * $rate, 2);
        return [
            'rate' => $rate,
            'aed'  => $amountInINR * $rate,
        ];
    }

    function join_jitsi_meeting(Request $request)
    {
        $rules = [
            'room' => 'required',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }

        $jitsi_meeting = JitsiMeeting::where('room',$request->room)->first();
        //  return $jitsi_meeting->doctor_link;
        if($jitsi_meeting){
            $jitsi_meeting = GlobalFunction::persistNormalizedJitsiMeetingLinks($jitsi_meeting);
            $appointment_id = (int) $jitsi_meeting->appointment_id;
            $current_date_time = Carbon::now()->format('Y-m-d H:i:s');

            if ($request->boolean('conference_joined')) {
                if ($jitsi_meeting->start_time > $current_date_time) {
                    return response()->json(['status' => false, 'message' => "Your consultation will start at {$jitsi_meeting->start_time}"]);
                }

                if (!GlobalFunction::handleJitsiConferenceJoined($jitsi_meeting, $request)) {
                    return response()->json(['status' => false, 'message' => 'Invalid meeting participant']);
                }

                return response()->json(['status' => true]);
            }

            if($jitsi_meeting->start_time > $current_date_time)
            {
                return response()->json(['status' => false, 'message' => "Your consultation will start at {$jitsi_meeting->start_time}"]);
            }
            if($request->has('doctor_id'))
            {
                $doctor_jitsi_meeting = JitsiMeeting::where('room',$request->room)->where('doctor_id',$request->doctor_id)->first();
                if($doctor_jitsi_meeting)
                {
                    $doctor_jitsi_meeting->doctor_joined = 1;
                    $doctor_jitsi_meeting->save();

                    $joinFields = GlobalFunction::jitsiMeetingJoinResponseFields($jitsi_meeting->doctor_link, $request->room);
                    return response()->json(array_merge([
                        'status' => true,
                        'appointment_id' => $appointment_id,
                    ], $joinFields));
                }
            }
           
            if($request->has('user_id'))
            {
                if ((int) $request->user_id === (int) $jitsi_meeting->user_id) {
                    $jitsi_meeting->user_joined = 1;
                    $jitsi_meeting->save();
                }

                if((int) $jitsi_meeting->doctor_joined === 1)
                {
                    $joinFields = GlobalFunction::jitsiMeetingJoinResponseFields($jitsi_meeting->patient_link, $request->room);
                    return response()->json(array_merge([
                        'status' => true,
                        'appointment_id' => $appointment_id,
                    ], $joinFields));
                }
                else{
                   // return response()->json(['status' => true, 'message' => 'Kindly hold on, your doctor will be with you shortly.']);

                    return response()->json(['status' => true, 'appointment_id' => $appointment_id, 'message' => 'Kindly hold on, your doctor will be with you shortly.']);
                }
            }
        }
    }

    function join_jitsi_meeting_mail(Request $request)
    {
        $rules = [
            'room' => 'required',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }

        $jitsi_meeting = JitsiMeeting::where('room',$request->room)->first();
        if($jitsi_meeting){
            $jitsi_meeting = GlobalFunction::persistNormalizedJitsiMeetingLinks($jitsi_meeting);
            $appointment = Appointments::where('id',$jitsi_meeting->appointment_id)->where('status',2)->first();
            if($appointment)
            {
                $message = "Your appointment is completed successfully";
                return view('pages.jitsi_meeting_message', compact('message'));
            }
            $current_date_time = Carbon::now()->format('Y-m-d H:i:s');

            if($jitsi_meeting->start_time > $current_date_time)
            {
                $message = "Your will start at {$jitsi_meeting->start_time}";
                return view('pages.jitsi_meeting_message', compact('message'));
            }
            if($request->has('doctor_id'))
            {
                $doctor_jitsi_meeting = JitsiMeeting::where('room',$request->room)->where('doctor_id',$request->doctor_id)->first();
                if($doctor_jitsi_meeting)
                {
                    $doctor_jitsi_meeting->doctor_joined = 1;
                    $doctor_jitsi_meeting->save();
                    return redirect(GlobalFunction::normalizeJitsiWrapperUrl($jitsi_meeting->doctor_link));

                }
            }
            if($request->has('user_id'))
            {
                if($jitsi_meeting->doctor_joined == 1)
                {
                    return redirect(GlobalFunction::normalizeJitsiWrapperUrl($jitsi_meeting->patient_link));
                }
                else{
                    $message = 'Kindly hold on, your doctor will be with you shortly.';
                    return view('pages.jitsi_meeting_message', compact('message'));
                }
            }
        }
    }

}
