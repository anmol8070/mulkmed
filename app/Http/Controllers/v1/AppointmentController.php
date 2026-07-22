<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Models\AddedPatients;
use App\Models\AppointmentDocs;
use App\Models\Appointments;
use App\Models\Constants;
use App\Models\Coupons;
use App\Models\UserCoupons;
use App\Models\DoctorEarningHistory;
use App\Models\DoctorPayoutHistory;
use App\Models\DoctorPlans;
use App\Models\DoctorReviews;
use App\Models\Doctors;
use App\Models\DoctorWalletStatements;
use App\Models\GlobalFunction;
use App\Models\GlobalSettings;
use App\Models\PlatformData;
use App\Models\PlatformEarningHistory;
use App\Models\PatientEmrReport;
use App\Models\Prescriptions;
use App\Models\ScheduledReminders;
use App\Models\Users;
use App\Models\UserPlan;
use App\Models\JitsiMeeting;
use App\Models\AI_Vital;
use App\Models\AIVitalScanMisa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Helpers\Crypto;
use App\Helpers\EmailHelpers;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use PDF;

class AppointmentController extends Controller
{
    //
    function scheduleAppointmentReminders(Request $request)
    {
        $rules = [
            'user_id' => 'required',
            'appointment_id' => 'required',
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

        $appointment = Appointments::where('id', $request->appointment_id)
        ->Where('payment_status',1)
        ->with(['user', 'patient', 'doctor', 'documents', 'rating', 'prescription'])
        ->first();
        if ($appointment == null) {
            return GlobalFunction::sendSimpleResponse(false, 'Appointment does not exists!');
        }

        $item = ScheduledReminders::where('appointment_id', $appointment->id)->first();
        if($item != null){
            return GlobalFunction::sendSimpleResponse(false, 'This appointment has scheduled notifications already!');
        }

        foreach($request->scheduled_at as $schedule){
            $data = explode('/',$schedule);
            $item = new ScheduledReminders();
            $item->user_id = $user->id;
            $item->appointment_id = $appointment->id;
            $item->scheduled_at = $data[0];
            $item->after_time_string = $data[1];
            $item->save();
        }

        return GlobalFunction::sendSimpleResponse(true, 'Reminders scheduled successfully!');

    }

    function addAppointment(Request $request)
    {
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
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }

        $settings = GlobalSettings::first();

        $user = Users::find($request->user_id);
        if ($user == null) {
            return response()->json(['status' => false, 'message' => "User doesn't exists!"]);
        }

        $appointmentCount = Appointments::where('user_id', $user->id)
            ->where('status', Constants::orderPlacedPending)
            ->orWhere('status', Constants::orderAccepted)
            ->count();
        if ($appointmentCount >= $settings->max_order_at_once) {
            return response()->json(['status' => false, 'message' => "Maximum, at a time order limit, reached!"]);
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

        if ($user->wallet < $request->payable_amount) {
            return GlobalFunction::sendSimpleResponse(false, 'Insufficient balance in wallet');
        }

        $appointment = new Appointments();
        if ($request->has('patient_id')) {
            $patient = AddedPatients::find($request->patient_id);
            if ($patient == null) {
                return response()->json(['status' => false, 'message' => "Patient doesn't exists!"]);
            }
            $appointment->patient_id = $request->patient_id;
        }

        $appointment->appointment_number = GlobalFunction::generateAppointmentNumber();
        $appointment->completion_otp = rand(1000, 9999);
        $appointment->user_id = $request->user_id;
        $appointment->doctor_id = $request->doctor_id;
        $appointment->date = $request->date;
        $appointment->time = $request->time;
        $appointment->type = $request->type;

        $appointment->problem = GlobalFunction::cleanString($request->problem);
        $appointment->order_summary = $request->order_summary;
        $appointment->is_coupon_applied = $request->is_coupon_applied;

        $appointment->service_amount = $request->service_amount;
        $appointment->discount_amount = $request->discount_amount;
        $appointment->subtotal = $request->subtotal;
        $appointment->total_tax_amount = $request->total_tax_amount;
        $appointment->payable_amount = $request->payable_amount;

        if ($request->is_coupon_applied == 1) {
            $appointment->coupon_title = $request->coupon_title;
            // add coupon to used coupon
            $discounts = explode(',', $user->coupons_used);
            array_push($discounts, $request->coupon_id);
            $user->coupons_used = implode(',', $discounts);
        }

        $appointment->save();
        if ($request->has('documents')) {
            foreach ($request->documents as $document) {
                $docs = new AppointmentDocs();
                $docs->appointment_id = $appointment->id;
                $docs->image = GlobalFunction::saveFileAndGivePath($document);
                $docs->save();
            }
        }
        // Deducting Money From Wallet
        $user->wallet = $user->wallet - $request->payable_amount;
        $user->save();

        // Send Push to user
        $title = "Appointment :" . $appointment->appointment_number;
        $message = "Appointment has been placed successfully!";
        $notifyData = [
            'type'=> Constants::notifyAppointment.'',
            'id'=> $appointment->id.''
        ];
        GlobalFunction::sendPushToUser($title, $message, $user, $notifyData);

        // Send push to doctor
        $title = "New Appointment Request Received";
        $message = "Review the details and accept.";
        $notifyData = [
            'type'=> Constants::notifyAppointment.'',
            'id'=> $appointment->id.''
        ];
        GlobalFunction::sendPushToDoctor($title, $message, $doctor,$notifyData);

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

    function addAppointmentDocs(Request $request)
    {
        try {
          $rules = [
                'appointment_id' => 'required',  
                'documents'      => 'required|array',
                'documents.*'    => 'file|mimes:jpg,jpeg,png,pdf|max:5120',
            ];

            $messages = [
                'documents.*.max'   => 'Each document must not be larger than 5 MB.',
                'documents.*.mimes' => 'Only JPG, JPEG, PNG, and PDF files are allowed.',
                'documents.required'=> 'Please upload at least one document.',
            ];

            $validator = Validator::make($request->all(), $rules, $messages);

            if ($validator->fails()) {
                $messages = $validator->errors()->all();
                $msg = $messages[0];
                return response()->json([
                    'status'  => false,
                    'message' => $msg,
                ]);
            }
            if ($request->has('documents')) {
                foreach ($request->documents as $document) {
                    $docs = new AppointmentDocs();
                    $docs->appointment_id = $request->appointment_id;
                    $docs->image = GlobalFunction::saveFileAndGivePath($document);
                    $docs->save();
                }
            }
            return response()->json(['status' => true, 'message' => 'Documents Saved successfully']);        
        
        } catch (\Throwable $th) {
            Log::error('INR→AED conversion error', [
                'error' => $th->getMessage(),
                'trace' => $th->getTraceAsString()
            ]);

            return ['status' => false, 'message' => $th->getMessage()];
        }
    }

    function deleteAppointmentDocs(Request $request)
    {
        try {
            $rules = [
                'document_id' => 'required',
            ];
            $validator = Validator::make($request->all(), $rules);
            if ($validator->fails()) {
                $messages = $validator->errors()->all();
                $msg = $messages[0];
                return response()->json(['status' => false, 'message' => $msg]);
            }
            
            $appointment_docs = AppointmentDocs::find($request->document_id);

            if($appointment_docs){
                $appointment_docs->is_deleted = 1;
                $appointment_docs->save();
            }

            return response()->json(['status' => true, 'message' => 'Documents Deleted successfully']);        
        
        } catch (\Throwable $th) {
            Log::error('INR→AED conversion error', [
                'error' => $th->getMessage(),
                'trace' => $th->getTraceAsString()
            ]);

            return ['status' => false, 'message' => $th->getMessage()];
        }
    }

    public function initiatePayment(Request $request)
    {
        try {
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


            $doctor_plan = null;
            if($request->has('plan_id') && $request->plan_id != 0){
                $doctor_plan = DoctorPlans::where('id', $request->plan_id)->where('is_deleted', 0)->first();
                if($doctor_plan == null){
                    return response()->json(['status' => false, 'message' => "this plan is invalid!"]);
                }
            }
           
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
                $user_plan->plan_text = $doctor_plan->plan_text;
                // $user_plan->valid_from = Carbon::today();
                // $user_plan->valid_to = Carbon::today()->addDays($doctor_plan->number_of_days);
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
                "redirect_url" => env('CCAVENUE_REDIRECT_URL'),
                "cancel_url" => env('CCAVENUE_CANCEL_URL'),
                "language" => "EN",
                "billing_name" => $request->name,
                "billing_email" => $request->email,
                "billing_tel" => $request->phone,
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
                // 'final_doctor_charge' => $final_doctor_charge
            ]);
        }

        catch (\Throwable $e) {
            // Log unexpected errors
            Log::error('INR→AED conversion error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return ['status' => false, 'message' => $e->getMessage()];
        }
        
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

    function jitsiCompleteMeeting(Request $request)
    {
        return DB::transaction(function () use ($request) {

            $JitsiMeeting = JitsiMeeting::where('room', $request->room_id)->first();

            if($JitsiMeeting == null){
                return view('meetings.meeting', ['message' => "Meeting is invalid"]);
            }

            // Lock appointment row FOR UPDATE to prevent race condition
            $appointment = Appointments::where('id', $JitsiMeeting->appointment_id)
                ->lockForUpdate()
                ->first();

            if($appointment->status != Constants::orderPlacedPending){
                return response()->json(['status' => false, 'message' => 'Appointment is already updated']);
            }

            if($appointment->user_plan_id != 0){
                $userPlan = UserPlan::where('id', $appointment->user_plan_id)
                    ->lockForUpdate()
                    ->first();

                if($userPlan->status == Constants::statusUserPlanInactive){
                    return view('meetings.meeting', ['message' => "Plan is inactive"]);
                }

                $userPlan->consultations_used += 1;
                $userPlan->save();

                if($userPlan->consultations_used >= $userPlan->consultations_total)
                {
                    $userPlan->status = Constants::statusUserPlanInactive;
                    $userPlan->save();
                }
                    $appointment->status = Constants::orderAccepted;
                    $appointment->save();

                    $request->merge([
                        'doctor_id' => $JitsiMeeting->doctor_id,
                        'appointment_id' => $JitsiMeeting->appointment_id,
                        // 'completion_otp' => $appointment->completion_otp,
                        'diagnosed_with' => $appointment->problem ?? "NA"
                    ]);
                    
                    return $this->completeAppointment($request);
            }

            $appointment->status = Constants::orderAccepted;
            $appointment->save();

            $request->merge([
                'doctor_id' => $JitsiMeeting->doctor_id,
                'appointment_id' => $JitsiMeeting->appointment_id,
                // 'completion_otp' => $appointment->completion_otp,
                'diagnosed_with' => $appointment->problem ?? "NA"
            ]);

            return $this->completeAppointment($request);

        }); // end transaction
    }

    function jitsiJoinMeeting(Request $request, $id)
    {
        $meeting = JitsiMeeting::where('room',$id)->first();

        // $start_time = Carbon::parse($meeting->start_time, 'Asia/Dubai');
        // $end_time = Carbon::parse($meeting->end_time, 'Asia/Dubai');
        // $now = Carbon::now()->setTimezone('Asia/Dubai');

        $start_time = Carbon::parse($meeting->start_time, 'Asia/Kolkata');
        $end_time = Carbon::parse($meeting->end_time, 'Asia/Kolkata');
        $now = Carbon::now()->setTimezone('Asia/Kolkata');

        if ($now->lt($start_time)) {
            return view('meetings.not_started', ['start' => $start_time]);
        }
    
        if ($now->gt($end_time)) {
            return view('meetings.ended', ['end' => $end_time]);
        }

        
        // $appointment = Appointments::find($meeting->appointment_id);
        $decoded = json_decode(Crypt::decryptString($request->query('token')), true);

        if ($decoded['role'] === 'doctor') {
        $meeting->doctor_joined = true;
        } elseif ($decoded['role'] === 'patient') {
            $meeting->user_joined = true;
        }
        $meeting->save();

        // if($appointment && $appointment->status == Constants::orderCompleted){
        //     return view('meetings.meeting_message', ['message' => "Appointment is Already Completed"]);
        // }

        return view('meetings.meeting', ['room' => $meeting->room, 'end_time' => $end_time]);
    
        // return redirect()->away($meeting->link); 
    }

    function fetchAcceptedPendingAppointmentsOfDoctorByDate(Request $request)
    {
        $rules = [
            'doctor_id' => 'required',
            'date' => 'required',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }
        $doctor = Doctors::where('id', $request->doctor_id)->first();
        if ($doctor == null) {
            return response()->json(['status' => false, 'message' => "Doctor doesn't exists!"]);
        }
        $appointments = Appointments::where('doctor_id', $request->doctor_id)
            ->where('date', $request->date)
            ->Where('payment_status',1)
            ->whereIn('status', [Constants::orderPlacedPending, Constants::orderAccepted])
            ->with(['user'])
            ->get();

        return GlobalFunction::sendDataResponse(true, 'Bookings fetched successfully', $appointments);
    }

    function viewAppointment($id)
    {
        $item = Appointments::where('id', $id)
            ->with(['user', 'patient', 'doctor', 'documents', 'prescription', 'rating'])
            ->Where('payment_status',1)
            ->first();

        $settings = GlobalSettings::first();

        // Generating Rating Bar
        $starDisabled = '<i class="fas fa-star starDisabled"></i>';
        $starActive = '<i class="fas fa-star starActive"></i>';

        $ratingBar = '';
        if ($item->rating != null) {
            for ($i = 0; $i < 5; $i++) {
                if ($item->rating->rating > $i) {
                    $ratingBar = $ratingBar . $starActive;
                } else {
                    $ratingBar = $ratingBar . $starDisabled;
                }
            }
        }
        // Having json object of appointment summary
        $orderSummary = json_decode($item->order_summary, true);
        $prescription = null;
        if ($item->prescription != null) {
            $prescription = json_decode($item->prescription->medicine, true);
        }

        return view('viewAppointment', [
            'appointment' => $item,
            'ratingBar' => $ratingBar,
            'settings' => $settings,
            'orderSummary' => $orderSummary,
            'prescription' => $prescription,
        ]);
    }

    function fetchDeclinedAppointmentsList(Request $request)
    {
        $totalData =  Appointments::where('status', Constants::orderDeclined)->Where('payment_status',1)->count();
        $rows = Appointments::where('status', Constants::orderDeclined)->Where('payment_status',1)->orderBy('id', 'DESC')->get();
        $settings = GlobalSettings::first();

        $result = $rows;

        $columns = array(
            0 => 'id',
            1 => 'fullname',
            2 => 'identity',
            3 => 'username',
        );

        $limit = $request->input('length');
        $start = $request->input('start');
        $order = $columns[$request->input('order.0.column')];
        $dir = $request->input('order.0.dir');

        $totalFiltered = $totalData;
        if (empty($request->input('search.value'))) {
            $result = Appointments::where('status', Constants::orderDeclined)->offset($start)
                ->limit($limit)
                ->Where('payment_status',1)
                ->orderBy($order, $dir)
                ->get();
        } else {
            $search = $request->input('search.value');
            $result =  Appointments::where('status', Constants::orderDeclined)->where(function ($query) use ($search) {
                $query->Where('appointment_number', 'LIKE', "%{$search}%")
                    ->orWhere('payable_amount', 'LIKE', "%{$search}%");
            })->offset($start)
                ->limit($limit)
                ->Where('payment_status',1)
                ->orderBy($order, $dir)
                ->get();
            $totalFiltered = Appointments::where('status', Constants::orderDeclined)->where(function ($query) use ($search) {
                $query->Where('appointment_number', 'LIKE', "%{$search}%")
                    ->orWhere('payable_amount', 'LIKE', "%{$search}%");
            })->Where('payment_status',1)->count();
        }
        $data = array();
        foreach ($result as $item) {


            $doctor = "";
            if ($item->doctor != null) {
                $doctor = '<a href="' . route('viewDoctorProfile', $item->doctor->id) . '"><span class="badge bg-primary text-white">' . $item->doctor->name . '</span></a>';
            }
            $user = "";
            if ($item->user != null) {
                $user = '<a href="' . route('viewUserProfile', $item->user->id) . '"><span class="badge bg-primary text-white">' . $item->user->fullname . '</span></a>';
            }

            $view = '<a href="' . route('viewAppointment', $item->id) . '" class="mr-2 btn btn-info text-white " rel=' . $item->id . ' >' . __("View") . '</a>';

            $status = GlobalFunction::returnAppointmentStatus($item->status);

            $action = $view;

            $dateTime =  $item->date . '<br>' . GlobalFunction::formateTimeString($item->time);
            $payableAmount = $settings->currency . $item->payable_amount;

            $data[] = array(
                $item->appointment_number,
                $user,
                $doctor,
                $status,
                $dateTime,
                $settings->currency . $item->service_amount,
                $settings->currency . $item->discount_amount,
                $settings->currency . $item->subtotal,
                $settings->currency . $item->total_tax_amount,
                $payableAmount,
                $item->created_at->format('d M, Y'),
                $action,
            );
        }
        $json_data = array(
            "draw"            => intval($request->input('draw')),
            "recordsTotal"    => intval($totalData),
            "recordsFiltered" => $totalFiltered,
            "data"            => $data
        );
        echo json_encode($json_data);
        exit();
    }

    function fetchCancelledAppointmentsList(Request $request)
    {
        $totalData =  Appointments::where('status', Constants::orderCancelled)->Where('payment_status',1)->count();
        $rows = Appointments::where('status', Constants::orderCancelled)->Where('payment_status',1)->orderBy('id', 'DESC')->get();
        $settings = GlobalSettings::first();

        $result = $rows;

        $columns = array(
            0 => 'id',
            1 => 'fullname',
            2 => 'identity',
            3 => 'username',
        );

        $limit = $request->input('length');
        $start = $request->input('start');
        $order = $columns[$request->input('order.0.column')];
        $dir = $request->input('order.0.dir');

        $totalFiltered = $totalData;
        if (empty($request->input('search.value'))) {
            $result = Appointments::where('status', Constants::orderCancelled)->offset($start)
                ->limit($limit)
                ->Where('payment_status',1)
                ->orderBy($order, $dir)
                ->get();
        } else {
            $search = $request->input('search.value');
            $result =  Appointments::where('status', Constants::orderCancelled)->where(function ($query) use ($search) {
                $query->Where('appointment_number', 'LIKE', "%{$search}%")
                    ->orWhere('payable_amount', 'LIKE', "%{$search}%");
            })->offset($start)
                ->limit($limit)
                ->Where('payment_status',1)
                ->orderBy($order, $dir)
                ->get();
            $totalFiltered = Appointments::where('status', Constants::orderCancelled)->where(function ($query) use ($search) {
                $query->Where('appointment_number', 'LIKE', "%{$search}%")
                    ->orWhere('payable_amount', 'LIKE', "%{$search}%");
            })->Where('payment_status',1)->count();
        }
        $data = array();
        foreach ($result as $item) {


            $doctor = "";
            if ($item->doctor != null) {
                $doctor = '<a href="' . route('viewDoctorProfile', $item->doctor->id) . '"><span class="badge bg-primary text-white">' . $item->doctor->name . '</span></a>';
            }
            $user = "";
            if ($item->user != null) {
                $user = '<a href="' . route('viewUserProfile', $item->user->id) . '"><span class="badge bg-primary text-white">' . $item->user->fullname . '</span></a>';
            }

            $view = '<a href="' . route('viewAppointment', $item->id) . '" class="mr-2 btn btn-info text-white " rel=' . $item->id . ' >' . __("View") . '</a>';

            $status = GlobalFunction::returnAppointmentStatus($item->status);

            $action = $view;

            $dateTime =  $item->date . '<br>' . GlobalFunction::formateTimeString($item->time);
            $payableAmount = $settings->currency . $item->payable_amount;

            $data[] = array(
                $item->appointment_number,
                $user,
                $doctor,
                $status,
                $dateTime,
                $settings->currency . $item->service_amount,
                $settings->currency . $item->discount_amount,
                $settings->currency . $item->subtotal,
                $settings->currency . $item->total_tax_amount,
                $payableAmount,
                $item->created_at->format('d M, Y'),
                $action,
            );
        }
        $json_data = array(
            "draw"            => intval($request->input('draw')),
            "recordsTotal"    => intval($totalData),
            "recordsFiltered" => $totalFiltered,
            "data"            => $data
        );
        echo json_encode($json_data);
        exit();
    }

    function fetchCompletedAppointmentsList(Request $request)
    {
        $totalData =  Appointments::where('status', Constants::orderCompleted)->Where('payment_status',1)->count();
        $rows = Appointments::where('status', Constants::orderCompleted)->Where('payment_status',1)->orderBy('id', 'DESC')->get();
        $settings = GlobalSettings::first();

        $result = $rows;

        $columns = array(
            0 => 'id',
            1 => 'fullname',
            2 => 'identity',
            3 => 'username',
        );

        $limit = $request->input('length');
        $start = $request->input('start');
        $order = $columns[$request->input('order.0.column')];
        $dir = $request->input('order.0.dir');

        $totalFiltered = $totalData;
        if (empty($request->input('search.value'))) {
            $result = Appointments::where('status', Constants::orderCompleted)->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->Where('payment_status',1)
                ->get();
        } else {
            $search = $request->input('search.value');
            $result =  Appointments::where('status', Constants::orderCompleted)->where(function ($query) use ($search) {
                $query->Where('appointment_number', 'LIKE', "%{$search}%")
                    ->orWhere('payable_amount', 'LIKE', "%{$search}%");
            })->offset($start)
                ->limit($limit)
                ->Where('payment_status',1)
                ->orderBy($order, $dir)
                ->get();
            $totalFiltered = Appointments::where('status', Constants::orderCompleted)->where(function ($query) use ($search) {
                $query->Where('appointment_number', 'LIKE', "%{$search}%")
                    ->orWhere('payable_amount', 'LIKE', "%{$search}%");
            })->Where('payment_status',1)->count();
        }
        $data = array();
        foreach ($result as $item) {


            $doctor = "";
            if ($item->doctor != null) {
                $doctor = '<a href="' . route('viewDoctorProfile', $item->doctor->id) . '"><span class="badge bg-primary text-white">' . $item->doctor->name . '</span></a>';
            }
            $user = "";
            if ($item->user != null) {
                $user = '<a href="' . route('viewUserProfile', $item->user->id) . '"><span class="badge bg-primary text-white">' . $item->user->fullname . '</span></a>';
            }

            $view = '<a href="' . route('viewAppointment', $item->id) . '" class="mr-2 btn btn-info text-white " rel=' . $item->id . ' >' . __("View") . '</a>';

            $status = GlobalFunction::returnAppointmentStatus($item->status);

            $action = $view;

            $dateTime =  $item->date . '<br>' . GlobalFunction::formateTimeString($item->time);
            $payableAmount = $settings->currency . $item->payable_amount;

            $data[] = array(
                $item->appointment_number,
                $user,
                $doctor,
                $status,
                $dateTime,
                $settings->currency . $item->service_amount,
                $settings->currency . $item->discount_amount,
                $settings->currency . $item->subtotal,
                $settings->currency . $item->total_tax_amount,
                $payableAmount,
                $item->created_at->format('d M, Y'),
                $action,
            );
        }
        $json_data = array(
            "draw"            => intval($request->input('draw')),
            "recordsTotal"    => intval($totalData),
            "recordsFiltered" => $totalFiltered,
            "data"            => $data
        );
        echo json_encode($json_data);
        exit();
    }

    function fetchAcceptedAppointmentsList(Request $request)
    {
        $totalData =  Appointments::where('status', Constants::orderAccepted)->Where('payment_status',1)->count();
        $rows = Appointments::where('status', Constants::orderAccepted)->Where('payment_status',1)->orderBy('id', 'DESC')->get();
        $settings = GlobalSettings::first();

        $result = $rows;

        $columns = array(
            0 => 'id',
            1 => 'fullname',
            2 => 'identity',
            3 => 'username',
        );

        $limit = $request->input('length');
        $start = $request->input('start');
        $order = $columns[$request->input('order.0.column')];
        $dir = $request->input('order.0.dir');

        $totalFiltered = $totalData;
        if (empty($request->input('search.value'))) {
            $result = Appointments::where('status', Constants::orderAccepted)->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->Where('payment_status',1)
                ->get();
        } else {
            $search = $request->input('search.value');
            $result =  Appointments::where('status', Constants::orderAccepted)->where(function ($query) use ($search) {
                $query->Where('appointment_number', 'LIKE', "%{$search}%")
                    ->orWhere('payable_amount', 'LIKE', "%{$search}%");
            })->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->Where('payment_status',1)
                ->get();
            $totalFiltered = Appointments::where('status', Constants::orderAccepted)->where(function ($query) use ($search) {
                $query->Where('appointment_number', 'LIKE', "%{$search}%")
                    ->orWhere('payable_amount', 'LIKE', "%{$search}%");
            })->Where('payment_status',1)->count();
        }
        $data = array();
        foreach ($result as $item) {


            $doctor = "";
            if ($item->doctor != null) {
                $doctor = '<a href="' . route('viewDoctorProfile', $item->doctor->id) . '"><span class="badge bg-primary text-white">' . $item->doctor->name . '</span></a>';
            }
            $user = "";
            if ($item->user != null) {
                $user = '<a href="' . route('viewUserProfile', $item->user->id) . '"><span class="badge bg-primary text-white">' . $item->user->fullname . '</span></a>';
            }

            $view = '<a href="' . route('viewAppointment', $item->id) . '" class="mr-2 btn btn-info text-white " rel=' . $item->id . ' >' . __("View") . '</a>';

            $status = GlobalFunction::returnAppointmentStatus($item->status);

            $action = $view;

            $dateTime =  $item->date . '<br>' . GlobalFunction::formateTimeString($item->time);
            $payableAmount = $settings->currency . $item->payable_amount;

            $data[] = array(
                $item->appointment_number,
                $user,
                $doctor,
                $status,
                $dateTime,
                $settings->currency . $item->service_amount,
                $settings->currency . $item->discount_amount,
                $settings->currency . $item->subtotal,
                $settings->currency . $item->total_tax_amount,
                $payableAmount,
                $item->created_at->format('d M, Y'),
                $action,
            );
        }
        $json_data = array(
            "draw"            => intval($request->input('draw')),
            "recordsTotal"    => intval($totalData),
            "recordsFiltered" => $totalFiltered,
            "data"            => $data
        );
        echo json_encode($json_data);
        exit();
    }

    function fetchPendingAppointmentsList(Request $request)
    {
        $totalData =  Appointments::where('status', Constants::orderPlacedPending)->Where('payment_status',1)->count();
        $rows = Appointments::where('status', Constants::orderPlacedPending)->Where('payment_status',1)->orderBy('id', 'DESC')->get();
        $settings = GlobalSettings::first();

        $result = $rows;

        $columns = array(
            0 => 'id',
            1 => 'fullname',
            2 => 'identity',
            3 => 'username',
        );

        $limit = $request->input('length');
        $start = $request->input('start');
        $order = $columns[$request->input('order.0.column')];
        $dir = $request->input('order.0.dir');

        $totalFiltered = $totalData;
        if (empty($request->input('search.value'))) {
            $result = Appointments::where('status', Constants::orderPlacedPending)->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->Where('payment_status',1)
                ->get();
        } else {
            $search = $request->input('search.value');
            $result =  Appointments::where('status', Constants::orderPlacedPending)->where(function ($query) use ($search) {
                $query->Where('appointment_number', 'LIKE', "%{$search}%")
                    ->orWhere('payable_amount', 'LIKE', "%{$search}%");
            })->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->Where('payment_status',1)
                ->get();
            $totalFiltered = Appointments::where('status', Constants::orderPlacedPending)->where(function ($query) use ($search) {
                $query->Where('appointment_number', 'LIKE', "%{$search}%")
                    ->orWhere('payable_amount', 'LIKE', "%{$search}%");
            })->Where('payment_status',1)->count();
        }
        $data = array();
        foreach ($result as $item) {


            $doctor = "";
            if ($item->doctor != null) {
                $doctor = '<a href="' . route('viewDoctorProfile', $item->doctor->id) . '"><span class="badge bg-primary text-white">' . $item->doctor->name . '</span></a>';
            }
            $user = "";
            if ($item->user != null) {
                $user = '<a href="' . route('viewUserProfile', $item->user->id) . '"><span class="badge bg-primary text-white">' . $item->user->fullname . '</span></a>';
            }

            $view = '<a href="' . route('viewAppointment', $item->id) . '" class="mr-2 btn btn-info text-white " rel=' . $item->id . ' >' . __("View") . '</a>';

            $status = GlobalFunction::returnAppointmentStatus($item->status);

            $action = $view;

            $dateTime =  $item->date . '<br>' . GlobalFunction::formateTimeString($item->time);
            $payableAmount = $settings->currency . $item->payable_amount;

            $data[] = array(
                $item->appointment_number,
                $user,
                $doctor,
                $status,
                $dateTime,
                $settings->currency . $item->service_amount,
                $settings->currency . $item->discount_amount,
                $settings->currency . $item->subtotal,
                $settings->currency . $item->total_tax_amount,
                $payableAmount,
                $item->created_at->format('d M, Y'),
                $action,
            );
        }
        $json_data = array(
            "draw"            => intval($request->input('draw')),
            "recordsTotal"    => intval($totalData),
            "recordsFiltered" => $totalFiltered,
            "data"            => $data
        );
        echo json_encode($json_data);
        exit();
    }

    function fetchAllAppointmentsList(Request $request)
    {
        $totalData =  Appointments::count();
        $rows = Appointments::orderBy('id', 'DESC')->Where('payment_status',1)->get();
        $settings = GlobalSettings::first();

        $result = $rows;

        $columns = array(
            0 => 'id',
            1 => 'fullname',
            2 => 'identity',
            3 => 'username',
        );

        $limit = $request->input('length');
        $start = $request->input('start');
        $order = $columns[$request->input('order.0.column')];
        $dir = $request->input('order.0.dir');

        $totalFiltered = $totalData;
        if (empty($request->input('search.value'))) {
            $result = Appointments::offset($start)
                ->limit($limit)
                ->Where('payment_status',1)
                ->orderBy($order, $dir)
                ->get();
        } else {
            $search = $request->input('search.value');
            $result =  Appointments::where(function ($query) use ($search) {
                $query->Where('appointment_number', 'LIKE', "%{$search}%")
                    ->orWhere('payable_amount', 'LIKE', "%{$search}%");
            })->offset($start)
                ->limit($limit)
                ->Where('payment_status',1)
                ->orderBy($order, $dir)
                ->get();
            $totalFiltered = Appointments::where(function ($query) use ($search) {
                $query->Where('appointment_number', 'LIKE', "%{$search}%")
                    ->orWhere('payable_amount', 'LIKE', "%{$search}%");
            })->Where('payment_status',1)->count();
        }
        $data = array();
        foreach ($result as $item) {

            $doctor = "";
            if ($item->doctor != null) {
                $doctor = '<a href="' . route('viewDoctorProfile', $item->doctor->id) . '"><span class="badge bg-primary text-white">' . $item->doctor->name . '</span></a>';
            }
            $user = "";
            if ($item->user != null) {
                $user = '<a href="' . route('viewUserProfile', $item->user->id) . '"><span class="badge bg-primary text-white">' . $item->user->fullname . '</span></a>';
            }

            $view = '<a href="' . route('viewAppointment', $item->id) . '" class="mr-2 btn btn-info text-white " rel=' . $item->id . ' >' . __("View") . '</a>';

            $status = GlobalFunction::returnAppointmentStatus($item->status);

            $action = $view;

            $dateTime =  $item->date . '<br>' . GlobalFunction::formateTimeString($item->time);
            $payableAmount = $settings->currency . $item->payable_amount;

            $data[] = array(
                $item->appointment_number,
                $user,
                $doctor,
                $status,
                $dateTime,
                $settings->currency . $item->service_amount,
                $settings->currency . $item->discount_amount,
                $settings->currency . $item->subtotal,
                $settings->currency . $item->total_tax_amount,
                $payableAmount,
                $item->created_at->format('d M, Y'),
                $action,
            );
        }
        $json_data = array(
            "draw"            => intval($request->input('draw')),
            "recordsTotal"    => intval($totalData),
            "recordsFiltered" => $totalFiltered,
            "data"            => $data
        );
        echo json_encode($json_data);
        exit();
    }

    function appointments(Request $request)
    {
        return view('appointments');
    }

    function fetchMyAppointments(Request $request)
    {
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


        $result = Appointments::with(['user', 'patient', 'doctor', 'documents', 'prescription', 'rating', 'appointmentMeeting','emrdocuments'])
            ->Where('user_id', $request->user_id)
            ->Where('payment_status',1)
            ->orderBy('id', 'DESC')
            ->get();

        $appointmentIds = $result->pluck('id')->all();
        $latestEmrReportsByAppointment = collect();
        if (!empty($appointmentIds)) {
        $latestEmrReportsByAppointment = PatientEmrReport::query()
                ->whereIn('appointment_id', $appointmentIds)
            ->orderByDesc('id')
                    ->get(['appointment_id', 'is_finalized', 'dhpo_prescription_document', 'dhpo_prescriptions'])
            ->unique('appointment_id')
            ->keyBy('appointment_id');
        }

        $normalizeDhpoDocuments = function ($rawDhpoDocuments): array {
            $dhpoPrescriptionDocument = [];
            if (is_array($rawDhpoDocuments)) {
                $dhpoPrescriptionDocument = $rawDhpoDocuments;
            } elseif (is_string($rawDhpoDocuments)) {
                $decodedDhpoDocuments = json_decode($rawDhpoDocuments, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decodedDhpoDocuments)) {
                    $dhpoPrescriptionDocument = $decodedDhpoDocuments;
                } elseif (trim($rawDhpoDocuments) !== '') {
                    $dhpoPrescriptionDocument = [trim($rawDhpoDocuments)];
                }
            }

            return collect($dhpoPrescriptionDocument)
                ->map(fn($path) => trim((string) $path))
                ->filter(fn($path) => $path !== '')
                ->values()
                ->all();
        };

            $hasDhpoPrescriptions = function ($rawDhpoPrescriptions): bool {
                if (is_array($rawDhpoPrescriptions)) {
                    $items = $rawDhpoPrescriptions;
                } elseif (is_string($rawDhpoPrescriptions)) {
                    $decoded = json_decode($rawDhpoPrescriptions, true);
                    $items = (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : [];
                } else {
                    $items = [];
                }

                foreach ($items as $item) {
                    if (!is_array($item)) {
                        continue;
                    }
                    foreach ($item as $value) {
                        if ($value !== null && trim((string) $value) !== '') {
                            return true;
                        }
                    }
                }

                return false;
            };

        foreach ($result as $appointment) {
            $appointmentId = (int) $appointment->id;
            $latestEmrReport = $latestEmrReportsByAppointment->get($appointmentId);
            $hasEmrReport = (bool) ($latestEmrReport?->is_finalized ?? false);
                $hasPrescriptionData = $hasDhpoPrescriptions($latestEmrReport?->dhpo_prescriptions ?? null);
                // $emrReportPdfUrl = ($hasEmrReport && $hasPrescriptionData)
                // ? url("/api/v1/emr/download-pdf?appointment_id={$appointmentId}")
                // : null;
                // $prescriptionPdfUrl = ($hasEmrReport && $hasPrescriptionData)
                // ? url("/api/v1/emr/download-prescription-pdf?appointment_id={$appointmentId}")
                // : null;
            $dhpoPrescriptionDocument = $normalizeDhpoDocuments(
                $latestEmrReport?->dhpo_prescription_document ?? null
            );

            //added in the 30-4-2026
            // ✅ EMR should depend ONLY on finalized report
            $emrReportPdfUrl = $hasEmrReport
                ? url("/api/v1/emr/download-pdf?appointment_id={$appointmentId}")
                : null;

            // ✅ Prescription should NOT break if empty (as per your requirement)
            $prescriptionPdfUrl = $hasEmrReport
                ? url("/api/v1/emr/download-prescription-pdf?appointment_id={$appointmentId}")
                : null;


            if ($appointment->emrdocuments && $appointment->emrdocuments->isNotEmpty()) {
                foreach ($appointment->emrdocuments as $emrDocument) {
                    $emrDocument->emr_report_pdf = $emrReportPdfUrl;
                    $emrDocument->prescription_pdf = $prescriptionPdfUrl;
                    $emrDocument->dhpo_prescription_document = $dhpoPrescriptionDocument;
                }
                $appointment->setRelation('emrdocuments', $appointment->emrdocuments);
            } else {
                $appointment->setRelation('emrdocuments', collect([[
                    'id' => null,
                    'appointment_id' => $appointmentId,
                    'image' => null,
                    'is_deleted' => 0,
                    'created_at' => null,
                    'updated_at' => null,
                    'emr_report_pdf' => $emrReportPdfUrl,
                    'prescription_pdf' => $prescriptionPdfUrl,
                    'dhpo_prescription_document' => $dhpoPrescriptionDocument,
                ]]));
            }

            $appointment->previous_appointments =
                Appointments::with(['user', 'patient', 'doctor', 'documents', 'prescription', 'rating'])
                ->Where('user_id', $request->user_id)
                ->Where('doctor_id', $appointment->doctor_id)
                ->Where('payment_status',1)
                ->WhereNotIn('id', [$appointment->id])
                ->WhereIn('status', [Constants::orderCompleted, Constants::orderCancelled, Constants::orderDeclined])
                ->orderByDesc('id')
                ->get();

            $jitsiMeeting = DB::table('jitsi_meetings')
                    ->where('appointment_id', $appointment->id)
                    ->first();

            $vital_scan = AI_Vital::where('user_id',$request->user_id)->where('appointment_id',$appointment->id)->get();
            $isVitalScanDone = 0;

            if(count($vital_scan)){
                $isVitalScanDone = 1;
            }

            $appointment->is_vital_scan_done = $isVitalScanDone;

            $appointment->jitsi_link = null;
            if ($jitsiMeeting && !empty($jitsiMeeting->room)) {
            $appointment->jitsi_link = url("/api/v1/join_jitsi_meeting?user_id={$appointment->user_id}&room={$jitsiMeeting->room}");
            }
            $appointment->ai_vital_link = null;
            $ai_vital = AI_Vital::where('user_id', $appointment->user_id)
                ->where('appointment_id', $appointment->id)
                ->orderBy('id', 'desc')
                ->first();
            if ($ai_vital) {
                $appointment->ai_vital_link = $ai_vital->pdf_file ?? null;
            }

            $appointment->is_feedback_submitted = DoctorReviews::where('appointment_id', $appointment->id)
            ->where('doctor_id', $appointment->doctor_id)
            ->where('user_id', $request->user_id)
            ->exists();
        }

        return GlobalFunction::sendDataResponse(true, 'data fetched successfully', $result);
    }

    function aiVitalMesaReportPdf(Request $request)
    {
        $rules = [
            'user_id' => 'required',
            'report_id' => 'required',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }

        $ai_vital_report = AIVitalScanMisa::where('user_id',$request->user_id)->where('id',$request->report_id)->first();
        $data = [];
        $user = Users::where('id',$request->user_id)->first();
        $data['user'] = $user; 
        $data['scan_date'] = $ai_vital_report->scan_date; 
        $data['report'] = json_decode($ai_vital_report->report); 
        // return $data;
        $filename = "aiVitalMIDAS_Report.pdf";
        // return view('pages.vitalScanReport', $data);
        $pdf = PDF::loadView('pages.vitalScanReport',$data)
        ->setPaper('a4', 'portrait')
        ->setOptions([
            'dpi' => 150,
            'isRemoteEnabled' => true,
            'isHtml5ParserEnabled' => true,
        ]);
        return $pdf->download($filename);
    }

    function vitalReportPdf(Request $request)
    {     
        $rules = [
            'user_id' => 'required',
            'report_id' => 'required',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }

        // return $request->report_id;
        $ai_vital_report = AI_Vital::where('user_id',$request->user_id)->where('id',$request->report_id)->first();
        $data = [];
        $user = Users::where('id',$request->user_id)->first();
        $data['user'] = $user; 
        $data['scan_date'] = $ai_vital_report->scan_date ?? null; 
        $data['report'] = !empty($ai_vital_report->report) ? json_decode($ai_vital_report->report) : ''; 
        // return $data;
        $filename = "vitalScanReport.pdf";
        // return view('pages.vitalScanReport', $data);
        $pdf = PDF::loadView('pages.vitalScanReport',$data)
        ->setPaper('a4', 'portrait')
        ->setOptions([
            'dpi' => 150,
            'isRemoteEnabled' => true,
            'isHtml5ParserEnabled' => true,
        ]);
        return $pdf->download($filename);
    }

    function fetchMyPrescriptions(Request $request)
    {
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

        $items = Prescriptions::with(['user', 'appointment', 'appointment.doctor'])
            ->where('user_id', $user->id)
            ->orderBy('id', 'DESC')
            ->get();

        return GlobalFunction::sendDataResponse(true, 'data fetched successfully', $items);
    }

    function downloadPrescriptions(Request $request)
    {
        $rules = [
            'user_id' => 'required',
            'prescription_id' => 'required',
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

        $items = Prescriptions::with(['user', 'appointment', 'appointment.doctor'])
            ->where('user_id', $user->id)
            ->where('id', $request->prescription_id)
            ->first();

        $data = [];
        $data['user'] = $user;  
        $data['prescription'] = $items; 
        $data['prescriptionPdfImages'] = [
            'signature' => !empty($items?->appointment?->doctor?->digital_signature)
                ? url('/storage/' . ltrim($items->appointment->doctor->digital_signature, '/\\'))
                : url('/images/no-signature.png'),
        ];
        $filename = "prescription.pdf";
        // return $data;
        $pdf = PDF::loadView('pages.prescription',$data)
        ->setPaper('a4', 'portrait')
        ->setOptions([
            'dpi' => 150,
            'isRemoteEnabled' => true,
            'isHtml5ParserEnabled' => true,
        ]);
        return $pdf->download($filename);
        return GlobalFunction::sendDataResponse(true, 'data fetched successfully', $items);
    }

    function addRating(Request $request)
    {
        $rules = [
            'appointment_id' => 'required',
            'user_id' => 'required',
            'comment' => 'required',
            'rating' => 'required',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }

        $appointment = Appointments::where('id', $request->appointment_id)
            ->with(['user', 'patient', 'doctor', 'documents', 'rating', 'prescription'])
            ->first();
        if ($appointment == null) {
            return GlobalFunction::sendSimpleResponse(false, 'Appointment does not exists!');
        }
        if ($appointment->user_id != $request->user_id) {
            return response()->json(['status' => false, 'message' => "This appointment doesn't belong to this user"]);
        }
        if ($appointment->status != Constants::orderCompleted) {
            return response()->json(['status' => false, 'message' => "This appointment is not yet completed to rate!"]);
        }
        if ($appointment->is_rated == 1) {
            return response()->json(['status' => false, 'message' => "This appointment has been rated already!"]);
        }

        // Add rating
        $review = new DoctorReviews();
        $review->user_id = $appointment->user_id;
        $review->doctor_id = $appointment->doctor_id;
        $review->appointment_id = $appointment->id;
        $review->rating = $request->rating;
        $review->comment = GlobalFunction::cleanString($request->comment);
        $review->save();

        $appointment->is_rated = 1;
        $appointment->save();

        $doctor = $review->doctor;
        $doctor->rating = $doctor->avgRating();
        $doctor->save();

        $appointment = Appointments::where('id', $request->appointment_id)
            ->with(['user', 'patient', 'doctor', 'documents', 'rating', 'prescription'])
            ->first();

        return GlobalFunction::sendDataResponse(true, 'appointment rated successfully!', $appointment);
    }

    function submitDoctorWithdrawRequest(Request $request)
    {
        $rules = [
            'doctor_id' => 'required',
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
        $settings = GlobalSettings::first();
        if ($doctor->wallet < $settings->min_amount_payout_doctor) {
            return response()->json(['status' => false, 'message' => "Insufficient amount to withdraw!"]);
        }

        $item = new DoctorPayoutHistory();
        $item->request_number = GlobalFunction::generateDoctorWithdrawRequestNumber();
        $item->amount = $doctor->wallet;
        $item->doctor_id = $doctor->id;
        $item->save();

        $summary = 'Withdraw request :' . $item->request_number;
        // Adding wallet statement
        GlobalFunction::addDoctorStatementEntry(
            $doctor->id,
            null,
            $doctor->wallet,
            Constants::debit,
            Constants::doctorWalletWithdraw,
            $summary
        );

        //resetting users wallet
        $doctor->wallet = 0;
        $doctor->save();

        return GlobalFunction::sendSimpleResponse(true, 'Doctor withdraw request submitted successfully!');
    }

    function rescheduleAppointment(Request $request)
    {
        $rules = [
            'appointment_id' => 'required',
            'user_id' => 'required',
            'date' => 'required',
            'time' => 'required',
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

        $appointment = Appointments::where('id', $request->appointment_id)
            ->with(['user', 'patient', 'doctor', 'documents', 'rating', 'prescription'])
            ->first();
        if ($appointment == null) {
            return GlobalFunction::sendSimpleResponse(false, 'Appointment does not exists!');
        }
        if ($appointment->user_id != $request->user_id) {
            return response()->json(['status' => false, 'message' => "This appointment doesn't belong to this user"]);
        }

        $appointment->date = $request->date;
        $appointment->time = $request->time;
        $appointment->status = 1;
        $appointment->updated_at = now();
        $appointment->save();


        $room = 'appointment-' . Str::random(10);
        $jitsiBaseUrl = env('JITSI_URL');
        $jitsiJwt = env('JWT_TOKEN_JITSI_MEETING');
        $link = $jitsiBaseUrl . '?roomId=' . $room . '&jwt=' . $jitsiJwt;
        $date = $appointment->date;
        $time = $appointment->time;
        $formattedTime = substr($time, 0, 2) . ':' . substr($time, 2, 2);
        $startDateTime = Carbon::createFromFormat('Y-m-d H:i', $date . ' ' . $formattedTime);
        $endDateTime = $startDateTime->copy()->addHour();
        $appointmentdate = Carbon::createFromFormat('Y-m-d', $date)->format('d-m-Y');
        $appointmentTime = Carbon::createFromFormat('H:i', $formattedTime)->format('h:i A');

        $meeting_link_patient = GlobalFunction::GeneratePatientJitsiMeetingLink($appointment, $room);
        $meeting_link_doctor = GlobalFunction::GenerateDoctorJitsiMeetingLink($appointment, $room);
                
        $meeting = JitsiMeeting::where('appointment_id',$request->appointment_id)->where('user_id',$request->user_id)->latest()->first();
        $meeting->room = $room;
        $meeting->patient_link = $meeting_link_patient;
        $meeting->doctor_link = $meeting_link_doctor;
        $meeting->start_time = $startDateTime;
        $meeting->end_time = $endDateTime;
        $meeting->save();
        // Delete user scheduled reminders

        $appointment->jitsiMeetingLink = $meeting_link_patient;
        GlobalFunction::deleteAppointmentScheduledReminders($appointment);

        // Send Push to user
        $title = "Appointment :" . $appointment->appointment_number;
        $message = "Appointment has been rescheduled successfully!";
        $notifyData = [
            'type'=> Constants::notifyAppointment.'',
            'id'=>$appointment->id.''
        ];
        GlobalFunction::sendPushToUser($title, $message, $user, $notifyData);

        return GlobalFunction::sendDataResponse(true, 'Appointment rescheduled successfully!', $appointment);
    }

    function cancelAppointment(Request $request)
    {
        $rules = [
            'appointment_id' => 'required',
            'user_id' => 'required',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }

        $appointment = Appointments::where('id', $request->appointment_id)
            ->with(['user', 'patient', 'doctor', 'documents', 'prescription', 'rating'])
            ->first();
        if ($appointment == null) {
            return GlobalFunction::sendSimpleResponse(false, 'Appointment does not exists!');
        }
        $user = Users::find($request->user_id);
        if ($user == null) {
            return response()->json(['status' => false, 'message' => "User doesn't exists!"]);
        }
        if ($appointment->user_id != $request->user_id) {
            return response()->json(['status' => false, 'message' => "This appointment doesn't belong to this user"]);
        }
        if ($appointment->status == Constants::orderCancelled || $appointment->status == Constants::orderDeclined || $appointment->status == Constants::orderCompleted) {
            return response()->json(['status' => false, 'message' => "This appointment is not eligible to be cancelled!"]);
        }
        $appointment->status = Constants::orderCancelled;
        $appointment->save();

        // Refunding to user
        $user->wallet = $user->wallet + $appointment->payable_amount;
        $user->save();
        // Adding statement entry
        $summary = 'Booking Cancelled By User: ' . $appointment->appointment_number . ' Refund';
        GlobalFunction::addUserStatementEntry($user->id, $appointment->appointment_number, $appointment->payable_amount, Constants::credit, Constants::refund, $summary);

        // Send Push to user
        $title = "Appointment :" . $appointment->appointment_number;
        $message = "Appointment has been cancelled successfully!";
        $notifyData = [
            'type'=> Constants::notifyAppointment.'',
            'id'=> $appointment->id.''
        ];
        GlobalFunction::sendPushToUser($title, $message, $user, $notifyData);

        return GlobalFunction::sendDataResponse(true, 'appointment cancelled successfully!', $appointment);
    }

    function getJitsiMeeting(Request $request)
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

        $jitsi_link_info = JitsiMeeting::where('room',$request->room)->first();
        if($jitsi_link_info)
        {
            return GlobalFunction::sendDataResponse(true, 'Data fetched successfully!', $jitsi_link_info);
        }else{
             return response()->json(['status' => false, 'message' => "meeting doesn't exists!"]);
        }
    }

    function fetchDoctorPayoutHistory(Request $request)
    {
        $rules = [
            'doctor_id' => 'required',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }

        $doctor = Doctors::find($request->doctor_id);
        if ($doctor == null) {
            return response()->json(['status' => false, 'message' => "doctor doesn't exists!"]);
        }

        $history = DoctorPayoutHistory::where('doctor_id', $doctor->id)
            ->orderBy('id', 'DESC')
            ->get();

        return GlobalFunction::sendDataResponse(true, 'Payout history Data fetched successfully!', $history);
    }

    function completeAppointment(Request $request)
    {
        $rules = [
            'doctor_id' => 'required',
            'appointment_id' => 'required',
            // 'completion_otp' => 'required',
            'diagnosed_with' => 'required',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }
        $doctor = Doctors::where('id', $request->doctor_id)->first();
        if ($doctor == null) {
            return GlobalFunction::sendSimpleResponse(false, 'Doctor does not exists!');
        }
        $appointment = Appointments::where('id', $request->appointment_id)->first();
        if ($appointment == null) {
            return GlobalFunction::sendSimpleResponse(false, 'Appointment does not exists!');
        }
        if ($appointment->doctor_id != $request->doctor_id) {
            return response()->json(['status' => false, 'message' => "Appointment is not owned by this doctor!"]);
        }
        // if ($appointment->completion_otp != $request->completion_otp) {
        //     return response()->json(['status' => false, 'message' => "Completion OTP is incorrect!"]);
        // }
        if ($appointment->status == Constants::orderAccepted) {
            $appointment->status = Constants::orderCompleted;
            $appointment->diagnosed_with = $request->diagnosed_with;
            $appointment->save();

            // Commission calculation
            $earning = $appointment->subtotal;
            $settings = GlobalSettings::first();
            $commissionAmount = ($settings->comission / 100) * $earning;

            // Adding Earning statement
            $earningSummary = "Earning from appointment: " . $appointment->appointment_number;
            GlobalFunction::addDoctorStatementEntry($doctor->id, $appointment->appointment_number, $earning, Constants::credit, Constants::doctorWalletEarning, $earningSummary);

            // Adding Commission deduct statement
            $commissionSummary = "Commission of appointment: " . $appointment->appointment_number . " : (" . $settings->comission . "%)";
            GlobalFunction::addDoctorStatementEntry($doctor->id, $appointment->appointment_number, $commissionAmount, Constants::debit, Constants::doctorWalletCommission, $commissionSummary);

            // Adding earning to doctor wallet + count increase + lifetime earning increase
            $earningAfterCommission = $earning - $commissionAmount;
            $doctor->wallet = $doctor->wallet + $earningAfterCommission;
            $doctor->total_patients_cured = $doctor->total_patients_cured + 1;
            $doctor->lifetime_earnings = $doctor->lifetime_earnings + $earningAfterCommission;
            $doctor->save();

            // Adding Earning Logs Of Doctor
            $doctorEarningHistory = new DoctorEarningHistory();
            $doctorEarningHistory->doctor_id = $doctor->id;
            $doctorEarningHistory->appointment_id = $appointment->id;
            $doctorEarningHistory->earning_number = GlobalFunction::generateDoctorEarningHistoryNumber();
            $doctorEarningHistory->amount = $earningAfterCommission;
            $doctorEarningHistory->save();

            // Adding Earning Logs of Platform
            $platformEarningHistory = new PlatformEarningHistory();
            $platformEarningHistory->earning_number = GlobalFunction::generatePlatformEarningHistoryNumber();
            $platformEarningHistory->amount = $commissionAmount;
            $platformEarningHistory->commission_percentage = $settings->comission;
            $platformEarningHistory->appointment_id = $appointment->id;
            $platformEarningHistory->doctor_id = $doctor->id;
            $platformEarningHistory->save();

            // Increasing total platform earning data
            $platformData = PlatformData::first();
            $platformData->lifetime_earnings = $platformData->lifetime_earnings + $commissionAmount;
            $platformData->save();

            // Send Push to user
            $title = "Appointment :" . $appointment->appointment_number;
            $message = "Appointment has been completed by doctor!";
            $notifyData = [
                'type'=> Constants::notifyAppointment.'',
                'id'=> $appointment->id.''
            ];
            GlobalFunction::sendPushToUser($title, $message, $appointment->user, $notifyData);

            return GlobalFunction::sendSimpleResponse(true, 'Appointment completed successfully');
        } else {
            return response()->json(['status' => false, 'message' => "This booking can't be completed!"]);
        }
    }

    function completeAppointmentFromSheduler(Request $request)
    {
        $rules = [
            'doctor_id' => 'required',
            'appointment_id' => 'required',
            // 'completion_otp' => 'required',
            'diagnosed_with' => 'required',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }
        $doctor = Doctors::where('id', $request->doctor_id)->first();
        if ($doctor == null) {
            return GlobalFunction::sendSimpleResponse(false, 'Doctor does not exists!');
        }
        $appointment = Appointments::where('id', $request->appointment_id)->first();
        if ($appointment == null) {
            return GlobalFunction::sendSimpleResponse(false, 'Appointment does not exists!');
        }
        if ($appointment->doctor_id != $request->doctor_id) {
            return response()->json(['status' => false, 'message' => "Appointment is not owned by this doctor!"]);
        }
        // if ($appointment->completion_otp != $request->completion_otp) {
        //     return response()->json(['status' => false, 'message' => "Completion OTP is incorrect!"]);
        // }
        if ($appointment->status == Constants::orderAccepted) {
            $appointment->status = Constants::orderCompleted;
            $appointment->diagnosed_with = $request->diagnosed_with;
            $appointment->save();

            // Commission calculation
            $earning = $appointment->subtotal;
            $settings = GlobalSettings::first();
            $commissionAmount = ($settings->comission / 100) * $earning;

            // Adding Earning statement
            $earningSummary = "Earning from appointment: " . $appointment->appointment_number;
            GlobalFunction::addDoctorStatementEntry($doctor->id, $appointment->appointment_number, $earning, Constants::credit, Constants::doctorWalletEarning, $earningSummary);

            // Adding Commission deduct statement
            $commissionSummary = "Commission of appointment: " . $appointment->appointment_number . " : (" . $settings->comission . "%)";
            GlobalFunction::addDoctorStatementEntry($doctor->id, $appointment->appointment_number, $commissionAmount, Constants::debit, Constants::doctorWalletCommission, $commissionSummary);

            // Adding earning to doctor wallet + count increase + lifetime earning increase
            $earningAfterCommission = $earning - $commissionAmount;
            $doctor->wallet = $doctor->wallet + $earningAfterCommission;
            $doctor->total_patients_cured = $doctor->total_patients_cured + 1;
            $doctor->lifetime_earnings = $doctor->lifetime_earnings + $earningAfterCommission;
            $doctor->save();

            // Adding Earning Logs Of Doctor
            $doctorEarningHistory = new DoctorEarningHistory();
            $doctorEarningHistory->doctor_id = $doctor->id;
            $doctorEarningHistory->appointment_id = $appointment->id;
            $doctorEarningHistory->earning_number = GlobalFunction::generateDoctorEarningHistoryNumber();
            $doctorEarningHistory->amount = $earningAfterCommission;
            $doctorEarningHistory->save();

            // Adding Earning Logs of Platform
            $platformEarningHistory = new PlatformEarningHistory();
            $platformEarningHistory->earning_number = GlobalFunction::generatePlatformEarningHistoryNumber();
            $platformEarningHistory->amount = $commissionAmount;
            $platformEarningHistory->commission_percentage = $settings->comission;
            $platformEarningHistory->appointment_id = $appointment->id;
            $platformEarningHistory->doctor_id = $doctor->id;
            $platformEarningHistory->save();

            // Increasing total platform earning data
            $platformData = PlatformData::first();
            $platformData->lifetime_earnings = $platformData->lifetime_earnings + $commissionAmount;
            $platformData->save();

            // // Send Push to user
            // $title = "Appointment :" . $appointment->appointment_number;
            // $message = "Appointment has been completed by doctor!";
            // $notifyData = [
            //     'type'=> Constants::notifyAppointment.'',
            //     'id'=> $appointment->id.''
            // ];
            // GlobalFunction::sendPushToUser($title, $message, $appointment->user, $notifyData);

            return GlobalFunction::sendSimpleResponse(true, 'Appointment completed successfully');
        } else {
            return response()->json(['status' => false, 'message' => "This booking can't be completed!"]);
        }
    }

    function cancelAppointmentFromSheduler(Request $request)
    {
        $rules = [
            'doctor_id' => 'required',
            'appointment_id' => 'required',
            // 'completion_otp' => 'required',
            'diagnosed_with' => 'required',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }
        $doctor = Doctors::where('id', $request->doctor_id)->first();
        if ($doctor == null) {
            return GlobalFunction::sendSimpleResponse(false, 'Doctor does not exists!');
        }
        $appointment = Appointments::where('id', $request->appointment_id)->first();
        if ($appointment == null) {
            return GlobalFunction::sendSimpleResponse(false, 'Appointment does not exists!');
        }
        if ($appointment->doctor_id != $request->doctor_id) {
            return response()->json(['status' => false, 'message' => "Appointment is not owned by this doctor!"]);
        }
        // if ($appointment->completion_otp != $request->completion_otp) {
        //     return response()->json(['status' => false, 'message' => "Completion OTP is incorrect!"]);
        // }
        if ($appointment->status == Constants::orderAccepted) {
            $appointment->status = Constants::orderCancelled;
            $appointment->diagnosed_with = $request->diagnosed_with;
            $appointment->save();

            // Commission calculation
            $earning = $appointment->subtotal;
            $settings = GlobalSettings::first();
            $commissionAmount = ($settings->comission / 100) * $earning;

            // Adding Earning statement
            $earningSummary = "Earning from appointment: " . $appointment->appointment_number;
            GlobalFunction::addDoctorStatementEntry($doctor->id, $appointment->appointment_number, $earning, Constants::credit, Constants::doctorWalletEarning, $earningSummary);

            // Adding Commission deduct statement
            $commissionSummary = "Commission of appointment: " . $appointment->appointment_number . " : (" . $settings->comission . "%)";
            GlobalFunction::addDoctorStatementEntry($doctor->id, $appointment->appointment_number, $commissionAmount, Constants::debit, Constants::doctorWalletCommission, $commissionSummary);

            // Adding earning to doctor wallet + count increase + lifetime earning increase
            $earningAfterCommission = $earning - $commissionAmount;
            $doctor->wallet = $doctor->wallet + $earningAfterCommission;
            $doctor->total_patients_cured = $doctor->total_patients_cured + 1;
            $doctor->lifetime_earnings = $doctor->lifetime_earnings + $earningAfterCommission;
            $doctor->save();

            // Adding Earning Logs Of Doctor
            $doctorEarningHistory = new DoctorEarningHistory();
            $doctorEarningHistory->doctor_id = $doctor->id;
            $doctorEarningHistory->appointment_id = $appointment->id;
            $doctorEarningHistory->earning_number = GlobalFunction::generateDoctorEarningHistoryNumber();
            $doctorEarningHistory->amount = $earningAfterCommission;
            $doctorEarningHistory->save();

            // Adding Earning Logs of Platform
            $platformEarningHistory = new PlatformEarningHistory();
            $platformEarningHistory->earning_number = GlobalFunction::generatePlatformEarningHistoryNumber();
            $platformEarningHistory->amount = $commissionAmount;
            $platformEarningHistory->commission_percentage = $settings->comission;
            $platformEarningHistory->appointment_id = $appointment->id;
            $platformEarningHistory->doctor_id = $doctor->id;
            $platformEarningHistory->save();

            // Increasing total platform earning data
            $platformData = PlatformData::first();
            $platformData->lifetime_earnings = $platformData->lifetime_earnings + $commissionAmount;
            $platformData->save();

            // // Send Push to user
            // $title = "Appointment :" . $appointment->appointment_number;
            // $message = "Appointment has been completed by doctor!";
            // $notifyData = [
            //     'type'=> Constants::notifyAppointment.'',
            //     'id'=> $appointment->id.''
            // ];
            // GlobalFunction::sendPushToUser($title, $message, $appointment->user, $notifyData);

            return GlobalFunction::sendSimpleResponse(true, 'Appointment completed successfully');
        } else {
            return response()->json(['status' => false, 'message' => "This booking can't be completed!"]);
        }
    }

    function markMissedAppointmentFromSheduler(Request $request)
    {
        $rules = [
            // 'doctor_id' => 'required',
            'appointment_id' => 'required',
            // 'completion_otp' => 'required',
            'diagnosed_with' => 'required',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }
        $doctor = null;
        if($request->has('doctor_id')){
            $doctor = Doctors::where('id', $request->doctor_id)->first();
        }
        // if ($doctor == null) {
        //     return GlobalFunction::sendSimpleResponse(false, 'Doctor does not exists!');
        // }
        $appointment = Appointments::where('id', $request->appointment_id)->first();
        if ($appointment == null) {
            return GlobalFunction::sendSimpleResponse(false, 'Appointment does not exists!');
        }
        if ($appointment->doctor_id != $request->doctor_id) {
            return response()->json(['status' => false, 'message' => "Appointment is not owned by this doctor!"]);
        }
        // if ($appointment->completion_otp != $request->completion_otp) {
        //     return response()->json(['status' => false, 'message' => "Completion OTP is incorrect!"]);
        // }
        if ($appointment->status == Constants::orderAccepted) {
            $appointment->status = Constants::orderMissed;
            $appointment->diagnosed_with = $request->diagnosed_with;
            $appointment->save();
            Log::info('V1 appointment marked missed', [
                'appointment_id' => $appointment->id,
                'doctor_id' => $appointment->doctor_id,
            ]);

            $appointment->loadMissing(['user', 'doctor']);
            $jitsiMeeting = JitsiMeeting::where('appointment_id', $appointment->id)->latest()->first();
            $patientName = trim(
                ($appointment->user->fullname ?? '') !== ''
                    ? $appointment->user->fullname
                    : (($appointment->user->first_name ?? '') . ' ' . ($appointment->user->last_name ?? ''))
            );
            if ($patientName === '') {
                $patientName = 'Patient';
            }
                $patientCountryCode = GlobalFunction::normalizeCountryCode($appointment->user->country_code ?? null);
                $patientPhone = preg_replace('/\D+/', '', (string) ($appointment->user->phone_number ?? ''));
                $patientLabel = $patientName;
                if (!empty($patientPhone)) {
                    $patientLabel = trim($patientName . ' +' . ($patientCountryCode ?: '') . $patientPhone);
                }
            $doctorName = trim($appointment->doctor->name ?? ($doctor->name ?? 'Doctor'));

            $message = null;
            if ($jitsiMeeting && (int) $jitsiMeeting->doctor_joined === 0 && (int) $jitsiMeeting->user_joined === 1) {
                $message = "Dr missed call from {$patientLabel}.";
            } elseif ($jitsiMeeting && (int) $jitsiMeeting->user_joined === 0 && (int) $jitsiMeeting->doctor_joined === 1) {
                    $message = "{$patientLabel} missed call from {$doctorName}.";
            } elseif ($jitsiMeeting && (int) $jitsiMeeting->user_joined === 0 && (int) $jitsiMeeting->doctor_joined === 0) {
                    $message = "Missed call between Dr. {$doctorName} and {$patientLabel}.";
            }

            if ($message) {
                Log::info('V1 missed SMS prepared', [
                    'appointment_id' => $appointment->id,
                    'message' => $message,
                ]);
                foreach (['971522463433', '971569337544'] as $mobile) {
                    try {
                        Log::info('V1 missed SMS sending', [
                            'appointment_id' => $appointment->id,
                            'mobile' => $mobile,
                        ]);
                        EmailHelpers::sendSms($mobile, $message);
                        Log::info('V1 missed SMS sent', [
                            'appointment_id' => $appointment->id,
                            'mobile' => $mobile,
                        ]);
                    } catch (\Throwable $e) {
                        Log::warning('Missed alert SMS failed (appointment v1)', [
                            'mobile' => $mobile,
                            'appointment_id' => $appointment->id,
                            'message' => $e->getMessage(),
                        ]);
                    }
                }
            } else {
                Log::info('V1 missed SMS skipped due to join-state mismatch', [
                    'appointment_id' => $appointment->id,
                    'doctor_joined' => $jitsiMeeting->doctor_joined ?? null,
                    'user_joined' => $jitsiMeeting->user_joined ?? null,
                ]);
            }

            // Commission calculation
            $earning = $appointment->subtotal;
            $settings = GlobalSettings::first();
            $commissionAmount = ($settings->comission / 100) * $earning;

            // Adding Earning statement
            $earningSummary = "Earning from appointment: " . $appointment->appointment_number;
            if($doctor)
            {
                GlobalFunction::addDoctorStatementEntry($doctor->id, $appointment->appointment_number, $earning, Constants::credit, Constants::doctorWalletEarning, $earningSummary);
            }
            // Adding Commission deduct statement
            $commissionSummary = "Commission of appointment: " . $appointment->appointment_number . " : (" . $settings->comission . "%)";
            if($doctor){
                GlobalFunction::addDoctorStatementEntry($doctor->id, $appointment->appointment_number, $commissionAmount, Constants::debit, Constants::doctorWalletCommission, $commissionSummary);
            }

            // Adding earning to doctor wallet + count increase + lifetime earning increase
            $earningAfterCommission = $earning - $commissionAmount;
            if($doctor){
                $doctor->wallet = $doctor->wallet + $earningAfterCommission;
                $doctor->total_patients_cured = $doctor->total_patients_cured + 1;
                $doctor->lifetime_earnings = $doctor->lifetime_earnings + $earningAfterCommission;
                $doctor->save();
            }

            // Adding Earning Logs Of Doctor
            if($doctor){
                $doctorEarningHistory = new DoctorEarningHistory();
                $doctorEarningHistory->doctor_id = $doctor->id;
                $doctorEarningHistory->appointment_id = $appointment->id;
                $doctorEarningHistory->earning_number = GlobalFunction::generateDoctorEarningHistoryNumber();
                $doctorEarningHistory->amount = $earningAfterCommission;
                $doctorEarningHistory->save();
             }

            // Adding Earning Logs of Platform
             if($doctor){
                $platformEarningHistory = new PlatformEarningHistory();
                $platformEarningHistory->earning_number = GlobalFunction::generatePlatformEarningHistoryNumber();
                $platformEarningHistory->amount = $commissionAmount;
                $platformEarningHistory->commission_percentage = $settings->comission;
                $platformEarningHistory->appointment_id = $appointment->id;
                $platformEarningHistory->doctor_id = $doctor->id;
                $platformEarningHistory->save();
             }            
    
            // Increasing total platform earning data
            $platformData = PlatformData::first();
            $platformData->lifetime_earnings = $platformData->lifetime_earnings + $commissionAmount;
            $platformData->save();

            // // Send Push to user
            // $title = "Appointment :" . $appointment->appointment_number;
            // $message = "Appointment has been completed by doctor!";
            // $notifyData = [
            //     'type'=> Constants::notifyAppointment.'',
            //     'id'=> $appointment->id.''
            // ];
            // GlobalFunction::sendPushToUser($title, $message, $appointment->user, $notifyData);

            return GlobalFunction::sendSimpleResponse(true, 'Appointment completed successfully');
        } else {
            return response()->json(['status' => false, 'message' => "This booking can't be completed!"]);
        }
    }

    function declineAppointment(Request $request)
    {
        $rules = [
            'appointment_id' => 'required',
            'doctor_id' => 'required',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }
        $appointment = Appointments::where('id', $request->appointment_id)
            ->with(['user', 'patient', 'doctor', 'documents'])
            ->first();
        if ($appointment == null) {
            return GlobalFunction::sendSimpleResponse(false, 'Appointment does not exists!');
        }
        $doctor = Doctors::where('id', $request->doctor_id)->first();
        if ($doctor == null) {
            return GlobalFunction::sendSimpleResponse(false, 'Doctor does not exists!');
        }
        if ($appointment->doctor_id != $doctor->id) {
            return GlobalFunction::sendSimpleResponse(false, 'Appointment not owned by this doctor!');
        }
        if ($appointment->status == Constants::orderPlacedPending) {
            $appointment->status = Constants::orderDeclined;
            $appointment->save();

            // Refunding to user
            $user = $appointment->user;
            $user->wallet = $user->wallet + $appointment->payable_amount;
            $user->save();
            // Adding statement entry
            $summary = 'Appointment Declined By Doctor : ' . $appointment->appointment_number . ' Refund';
            GlobalFunction::addUserStatementEntry($user->id, $appointment->appointment_number, $appointment->payable_amount, Constants::credit, Constants::refund, $summary);

            // Send Push to user
            $title = "Appointment :" . $appointment->appointment_number;
            $message = "Appointment has been declined!";
            $notifyData = [
                'type'=> Constants::notifyAppointment.'',
                'id'=> $appointment->id.''
            ];
            GlobalFunction::sendPushToUser($title, $message, $appointment->user, $notifyData);

             // Delete user scheduled reminders
             GlobalFunction::deleteAppointmentScheduledReminders($appointment);

            return GlobalFunction::sendSimpleResponse(true, 'Appointment declined successfully');
        } else {
            return response()->json(['status' => false, 'message' => "This appointment can't be declined!"]);
        }
    }

    function acceptAppointment(Request $request)
    {
        $rules = [
            'appointment_id' => 'required',
            'doctor_id' => 'required',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }
        $appointment = Appointments::where('id', $request->appointment_id)
            ->with(['user', 'patient', 'doctor', 'documents'])
            ->first();
        if ($appointment == null) {
            return GlobalFunction::sendSimpleResponse(false, 'Appointment does not exists!');
        }
        $doctor = Doctors::where('id', $request->doctor_id)->first();
        if ($doctor == null) {
            return GlobalFunction::sendSimpleResponse(false, 'Doctor does not exists!');
        }
        if ($appointment->doctor_id != $doctor->id) {
            return GlobalFunction::sendSimpleResponse(false, 'Appointment not owned by this doctor!');
        }

        if ($appointment->status == Constants::orderPlacedPending) {
            $appointment->status = Constants::orderAccepted;
            $appointment->save();

            // Send Push to user
            $title = "Appointment :" . $appointment->appointment_number;
            $message = "Appointment has been accepted!";
            $notifyData = [
                'type'=> Constants::notifyAppointment.'',
                'id'=> $appointment->id.''
            ];

             // Activate Scheduled Reminders
             ScheduledReminders::where('appointment_id', $appointment->id)->update(['status'=> 1]);

            GlobalFunction::sendPushToUser($title, $message, $appointment->user, $notifyData);

            return GlobalFunction::sendSimpleResponse(true, 'Appointment accepted successfully');
        } else {
            return response()->json(['status' => false, 'message' => "This appointment can't be accepted!"]);
        }
    }

    function fetchAppointmentDetails(Request $request)
    {
        $rules = [
            'appointment_id' => 'required',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }
        $result = Appointments::where('id', $request->appointment_id)
            ->with(['user', 'patient', 'doctor', 'documents', 'prescription', 'rating', 'rating','emrdocuments'])
            ->first();
        if ($result == null) {
            return GlobalFunction::sendSimpleResponse(false, 'Appointment does not exists!');
        }

        $reportQuery = PatientEmrReport::where('appointment_id', $result->id);
        if ($request->filled('doctor_id')) {
            $reportQuery->where('doctor_id', (int) $request->doctor_id);
        } else {
            $reportQuery->where('doctor_id', (int) $result->doctor_id);
        }
        $reportIds = $reportQuery->orderBy('id')->pluck('id')->values();
        $result->report_ids = $reportIds;
        $result->report_id = $reportIds->first();
        $reportForDocs = (clone $reportQuery)->orderByDesc('id')->first(['dhpo_prescription_document']);
        $dhpoPrescriptionDocument = [];
        if (!is_null($reportForDocs?->dhpo_prescription_document)) {
            $rawDhpoDocuments = $reportForDocs->dhpo_prescription_document;
            if (is_array($rawDhpoDocuments)) {
                $dhpoPrescriptionDocument = $rawDhpoDocuments;
            } elseif (is_string($rawDhpoDocuments)) {
                $decodedDhpoDocuments = json_decode($rawDhpoDocuments, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decodedDhpoDocuments)) {
                    $dhpoPrescriptionDocument = $decodedDhpoDocuments;
                } elseif (trim($rawDhpoDocuments) !== '') {
                    $dhpoPrescriptionDocument = [trim($rawDhpoDocuments)];
                }
            }
        }
        $result->dhpo_prescription_document = collect($dhpoPrescriptionDocument)
            ->map(fn($path) => trim((string) $path))
            ->filter(fn($path) => $path !== '')
            ->values()
            ->all();

        $result->previous_appointments =
            Appointments::with(['user', 'patient', 'doctor', 'documents', 'prescription', 'rating'])
            ->Where('doctor_id', $result->doctor_id)
            ->Where('user_id', $result->user_id)
            ->WhereNotIn('id', [$result->id])
            ->WhereIn('status', [Constants::orderCompleted, Constants::orderCancelled, Constants::orderDeclined])
            ->get();

        $jitsiMeeting = DB::table('jitsi_meetings')
                ->where('appointment_id', $result->id)

                ->first();

        // $result->jitsi_link = $jitsiMeeting?->doctor_link;
        $result->jitsi_link = null;
        if ($jitsiMeeting && !empty($jitsiMeeting->room) && !empty($jitsiMeeting->doctor_id)) {
        $result->jitsi_link = url("/api/v1/join_jitsi_meeting?doctor_id={$jitsiMeeting->doctor_id}&room={$jitsiMeeting->room}");
        }

        $result->ai_vital_link = null;
        $ai_vital = AI_Vital::where('user_id', $result->user_id)
        ->Where('appointment_id', $result->id)
            ->orderBy('appointment_id', 'desc')
            ->orderBy('id', 'desc')
            ->first();
        $aiVitalsReport = null;
        if ($ai_vital) {
            $rawAiVitalsReport = $ai_vital->report ?? null;
            if (is_array($rawAiVitalsReport)) {
                $aiVitalsReport = $rawAiVitalsReport;
            } elseif (is_object($rawAiVitalsReport)) {
                $aiVitalsReport = (array) $rawAiVitalsReport;
            } elseif (is_string($rawAiVitalsReport) && trim($rawAiVitalsReport) !== '') {
                $decodedAiVitalsReport = json_decode($rawAiVitalsReport, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decodedAiVitalsReport)) {
                    $aiVitalsReport = $decodedAiVitalsReport;
                }
            }
        }
        if ($ai_vital) {
            $result->ai_vital_link = $ai_vital->pdf_file ?? null;
        }

        // Prefer appointment id for MRN; fallback to report id.
        $mrnNumeric = (int) ($result->id ?? 0);
        if ($mrnNumeric <= 0) {
        $mrnNumeric = (int) ($result->report_id ?? 0);
        }
        if ($mrnNumeric <= 0) {
            $mrnNumeric = 1;
        }
        $mrnNo = 'MMH' . str_pad((string) $mrnNumeric, 4, '0', STR_PAD_LEFT);

        $resultData = $result->toArray();
        $aiVitalsData = [
            'appointment_id' => (int) ($resultData['id'] ?? $result->id ?? 0),
            'doctor_id' => (int) ($resultData['doctor_id'] ?? $result->doctor_id ?? 0),
            'user_id' => (int) ($resultData['user_id'] ?? $result->user_id ?? 0),   
            'ai_vitals_report_id' => $ai_vital ? (int) $ai_vital->id : null,
            'ai_vitals_scan_date' => $ai_vital->scan_date ?? null,
            'ai_vitals_report' => $aiVitalsReport,
        ];
        if (isset($resultData['emrdocuments']) && is_array($resultData['emrdocuments']) && count($resultData['emrdocuments']) > 0) {
            foreach ($resultData['emrdocuments'] as $index => $emrDocument) {
                if (is_array($emrDocument)) {
                    $resultData['emrdocuments'][$index] = array_merge($emrDocument, $aiVitalsData);
                }
            }
        } elseif (isset($resultData['emrdocuments']) && is_array($resultData['emrdocuments'])) {
            // Keep ai vitals under emrdocuments even when no EMR row exists yet.
            $resultData['emrdocuments'][] = $aiVitalsData;
        }
        $resultData = array_merge(
            ['mrn_no' => $mrnNo],
            $resultData
        );

        return GlobalFunction::sendDataResponse(true, 'Data fetched successfully', $resultData);
    }

    function fetchDoctorWalletStatement(Request $request)
    {
        $rules = [
            'doctor_id' => 'required',
            'start' => 'required',
            'count' => 'required',
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
        $statement = DoctorWalletStatements::where('doctor_id', $doctor->id)
            ->offset($request->start)
            ->limit($request->count)
            ->orderBy('id', 'DESC')
            ->get();

        return GlobalFunction::sendDataResponse(true, 'Statement Data fetched successfully!', $statement);
    }

    function fetchDoctorEarningHistory(Request $request)
    {
        $rules = [
            'doctor_id' => 'required',
            'month' => 'required',
            'year' => 'required',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }

        $doctor = Doctors::find($request->doctor_id);
        if ($doctor == null) {
            return response()->json(['status' => false, 'message' => "doctor doesn't exists!"]);
        }

        $statement = DoctorEarningHistory::where('doctor_id', $doctor->id)
            ->whereMonth('created_at', $request->month)
            ->whereYear('created_at', $request->year)
            ->orderBy('id', 'DESC')
            ->get();

        return GlobalFunction::sendDataResponse(true, 'Earning history Data fetched successfully!', $statement);
    }

    function fetchAcceptedAppointsByDate(Request $request)
    {
        // return "check";
        $rules = [
            'date' => 'required',
            'doctor_id' => 'required',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }
        $doctor = Doctors::where('id', $request->doctor_id)->first();
        if ($doctor == null) {
            return GlobalFunction::sendSimpleResponse(false, 'Doctor does not exists!');
        }

        $result = Appointments::with(['user', 'patient', 'doctor', 'documents', 'prescription', 'rating'])
            ->Where('doctor_id', $request->doctor_id)
            ->Where('date', $request->date)
            ->Where('payment_status',1)
            ->where('status', Constants::orderAccepted)
            ->get();

        foreach ($result as $appointment) {
            $appointment->previous_appointments =
                Appointments::with(['user', 'patient', 'doctor', 'documents', 'prescription', 'rating'])
                ->Where('doctor_id', $request->doctor_id)
                ->WhereNotIn('id', [$appointment->id])
                ->Where('payment_status',1)
                ->WhereIn('status', [Constants::orderCompleted, Constants::orderCancelled, Constants::orderDeclined])
                ->where('status', Constants::orderPlacedPending)
                ->get();

            $jitsiMeeting = DB::table('jitsi_meetings')
                                ->where('appointment_id', $appointment->id)
                                ->first();

            $appointment->jitsi_link = $jitsiMeeting?->doctor_link;
            $appointment->ai_vital_link = null;
            $ai_vital = AI_Vital::where('user_id',$appointment->user_id)->Where('appointment_id', $appointment->id)->orderBy('id','desc')->first();
            if($ai_vital)
            {
                $appointment->ai_vital_link = $ai_vital->pdf_file;
            }
        }

        return GlobalFunction::sendDataResponse(true, 'data fetched successfully', $result);
    }

    function fetchAppointmentHistory(Request $request)
    {
        $rules = [
            'start' => 'required',
            'count' => 'required',
            'doctor_id' => 'required',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }
        $doctor = Doctors::where('id', $request->doctor_id)->first();
        if ($doctor == null) {
            return GlobalFunction::sendSimpleResponse(false, 'Doctor does not exists!');
        }
        $result = Appointments::with(['user', 'patient', 'doctor', 'documents', 'prescription', 'rating'])
            ->Where('doctor_id', $request->doctor_id)
            ->Where('payment_status',1)
            ->offset($request->start)
            ->limit($request->count)
            ->orderBy('id', 'DESC')
            ->get();

        foreach ($result as $appointment) {
            $appointment->previous_appointments =
                Appointments::with(['user', 'patient', 'doctor', 'documents', 'prescription', 'rating'])
                ->Where('doctor_id', $request->doctor_id)
                ->Where('user_id', $appointment->user_id)
                ->Where('payment_status',1)
                ->WhereNotIn('id', [$appointment->id])
                ->WhereIn('status', [Constants::orderCompleted, Constants::orderCancelled, Constants::orderDeclined])
                ->get();
        }

        return GlobalFunction::sendDataResponse(true, 'data fetched successfully', $result);
    }

    function fetchAppointmentRequests(Request $request)
    {
        $rules = [
            'start' => 'required',
            'count' => 'required',
            'doctor_id' => 'required',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }
        $doctor = Doctors::where('id', $request->doctor_id)->first();
        if ($doctor == null) {
            return GlobalFunction::sendSimpleResponse(false, 'Doctor does not exists!');
        }
        $result = Appointments::with(['user', 'patient', 'doctor', 'documents', 'prescription', 'rating'])
            ->Where('doctor_id', $request->doctor_id)
            // ->where('status', Constants::orderPlacedPending)
            ->Where('status', Constants::orderAccepted)
            ->Where('payment_status',1)
            ->offset($request->start)
            ->limit($request->count)
            ->get();

        foreach ($result as $appointment) {
            $appointment->previous_appointments =
                Appointments::with(['user', 'patient', 'doctor', 'documents', 'prescription', 'rating'])
                ->Where('doctor_id', $request->doctor_id)
                ->Where('user_id', $appointment->user_id)
                ->WhereNotIn('id', [$appointment->id])
                ->Where('payment_status',1)
                ->WhereIn('status', [Constants::orderCompleted, Constants::orderCancelled, Constants::orderDeclined])
                ->where('status', Constants::orderPlacedPending)
                ->get();

            $jitsiMeeting = DB::table('jitsi_meetings')
                                ->where('appointment_id', $appointment->id)
                                ->first();

            $appointment->jitsi_link = $jitsiMeeting?->doctor_link;
            $appointment->ai_vital_link = null;
            $ai_vital = AI_Vital::where('user_id',$appointment->user_id)->Where('appointment_id', $appointment->id)->orderBy('id','desc')->first();
            if($ai_vital)
            {
                $appointment->ai_vital_link = $ai_vital->pdf_file;
            }
        }

        return GlobalFunction::sendDataResponse(true, 'data fetched successfully', $result);
    }

    function editPrescription(Request $request)
    {
        $rules = [
            'prescription_id' => 'required',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }
        $prescription = Prescriptions::where('id', $request->prescription_id)
            ->first();
        if ($prescription == null) {
            return GlobalFunction::sendSimpleResponse(false, 'Prescription does not exists!');
        }
        $prescription->medicine = $request->medicine;
        $prescription->diagnosis = $request->diagnosis;
        $prescription->erx_no =$request->has('erx') ? $request->erx : null;
        $prescription->save();

        return GlobalFunction::sendSimpleResponse(true, 'Prescription edited successfully');
    }
    //
    function addPrescription(Request $request)
    {
        $rules = [
            'appointment_id' => 'required',
            'user_id' => 'required',
            'medicine' => 'required',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }
        $appointment = Appointments::where('id', $request->appointment_id)
            ->with(['user', 'patient', 'doctor', 'documents'])
            ->first();
        if ($appointment == null) {
            return GlobalFunction::sendSimpleResponse(false, 'Appointment does not exists!');
        }
        $user = Users::where('id', $request->user_id)->first();
        if ($user == null) {
            return GlobalFunction::sendSimpleResponse(false, 'User does not exists!');
        }
        if ($appointment->user_id != $user->id) {
            return GlobalFunction::sendSimpleResponse(false, 'Appointment not owned by this user!');
        }
        $prescription = Prescriptions::where('user_id', $user->id)->where('appointment_id', $appointment->id)->first();
        if ($prescription != null) {
            return GlobalFunction::sendSimpleResponse(false, 'This appointment has prescription already!');
        }

        $prescription = new Prescriptions();
        $prescription->user_id = $user->id;
        $prescription->appointment_id = $appointment->id;
        $prescription->medicine = $request->medicine;
        $prescription->diagnosis = $request->diagnosis;
        $prescription->erx_no = $request->has('erx') ? $request->erx : null;
        $prescription->save();

        return GlobalFunction::sendSimpleResponse(true, 'Prescription added successfully');
    }

    function fetchCoupons(Request $request)
    {
        $rules = [
            'user_id' => 'required',
            // 'doctor_id' => 'required'
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
        $coupons = Coupons::whereNotIn('id', explode(',', $user->coupons_used))->orderBy('id', 'DESC')->get();
        // $currentDate = Carbon::now()->format('Y-d-m');
        // $user_coupons = UserCoupons::where('user_id',$request->user_id)->where('number_of_limits','>',0)->get();  

        // $coupons_for_user = [];
        // foreach ($user_coupons as $key => $user_coupon) {
        //     $doctorPlans = DoctorPlans::where('is_deleted', 0)
        //                     ->whereJsonContains('doctor_ids', (string) $request->doctor_id)
        //                     ->where('id',$user_coupon->plan_id)
        //                     ->get();
        //     if(count($doctorPlans) > 0){
        //         array_push($coupons_for_user,$user_coupon);
        //     }
        // }
        // return response()->json(['status' => true, 'coupons' => $coupons, 'user_coupons'=>$coupons_for_user]);
        // return GlobalFunction::sendDataResponse(true, 'coupons fetched successfully', $coupons);
        return response()->json(['status' => true, 'coupons' => $coupons]);

    }
}
