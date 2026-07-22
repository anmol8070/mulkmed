<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use App\Models\AddedPatients;
use App\Models\AppointmentDocs;
use App\Models\Appointments;
use App\Models\Constants;
use App\Models\Coupons;
use App\Models\DoctorCategories;
use App\Models\Doctors;
use App\Models\CommonHealthProblems;
use App\Models\GlobalFunction;
use App\Models\GlobalSettings;
use App\Models\IsabelQuestion;
use App\Models\UserNotification;
use App\Models\Users;
use App\Models\User;
use App\Models\UserWalletRechargeLogs;
use App\Models\UserWalletStatements;
use App\Models\UserWithdrawRequest;
use App\Models\PhoneVerification;
use App\Models\SpecialityWiseDisease;
use App\Models\Banners;
use App\Models\DashboardBanners;
use App\Models\PartnersTable;
use App\Models\SectionSequence;
use App\Helpers\EmailHelpers;
use App\Helpers\Helpers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use App\Mail\SendUsernameMail;
use App\Mail\SendPasswordMail;
use App\Models\DoctorsBySymptoms;
use App\Models\JitsiMeeting;
use App\Models\AI_Vital;
use App\Models\AIVitalScanMisa;
use App\Models\OrderMedicineCategories;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UsersController extends Controller
{
    function TEST_sendNotificationToUser()
    {
        $user = Users::find(12);
        return GlobalFunction::sendPushToUser('Title', 'Message', $user);
    }

    function fetchUserAppointmentsList(Request $request)
    {
        $totalData =  Appointments::where('user_id', $request->userId)->count();
        $rows = Appointments::where('user_id', $request->userId)->orderBy('id', 'DESC')->get();
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
            $result = Appointments::where('user_id', $request->userId)->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
        } else {
            $search = $request->input('search.value');
            $result =  Appointments::where('user_id', $request->userId)->where(function ($query) use ($search) {
                $query->Where('appointment_number', 'LIKE', "%{$search}%")
                    ->orWhere('payable_amount', 'LIKE', "%{$search}%");
            })->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
            $totalFiltered = Appointments::where('user_id', $request->userId)->where(function ($query) use ($search) {
                $query->Where('appointment_number', 'LIKE', "%{$search}%")
                    ->orWhere('payable_amount', 'LIKE', "%{$search}%");
            })->count();
        }
        $data = array();
        foreach ($result as $item) {

            $doctor = "";
            if ($item->doctor != null) {
                $doctor = '<a href="' . route('viewDoctorProfile', $item->doctor->id) . '"><span class="badge bg-primary text-white">' . $item->doctor->name . '</span></a>';
            }

            $view = '<a href="' . route('viewAppointment', $item->id) . '" class="mr-2 btn btn-info text-white " rel=' . $item->id . ' >' . __("View") . '</a>';

            $status = GlobalFunction::returnAppointmentStatus($item->status);

            $action = $view;

            $dateTime =  $item->date . '<br>' . GlobalFunction::formateTimeString($item->time);
            $payableAmount = $settings->currency . $item->payable_amount;

            $data[] = array(
                $item->appointment_number,
                $doctor,
                $status,
                $dateTime,
                $settings->currency . $item->service_amount,
                $settings->currency . $item->discount_amount,
                $settings->currency . $item->subtotal,
                $settings->currency . $item->total_tax_amount,
                $payableAmount,
                GlobalFunction::formateTimeString($item->created_at),
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

    function viewUserProfile($id)
    {
        $user = Users::find($id);
        $settings = GlobalSettings::first();
        $totalAppointments = Appointments::where('user_id', $id)->count();
        return view('viewUserProfile', [
            'user' => $user,
            'settings' => $settings,
            'totalAppointments' => $totalAppointments,
        ]);
    }

    function blockUserFromAdmin($id)
    {
        $user = Users::find($id);
        $user->is_block = 1;
        $user->save();

        return GlobalFunction::sendSimpleResponse(true, 'User blocked successfully!');
    }

    function unblockUserFromAdmin($id)
    {
        $user = Users::find($id);
        $user->is_block = 0;
        $user->save();

        return GlobalFunction::sendSimpleResponse(true, 'User unblocked successfully!');
    }

    function rejectUserWithdrawal(Request $request)
    {
        $item = UserWithdrawRequest::find($request->id);
        if ($request->has('summary')) {
            $item->summary = $request->summary;
        }
        $item->status = Constants::statusWithdrawalRejected;
        $item->save();

        $summary = '(Rejected) Withdraw request :' . $item->request_number;
        // Adding wallet statement
        GlobalFunction::addUserStatementEntry(
            $item->user->id,
            null,
            $item->amount,
            Constants::credit,
            Constants::deposit,
            $summary
        );

        //adding money to user wallet
        $item->user->wallet = $item->user->wallet + $item->amount;
        $item->user->save();

        return GlobalFunction::sendSimpleResponse(true, 'request rejected successfully');
    }

    function completeUserWithdrawal(Request $request)
    {
        $item = UserWithdrawRequest::find($request->id);
        if ($request->has('summary')) {
            $item->summary = $request->summary;
        }
        $item->status = Constants::statusWithdrawalCompleted;
        $item->save();

        return GlobalFunction::sendSimpleResponse(true, 'request completed successfully');
    }

    function fetchDetails(Request $request)
    {
        if($request->has('speciality_id'))
        {
            $speciality = DoctorCategories::find($request->speciality_id);
            $doctors    = Doctors::with('expertise')->where('category_id', $speciality->id)
                            ->where('status', Constants::statusDoctorApproved)
                            ->where('on_vacation', Constants::doctorNotOnVacation)
                            ->get();

            $banners    = Banners::where('section','Top specialities')
                            ->where('section_id',$speciality->id)
                            ->where('page','Specaility details page')
                            ->where('is_deleted',0)
                            ->get();

            return response()->json([
                'status' => true, 
                'speciality' => $speciality, 
                'doctors' => $doctors , 
                'banners' => $banners], 200);
        }

        if($request->has('problem_id'))
        {
            $common_health_problems = CommonHealthProblems::find($request->problem_id);
            if($request->has('lang'))
            {
                if($request->lang == 'hi')
                {
                    $lang = $request->get('lang', 'hi');
                    app()->setLocale($lang);
                    $common_health_problems->problem = __($common_health_problems->problem);
                    $common_health_problems->info = __($common_health_problems->info);
                }
                if($request->lang == 'ur')
                {
                    $lang = $request->get('lang', 'ur');
                    app()->setLocale($lang);
                    $common_health_problems->problem = __($common_health_problems->problem);
                    $common_health_problems->info = __($common_health_problems->info);
                }
                if($request->lang == 'ar')
                {
                    $lang = $request->get('lang', 'ar');
                    app()->setLocale($lang);
                    $common_health_problems->problem = __($common_health_problems->problem);
                    $common_health_problems->info = __($common_health_problems->info);
                }
                if($request->lang == 'fr')
                {
                    $lang = $request->get('lang', 'fr');
                    app()->setLocale($lang);
                    $common_health_problems->problem = __($common_health_problems->problem);
                    $common_health_problems->info = __($common_health_problems->info);
                }
            }
            
            $doctors    = Doctors::with('expertise')->where('category_id', $common_health_problems->speciality)
                            ->where('status', Constants::statusDoctorApproved)
                            ->where('on_vacation', Constants::doctorNotOnVacation)
                            ->get();

            foreach ($doctors as $key => $doctor) {
                foreach ($doctor->expertise as $key => $value) {
                    if($request->has('lang'))
                    {
                        if($request->lang == 'hi')
                        {
                            $lang = $request->get('lang', 'hi');
                            app()->setLocale($lang);
                            $value->title = __($value->title);
                        }
                        if($request->lang == 'ur')
                        {
                            $lang = $request->get('lang', 'ur');
                            app()->setLocale($lang);
                            $value->title = __($value->title);
                        }
                        if($request->lang == 'ar')
                        {
                            $lang = $request->get('lang', 'ar');
                            app()->setLocale($lang);
                            $value->title = __($value->title);
                        }
                        if($request->lang == 'fr')
                        {
                            $lang = $request->get('lang', 'fr');
                            app()->setLocale($lang);
                            $value->title = __($value->title);
                        }
                    }
                }
            }
            $banners    = Banners::where('section','Common health Problems')
                            ->where('section_id',$common_health_problems->id)
                            ->where('page','Problemwise details page')
                            ->where('is_deleted',0)
                            ->get();

            return response()->json([
                'status' => true, 
                'common_health_problems' => $common_health_problems, 
                'doctors' => $doctors , 
                'banners' => $banners], 200);
        }

        if($request->has('disease_id'))
        {
            $speciality_wise_disease = SpecialityWiseDisease::find($request->disease_id);
            $doctors    = Doctors::with('expertise')->where('category_id', $speciality_wise_disease->speciality)
                            ->where('status', Constants::statusDoctorApproved)
                            ->where('on_vacation', Constants::doctorNotOnVacation)
                            ->get();

            $banners    = Banners::where('section','Specialitywise disease')
                            ->where('section_id',$speciality_wise_disease->id)
                            ->where('page','Specaility details page')
                            ->where('is_deleted',0)
                            ->get();

            return response()->json([
                'status' => true, 
                'speciality_wise_disease' => $speciality_wise_disease, 
                'doctors' => $doctors , 
                'banners' => $banners], 200);
        }
    }

    function fetchUserCompletedWithdrawalsList(Request $request)
    {
        $totalData =  UserWithdrawRequest::where('status', Constants::statusWithdrawalCompleted)->with('user')->count();
        $rows = UserWithdrawRequest::where('status', Constants::statusWithdrawalCompleted)->with('user')->orderBy('id', 'DESC')->get();
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
            $result = UserWithdrawRequest::where('status', Constants::statusWithdrawalCompleted)
                ->with('user')
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
        } else {
            $search = $request->input('search.value');
            $result =  UserWithdrawRequest::where('status', Constants::statusWithdrawalCompleted)
                ->where(function ($query) use ($search) {
                    $query->where('request_number', 'LIKE', "%{$search}%")
                        ->orWhere('amount', 'LIKE', "%{$search}%")
                        ->orWhere('holder', 'LIKE', "%{$search}%")
                        ->orWhere('summary', 'LIKE', "%{$search}%")
                        ->orWhereHas('user', function ($query) use ($search) {
                            $query->Where('fullname', 'LIKE', "%{$search}%");
                        });
                })
                ->with('user')
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
            $totalFiltered = UserWithdrawRequest::where('status', Constants::statusWithdrawalCompleted)
                ->with('user')
                ->where(function ($query) use ($search) {
                    $query->where('request_number', 'LIKE', "%{$search}%")
                        ->orWhere('amount', 'LIKE', "%{$search}%")
                        ->orWhere('holder', 'LIKE', "%{$search}%")
                        ->orWhere('summary', 'LIKE', "%{$search}%")
                        ->orWhereHas('user', function ($query) use ($search) {
                            $query->Where('fullname', 'LIKE', "%{$search}%");
                        });
                })
                ->count();
        }
        $data = array();
        foreach ($result as $item) {

            $holder = '<span class="text-dark font-weight-bold font-14">' . $item->holder . '</span>';
            $bank_title = '<div class="bank-details"><span>' . $item->bank_title . '</span>';
            $account_number = '<span>' . __('Account : ') .  $item->account_number . '</span>';
            $swift_code = '<span>' . __('Swift Code : ') . $item->swift_code . '</span></div>';
            $bankDetails = $holder . $bank_title . $account_number . $swift_code;

            // Amount & Status
            $amount = '<span class="text-dark font-weight-bold font-16">' . $settings->currency . $item->amount . '</span><br>';
            $status = '<span class="badge bg-success text-white"rel="' . $item->id . '">' . __('Completed') . '</span>';
            $amountData = $amount . $status;

            $user = "";
            if ($item->user != null) {
                $user = '<a href="' . route('viewUserProfile', $item->user->id) . '"><span class="badge bg-primary text-white">' . $item->user->fullname . '</span></a>';
            }

            $data[] = array(
                $item->request_number,
                $bankDetails,
                $amountData,
                $user,
                $item->summary,
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

    function fetchUserRejectedWithdrawalsList(Request $request)
    {
        $totalData =  UserWithdrawRequest::where('status', Constants::statusWithdrawalRejected)->with('user')->count();
        $rows = UserWithdrawRequest::where('status', Constants::statusWithdrawalRejected)->with('user')->orderBy('id', 'DESC')->get();
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
            $result = UserWithdrawRequest::where('status', Constants::statusWithdrawalRejected)
                ->with('user')
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
        } else {
            $search = $request->input('search.value');
            $result =  UserWithdrawRequest::where('status', Constants::statusWithdrawalRejected)
                ->where(function ($query) use ($search) {
                    $query->where('request_number', 'LIKE', "%{$search}%")
                        ->orWhere('amount', 'LIKE', "%{$search}%")
                        ->orWhere('holder', 'LIKE', "%{$search}%")
                        ->orWhere('summary', 'LIKE', "%{$search}%")
                        ->orWhereHas('user', function ($query) use ($search) {
                            $query->Where('fullname', 'LIKE', "%{$search}%");
                        });
                })
                ->with('user')
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
            $totalFiltered = UserWithdrawRequest::where('status', Constants::statusWithdrawalRejected)
                ->with('user')
                ->where(function ($query) use ($search) {
                    $query->where('request_number', 'LIKE', "%{$search}%")
                        ->orWhere('amount', 'LIKE', "%{$search}%")
                        ->orWhere('holder', 'LIKE', "%{$search}%")
                        ->orWhere('summary', 'LIKE', "%{$search}%")
                        ->orWhereHas('user', function ($query) use ($search) {
                            $query->Where('fullname', 'LIKE', "%{$search}%");
                        });
                })
                ->count();
        }
        $data = array();
        foreach ($result as $item) {

            $holder = '<span class="text-dark font-weight-bold font-14">' . $item->holder . '</span>';
            $bank_title = '<div class="bank-details"><span>' . $item->bank_title . '</span>';
            $account_number = '<span>' . __('Account : ') .  $item->account_number . '</span>';
            $swift_code = '<span>' . __('Swift Code : ') . $item->swift_code . '</span></div>';
            $bankDetails = $holder . $bank_title . $account_number . $swift_code;

            // Amount & Status
            $amount = '<span class="text-dark font-weight-bold font-16">' . $settings->currency . $item->amount . '</span><br>';
            $status = '<span class="badge bg-danger text-white"rel="' . $item->id . '">' . __('Rejected') . '</span>';
            $amountData = $amount . $status;

            $user = "";
            if ($item->user != null) {
                $user = '<a href="' . route('viewUserProfile', $item->user->id) . '"><span class="badge bg-primary text-white">' . $item->user->fullname . '</span></a>';
            }

            $data[] = array(
                $item->request_number,
                $bankDetails,
                $amountData,
                $user,
                $item->summary,
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

    function fetchUserPendingWithdrawalsList(Request $request)
    {
        $totalData =  UserWithdrawRequest::where('status', Constants::statusWithdrawalPending)->with('user')->count();
        $rows = UserWithdrawRequest::where('status', Constants::statusWithdrawalPending)->with('user')->orderBy('id', 'DESC')->get();
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
            $result = UserWithdrawRequest::where('status', Constants::statusWithdrawalPending)
                ->with('user')
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
        } else {
            $search = $request->input('search.value');
            $result =  UserWithdrawRequest::where('status', Constants::statusWithdrawalPending)
                ->where(function ($query) use ($search) {
                    $query->where('request_number', 'LIKE', "%{$search}%")
                        ->orWhere('amount', 'LIKE', "%{$search}%")
                        ->orWhere('holder', 'LIKE', "%{$search}%")
                        ->orWhereHas('user', function ($query) use ($search) {
                            $query->Where('fullname', 'LIKE', "%{$search}%");
                        });
                })
                ->with('user')
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
            $totalFiltered = UserWithdrawRequest::where('status', Constants::statusWithdrawalPending)
                ->with('user')
                ->where(function ($query) use ($search) {
                    $query->where('request_number', 'LIKE', "%{$search}%")
                        ->orWhere('amount', 'LIKE', "%{$search}%")
                        ->orWhere('holder', 'LIKE', "%{$search}%")
                        ->orWhereHas('user', function ($query) use ($search) {
                            $query->Where('fullname', 'LIKE', "%{$search}%");
                        });
                })
                ->count();
        }
        $data = array();
        foreach ($result as $item) {

            $holder = '<span class="text-dark font-weight-bold font-14">' . $item->holder . '</span>';
            $bank_title = '<div class="bank-details"><span>' . $item->bank_title . '</span>';
            $account_number = '<span>' . __('Account : ') .  $item->account_number . '</span>';
            $swift_code = '<span>' . __('Swift Code : ') . $item->swift_code . '</span></div>';
            $bankDetails = $holder . $bank_title . $account_number . $swift_code;

            $user = "";
            if ($item->user != null) {
                $user = '<a href="' . route('viewUserProfile', $item->user->id) . '"><span class="badge bg-primary text-white">' . $item->user->fullname . '</span></a>';
            }

            // Amount & Status
            $amount = '<span class="text-dark font-weight-bold font-16">' . $settings->currency . $item->amount . '</span><br>';
            $status = '<span class="badge bg-warning text-white"rel="' . $item->id . '">' . __('Pending') . '</span>';
            $amountData = $amount . $status;

            $complete = '<a href="" class="mr-2 btn btn-success text-white complete" rel=' . $item->id . ' >' . __("Complete") . '</a>';
            $reject = '<a href="" class="mr-2 btn btn-danger text-white reject" rel=' . $item->id . ' >' . __("Reject") . '</a>';
            // $delete = '<a href="" class="mr-2 btn btn-danger text-white delete" rel=' . $item->id . ' >' . __("Delete") . '</a>';
            $action =  $complete . $reject;

            $data[] = array(
                $item->request_number,
                $bankDetails,
                $amountData,
                $user,
                $item->created_at->format('d M, Y'),
                $action
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

    function userWithdraws()
    {
        return view('userWithdraws');
    }

    function users()
    {
        return view('users');
    }

    function send_otp(Request $request)
    {
        if($request->type == "forgot_password")
        {
            $user = Users::where('phone_number', $request->phone)->first();

            $phoneverify    = PhoneVerification::where(['phone_number' => $request->phone])->latest()->first();
            $token          = rand(100000, 999999);
            $token          = (string) $token; 
            if(isset($phoneverify)){
                $phoneverify->token         = $token;
                $phoneverify->created_at    = now();
                $phoneverify->updated_at    = now();
                $phoneverify->save();
            }else{
                $phoneverify                = new PhoneVerification();
                $phoneverify->phone_number  = $request->phone;
                $phoneverify->token         = $token;
                $phoneverify->created_at    = now();
                $phoneverify->updated_at    = now();
                $phoneverify->save();
            }
            $cleanCode = ltrim($request->country_code, '+');

            $message = "Dear {$user->fullname},

You requested to reset your Mulk Med password. Use the code {$token} to proceed.

Regards,
Team Mulk Med.";
            $result = EmailHelpers::sendSms($cleanCode . $request->phone, $message);
            return response()->json(['status' => true, 'otp' => $token , 'message' => 'OTP sent successfully'], 200);
        }
        if($request->type == "forgot_username")
        {
            $user = Users::where('phone_number', $request->phone)->first();

            $phoneverify    = PhoneVerification::where(['phone_number' => $request->phone])->latest()->first();
            $token          = rand(100000, 999999);
            $token          = (string) $token; 
            if(isset($phoneverify)){
                $phoneverify->token         = $token;
                $phoneverify->created_at    = now();
                $phoneverify->updated_at    = now();
                $phoneverify->save();
            }else{
                $phoneverify                = new PhoneVerification();
                $phoneverify->phone_number  = $request->phone;
                $phoneverify->token         = $token;
                $phoneverify->created_at    = now();
                $phoneverify->updated_at    = now();
                $phoneverify->save();
            }
            $message = "Dear {$user->fullname},

You requested to reset your Mulk Med Username. Use the code {$token} to proceed.

Regards,
Team Mulk Med.";
            $cleanCode = ltrim($request->country_code, '+');

            $result = EmailHelpers::sendSms($cleanCode . $request->phone, $message);
            return response()->json(['status' => true, 'otp' => $token , 'message' => 'OTP sent successfully'], 200);
        }


        $existing_user  = Users::where('phone_number', $request->phone)->first();
        
        if(isset($existing_user)){
            return response()->json(["status" => false,"message" => 'User with provided phone number already exist']);
        }
        $phoneverify    = PhoneVerification::where(['phone_number' => $request->phone])->latest()->first();
        $token          = rand(100000, 999999);
        $token          = (string) $token; 
        if(isset($phoneverify)){
            $phoneverify->token         = $token;
            $phoneverify->created_at    = now();
            $phoneverify->updated_at    = now();
            $phoneverify->save();
        }else{
            $phoneverify                = new PhoneVerification();
            $phoneverify->phone_number  = $request->phone;
            $phoneverify->token         = $token;
            $phoneverify->created_at    = now();
            $phoneverify->updated_at    = now();
            $phoneverify->save();
        }

        // if($request->country_code != "+91")
        // {
            $cleanCode = ltrim($request->country_code, '+');
            // $message = "Do not share: Your MULK MED OTP is ". $token;
            $message = "Dear user,

Your Mulk Med verification code is {$token}. Please enter this code to verify your account.

Regards,
Team Mulk Med.";            
            $result = EmailHelpers::sendSms($cleanCode . $request->phone, $message);
        // }
        // return response()->json($result);
        return response()->json(['status' => true, 'otp' => $token , 'message' => 'OTP sent successfully'], 200);
    }

    function otp_verify(Request $request)
    {
        $phoneverify = PhoneVerification::where(['phone_number' => $request['phone'], 'token' => $request['otp']])->first();
        if (isset($phoneverify)) {
            $now_date           = now();
            $otp_created_date   = $phoneverify->created_at;
            $result             = $otp_created_date->diffInSeconds($now_date);
            $duration           = 60;
            if ($result < $duration) {
                return response()->json([
                    'status'    => true,
                    'message'   => 'OTP Verified Successfully'
                ]);
            }
            else{
                return response()->json([
                    'status'    => false,
                    'message'   => 'OTP Expired'
                ]);
            }
        }
        else{
            return response()->json([
                'status'    => false,
                'message'   => 'Invalid OTP'
            ]);
        }
    }

    public function sendUsernameReminder(Request $request)
    {
        $rules = [
            'email' => 'required|email',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }

        $user = Users::where('identity', $request->email)->first();
        if(isset($user))
        {
            Mail::to($user->identity)->send(new SendUsernameMail($user->username, $user->fullname, $user->password));
            return response()->json(["status" => true,"message" => 'An email with your username has been sent.']);
        }else{
            return response()->json(["status" => false,"message" => 'User with provided email does not exist']);
        }
    }

    public function forgetUsernameUsingMobileNumber(Request $request)
    {
        $rules = [
            'phone_number' => 'required',
            'username' => 'required',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }

        $user = Users::where('phone_number', $request->phone_number)->where('country_code',$request->country_code)->first();
        // $user = Users::where('phone_number', $request->phone_number)->first();
        if(isset($user))
        {
            $user->username = $request->username;
            $user->save();
            return response()->json(["status" => true,"message" => 'Your username has been updated successfully.',"username"=>$request->username]);
        }else{
            return response()->json(["status" => false,"message" => 'User with provided phone number does not exist']);
        }
    }
    
    public function send_otp_for_registered_user(Request $request)
    {  
                Log::info('otp for resgisted user API request'. $request);
        $rules = [
            'phone_number' => 'required',
            // 'country_code' => 'required',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }

        $user = Users::where('phone_number', $request->phone_number)->where('country_code',$request->country_code)->first();
        // $user = Users::where('phone_number', $request->phone_number)->first();
        if(isset($user))
        {
            $phoneverify    = PhoneVerification::where(['phone_number' => $request->phone_number])->latest()->first();
            $token          = rand(100000, 999999);
            $token          = (string) $token; 
            if(isset($phoneverify)){
                $phoneverify->token         = $token;
                $phoneverify->created_at    = now();
                $phoneverify->updated_at    = now();
                $phoneverify->save();
            }else{
                $phoneverify                = new PhoneVerification();
                $phoneverify->phone_number  = $request->phone_number;
                $phoneverify->token         = $token;
                $phoneverify->created_at    = now();
                $phoneverify->updated_at    = now();
                $phoneverify->save();
            }

            // if($user->country_code != "+91")
            // {
                $cleanCode = ltrim($user->country_code, '+');
                // $message = ""; 
                if($request->type == "forgot_password")
                {
                    $message = "Dear {$user->fullname},

You requested to reset your Mulk Med password. Use the code {$token} to proceed.

Regards,
Team Mulk Med.";
                    $result = EmailHelpers::sendSms($cleanCode . $request->phone_number, $message);

                }
                if($request->type == "forgot_username")
                {
                    $message = "Dear {$user->fullname},

You requested to reset your Mulk Med Username. Use the code {$token} to proceed.

Regards,
Team Mulk Med.";
                    $result = EmailHelpers::sendSms($cleanCode . $request->phone_number, $message);
                }
            // }
            // return response()->json($result);
            return response()->json(['status' => true, 'otp' => $token , 'message' => 'OTP sent successfully'], 200);
        }else{
            return response()->json(["status" => false,"message" => 'User with provided phone number does not exist']);
        }
    }

    public function forgetpasswordUsingEmail(Request $request)
    {
        $rules = [
            'email' => 'required|email',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }

        $user = Users::where('identity', $request->email)->first();
        if(isset($user))
        {
            Mail::to($user->identity)->send(new SendPasswordMail($user->password, $user->username, $user->fullname));
            return response()->json(["status" => true,"message" => 'An email with your password has been sent.']);
        }else{
            return response()->json(["status" => false,"message" => 'User with provided email does not exist']);
        }
    }

    public function forgetpasswordUsingMobileNumber(Request $request)
    {
        $rules = [
            'phone_number' => 'required',
            'password' => 'required',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }

        $user = Users::where('phone_number', $request->phone_number)->where('country_code',$request->country_code)->first();
        // $user = Users::where('phone_number', $request->phone_number)->first();
        if(isset($user))
        {
            $user->password = $request->password;
            $user->save();
            return response()->json(["status" => true,"message" => 'Your password has been updated successfully.',"password"=>$request->password]);
        }else{
            return response()->json(["status" => false,"message" => 'User with provided phone number does not exist']);
        }
    }

    function fetchUsersList(Request $request)
    {
        $totalData =  Users::count();
        $rows = Users::orderBy('id', 'DESC')->get();

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
            $result = Users::offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
        } else {
            $search = $request->input('search.value');
            $result =  Users::where(function ($query) use ($search) {
                $query->Where('identity', 'LIKE', "%{$search}%")
                    ->orWhere('fullname', 'LIKE', "%{$search}%");
            })->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
            $totalFiltered = Users::where(function ($query) use ($search) {
                $query->Where('identity', 'LIKE', "%{$search}%")
                    ->orWhere('fullname', 'LIKE', "%{$search}%");
            })->count();
        }
        $data = array();
        foreach ($result as $item) {

            if ($item->profile_image == null) {
                $image = '<img src="http://placehold.jp/150x150.png" width="50" height="50">';
            } else {
                $imgUrl = GlobalFunction::createMediaUrl($item->profile_image);
                $image = '<img src="' . $imgUrl . '" width="50" height="50">';
            }

            $appointmentCount = Appointments::where('user_id', $item->id)->count();

            $view = '<a href="' . route('viewUserProfile', $item->id) . '" class="mr-2 btn btn-info text-white " rel=' . $item->id . ' >' . __("View") . '</a>';

            $block = "";
            if ($item->is_block == 0) {
                $block = '<a href="" class="mr-2 btn btn-danger text-white block" rel=' . $item->id . ' >' . __("Block") . '</a>';
            } else {
                $block = '<a href="" class="mr-2 btn btn-success text-white unblock" rel=' . $item->id . ' >' . __("Unblock") . '</a>';
            }

            $action = $view  . $block;

            $data[] = array(
                $image,
                $item->identity,
                $item->fullname,
                $appointmentCount,
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

    function fetchUserWithdrawRequests(Request $request)
    {
        $rules = [
            'user_id' => 'required',
            'start' => 'required',
            'count' => 'required',
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

        $withdraws = UserWithdrawRequest::where('user_id', $user->id)
            ->offset($request->start)
            ->limit($request->count)
            ->orderBy('id', 'DESC')
            ->get();

        return GlobalFunction::sendDataResponse(true, 'withdraw requests fetched successfully!', $withdraws);
    }

    function submitUserWithdrawRequest(Request $request)
    {
        $rules = [
            'user_id' => 'required',
            'bank_title' => 'required',
            'account_number' => 'required',
            'holder' => 'required',
            'swift_code' => 'required',
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
        if ($user->wallet < 1) {
            return response()->json(['status' => false, 'message' => "Not enough balance to withdraw!"]);
        }

        $withdraw = new UserWithdrawRequest();
        $withdraw->user_id = $user->id;
        $withdraw->request_number = GlobalFunction::generateUserWithdrawRequestNumber();
        $withdraw->bank_title = GlobalFunction::cleanString($request->bank_title);
        $withdraw->amount = $user->wallet;
        $withdraw->account_number = GlobalFunction::cleanString($request->account_number);
        $withdraw->holder = GlobalFunction::cleanString($request->holder);
        $withdraw->swift_code = GlobalFunction::cleanString($request->swift_code);
        $withdraw->save();

        $summary = 'Withdraw request :' . $withdraw->request_number;
        // Adding wallet statement
        GlobalFunction::addUserStatementEntry(
            $user->id,
            null,
            $user->wallet,
            Constants::debit,
            Constants::withdraw,
            $summary
        );

        //resetting users wallet
        $user->wallet = 0;
        $user->save();

        return GlobalFunction::sendSimpleResponse(true, 'withdraw request submitted successfully!');
    }

    function fetchMyUserDetails(Request $request)
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

        $user = GlobalFunction::generateUserFullData($user->id);

        return GlobalFunction::sendDataResponse(true, 'user data fetched successfully', $user);
    }

    function fetchPatients(Request $request)
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

        $patients = AddedPatients::where('user_id', $user->id)->get();

        return GlobalFunction::sendDataResponse(true, 'patients data fetched successfully', $patients);
    }

    function addMoneyToUserWallet(Request $request)
    {
        $rules = [
            'user_id' => 'required',
            'amount' => 'required',
            'transaction_id' => 'required',
            'transaction_summary' => 'required',
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
        $user->wallet = $user->wallet + $request->amount;
        $user->save();
        // Adding Statement entry
        GlobalFunction::addUserStatementEntry(
            $user->id,
            null,
            $request->amount,
            Constants::credit,
            Constants::deposit,
            $request->transaction_summary
        );
        // Recharge Wallet History
        $rechargeLog = new UserWalletRechargeLogs();
        $rechargeLog->user_id = $user->id;
        $rechargeLog->amount = $request->amount;
        $rechargeLog->gateway = $request->gateway;
        $rechargeLog->transaction_id = $request->transaction_id;
        $rechargeLog->transaction_summary = $request->transaction_summary;
        $rechargeLog->save();

        return GlobalFunction::sendSimpleResponse(true, 'Money added to wallet successfully!');
    }

    function fetchUserWalletRechargeLogsList(Request $request)
    {
        $userId = $request->userId;
        $totalData =  UserWalletRechargeLogs::where('user_id', $userId)->count();
        $rows = UserWalletRechargeLogs::where('user_id', $userId)->orderBy('id', 'DESC')->get();
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
            $result = UserWalletRechargeLogs::where('user_id', $userId)
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
        } else {
            $search = $request->input('search.value');
            $result =  UserWalletRechargeLogs::where('user_id', $userId)
                ->where(function ($query) use ($search) {
                    $query->Where('amount', 'LIKE', "%{$search}%")
                        ->orWhere('transaction_summary', 'LIKE', "%{$search}%")
                        ->orWhere('transaction_id', 'LIKE', "%{$search}%");
                })
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
            $totalFiltered = UserWalletRechargeLogs::where('user_id', $userId)
                ->where(function ($query) use ($search) {
                    $query->Where('amount', 'LIKE', "%{$search}%")
                        ->orWhere('transaction_summary', 'LIKE', "%{$search}%")
                        ->orWhere('transaction_id', 'LIKE', "%{$search}%");
                })
                ->count();
        }
        $data = array();
        foreach ($result as $item) {

            $gateway = GlobalFunction::detectPaymentGateway($item->gateway);

            $data[] = array(
                $settings->currency . $item->amount,
                $gateway,
                $item->transaction_id,
                $item->transaction_summary,
                GlobalFunction::formateTimeString($item->created_at),
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

    function fetchUserPatientsList(Request $request)
    {
        $userId = $request->userId;
        $totalData =  AddedPatients::where('user_id', $userId)->count();
        $rows = AddedPatients::where('user_id', $userId)->orderBy('id', 'DESC')->get();
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
            $result = AddedPatients::where('user_id', $userId)
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
        } else {
            $search = $request->input('search.value');
            $result =  AddedPatients::where('user_id', $userId)
                ->where(function ($query) use ($search) {
                    $query->Where('fullname', 'LIKE', "%{$search}%")
                        ->orWhere('relation', 'LIKE', "%{$search}%");
                })
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
            $totalFiltered = AddedPatients::where('user_id', $userId)
                ->where(function ($query) use ($search) {
                    $query->Where('fullname', 'LIKE', "%{$search}%")
                        ->orWhere('relation', 'LIKE', "%{$search}%");
                })
                ->count();
        }
        $data = array();
        foreach ($result as $item) {

            $imgUrl = "http://placehold.jp/150x150.png";
            if ($item->image == null) {
                $img = '<img src="http://placehold.jp/150x150.png" width="50" height="50">';
            } else {
                $imgUrl = GlobalFunction::createMediaUrl($item->image);
                $img = '<img src="' . $imgUrl . '" width="50" height="50">';
            }

            $gender = '';
            if ($item->gender == Constants::genderMale) {
                $gender = '<span  class="badge bg-primary text-white ">' . __("Male") . '</span>';
            }
            if ($item->gender == Constants::genderFemale) {
                $gender = '<span  class="badge bg-info text-white ">' . __("Female") . '</span>';
            }

            $data[] = array(
                $img,
                $item->fullname,
                $item->age,
                $gender,
                $item->relation,
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

    function fetchUserWithdrawRequestsList(Request $request)
    {
        $userId = $request->userId;
        $totalData =  UserWithdrawRequest::where('user_id', $userId)->with(['user'])->count();
        $rows = UserWithdrawRequest::where('user_id', $userId)->with(['user'])->orderBy('id', 'DESC')->get();
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
            $result = UserWithdrawRequest::where('user_id', $userId)->with(['user'])
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
        } else {
            $search = $request->input('search.value');
            $result = UserWithdrawRequest::where('user_id', $userId)->with(['user'])
                ->where(function ($query) use ($search) {
                    $query->where('request_number', 'LIKE', "%{$search}%")
                        ->orWhere('amount', 'LIKE', "%{$search}%")
                        ->orWhere('summary', 'LIKE', "%{$search}%");
                })
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
            $totalFiltered = UserWithdrawRequest::where('user_id', $userId)->with(['user'])
                ->where(function ($query) use ($search) {
                    $query->where('request_number', 'LIKE', "%{$search}%")
                        ->orWhere('amount', 'LIKE', "%{$search}%")
                        ->orWhere('summary', 'LIKE', "%{$search}%");
                })
                ->count();
        }
        $data = array();
        foreach ($result as $item) {

            $holder = '<span class="text-dark font-weight-bold font-14">' . $item->holder . '</span>';
            $bank_title = '<div class="bank-details"><span>' . $item->bank_title . '</span>';
            $account_number = '<span>' . __('Account : ') .  $item->account_number . '</span>';
            $swift_code = '<span>' . __('Swift Code : ') . $item->swift_code . '</span></div>';
            $bankDetails = $holder . $bank_title . $account_number . $swift_code;

            $complete = '<a href="" class="mr-2 btn btn-success text-white complete" rel=' . $item->id . ' >' . __("Complete") . '</a>';
            $reject = '<a href="" class="mr-2 btn btn-danger text-white reject" rel=' . $item->id . ' >' . __("Reject") . '</a>';
            // $delete = '<a href="" class="mr-2 btn btn-danger text-white delete" rel=' . $item->id . ' >' . __("Delete") . '</a>';
            $action = '';

            // Amount & Status
            $amount = '<span class="text-dark font-weight-bold font-16">' . $settings->currency . $item->amount . '</span><br>';
            $status = "";
            if ($item->status == Constants::statusWithdrawalPending) {
                $status = '<span class="badge bg-warning text-white"rel="' . $item->id . '">' . __('Pending') . '</span>';
                $action =  $complete . $reject;
            }
            if ($item->status == Constants::statusWithdrawalCompleted) {
                $status = '<span class="badge bg-success text-white"rel="' . $item->id . '">' . __('Completed') . '</span>';
            }
            if ($item->status == Constants::statusWithdrawalRejected) {
                $status = '<span class="badge bg-danger text-white"rel="' . $item->id . '">' . __('Rejected') . '</span>';
            }
            $amountData = $amount . $status;

            $data[] = array(
                $item->request_number,
                $bankDetails,
                $amountData,
                GlobalFunction::formateTimeString($item->created_at),
                $item->summary,
                $action
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

    function fetchUserWalletStatementList(Request $request)
    {
        $totalData =  UserWalletStatements::where('user_id', $request->userId)->count();
        $rows = UserWalletStatements::where('user_id', $request->userId)->orderBy('id', 'DESC')->get();
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
            $result = UserWalletStatements::where('user_id', $request->userId)
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
        } else {
            $search = $request->input('search.value');
            $result =  UserWalletStatements::where('user_id', $request->userId)
                ->where(function ($query) use ($search) {
                    $query->Where('appointment_number', 'LIKE', "%{$search}%")
                        ->orWhere('transaction_id', 'LIKE', "%{$search}%")
                        ->orWhere('summary', 'LIKE', "%{$search}%")
                        ->orWhere('created_at', 'LIKE', "%{$search}%")
                        ->orWhere('amount', 'LIKE', "%{$search}%");
                })
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
            $totalFiltered = UserWalletStatements::where('user_id', $request->userId)
                ->where(function ($query) use ($search) {
                    $query->Where('appointment_number', 'LIKE', "%{$search}%")
                        ->orWhere('transaction_id', 'LIKE', "%{$search}%")
                        ->orWhere('summary', 'LIKE', "%{$search}%")
                        ->orWhere('created_at', 'LIKE', "%{$search}%")
                        ->orWhere('amount', 'LIKE', "%{$search}%");
                })
                ->count();
        }
        $data = array();
        foreach ($result as $item) {

            $cr_dr = $item->cr_or_dr;
            $icon = '';
            $textClass = '';
            $crDrBadge = '';

            if ($cr_dr == Constants::credit) {
                $icon =  '<i class="fas fa-plus-circle m-1 ic-credit"></i>';
                $textClass = 'text-credit';
                $crDrBadge = '<span  class="badge bg-success text-white ">' . __("Credit") . '</span>';
            } else {
                $icon =  '<i class="fas fa-minus-circle m-1 ic-debit"></i>';
                $textClass = 'text-debit';
                $crDrBadge = '<span  class="badge bg-danger text-white ">' . __("Debit") . '</span>';
            }
            $transaction = $icon . '<span class=' . $textClass . '>' . $item->transaction_id . '</span>';

            $data[] = array(
                $transaction,
                $item->summary,
                $settings->currency . $item->amount,
                $crDrBadge,
                GlobalFunction::formateTimeString($item->created_at),
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

    function editPatient(Request $request)
    {
        $rules = [
            'patient_id' => 'required',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }

        $patient = AddedPatients::find($request->patient_id);
        if ($patient == null) {
            return GlobalFunction::sendSimpleResponse(false, 'Patient does not exists !');
        }

        if ($request->has('fullname')) {
            $patient->fullname = GlobalFunction::cleanString($request->fullname);
        }
        if ($request->has('gender')) {
            $patient->gender = $request->gender;
        }
        if ($request->has('age')) {
            $patient->age = $request->age;
        }
        if ($request->has('relation')) {
            $patient->relation = GlobalFunction::cleanString($request->relation);
        }
        if ($request->has('image')) {
            $patient->image =
                GlobalFunction::saveFileAndGivePath($request->image);
        }
        $patient->save();

        return GlobalFunction::sendSimpleResponse(true, 'Patient updated successfully');
    }

    function deletePatient(Request $request)
    {
        $rules = [
            'patient_id' => 'required',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }

        $patient = AddedPatients::find($request->patient_id);
        if ($patient == null) {
            return GlobalFunction::sendSimpleResponse(false, 'Patient does not exists !');
        }

        $patient->delete();

        return GlobalFunction::sendSimpleResponse(true, 'Patient deleted successfully');
    }

    function addPatient(Request $request)
    {
        $rules = [
            'user_id' => 'required',
            'fullname' => 'required',
            'gender' => 'required',
            'age' => 'required',
            'relation' => 'required',
            'image' => 'required',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }

        $user = Users::find($request->user_id);
        if ($user == null) {
            return GlobalFunction::sendSimpleResponse(false, 'User does not exists !');
        }

        $patient = new AddedPatients();
        $patient->user_id = $request->user_id;
        $patient->fullname = GlobalFunction::cleanString($request->fullname);
        $patient->gender = $request->gender;
        $patient->age = $request->age;
        $patient->relation = GlobalFunction::cleanString($request->relation);
        $patient->image = GlobalFunction::saveFileAndGivePath($request->image);
        $patient->save();

        return GlobalFunction::sendSimpleResponse(true, 'Patient Added successfully');
    }

    function fetchNotification(Request $request)
    {
        $rules = [
            'start' => 'required',
            'count' => 'required',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }

        $notifications = UserNotification::offset($request->start)
            ->limit($request->count)
            ->orderBy('id', 'DESC')
            ->get();

        return GlobalFunction::sendDataResponse(true, 'Data fetched successfully', $notifications);
    }

    function fetchHomePageData(Request $request)
    {
        // $rules = [
        //     'user_id' => 'required',
        // ];

        // $validator = Validator::make($request->all(), $rules);
        // if ($validator->fails()) {
        //     $messages = $validator->errors()->all();
        //     $msg = $messages[0];
        //     return response()->json(['status' => false, 'message' => $msg]);
        // }

        $appointments = [];
        if($request->has('user_id'))
        {
            $user = Users::find($request->user_id);
            if ($user == null) {
                return GlobalFunction::sendSimpleResponse(false, 'User does not exists !');
            }

            $appointments = Appointments::where('user_id', $user->id)->where('date', $request->date)
                ->with(['doctor', 'documents'])->where('status', Constants::orderAccepted)->get();
        }

        if($request->has('search'))
        {
            $cats = DoctorCategories::where('is_deleted', 0)->where("title", 'like', '%' . $request->search . '%')->get();
        }else{
            $cats = DoctorCategories::where('is_deleted', 0)->get();
        }
        foreach ($cats as $cat) {
            $doctors = Doctors::with('expertise')->where('category_id', $cat->id)
                ->where('status', Constants::statusDoctorApproved)
                ->where('on_vacation', Constants::doctorNotOnVacation)
                ->get();
            $cat->doctors = $doctors;

            $banners = Banners::where('section_id',$cat->id)->where('is_deleted',0)->get();
            $cat->banners = $banners;
        }

        if($request->has('search'))
        {
            $chp = CommonHealthProblems::select('common_health_problems.id','common_health_problems.problem', 'common_health_problems.image', 'doctor_cats.title as speciality', 
                'common_health_problems.priority')->join('doctor_cats', 'doctor_cats.id', 'common_health_problems.speciality')
                ->where("common_health_problems.is_deleted", 0)
                ->where("common_health_problems.problem", 'like', '%' . $request->search . '%')
                ->orderBy('priority')
                ->get();
        }
        else{
            $chp = CommonHealthProblems::select('common_health_problems.id','common_health_problems.problem', 'common_health_problems.image', 'doctor_cats.title as speciality', 
                'common_health_problems.priority')->join('doctor_cats', 'doctor_cats.id', 'common_health_problems.speciality')
                ->where("common_health_problems.is_deleted", 0)
                ->orderBy('priority')->get();
        }
        foreach ($chp as $key => $value) {
            if($request->has('lang'))
            {
                if($request->lang == 'hi')
                {
                    $lang = $request->get('lang', 'hi');
                    app()->setLocale($lang);
                    $value->problem = __($value->problem);
                    $value->speciality = __($value->speciality);
                }
                if($request->lang == 'ur')
                {
                    $lang = $request->get('lang', 'ur');
                    app()->setLocale($lang);
                    $value->problem = __($value->problem);
                    $value->speciality = __($value->speciality);
                }
                if($request->lang == 'ar')
                {
                    $lang = $request->get('lang', 'ar');
                    app()->setLocale($lang);
                    $value->problem = __($value->problem);
                    $value->speciality = __($value->speciality);
                }
                if($request->lang == 'fr')
                {
                    $lang = $request->get('lang', 'fr');
                    app()->setLocale($lang);
                    $value->problem = __($value->problem);
                    $value->speciality = __($value->speciality);
                }
            }
        }

        $speciality_wise_disease = SpecialityWiseDisease::select('doctor_cats.title as speciality','speciality_wise_disease.speciality as speciality_id')
                ->join('doctor_cats', 'doctor_cats.id', 'speciality_wise_disease.speciality')
                ->where("speciality_wise_disease.is_deleted", 0)
                ->orderBy('speciality_wise_disease.priority')
                ->distinct('speciality_wise_disease.speciality')->get();
        foreach ($speciality_wise_disease as $key => $disease) {
            if($request->has('search'))
            {
                $speciality_wise_disease[$key]->disease = SpecialityWiseDisease::select('speciality_wise_disease.id','speciality_wise_disease.problem', 'speciality_wise_disease.image', 
                    'speciality_wise_disease.priority')
                    ->where("speciality_wise_disease.is_deleted", 0)
                    ->where('speciality_wise_disease.speciality' , $disease->speciality_id)
                    ->where("speciality_wise_disease.problem", 'like', '%' . $request->search . '%')
                    ->orderBy('priority')->get();
            }else{
                $speciality_wise_disease[$key]->disease = SpecialityWiseDisease::select('speciality_wise_disease.id','speciality_wise_disease.problem', 'speciality_wise_disease.image', 
                    'speciality_wise_disease.priority')
                    ->where("speciality_wise_disease.is_deleted", 0)
                    ->where('speciality_wise_disease.speciality' , $disease->speciality_id)
                    ->orderBy('priority')->get();
            }
        }
        $main_banners = Banners::where('section',"Video Consultation")->where('is_deleted',0)->get();

        if($request->has('search'))
        {
            $doctors = Doctors::with('expertise')
                            ->where('status', Constants::statusDoctorApproved)
                            ->where('on_vacation', Constants::doctorNotOnVacation)
                            ->where("name", 'like', '%' . $request->search . '%')
                            ->get();
        }else{
            $doctors = [];
        }
        return response()->json([
            'status' => true,
            'message' => 'data fetched successfully!',
            // 'categories' => $cats,
            'doctors' => $doctors,
            // 'appointments' => $appointments,
            'common_health_problems' => $chp,
            // 'speciality_wise_disease' => $speciality_wise_disease,
            'main banners'=> $main_banners
        ]);
    }

    function fetchWalletStatement(Request $request)
    {
        $rules = [
            'user_id' => 'required',
            'start' => 'required',
            'count' => 'required',
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
        $statement = UserWalletStatements::where('user_id', $user->id)
            ->offset($request->start)
            ->limit($request->count)
            ->orderBy('id', 'DESC')
            ->get();

        return GlobalFunction::sendDataResponse(true, 'Statement Data fetched successfully!', $statement);
    }

    function logOut(Request $request)
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

        $user = Users::where('id', $request->user_id)->first();
        if ($user == null) {
            return GlobalFunction::sendSimpleResponse(false, 'User does not exists !');
        }

        $user->device_token = null;
        $user->save();

        return GlobalFunction::sendSimpleResponse(true, 'user log out successfully');
    }

    function deleteUserAccount(Request $request)
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

        $user = Users::where('id', $request->user_id)->first();
        if ($user == null) {
            return GlobalFunction::sendSimpleResponse(false, 'User does not exists !');
        }

        AddedPatients::where('user_id', $user->id)->delete();
        UserWalletStatements::where('user_id', $user->id)->delete();
        UserWithdrawRequest::where('user_id', $user->id)->delete();

        $user->delete();

        return GlobalFunction::sendSimpleResponse(true, 'user data deleted successfully');
    }

    function updateUserDetails(Request $request)
    {
        $rules = [
            'identity' => 'required',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }

        $user = Users::where('identity', $request->identity)->first();
        if ($user == null) {
            return GlobalFunction::sendSimpleResponse(false, 'User does not exists !');
        }

        if ($request->has('fullname')) {
            $user->fullname = GlobalFunction::cleanString($request->fullname);
        }
        if ($request->has('profile_image')) {
            $user->profile_image = GlobalFunction::saveFileAndGivePath($request->file('profile_image'));
        }
        if ($request->has('saved_reels')) {
            $user->saved_reels = $request->saved_reels;
        }
        if ($request->has('country_code')) {
            $user->country_code = $request->country_code;
        }
        if ($request->has('phone_number')) {
            $user->phone_number = $request->phone_number;
        }
        if ($request->has('gender')) {
            $user->gender = $request->gender;
        }
        if ($request->has('dob')) {
            $user->dob = $request->dob;
        }
        if ($request->has('favourite_doctors')) {
            $user->favourite_doctors = $request->favourite_doctors;
        }
        if ($request->has('is_notification')) {
            $user->is_notification = $request->is_notification;
        }

        $user->save();

        $user = GlobalFunction::generateUserFullData($user->id);

        return GlobalFunction::sendDataResponse(true, 'user details updated successfully', $user);
    }

    function fetchFavoriteDoctors(Request $request)
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

        $user = Users::where('id', $request->user_id)->first();
        if ($user == null) {
            return GlobalFunction::sendSimpleResponse(false, 'User does not exists !');
        }

        $doctors = Doctors::whereIn('id', explode(',', $user->favourite_doctors))->with([
            'services',
            'experience',
            'expertise',
            'serviceLocations',
            'awards',
            'slots',
            'holidays',
        ])->get();

        return GlobalFunction::sendDataResponse(true, 'user details updated successfully', $doctors);
    }

    function registerUser(Request $request)
    {
        $rules = [
            // 'identity'      => 'required',
            'device_type'   => [Rule::in(1, 2)],
            'device_token'  => 'required',
            'fullname'      => 'required',
            'login_type'    => [Rule::in(1, 2, 3)],
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages   = $validator->errors()->all();
            $msg        = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }

        if($request->has('identity') && $request->identity != null){
            $user_with_email = $user = Users::where('identity', $request->identity)->first();

            if(isset($user_with_email))
            {
                return response()->json(['status' => false, 'message' => "user with entered email is already exist"]);
            }
        }

        $user_with_phone = $user = Users::where('phone_number', $request->phone_number)->where('country_code',$request->country_code)->first();
        // $user_with_phone = $user = Users::where('phone_number', $request->phone_number)->first();

        if(isset($user_with_phone))
        {
            return response()->json(['status' => false, 'message' => "user with entered phone number is already exist"]);
        }

        // $user = Users::where('identity', $request->identity)->first();

        // if($request->is_login == 1 && $user == null){
        //        return GlobalFunction::sendSimpleResponse(false, 'user not found');
        // }        

        // if ($user != null && $request->is_login == 1) {
        //     $user->device_type  = $request->device_type;
        //     $user->device_token = $request->device_token;
        //     $user->login_type   = $request->login_type;
        //     $user->save();

        //     $user = Users::find($user->id);

        //     return GlobalFunction::sendDataResponse(true, 'User exists already', $user);
        // } else {
        $user               = new Users();
        $user->identity     = $request->has('identity') ? $request->identity : null;
        $user->device_type  = $request->device_type;
        $user->device_token = $request->device_token;
        $user->email        = $request->has('identity') ? $request->identity : null;
        $user->fullname     = $request->fullname;
        $user->username     = $request->username;
        $user->password     = $request->password;
        $user->phone_number = $request->phone_number;
        $user->country_code = $request->country_code;
        $user->dob          = $request->dob;
        $user->gender       = $request->gender;
        $user->login_type   = $request->login_type;
        $user->app_version  = $request->app_version;
        $user->device_details  = $request->device_details;
        $user->save();

        $user = User::where('phone_number',$request->phone_number)->first();
        $token  = $user->createToken('auth_token')->plainTextToken;

        $user = Users::find($user->id);

        return response()->json(['status' => true,'token' => $token , 'user' => $user]);
        // }
    }

    function loginUser(Request $request)
    {
        $user = User::where('username',$request->username)->first();
        if($user)
        {
            $user = User::where('username',$request->username)->where('password',$request->password)->first();
            if($user)
            {
                $token  = $user->createToken('auth_token')->plainTextToken;
                $user->app_version  = $request->app_version;
                $user->device_details  = $request->device_details;
                $user->save();
                return response()->json(['status' => true,'token' => $token , 'user' => $user]);
            }
            else{
                return response()->json(['message' => 'Invalid credentials'], 401);
            }
        }else{
            return response()->json(['status' => false, 'message' => "user does not exist"]);
        }
    }

    function checkAvailability(Request $request)
    {
        $user = User::where('username',$request->username)->first();
        if(isset($user))
        {
            return response()->json(['status' => false, 'message' => "user already exist"]);
        }
        else{
            return response()->json(['status' => true, 'message' => "user does not exist"]);
        }
    }

    function dashboard(Request $request)
    {
        $sections = SectionSequence::select('id','section_name')->where('is_deleted', 0)->where('status',1)->orderBy('position', 'ASC')->get();

        $sectionSequence = [];

        foreach ($sections as $key => $sequence) {
            if($sequence->section_type == 'doctors_section')
            {
                $section = Doctors::with('expertise')
                                ->where('status', Constants::statusDoctorApproved)
                                ->where('on_vacation', Constants::doctorNotOnVacation)
                                ->orderBy('is_online', 'DESC')
                                ->get();
                if($section != [])
                {
                    $sequence->section_data = $section;
                    array_push($sectionSequence,$sequence);
                }
            }


            if($sequence->section_name == 'mulk_symptoms_checker')
            {
                $section = DashboardBanners::where('name', 'Mulkmed symptom checker')
                                ->where('is_deleted', 0)
                                ->get();
                if($section != [])
                {
                    $sequence->section_data = $section;
                    array_push($sectionSequence,$sequence);
                }
            }

            if($sequence->section_name == 'ads')
            {
                $section = DashboardBanners::where('name', 'ads banner')
                                ->where('is_deleted', 0)
                                ->get();
                if($section != [])
                {
                    $sequence->section_data = $section;
                    array_push($sectionSequence,$sequence);
                }
            }

            if($sequence->section_name == 'doctors_by_speciality')
            {
                $categories = DoctorCategories::where('is_deleted', 0)->get();
                foreach ($categories as $cat) {
                    $doctors = Doctors::with('expertise')->where('category_id', $cat->id)
                        ->where('status', Constants::statusDoctorApproved)
                        ->where('on_vacation', Constants::doctorNotOnVacation)
                        ->get();
                    $cat->doctors = $doctors;

                    $banners = Banners::where('section_id',$cat->id)->where('is_deleted',0)->get();
                    $cat->banners = $banners;
                    if($request->has('lang'))
                    {
                        if($request->lang == 'hi')
                        {
                            $lang = $request->get('lang', 'hi');
                            app()->setLocale($lang);
                            $cat->title = __($cat->title);
                        }
                        if($request->lang == 'ur')
                        {
                            $lang = $request->get('lang', 'ur');
                            app()->setLocale($lang);
                            $cat->title = __($cat->title);
                        }
                        if($request->lang == 'ar')
                        {
                            $lang = $request->get('lang', 'ar');
                            app()->setLocale($lang);
                            $cat->title = __($cat->title);
                        }
                        if($request->lang == 'fr')
                        {
                            $lang = $request->get('lang', 'fr');
                            app()->setLocale($lang);
                            $cat->title = __($cat->title);
                        }
                    }
                }

                $section = $categories;
                if($section != [])
                {
                    $sequence->section_data = $section;
                    array_push($sectionSequence,$sequence);
                }
            }

            if($sequence->section_name == 'mulk_ai_vitals')
            {
                $section = DashboardBanners::where('name', 'MULK AI Vitals')
                                ->where('is_deleted', 0)
                                ->get();
                if($section != [])
                {
                    $sequence->section_data = $section;
                    array_push($sectionSequence,$sequence);
                }
            }

            if($sequence->section_name == 'mulk_hnh')
            {
                $section = DashboardBanners::where('name', 'MULK HnH')
                                ->where('is_deleted', 0)
                                ->get();
                if($section != [])
                {
                    $sequence->section_data = $section;
                    array_push($sectionSequence,$sequence);
                }
            }

            if($sequence->section_name == 'orders_prescription')
            {
                $section = DashboardBanners::where('name', 'Orders Prescription')
                                ->where('is_deleted', 0)
                                ->get();
                if($section != [])
                {
                    $sequence->section_data = $section;
                    array_push($sectionSequence,$sequence);
                }
            }

            if($sequence->section_name == 'mulk_senior_card')
            {
                $section = DashboardBanners::where('name', 'Mulk Senior Card')
                                ->where('is_deleted', 0)
                                ->get();
                if($section != [])
                {
                    $sequence->section_data = $section;
                    array_push($sectionSequence,$sequence);
                }
            }

            if($sequence->section_name == 'doctors_by_symptoms')
            {
                $doctors_by_symptoms = DoctorsBySymptoms::select('doctors_by_symptoms.id','doctors_by_symptoms.problem', 'doctors_by_symptoms.image', 'doctor_cats.title as speciality', 
                                        'doctors_by_symptoms.priority')->join('doctor_cats', 'doctor_cats.id', 'doctors_by_symptoms.speciality')
                                        ->where("doctors_by_symptoms.is_deleted", 0)
                                        ->orderBy('priority')->get();

                foreach ($doctors_by_symptoms as $key => $doctors_by_symptom) {
                    if($request->has('lang'))
                    {
                        if($request->lang == 'hi')
                        {
                            $lang = $request->get('lang', 'hi');
                            app()->setLocale($lang);
                            $doctors_by_symptom->problem = __($doctors_by_symptom->problem);
                        }
                        if($request->lang == 'ur')
                        {
                            $lang = $request->get('lang', 'ur');
                            app()->setLocale($lang);
                            $doctors_by_symptom->problem = __($doctors_by_symptom->problem);
                        }
                        if($request->lang == 'ar')
                        {
                            $lang = $request->get('lang', 'ar');
                            app()->setLocale($lang);
                            $doctors_by_symptom->problem = __($doctors_by_symptom->problem);
                        }
                        if($request->lang == 'fr')
                        {
                            $lang = $request->get('lang', 'fr');
                            app()->setLocale($lang);
                            $doctors_by_symptom->problem = __($doctors_by_symptom->problem);
                        }
                    }
                }
                $section = $doctors_by_symptoms;
                if($section != [])
                {
                    $sequence->section_data = $section;
                    array_push($sectionSequence,$sequence);
                }
            }

            if($sequence->section_name == 'best_offers')
            {
                $section = DashboardBanners::where('name', 'Best Offers')
                                ->where('is_deleted', 0)
                                ->get();
                if($section != [])
                {
                    $sequence->section_data = $section;
                    array_push($sectionSequence,$sequence);
                }
            }

            if($sequence->section_name == 'surgical_cost_estimate')
            {
                $section = DashboardBanners::where('name', 'Surgical Cost estimation')
                                ->where('is_deleted', 0)
                                ->get();
                if($section != [])
                {
                    $sequence->section_data = $section;
                    array_push($sectionSequence,$sequence);
                }
            }

            if($sequence->section_name == 'tourist_gold_card')
            {
                $section = DashboardBanners::where('name', 'Tourist gold card')
                                ->where('is_deleted', 0)
                                ->get();
                if($section != [])
                {
                    $sequence->section_data = $section;
                    array_push($sectionSequence,$sequence);
                }
            }

            if ($sequence->section_name == 'partners_network') {
                $section = PartnersTable::where('is_deleted', 0)->get();

                if ($section->isNotEmpty()) {
                    // view URL for each partner
                    $section->map(function ($item) {
                        $partner = PartnersTable::find($item->id);
                        $imgUrl  = GlobalFunction::createMediaUrl($partner->image);

                        $item->data = view('partner_network.viewPartner', compact('partner', 'imgUrl'))->render();

                        return $item;
                    });

                    $sequence->section_data = $section;
                    array_push($sectionSequence, $sequence);
                }
            }
        }

        if ($request->has('user_id')) {
          $appointments = Appointments::where('user_id', $request->user_id)
            ->where('status', Constants::orderAccepted)
            ->where(function ($q) {
                $q->where('date', '>=', now()->toDateString())
                ->orWhere(function ($q2) {
                    $q2->where('date','>=', now()->toDateString())
                        ->where('time', '>=', now()->addHour()->format('Hi')); // compare properly
                });
            })
            ->orderBy('date', 'asc')
            ->orderBy('time', 'asc')
            ->get();

            if ($appointments->count()) {

                $appointmentsWithJitsi = [];

                foreach ($appointments as $appointment) {
                    $jitsi_meeting = JitsiMeeting::where('appointment_id', $appointment->id)->first();
                    if ($jitsi_meeting) {
                        $appointment->jitsi_link = $jitsi_meeting->link;
                        $appointment->image = asset('storage/uploads/dashboard_appointment_banner.png');
                        $appointmentsWithJitsi[] = $appointment;
                    }
                }

                if (count($appointmentsWithJitsi)) {
                    $appointmentBanner = new \stdClass();
                    $appointmentBanner->id = 2;
                    $appointmentBanner->section_name = "appointment_banner";
                    $appointmentBanner->section_data = $appointmentsWithJitsi;

                    array_splice($sectionSequence, 1, 0, [$appointmentBanner]);

                    foreach ($sectionSequence as $index => $sec) {
                        $sec->id = $index + 1;
                    }
                }
            }

        }


        return response()->json([
            'status' => true, 
            'sectionSequence' => $sectionSequence,
        ]);
    }

    function AIVitals(Request $request)
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

        $ai_vitals = new AI_Vital();
        $ai_vitals->user_id = $request->user_id;
        $ai_vitals->appointment_id = $request->appointment_id;
        $ai_vitals->report = $request->report;
        if($request->has('pdf_file'))
        {
            $ai_vitals->pdf_file = GlobalFunction::saveFileAndGivePath($request->pdf_file);
        }
        $ai_vitals->save();
        // Log::info('AI Vitals Response'. $request);
        return response()->json([
            'status' => true, 
            'message' => "Data saved successfully",
        ]);
    }

    function AIVitalsMisa(Request $request)
    {
        $rules = [
            'order_id' => 'required',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }

        $ai_vitals = AIVitalScanMisa::where('order_id', $request->order_id)->first();
        if($ai_vitals && $ai_vitals->payment_status == 1)
        {
            $ai_vitals->report = $request->report;
            if($request->has('report_from'))
            {
                $ai_vitals->report_from = $request->report_from;
            }
            if($request->has('pdf_file'))
            {
                $ai_vitals->pdf_file = GlobalFunction::saveFileAndGivePath($request->pdf_file);
            }
            $ai_vitals->save();
            // Log::info('AI Vitals Response'. $request);
            if($ai_vitals->report_from == "ai_vital"){
                return response()->json([
                    'status' => true,
                    'category_id' => 5,
                    'message' => "Data saved successfully",
                ]);
            }
            return response()->json([
                'status' => true, 
                'message' => "Data saved successfully",
            ]);
        }

        else{
            return response()->json([
                'status' => false, 
                'message' => "Data not saved",
            ]);
        }
        
    }

    function translate(Request $request)
    {
        $categories = DoctorCategories::where('is_deleted', 0)->get();
        foreach ($categories as $cat) {
            $doctors = Doctors::with('expertise')->where('category_id', $cat->id)
                ->where('status', Constants::statusDoctorApproved)
                ->where('on_vacation', Constants::doctorNotOnVacation)
                ->get();
            $cat->doctors = $doctors;

            $banners = Banners::where('section_id',$cat->id)->where('is_deleted',0)->get();
            $cat->banners = $banners;
            if($request->has('lang'))
            {
                if($request->lang == 'hi')
                {
                    return $response = Helpers::translate("Hello, how are you?", "ur");
                    return $cat->title = $response;

                    // $lang = $request->get('lang', 'hi');
                    // app()->setLocale($lang);
                    // $cat->title = __($cat->title);
                }
                if($request->lang == 'ur')
                {
                    $response = Http::post('https://libretranslate.com/translate', [
                                        'q' => $cat->title,
                                        'source' => "en",
                                        'target' => "ur",
                                        'format' => 'text'
                                    ]);
                    $cat->title= $response;

                    // $lang = $request->get('lang', 'ur');
                    // app()->setLocale($lang);
                    // $cat->title = __($cat->title);
                }
                if($request->lang == 'ar')
                {
                    $lang = $request->get('lang', 'ar');
                    app()->setLocale($lang);
                    $cat->title = __($cat->title);
                }
                if($request->lang == 'fr')
                {
                    $lang = $request->get('lang', 'fr');
                    app()->setLocale($lang);
                    $cat->title = __($cat->title);
                }
            }
        }

        return $section = $categories;

    }

    function isabelQuestionsAnswers(Request $request){
        return IsabelQuestion::with('answer')->get();
    }

    function fetchOrderMedicinePageData(Request $request){

        $partners = PartnersTable::select('id', 'title', 'data', 'image', 'headline', 'hospital_name', 'address', 'website_link')->where('is_deleted', 0)->get();

        $order_medicine_banner = Banners::select('id', 'image', 'section', 'sub_section', 'section_id', 'page')->where('section', 'Oder Medicines Banner')->where('is_deleted', 0)->first();

        $categories = OrderMedicineCategories::select('id', 'title', 'image', 'info')->where('is_deleted', 0)->get();

         return response()->json([
            'status' => true,
            'message' => 'data fetched successfully!',
            'order_medicine_banner' => $order_medicine_banner,
            'categories' => $categories,
            'partners'=> $partners
        ]);
    }

    function AIPrescriptionRead(Request $request){
        $rules = [
            'prescription_file' => 'required|file'
        ];

        $validator = Validator::make($request->all(), $rules);

        if($validator->fails()) {
            return GlobalFunction::sendSimpleResponse(false, $validator->errors()->first());
        }

       return $prescription_file = $request->file('prescription_file');
    }
}
