<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Models\AppointmentDocs;
use App\Models\Appointments;
use App\Models\Constants;
use App\Models\DoctorPlans;
use App\Models\Doctors;
use App\Models\GlobalFunction;
use App\Models\Users;
use App\Models\Coupons;
use App\Models\UserPlan;
use App\Models\JitsiMeeting;
use App\Models\HnHCards;
use App\Models\AIVitalScanMisa;
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

class CcavenueController extends Controller
{
   
    public function initiateAppointmentPayment(Request $request)
    {
        try {
            Log::info('BOOKING_TRACE v2.ccavenue.initiateAppointmentPayment hit', [
                'route' => 'api.v2.ccavenue.initiate',
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
            $baseTimezoneForConversion = GlobalFunction::getUtcTimezoneValue();
            Cache::put('booking_base_timezone_' . $order_id, $baseTimezoneForConversion, now()->addHours(24));

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
            $appointment->order_summary = $request->order_summary;
            $appointment->is_coupon_applied = $request->is_coupon_applied;

            $appointment->service_amount = $final_doctor_charges['base_price'];
            $appointment->discount_amount = $final_doctor_charges['discount'];
            $appointment->subtotal = $request->subtotal;
            $appointment->total_tax_amount = $request->total_tax_amount;
            $appointment->payable_amount = $final_doctor_charges['final_price'];
            $appointment->order_id = $order_id;
            $appointment->payment_status = 0;

            // save appointment
            $appointment->save();
            Log::info('BOOKING_TRACE v2 initiate appointment saved', [
                'appointment_id' => $appointment->id,
                'appointment_number' => $appointment->appointment_number,
                'order_id' => $order_id,
                'date' => $appointment->date,
                'time' => $appointment->time,
                'base_timezone' => $baseTimezoneForConversion,
            ]);

            if ($request->is_coupon_applied == 1) {
                $appointment->coupon_title = $request->coupon_title;
                // add coupon to used coupon
                $discounts = explode(',', $user->coupons_used);
                array_push($discounts, $request->coupon_id);
                $user->coupons_used = implode(',', $discounts);
            }

            // $amount = $request->amount;
            $amount = $final_doctor_charges['final_price'];


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
                $user_plan->valid_from = Carbon::today();
                $user_plan->valid_to = Carbon::today()->addDays($doctor_plan->number_of_days);
                $user_plan->status = 'active';
                $user_plan->save();

                $appointment->user_plan_id = $user_plan->id;
                $appointment->save();
            }


            $data = [
                "merchant_id" => env('CCAVENUE_MERCHANT_ID'),
                "order_id" => $order_id,
                "currency" => "AED",
                "amount" => $amount,
                "merchant_param1" => Constants::appointmentPaymentType,
                "redirect_url" => env('CCAVENUE_REDIRECT_URL'),
                "cancel_url" => env('CCAVENUE_CANCEL_URL'),
                "language" => "EN",
                "appointment_id" => $appointment->id
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

            return ['status' => false, 'message' => $e->getMessage()];
        }
        
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
                'gender' => [Rule::in(Constants::genderMale, Constants::genderFemale)],
                'address' => 'required'
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
            $hnh_card->address = $request->address;
            $hnh_card->order_id = $order_id;
            $hnh_card->payment_status = 0;
            $hnh_card->payment_amount = 45; // static for now
            $hnh_card->save();

            // initial 45 AED 
            $amount = $hnh_card->balance_aed; // static for now

            $data = [
                "billing_name" => $request->user_name,
                "billing_email" => $request->email,
                "billing_address" => $request->address,
                "merchant_id" => env('CCAVENUE_MERCHANT_ID'),
                "order_id" => $order_id,
                "currency" => "AED",
                "amount" => $amount,
                "merchant_param1" => Constants::HnHPaymentType,
                "redirect_url" => env('CCAVENUE_REDIRECT_URL'),
                "cancel_url" => env('CCAVENUE_CANCEL_URL'),
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

    function initiatePaymentAIVitalScan(Request $request){
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
            $ai_vital_misa->save();

            $data = [
                "merchant_id" => env('CCAVENUE_MERCHANT_ID'),
                "order_id" => $order_id,
                "currency" => "AED",
                "amount" => $amount,
                "merchant_param1" => Constants::AIVitalScanPaymentType,
                "redirect_url" => env('CCAVENUE_REDIRECT_URL'),
                "cancel_url" => env('CCAVENUE_CANCEL_URL'),
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

    function isAlreadyBooked($request)
    {
        $timeStr = str_pad($request->time, 4, '0', STR_PAD_LEFT);
        $hours   = substr($timeStr, 0, 2);
        $minutes = substr($timeStr, 2, 2);
        $requestEnd = date('Hi', strtotime("$hours:$minutes +" . Constants::meetingDurationInMinutes . " minutes"));

        $is_already_booked = Appointments::where('status', Constants::appointmentPaymentPendingStatus)
            ->where('payment_status', Constants::appointmentPaymentSuccessStatus)
            ->where('doctor_id', $request->doctor_id)
            ->where('date', $request->date)
            ->get()
            ->filter(function ($appt) use ($request, $requestEnd) {
                $start = str_pad($appt->time, 4, '0', STR_PAD_LEFT);
                $startH = substr($start, 0, 2);
                $startM = substr($start, 2, 2);
                $end = date('Hi', strtotime("$startH:$startM +" . Constants::meetingDurationInMinutes . " minutes"));

                // Overlap check in both directions
                return (
                    ($request->time >= (int)$appt->time && $request->time <= (int)$end) || // request in existing
                    ((int)$appt->time >= $request->time && (int)$appt->time <= (int)$requestEnd) // existing in request
                );
            })
            ->count();

        return $is_already_booked;
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

            $percent = $coupon->percentage;
            $max = $coupon->max_discount_amount;

            $calculatedDiscount = ($total_charge * $percent) / 100;
            $coupon_discount = min($calculatedDiscount, $max);
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
            Log::info('BOOKING_TRACE v2.ccavenue.payment_response hit', [
                'route' => 'api.v2.payment-response',
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
            Log::info('BOOKING_TRACE v2 payment response decoded', [
                'order_id' => $order_id,
                'status' => $status,
                'merchant_param1' => $responseData['merchant_param1'] ?? null,
            ]);

            if($responseData['merchant_param1'] == Constants::CCAvenueHnHPaymentType){
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

            else if($responseData['merchant_param1'] == Constants::CCAvenueAIVitalScanPaymentType){
                $ai_vital_misa = AIVitalScanMisa::where('order_id', $responseData['order_id'])->first();
                if($ai_vital_misa){
                    if($status == 'Success'){
                        $ai_vital_misa->payment_status = Constants::AIVitalsPaymentSuccessStatus;
                        $ai_vital_misa->save();
                    }

                    else if($status == 'Failure'){
                        $ai_vital_misa->payment_status = Constants::AIVitalsPaymentFailureStatus;
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

            // update status and save jitsi link
            if ($status === 'Success' && $order_id) {

                // Appointments::where('order_id', $order_id)->update(['payment_status' => Constants::appointmentPaymentSuccessStatus]);
                $appointment->payment_status = Constants::appointmentPaymentSuccessStatus;
                $appointment->status = Constants::orderAccepted;
                $appointment->save();
                $room = 'appointment-' . Str::random(10);
                $jitsiBaseUrl = env('JITSI_URL');
                $link = $jitsiBaseUrl . '?roomId=' . $room;
                $date = $appointment->date;
                $time = $appointment->time;
                $formattedTime = substr($time, 0, 2) . ':' . substr($time, 2, 2);
                $startDateTime = Carbon::createFromFormat('Y-m-d H:i', $date . ' ' . $formattedTime);
                $endDateTime = $startDateTime->copy()->addHour();

                $meeting = new JitsiMeeting;
                $meeting->room = $room;
                $meeting->link = $link;
                $meeting->appointment_id = $appointment->id;
                $meeting->user_id = $appointment->user_id;
                $meeting->doctor_id = $appointment->doctor_id;
                $meeting->start_time = $startDateTime;
                $meeting->end_time = $endDateTime;
                $meeting->save();

                // $path = "meetings/join/".$room;
                // $meeting_link = url($path);
                $meeting_link_patient = GlobalFunction::GeneratePatientJitsiMeetingLink($meeting);
                $meeting_link_doctor = GlobalFunction::GenerateDoctorJitsiMeetingLink($meeting);
                // $meeting_link_patient =  $link;
                // $meeting_link_doctor =  $link;

                $user = Users::find($appointment->user_id);
                $doctor = Doctors::find($appointment->doctor_id);

                $appointment = Appointments::find($appointment->id) ?? $appointment;
                $normalizedTime = GlobalFunction::normalizeTimeToHis($appointment->time);
                $userTimezone = GlobalFunction::getTimezoneByCountryCode($user->country_code);
                $doctorTimezone = GlobalFunction::getTimezoneByCountryCode($doctor->country_code);
                $baseTimezoneForConversion = Cache::get('booking_base_timezone_' . $order_id, GlobalFunction::getUtcTimezoneValue());
                $userDateForLog = GlobalFunction::convertDateTimeToUserTimezone($appointment->date, $normalizedTime, $userTimezone, 'd-m-Y', $baseTimezoneForConversion);
                $userTimeForLog = GlobalFunction::convertTimeToUserTimezone($normalizedTime, $userTimezone, $appointment->date, 'g:i A', $baseTimezoneForConversion);
                $doctorDateForLog = GlobalFunction::convertDateTimeToUserTimezone($appointment->date, $normalizedTime, $doctorTimezone, 'd-m-Y', $baseTimezoneForConversion);
                $doctorTimeForLog = GlobalFunction::convertTimeToUserTimezone($normalizedTime, $doctorTimezone, $appointment->date, 'g:i A', $baseTimezoneForConversion);
                $userAppointmentDate = $userDateForLog ?? Carbon::parse($appointment->date)->format('d-m-Y');
                $userAppointmentTime = $userTimeForLog ?? GlobalFunction::formatTimeForDisplay($appointment->time);
                $doctorAppointmentDate = $doctorDateForLog ?? Carbon::parse($appointment->date)->format('d-m-Y');
                $doctorAppointmentTime = $doctorTimeForLog ?? GlobalFunction::formatTimeForDisplay($appointment->time);
                Log::info('BOOKING_TRACE v2 payment success conversion snapshot', [
                    'appointment_id' => $appointment->id,
                    'raw_date' => $appointment->date,
                    'raw_time' => $appointment->time,
                    'normalized_time' => $normalizedTime,
                    'base_timezone' => $baseTimezoneForConversion,
                    'user_country_code' => $user->country_code,
                    'doctor_country_code' => $doctor->country_code,
                    'user_timezone' => $userTimezone,
                    'doctor_timezone' => $doctorTimezone,
                    'user_converted_date' => $userDateForLog,
                    'user_converted_time' => $userTimeForLog,
                    'doctor_converted_date' => $doctorDateForLog,
                    'doctor_converted_time' => $doctorTimeForLog,
                ]);

                \Mail::to($user->email)->send(new \App\Mail\JitsiMeetingLinkPatient($appointment, $doctor, $user, $meeting_link_patient));

                $cleanCode = ltrim($user->country_code, '+');
                $message = "Dear {$user->fullname},

Your appointment with Mulk Med is confirmed for {$userAppointmentDate} at {$userAppointmentTime} with Dr. {$doctor->name}.

Regards,
Team Mulk Med";            
                $result = EmailHelpers::sendSms($cleanCode . $user->phone_number, $message);
                Log::info('BOOKING_TRACE v2 user sms triggered', [
                    'appointment_id' => $appointment->id,
                    'receiver' => $cleanCode . $user->phone_number,
                    'message_preview' => $message,
                ]);
                
                $countryCode = ltrim($doctor->country_code, '+');
                $message = "Dear {$doctor->name},

Payment of AED {$appointment->payable_amount} received for your appointment at Mulk Med on {$doctorAppointmentDate} at {$doctorAppointmentTime}.

Thank you for choosing us.

Regards,
Team Mulk Med";            
                $result = EmailHelpers::sendSms($countryCode . $doctor->mobile_number, $message);
                Log::info('BOOKING_TRACE v2 doctor/payment sms triggered', [
                    'appointment_id' => $appointment->id,
                    'receiver' => $countryCode . $doctor->mobile_number,
                    'message_preview' => $message,
                ]);
                // \Mail::to($doctor->identity)->send(new \App\Mail\JitsiMeetingLinkPatient($meeting_link));
                \Mail::to($doctor->identity)->send(new \App\Mail\JitsiMeetingLinkDoctor($appointment, $doctor, $user, $meeting_link_doctor));

            }

            else if($status == 'Failure' && $order_id)
            {
                Appointments::where('order_id', $order_id)->update(['payment_status' => Constants::appointmentPaymentFailureStatus]);
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

            if($responseData['merchant_param1'] == 'hnhpayment'){
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

            else if($responseData['merchant_param1'] == Constants::CCAvenueAIVitalScanPaymentType){
                $ai_vital_misa = AIVitalScanMisa::where('order_id', $responseData['order_id'])->first();
                if($ai_vital_misa){
                   
                    if($status == 'Aborted'){
                        $ai_vital_misa->payment_status = Constants::AIVitalsPaymentAbortedStatus;
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

        if($responseData['merchant_param1'] == 'hnhpayment'){
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

}
