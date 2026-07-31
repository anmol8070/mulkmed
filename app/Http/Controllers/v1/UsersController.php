<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Models\AddedPatients;
use App\Models\AppointmentDocs;
use App\Models\Appointments;
use App\Models\BiddingBanners;
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
use App\Models\BestOfferPlans;
use App\Models\PartnersTable;
use App\Models\SectionSequence;
use App\Models\HnHCards;
use App\Models\SeniorCards;
use App\Models\TouristCards;
use App\Models\DoctorExpertise;
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
use App\Models\UserCoupons;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Mail\AiVitalReportMail;
use App\Services\SenoclockAiService;
use Carbon\Carbon;
use App\Mail\AiVitalMesaReportMail;
use PDF;
use Illuminate\Http\UploadedFile;
use DB;

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
            $hostAndConversionRate = Helpers::conversionRate();
            $conversionRate = (float) $hostAndConversionRate['conversionRate'];
            $speciality = DoctorCategories::find($request->speciality_id);
            $lang = strtolower((string) ($request->get('lang') ?: $request->header('lang', 'en')));
            if (in_array($lang, ['hi', 'ur', 'ar', 'fr']) && $speciality) {
                $titleColumn = $lang . '_title';
                $infoColumn = $lang . '_info';

                $translatedTitle = (string) ($speciality->{$titleColumn} ?? '');
                $translatedInfo = (string) ($speciality->{$infoColumn} ?? '');

                if (trim($translatedTitle) !== '') {
                    $speciality->title = $translatedTitle;
                }
                if (trim($translatedInfo) !== '') {
                    $speciality->info = $translatedInfo;
                }
            }
            if ($speciality) {
                unset(
                    $speciality->ar_title,
                    $speciality->ar_info,
                    $speciality->fr_title,
                    $speciality->fr_info,
                    $speciality->ur_title,
                    $speciality->ur_info,
                    $speciality->hi_title,
                    $speciality->hi_info
                );
            }
            $doctors    = Doctors::select('doctors.*',DB::raw("ROUND(consultation_fee * {$conversionRate}) as consultation_fee"))
                            ->with('expertise')->where('category_id', $speciality->id)
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
            $lang = strtolower((string) ($request->header('lang') ?: $request->get('lang', 'en')));
            if (in_array($lang, ['hi', 'ur', 'ar', 'fr']) && $common_health_problems) {
                $problemColumn = $lang . '_problem';
                $infoColumn = $lang . '_info';

                $translatedProblem = (string) ($common_health_problems->{$problemColumn} ?? '');
                    $translatedInfo = (string) ($common_health_problems->{$infoColumn} ?? '');

                if (trim($translatedProblem) !== '') {
$common_health_problems->problem = $translatedProblem;
                }
                if (trim($translatedInfo) !== '') {
                    $common_health_problems->info = $translatedInfo;
                }
            }
            
            $specialities = json_decode($common_health_problems->speciality, true) ?? []; // ensure array

            $hostAndConversionRate = Helpers::conversionRate();
            $conversionRate = (float) $hostAndConversionRate['conversionRate'];
            if(is_array($specialities)){
                if (empty($specialities)) {
                    $doctors = collect(); // return empty collection (or handle as you prefer)
                } else {
                    if($request->problem_id == 38){
                        $doctors = Doctors::select('doctors.*',DB::raw("ROUND(consultation_fee * {$conversionRate}) as consultation_fee"))
                                        ->with('expertise')
                                        // ->whereIn('category_id', $specialities)
                                        ->where('is_smo', 1)
                                        ->where('status', Constants::statusDoctorApproved)
                                        ->where('on_vacation', Constants::doctorNotOnVacation)
                                        ->orderByRaw("CASE WHEN clinic_name = 'Aakash Hospital' THEN 0 ELSE 1 END")
                                        ->get();
                    }
                    elseif($request->problem_id == 32){
                        $doctors = Doctors::select('doctors.*',DB::raw("ROUND(consultation_fee * {$conversionRate}) as consultation_fee"))
                                        ->with('expertise')
                                        ->whereIn('category_id', $specialities)
                                        ->where('is_smo', 0)
                                        ->where('status', Constants::statusDoctorApproved)
                                        ->where('on_vacation', Constants::doctorNotOnVacation)
                                        ->orderByRaw("CASE WHEN clinic_name = 'Aloka Eye Clinic' THEN 0 ELSE 1 END")
                                        ->get();
                    }else{
                        $doctors = Doctors::select('doctors.*',DB::raw("ROUND(consultation_fee * {$conversionRate}) as consultation_fee"))
                                        ->with('expertise')
                                        ->whereIn('category_id', $specialities)
                                        ->where('is_smo', 0)
                                        ->where('status', Constants::statusDoctorApproved)
                                        ->where('on_vacation', Constants::doctorNotOnVacation)
                                        ->get();
                    }
                    
                }
                // $doctors    = Doctors::with('expertise')->where('category_id', $common_health_problems->speciality)
                //                 ->where('status', Constants::statusDoctorApproved)
                //                 ->where('on_vacation', Constants::doctorNotOnVacation)
                //                 ->get();
            }

            else{
                if($request->problem_id == 38){
                    $doctors = Doctors::select('doctors.*',DB::raw("ROUND(consultation_fee * {$conversionRate}) as consultation_fee"))
                                    ->with('expertise')
                                    // ->where('category_id', $common_health_problems->speciality)
                                    ->where('is_smo', 1)
                                    ->where('status', Constants::statusDoctorApproved)
                                    ->where('on_vacation', Constants::doctorNotOnVacation)
                                    ->orderByRaw("CASE WHEN clinic_name = 'Aakash Hospital' THEN 0 ELSE 1 END")
                                    ->get();
                }
                elseif($request->problem_id == 32){
                    $doctors = Doctors::select('doctors.*',DB::raw("ROUND(consultation_fee * {$conversionRate}) as consultation_fee"))
                                    ->with('expertise')
                                    ->where('category_id', $common_health_problems->speciality)
                                    ->where('is_smo', 0)
                                    ->where('status', Constants::statusDoctorApproved)
                                    ->where('on_vacation', Constants::doctorNotOnVacation)
                                    ->orderByRaw("CASE WHEN clinic_name = 'Aloka Eye Clinic' THEN 0 ELSE 1 END")
                                    ->get();
                }
                else{
                    $doctors = Doctors::select('doctors.*',DB::raw("ROUND(consultation_fee * {$conversionRate}) as consultation_fee"))
                                ->with('expertise')
                                ->where('category_id', $common_health_problems->speciality)
                                ->where('is_smo', 0)
                                ->where('status', Constants::statusDoctorApproved)
                                ->where('on_vacation', Constants::doctorNotOnVacation)
                                ->get();
                }
            }

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
            $hostAndConversionRate = Helpers::conversionRate();
            $conversionRate = (float) $hostAndConversionRate['conversionRate'];
            $doctors    = Doctors::select('doctors.*',DB::raw("ROUND(consultation_fee * {$conversionRate}) as consultation_fee"))
                            ->with('expertise')->where('category_id', $speciality_wise_disease->speciality)
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
        try {
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
        } catch (\Throwable $th) {
            return $th->getmessage();
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
        if($request->has('user_id') && $request->user_id != null)
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
            $hostAndConversionRate = Helpers::conversionRate();
            $conversionRate = (float) $hostAndConversionRate['conversionRate'];
            $doctors = Doctors::select('doctors.*',DB::raw("ROUND(consultation_fee * {$conversionRate}) as consultation_fee"))
                ->with('expertise')->where('category_id', $cat->id)
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
            $chp = CommonHealthProblems::select('common_health_problems.*', 'doctor_cats.title as speciality', 
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

            $lang = $request->header('lang','en');
            if($lang == 'en')
            {
                $value->problem = $value->problem;
            }
            if($lang == 'hi')
            {
                $value->problem = $value->hi_problem;
            }
            if($lang == 'ur')
            {
                $value->problem = $value->ur_problem;
            }
            if($lang == 'ar')
            {
                $value->problem = $value->ar_problem;
            }
            if($lang == 'fr')
            {
                $value->problem = $value->fr_problem;
            }
        }

        $speciality_wise_disease = SpecialityWiseDisease::select(
                                            'doctor_cats.title as speciality',
                                            'speciality_wise_disease.speciality as speciality_id',
                                            'speciality_wise_disease.priority'
                                        )
                                        ->join('doctor_cats', 'doctor_cats.id', 'speciality_wise_disease.speciality')
                                        ->where('speciality_wise_disease.is_deleted', 0)
                                        ->orderBy('speciality_wise_disease.priority')
                                        ->distinct('speciality_wise_disease.speciality')
                                        ->get();
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

        $hostAndConversionRate = Helpers::conversionRate();
        $conversionRate = (float) $hostAndConversionRate['conversionRate'];
        if($request->has('search'))
        {
            $search = $request->search;
            $doctor_ids = Doctors::select('doctors.*','doctor_cats.title as category_name',DB::raw("ROUND(consultation_fee * {$conversionRate}) as consultation_fee"))
                                ->with('expertise')
                                ->leftJoin('doctor_cats', 'doctors.category_id', '=', 'doctor_cats.id')
                                ->leftJoin('doctor_expertise', 'doctors.id', '=', 'doctor_expertise.doctor_id')
                                ->where('status', Constants::statusDoctorApproved)
                                ->where('on_vacation', Constants::doctorNotOnVacation)
                                ->where(function($query) use ($search) {
                                        $query->whereRaw('LOWER(doctors.name) LIKE ?', ["%{$search}%"])
                                            ->orWhereRaw('LOWER(doctors.designation) LIKE ?', ["%{$search}%"])
                                            ->orWhereRaw('LOWER(doctor_cats.title) LIKE ?', ["%{$search}%"])
                                            ->orWhereRaw('LOWER(doctor_cats.keywords) LIKE ?', ["%{$search}%"])
                                            ->orWhereRaw('LOWER(doctor_expertise.title) LIKE ?', ["%{$search}%"]);
                                    })
                                ->pluck('doctors.id')->toArray();
                                
            $doctor_ids = array_unique($doctor_ids);

            $query = Doctors::select('doctors.*')
                        ->with('expertise')
                        ->where('status', Constants::statusDoctorApproved)
                        ->where('on_vacation', Constants::doctorNotOnVacation)
                        ->whereIn('doctors.id', $doctor_ids);

            if($request->has('availability'))
            {
                $availability = intval($request->availability);

                if ($availability === 1) {
                    $query = $query->where('is_online',1);
                } elseif ($availability === 0) {
                    $query = $query->where('is_online',0);
                }
            }
            
            if($request->has('price'))
            {
                if ($request->price === 'low-to-high') {
                    $query = $query->orderBy('consultation_fee', 'asc');
                } elseif ($request->price === 'high-to-low') {
                    $query = $query->orderBy('consultation_fee', 'desc');
                }
            }

            if ($request->has('languages') && $request->languages != null && $request->languages != "null" && $request->languages != '') {
                $query = $query->whereRaw(
                                "FIND_IN_SET(?, REPLACE(languages_spoken, ' ', ''))",
                                [$request->languages]
                            );
            }

            if($request->has('gender'))
            {
                $gender = intval($request->gender);
                if ($gender === 1) {
                    $query = $query->where('gender',1);
                } elseif ($gender === 0) {
                    $query = $query->where('gender',0);
                }
            }

            if($request->has('specialities') && $request->specialities != null && $request->specialities != "null" && $request->specialities != '')
            {
                $query = $query->where('category_id',$request->specialities);
            }

            $doctors = $query->get();
            // $doctors = Doctors::with('expertise')
            //                 ->where('status', Constants::statusDoctorApproved)
            //                 ->where('on_vacation', Constants::doctorNotOnVacation)
            //                 ->where("name", 'like', '%' . $request->search . '%')
            //                 ->get();
        }else{
            $query = Doctors::with('expertise')
                            ->where('status', Constants::statusDoctorApproved)
                            ->where('on_vacation', Constants::doctorNotOnVacation);                           

            if($request->has('availability'))
            {
                $availability = intval($request->availability);
                if ($availability === 1) {
                    $query = $query->where('is_online',1);
                } elseif ($availability === 0) {
                    $query = $query->where('is_online',0);
                }
            }
            
            if($request->has('price'))
            {
                if ($request->price === 'low-to-high') {
                    $query = $query->orderBy('consultation_fee', 'asc');
                } elseif ($request->price === 'high-to-low') {
                    $query = $query->orderBy('consultation_fee', 'desc');
                }
            }

            if ($request->has('languages') && $request->languages != null && $request->languages != "null" && $request->languages != '') {
                $query = $query->whereRaw(
                                "FIND_IN_SET(?, REPLACE(languages_spoken, ' ', ''))",
                                [$request->languages]
                            );
            }

            if($request->has('gender'))
            {
                $gender = intval($request->gender);
                if ($gender === 1) {
                    $query = $query->where('gender',1);
                } elseif ($gender === 0) {
                    $query = $query->where('gender',0);
                }
            }

            if($request->has('specialities') && $request->specialities != null && $request->specialities != "null" && $request->specialities != '')
            {
                $query = $query->where('category_id',$request->specialities);
            }

            $doctors = $query->get();
        }

        $languages      = [];
        $specialities   = [];
        $availability   = ['online','offline'];
        $gender         = ['male','female'];
        $price          = ['low-to-high','high-to-low'];

        if($doctors != [])
        {
            $languages  = $doctors
                            ->flatMap(function ($doctor) {
                                return array_map('trim', explode(',', $doctor->languages_spoken));
                            })
                            ->filter()
                            ->unique()
                            ->values();

            $doctorIds  = $doctors->pluck('id')->toArray();

            $specialities = Doctors::join('doctor_cats', 'doctors.category_id', '=', 'doctor_cats.id')
                                ->select('doctor_cats.id', 'doctor_cats.title')
                                ->whereIn('doctors.id',$doctorIds)
                                ->distinct()
                                ->get();
        }

        $sort_by = [
            'availability'  => $availability,
            'price'         => $price,
            'languages'     => $languages,
            'gender'        => $gender,
            'specialities'  => $specialities            
        ];

        return response()->json([
            'status' => true,
            'message' => 'data fetched successfully!',
            // 'categories' => $cats,
            'doctors' => $doctors,
            'sort_by' => $sort_by,
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
        if ($request->has('email')) {
            $user->email = $request->email;
            $user->identity = $request->email;
        }
         if ($request->has('ref_id')) {
            $user->ref_id = $request->ref_id;
        }

        $user->save();

        // $data = [];

        // if ($request->filled('fullname')) {
        //     $data['fullname'] = GlobalFunction::cleanString($request->fullname);
        // }

        // if ($request->hasFile('profile_image')) {
        //     $data['profile_image'] = GlobalFunction::saveFileAndGivePath(
        //         $request->file('profile_image')
        //     );
        // }

        // if ($request->has('saved_reels')) {
        //     $data['saved_reels'] = $request->saved_reels;
        // }

        // if ($request->filled('country_code')) {
        //     $data['country_code'] = $request->country_code;
        // }

        // if ($request->filled('phone_number')) {
        //     $data['phone_number'] = $request->phone_number;
        // }

        // if ($request->filled('gender')) {
        //     $data['gender'] = $request->gender;
        // }

        // if ($request->filled('dob')) {
        //     $data['dob'] = $request->dob;
        // }

        // if ($request->has('favourite_doctors')) {
        //     $data['favourite_doctors'] = $request->favourite_doctors;
        // }

        // if ($request->has('is_notification')) {
        //     $data['is_notification'] = $request->is_notification;
        // }

        // if ($request->filled('email')) {
        //     $data['email'] = $request->email;
        //     $data['identity'] = $request->email; // same as your logic
        // }

        // if ($request->filled('ref_id')) {
        //     $data['ref_id'] = $request->ref_id;
        // }

        // if (\Schema::connection('mysql') && \Schema::connection('mysql')->hasTable("users")){
        //     DB::connection('mysql')->table('users')->where('id', $user->id)->update($data);
        // }
        // if (\Schema::connection('mulkmed_india') && \Schema::connection('mulkmed_india')->hasTable("users")){
        //     DB::connection('mulkmed_india')->table('users')->where('id', $user->id)->update($data);
        // }

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

        $hostAndConversionRate = Helpers::conversionRate();
        $conversionRate = (float) $hostAndConversionRate['conversionRate'];
        $doctors = Doctors::select('*',DB::raw("ROUND(consultation_fee * {$conversionRate}) as consultation_fee"))
            ->whereIn('id', explode(',', $user->favourite_doctors))->with([
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

        $user = Users::where('identity', $request->identity)->first();

        if($request->is_login == 1 && $user == null){
               return GlobalFunction::sendSimpleResponse(false, 'user not found');
        }        

        if ($user != null && $request->is_login == 1) {
            $user->device_type  = $request->device_type;
            $user->device_token = $request->device_token;
            $user->login_type   = $request->login_type;
            $user->save();

            $user = Users::find($user->id);

            return GlobalFunction::sendDataResponse(true, 'User exists already', $user);
        } else {
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
            $user->ref_id       = $request->ref_id;
            $user->device_details  = $request->device_details;
            $user->save();

            // $data = [
            //             'identity'          => $request->has('identity') ? $request->identity : null,
            //             'email'             => $request->has('identity') ? $request->identity : null,
            //             'device_type'       => $request->device_type,
            //             'device_token'      => $request->device_token,
            //             'fullname'          => $request->fullname,
            //             'username'          => $request->username,
            //             'password'          => $request->password,
            //             'phone_number'      => $request->phone_number,
            //             'country_code'      => $request->country_code,
            //             'dob'               => $request->dob,
            //             'gender'            => $request->gender,
            //             'login_type'        => $request->login_type,
            //             'app_version'       => $request->app_version,
            //             'ref_id'            => $request->ref_id,
            //             'device_details'    => $request->device_details,
            //         ];

            // if (\Schema::connection('mysql') && \Schema::connection('mysql')->hasTable("users")){
            //     DB::connection('mysql')->table('users')->insert($data);
            // }
            // if (\Schema::connection('mulkmed_india') && \Schema::connection('mulkmed_india')->hasTable("users")){
            //     DB::connection('mulkmed_india')->table('users')->insert($data);
            // }

            $user = User::where('phone_number',$request->phone_number)->first();
            $token  = $user->createToken('auth_token')->plainTextToken;

            $user = Users::find($user->id);

            return response()->json(['status' => true,'token' => $token , 'user' => $user]);
        }
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
                $user->app_version      = $request->app_version;
                $user->device_details   = $request->device_details;
                $user->device_token     = $request->device_token ?? null;
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

    public function updateUserFCM(Request $request)
    {
    
        try {
     
        $validated = $request->validate([
            'id'           => 'required|integer|exists:users,id',
            'device_token' => 'required|string'
        ]);

        $updated = User::where('id', $validated['id'])
                        ->update([
                            'device_token' => $validated['device_token']
                        ]);

        if ($updated) {
            return response()->json([
                'status'  => true,
                'message' => 'FCM Token updated successfully'
            ]);
        }

        return response()->json([
            'status'  => false,
            'message' => 'Unable to update FCM token'
        ], );

               //code...
        } catch (\Throwable $th) {
            return response()->json([
            'status'  => false,
            'message' => $th->getMessage()
        ], );
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
        // return $dbName = \DB::connection()->getDatabaseName();
        $lang = request()->header('lang', 'en');
        $columnsection_name = match ($lang) {
                        'ar' => 'ar_section_name',
                        'fr' => 'fr_section_name',
                        'hi' => 'hi_section_name',
                        'ur' => 'ur_section_name',
                        default => 'section_name',
                    };

        $sections = SectionSequence::select('id',DB::raw("`$columnsection_name` as `section_name`"),'section_type')->where('is_deleted', 0)->where('status',1)->orderBy('position', 'ASC')->get();

        $sectionSequence = [];

        $hostAndConversionRate = Helpers::conversionRate();
        $conversionRate = (float) $hostAndConversionRate['conversionRate'];
        if($request->has('search'))
        {
            $searchLang = request()->header('lang', 'en');
            $searchColumndesignation = match ($searchLang) {
                'ar' => 'ar_designation',
                'fr' => 'fr_designation',
                'hi' => 'hi_designation',
                'ur' => 'ur_designation',
                default => 'designation',
            };
            $searchColumnlanguagesSpoken = match ($searchLang) {
                'ar' => 'ar_languages_spoken',
                'fr' => 'fr_languages_spoken',
                'hi' => 'hi_languages_spoken',
                'ur' => 'ur_languages_spoken',
                default => 'languages_spoken',
            };
            $search = strtolower(trim($request->search));

            foreach ($sections as $key => $sequence) {
                if($sequence->section_type == 'doctors_section')
                {
                    $section = [];
                    $columndesignation = $searchColumndesignation;
                    $columnlanguages_spoken = $searchColumnlanguagesSpoken;

                    $section = Doctors::select('doctors.*', DB::raw("$columndesignation as designation"), DB::raw("$columnlanguages_spoken as languages_spoken"),
                                    DB::raw("ROUND(consultation_fee * {$conversionRate}) as consultation_fee"))
                                ->with('expertise')
                                ->where('status', Constants::statusDoctorApproved)
                                ->where('on_vacation', Constants::doctorNotOnVacation)
                                ->whereExists(function ($query) {
                                    $query->select(DB::raw(1))
                                        ->from('doctor_cats')
                                        ->whereColumn('doctor_cats.id', 'doctors.category_id')
                                        ->where('doctor_cats.is_deleted', 0);
                                })
                                ->where(function($query) use ($search) {
                                    $query->whereRaw('LOWER(doctors.name) LIKE ?', ["%{$search}%"])
                                        ->orWhereRaw('LOWER(doctors.designation) LIKE ?', ["%{$search}%"])
                                        ->orWhereExists(function ($subQuery) use ($search) {
                                            $subQuery->select(DB::raw(1))
                                                ->from('doctor_cats')
                                                ->whereColumn('doctor_cats.id', 'doctors.category_id')
                                                ->where('doctor_cats.is_deleted', 0)
                                                ->where(function ($catQuery) use ($search) {
                                                    $catQuery->whereRaw('LOWER(doctor_cats.title) LIKE ?', ["%{$search}%"])
                                                        ->orWhereRaw('LOWER(doctor_cats.keywords) LIKE ?', ["%{$search}%"]);
                                                });
                                        })
                                        ->orWhereExists(function ($subQuery) use ($search) {
                                            $subQuery->select(DB::raw(1))
                                                ->from('doctor_expertise')
                                                ->whereColumn('doctor_expertise.doctor_id', 'doctors.id')
                                                ->whereRaw('LOWER(doctor_expertise.title) LIKE ?', ["%{$search}%"]);
                                        });
                                })
                                ->orderBy('is_online', 'DESC')
                                ->get();
                    
                    $cats = DoctorCategories::where('is_deleted', 0)->where("title", 'like', '%' . $request->search . '%')->get();
                    if($section != [])
                    {
                        $sequence->section_data = $section;
                        array_push($sectionSequence,$sequence);
                    }
                }

                if($sequence->section_type == 'mulk_med_virtual_hospital_doctors')
                {
                    if($request->has('search'))
                    {
                        $columndesignation = $searchColumndesignation;
                        $columnlanguages_spoken = $searchColumnlanguagesSpoken;

                        $section = Doctors::select('*', DB::raw("$columndesignation as designation"), DB::raw("$columnlanguages_spoken as languages_spoken"),
                                        DB::raw("ROUND(consultation_fee * {$conversionRate}) as consultation_fee"))
                                    ->with('expertise')
                                    ->where('status', Constants::statusDoctorApproved)
                                    ->where('on_vacation', Constants::doctorNotOnVacation)
                                    ->where("name", 'like', '%' . $request->search . '%')
                                    ->where('is_mulkmed', 1)
                                    ->orderBy('is_online', 'DESC')
                                    ->get();
                        $cats = DoctorCategories::where('is_deleted', 0)->where("title", 'like', '%' . $request->search . '%')->get();
                    }else{
                        $lang = request()->header('lang', 'en');

                        $columndesignation = match ($lang) {
                            'ar' => 'ar_designation',
                            'fr' => 'fr_designation',
                            'hi' => 'hi_designation',
                            'ur' => 'ur_designation',
                            default => 'designation',
                        };

                        $columnlanguages_spoken = match ($lang) {
                            'ar' => 'ar_languages_spoken',
                            'fr' => 'fr_languages_spoken',
                            'hi' => 'hi_languages_spoken',
                            'ur' => 'ur_languages_spoken',
                            default => 'languages_spoken',
                        };
                        
                        $section = Doctors::select('*', DB::raw("$columndesignation as designation"), DB::raw("$columnlanguages_spoken as languages_spoken"),
                                        DB::raw("ROUND(consultation_fee * {$conversionRate}) as consultation_fee"))
                                    ->with('expertise')
                                    ->where('status', Constants::statusDoctorApproved)
                                    ->where('on_vacation', Constants::doctorNotOnVacation)
                                    ->where('is_mulkmed', 1)
                                    ->orderBy('is_online', 'DESC')
                                    ->get();
                    }
                    if($section != [])
                    {
                        $sequence->section_data = $section;
                        array_push($sectionSequence,$sequence);
                    }
                }

                if($sequence->section_type == "common_health_problems")
                {
                    $section = [];
                    $section = CommonHealthProblems::select('common_health_problems.*')
                        ->join('doctor_cats', 'doctor_cats.id', 'common_health_problems.speciality')
                        ->where("common_health_problems.is_deleted", 0)
                        ->where("common_health_problems.problem", 'like', '%' . $request->search . '%')
                        ->orderBy('priority')
                        ->get();

                    foreach ($section as $key => $value) {
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

                        $lang = $request->header('lang','en');
                        if($lang == 'en')
                        {
                            $value->problem = $value->problem;
                        }
                        if($lang == 'hi')
                        {
                            $value->problem = $value->hi_problem;
                        }
                        if($lang == 'ur')
                        {
                            $value->problem = $value->ur_problem;
                        }
                        if($lang == 'ar')
                        {
                            $value->problem = $value->ar_problem;
                        }
                        if($lang == 'fr')
                        {
                            $value->problem = $value->fr_problem;
                        }
                    }
                    if($section != [])
                    {
                        $sequence->section_data = $section;
                        array_push($sectionSequence,$sequence);
                    }
                }

                if($sequence->section_type == 'doctors_by_speciality')
                {
                    $section = [];
                    $categories = DoctorCategories::where('is_deleted', 0)->where("title", 'like', '%' . $request->search . '%')->get();
                    $categoryIds = $categories->pluck('id');

                    $doctorsByCategory = collect();
                    $bannersByCategory = collect();
                    if ($categoryIds->isNotEmpty()) {
                        $doctorsByCategory = Doctors::select('*', DB::raw("ROUND(consultation_fee * {$conversionRate}) as consultation_fee"))
                            ->with('expertise')
                            ->whereIn('category_id', $categoryIds)
                            ->where('status', Constants::statusDoctorApproved)
                            ->where('on_vacation', Constants::doctorNotOnVacation)
                            ->get()
                            ->groupBy('category_id');

                        $bannersByCategory = Banners::whereIn('section_id', $categoryIds)
                            ->where('is_deleted', 0)
                            ->get()
                            ->groupBy('section_id');
                    }

                    foreach ($categories as $cat) {
                        $cat->doctors = $doctorsByCategory->get($cat->id, collect())->values();
                        $cat->banners = $bannersByCategory->get($cat->id, collect())->values();
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

                if($sequence->section_type == 'doctors_by_symptoms')
                {
                    $section = [];
                    $doctors_by_symptoms = DoctorsBySymptoms::select('doctors_by_symptoms.id','doctors_by_symptoms.problem', 'doctors_by_symptoms.image', 'doctor_cats.title as speciality', 
                                            'doctors_by_symptoms.priority')->join('doctor_cats', 'doctor_cats.id', 'doctors_by_symptoms.speciality')
                                            ->where("doctors_by_symptoms.is_deleted", 0)
                                            ->where("doctors_by_symptoms.problem", 'like', '%' . $request->search . '%')
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
            }

            return response()->json([
                'status' => true, 
                'sectionSequence' => $sectionSequence,
            ]);
        }
        foreach ($sections as $key => $sequence) {
            if($sequence->section_type == 'doctors_section')
            {
                if($request->has('search'))
                {
                    $lang = request()->header('lang', 'en');

                    $columndesignation = match ($lang) {
                        'ar' => 'ar_designation',
                        'fr' => 'fr_designation',
                        'hi' => 'hi_designation',
                        'ur' => 'ur_designation',
                        default => 'designation',
                    };

                    $columnlanguages_spoken = match ($lang) {
                        'ar' => 'ar_languages_spoken',
                        'fr' => 'fr_languages_spoken',
                        'hi' => 'hi_languages_spoken',
                        'ur' => 'ur_languages_spoken',
                        default => 'languages_spoken',
                    };

                    $section = Doctors::select('*', DB::raw("$columndesignation as designation"), DB::raw("$columnlanguages_spoken as languages_spoken"),
                                    DB::raw("ROUND(consultation_fee * {$conversionRate}) as consultation_fee"))
                                ->with('expertise')
                                ->where('status', Constants::statusDoctorApproved)
                                ->where('on_vacation', Constants::doctorNotOnVacation)
                                ->where("name", 'like', '%' . $request->search . '%')
                                ->orderBy('is_online', 'DESC')
                                ->get();
                    $cats = DoctorCategories::where('is_deleted', 0)->where("title", 'like', '%' . $request->search . '%')->get();
                }else{
                    $lang = request()->header('lang', 'en');

                    $columndesignation = match ($lang) {
                        'ar' => 'ar_designation',
                        'fr' => 'fr_designation',
                        'hi' => 'hi_designation',
                        'ur' => 'ur_designation',
                        default => 'designation',
                    };

                    $columnlanguages_spoken = match ($lang) {
                        'ar' => 'ar_languages_spoken',
                        'fr' => 'fr_languages_spoken',
                        'hi' => 'hi_languages_spoken',
                        'ur' => 'ur_languages_spoken',
                        default => 'languages_spoken',
                    };
 
                    $section = Doctors::select('*', DB::raw("$columndesignation as designation"), DB::raw("$columnlanguages_spoken as languages_spoken"),
                                    DB::raw("ROUND(consultation_fee * {$conversionRate}) as consultation_fee"))
                                ->with('expertise')
                                ->where('status', Constants::statusDoctorApproved)
                                ->where('on_vacation', Constants::doctorNotOnVacation)
                                ->orderBy('is_online', 'DESC')
                                ->get();
                }
                if($section != [])
                {
                    $sequence->section_data = $section;
                    array_push($sectionSequence,$sequence);
                }
            }

            if($sequence->section_type == 'mulk_med_virtual_hospital_doctors')
            {
                if($request->has('search'))
                {
                    $lang = request()->header('lang', 'en');

                    $columndesignation = match ($lang) {
                        'ar' => 'ar_designation',
                        'fr' => 'fr_designation',
                        'hi' => 'hi_designation',
                        'ur' => 'ur_designation',
                        default => 'designation',
                    };

                    $columnlanguages_spoken = match ($lang) {
                        'ar' => 'ar_languages_spoken',
                        'fr' => 'fr_languages_spoken',
                        'hi' => 'hi_languages_spoken',
                        'ur' => 'ur_languages_spoken',
                        default => 'languages_spoken',
                    };
                    $section = Doctors::select('*', DB::raw("$columndesignation as designation"), DB::raw("$columnlanguages_spoken as languages_spoken"),
                                    DB::raw("ROUND(consultation_fee * {$conversionRate}) as consultation_fee"))
                                ->with('expertise')
                                ->where('status', Constants::statusDoctorApproved)
                                ->where('on_vacation', Constants::doctorNotOnVacation)
                                ->where("name", 'like', '%' . $request->search . '%')
                                ->where('is_mulkmed', 1)
                                ->orderBy('is_online', 'DESC')
                                ->get();
                    $cats = DoctorCategories::where('is_deleted', 0)->where("title", 'like', '%' . $request->search . '%')->get();
                }else{
                    $lang = request()->header('lang', 'en');

                    $columndesignation = match ($lang) {
                        'ar' => 'ar_designation',
                        'fr' => 'fr_designation',
                        'hi' => 'hi_designation',
                        'ur' => 'ur_designation',
                        default => 'designation',
                    };

                    $columnlanguages_spoken = match ($lang) {
                        'ar' => 'ar_languages_spoken',
                        'fr' => 'fr_languages_spoken',
                        'hi' => 'hi_languages_spoken',
                        'ur' => 'ur_languages_spoken',
                        default => 'languages_spoken',
                    };
                    $section = Doctors::select('*', DB::raw("$columndesignation as designation"), DB::raw("$columnlanguages_spoken as languages_spoken"),
                                    DB::raw("ROUND(consultation_fee * {$conversionRate}) as consultation_fee"))
                                ->with('expertise')
                                ->where('status', Constants::statusDoctorApproved)
                                ->where('on_vacation', Constants::doctorNotOnVacation)
                                ->where('is_mulkmed', 1)
                                ->orderBy('is_online', 'DESC')
                                ->get();
                }
                if($section != [])
                {
                    $sequence->section_data = $section;
                    array_push($sectionSequence,$sequence);
                }
            }

            if($sequence->section_type == 'Mulk_Longevity_Care' || $sequence->section_type == 'mulk_longevity_care')
            {
                $section = DashboardBanners::where('name', 'Mulk Longevity Care')
                                ->where('is_deleted', 0)
                                ->get();
                if ($section->isEmpty()) {
                    $section = DashboardBanners::where('name', 'like', '%Longevity Care%')
                                    ->where('is_deleted', 0)
                                ->get();
                }
                if($section->isNotEmpty())
                {
                    $sequence->section_data = $section;
                    array_push($sectionSequence,$sequence);
                }

                // Separate section for Mulk Longevity Lab Report
                $labReportSection = DashboardBanners::where('name', 'Mulk Longevity Lab Report')
                                ->where('is_deleted', 0)
                                ->get();
                if($labReportSection->isNotEmpty())
                {
                    $labSequence = clone $sequence;
                    $labSequence->id = $sequence->id + 1000; // Give it a unique pseudo ID
                    $labSequence->section_name = 'Mulk Longevity Lab Report';
                    $labSequence->section_type = 'mulk_longevity_lab_report';
                    $labSequence->section_data = $labReportSection;
                    array_push($sectionSequence, $labSequence);
                }
            }

            if($sequence->section_type == "second_medical_openion")
            {
                $section = DashboardBanners::where('name', 'Second Medical Opinion (SMO)')
                                ->where('is_deleted', 0)
                                ->get();
                if($section != [])
                {
                    $sequence->section_data = $section;
                    array_push($sectionSequence,$sequence);
                }
            }

            if($sequence->section_type == "common_health_problems")
            {
               if ($request->has('search')) {
                    $chp = CommonHealthProblems::select(
                            'common_health_problems.*',
                            DB::raw('GROUP_CONCAT(doctor_cats.title SEPARATOR ", ") as speciality'),
                            
                        )
                        ->join('doctor_cats', function ($join) {
                            $join->whereRaw('JSON_CONTAINS(common_health_problems.speciality, CAST(doctor_cats.id AS JSON))');
                        })
                        ->where('common_health_problems.is_deleted', 0)
                        ->where('common_health_problems.problem', 'like', '%' . $request->search . '%')
                        ->groupBy('common_health_problems.id')
                        ->orderBy('priority')
                        ->get();
                } else {
                    $chp = CommonHealthProblems::where('is_deleted', 0)
                            ->orderBy('priority')
                            ->get()
                            ->map(function ($problem) {
                                $problem->speciality = DB::table('doctor_cats')
                                    ->where('id', $problem->speciality)
                                    ->pluck('title')
                                    ->implode(', ');
                                return $problem;
                            });
                }

                foreach ($chp as $key => $value) {

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

                    $lang = $request->header('lang','en');
                    if($lang == 'en')
                    {
                        $value->problem = $value->problem;
                    }
                    if($lang == 'hi')
                    {
                        $value->problem = $value->hi_problem;
                    }
                    if($lang == 'ur')
                    {
                        $value->problem = $value->ur_problem;
                    }
                    if($lang == 'ar')
                    {
                        $value->problem = $value->ar_problem;
                    }
                    if($lang == 'fr')
                    {
                        $value->problem = $value->fr_problem;
                    }
                }

                if ($chp->isNotEmpty()) {
                    $sequence->section_data = $chp;
                    array_push($sectionSequence, $sequence);
                }
            }


            if($sequence->section_type == 'mulk_symptoms_checker')
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

            if($sequence->section_type == 'ads')
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

            if($sequence->section_type == 'doctors_by_speciality')
            {
                if($request->has('search'))
                {
                    $categories = DoctorCategories::where('is_deleted', 0)->where("title", 'like', '%' . $request->search . '%')->get();
                }
                else{
                    $categories = DoctorCategories::where('is_deleted', 0)->get();
                }
                foreach ($categories as $cat) {
                    $doctors = Doctors::select('doctors.*',DB::raw("ROUND(consultation_fee * {$conversionRate}) as consultation_fee"))
                        ->with('expertise')->where('category_id', $cat->id)
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

            if($sequence->section_type == 'mulk_ai_vitals')
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

            if($sequence->section_type == 'mulk_hnh')
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

            if($sequence->section_type == 'orders_prescription')
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

            if($sequence->section_type == 'mulk_senior_card')
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

            if($sequence->section_type == 'doctors_by_symptoms')
            {
                if($request->has('search'))
                {
                    $doctors_by_symptoms = DoctorsBySymptoms::select('doctors_by_symptoms.id','doctors_by_symptoms.problem', 'doctors_by_symptoms.image', 'doctor_cats.title as speciality', 
                                        'doctors_by_symptoms.priority')->join('doctor_cats', 'doctor_cats.id', 'doctors_by_symptoms.speciality')
                                        ->where("doctors_by_symptoms.is_deleted", 0)
                                        ->where("doctors_by_symptoms.problem", 'like', '%' . $request->search . '%')
                                        ->orderBy('priority')->get();
                }
                else{
                    $doctors_by_symptoms = DoctorsBySymptoms::select('doctors_by_symptoms.id','doctors_by_symptoms.problem', 'doctors_by_symptoms.image', 'doctor_cats.title as speciality', 
                                        'doctors_by_symptoms.priority')->join('doctor_cats', 'doctor_cats.id', 'doctors_by_symptoms.speciality')
                                        ->where("doctors_by_symptoms.is_deleted", 0)
                                        ->orderBy('priority')->get();
                }

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

            if($sequence->section_type == 'best_offers')
            {
                $section = BestOfferPlans::where('is_deleted', 0)->get();
                if($section != [])
                {
                    $sequence->section_data = $section;
                    array_push($sectionSequence,$sequence);
                }
            }

            if($sequence->section_type == 'surgical_cost_estimate')
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

            if($sequence->section_type == 'tourist_gold_card')
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

            if ($sequence->section_type == 'bidding_banner') {
                $section = DashboardBanners::where('name', 'Lowest Price Finder')
                                ->where('is_deleted', 0)
                                ->get();

                  if($section != [])
                {
                    $sequence->section_data = $section;
                    array_push($sectionSequence,$sequence);
                }
            }

            if ($sequence->section_type == 'partners_network') {
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

            // if($sequence->section_type == "traveler_card")
            // {
            //     $section = DashboardBanners::where('name', 'Traveler Card')
            //                     ->where('is_deleted', 0)
            //                     ->get();
            //     if($section != [])
            //     {
            //         $sequence->section_data = $section;
            //         array_push($sectionSequence,$sequence);
            //     }
            // }

            // if($sequence->section_type == "traveler_card")
            // {
            //     // Override section name for traveler_card in dashboard response.
            //     $sequence->section_name = "Mulk Travel Coverage";
            // }

            if($sequence->section_type == "traveler_card")
            {
                // For traveler card section, show the "Mulk Travel Coverage" banner content.
                $section = DashboardBanners::where('name', 'Mulk Travel Coverage')
                                ->where('is_deleted', 0)
                                ->get();

                // Backward compatibility: fallback to old banner name if needed.
                if ($section->isEmpty()) {
                    $section = DashboardBanners::where('name', 'Traveler Card')
                                    ->where('is_deleted', 0)
                                    ->get();
                }

                if($section->isNotEmpty())
                {
                    $sequence->section_name = __('Mulk Travel Coverage');
                    $sequence->section_data = $section;
                    array_push($sectionSequence,$sequence);
                }
            }
        }

        if ($request->has('user_id')) {
            $appointments = Appointments::where('user_id', $request->user_id)
                ->where('status', Constants::orderAccepted)
                ->orderBy('date', 'asc')
                ->orderBy('time', 'asc')
                ->get();

            if ($appointments->count()) {
                $appointmentsWithJitsi = [];

                foreach ($appointments as $appointment) {
                    $jitsi_meeting = JitsiMeeting::where('appointment_id', $appointment->id)->first();
                    if ($jitsi_meeting) {
                        $appointment->jitsi_link = url("/api/v1/join_jitsi_meeting?user_id={$appointment->user_id}&room={$jitsi_meeting->room}");;
                        $appointment->image = asset('storage/uploads/dashboard_appointment_banner.png');
                        $appointmentsWithJitsi[] = $appointment;
                    }

                    $vital_scan = AI_Vital::where('user_id',$request->user_id)->where('appointment_id',$appointment->id)->get();
                    $isVitalScanDone = 0;

                    if(count($vital_scan)){
                        $isVitalScanDone = 1;
                    }
                    $appointment->is_vital_scan_done = $isVitalScanDone;
                }

                if (count($appointmentsWithJitsi)) {
                    $appointmentBanner = new \stdClass();
                    $appointmentBanner->id = 2;
                    $appointmentBanner->section_name = "appointment_banner";
                    $appointmentBanner->section_type = "appointment_banner";
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
        $ai_vitals->scan_date = $request->date;
        
        // if($request->has('pdf_file'))
        // {
        //     $ai_vitals->pdf_file = GlobalFunction::saveFileAndGivePath($request->pdf_file);
        // }

        $data = [];
        $user = Users::where('id',$ai_vitals->user_id)->first();
        $data['user'] = $user; 
        $data['scan_date'] = $request->date; 
        $data['report'] = json_decode($ai_vitals->report); 
        Log::info('AI Vitals scan date'. $request->date);

        // return $data;
        $filename = "aiVitalMIDAS_Report.pdf";
        // return view('pages.vitalScanReport', $data);
        $pdf = PDF::loadView('pages.vitalScanReport', $data)
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'dpi' => 150,
                'isRemoteEnabled' => true,
                'isHtml5ParserEnabled' => true,
            ]);

        $filename = 'vitalScan.pdf';

        // create temp file path
        $tempPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $filename;

        // write PDF bytes to temp file
        file_put_contents($tempPath, $pdf->output());

        // Create an UploadedFile instance (set $test = true so it bypasses is_uploaded_file checks)
        $uploadedFile = new UploadedFile(
            $tempPath,      // full path to temp file
            $filename,      // original filename
            'application/pdf', // mime type
            null,           // size (null lets PHP handle it)
            true            // $test = true (important)
        );

        // Now call your helper exactly as before
        $saveResult = GlobalFunction::saveFileAndGivePath($uploadedFile);
        $ai_vitals->pdf_file = $saveResult;
        $ai_vitals->save();

        // Trigger Senoclock AI classification and save response in ai_vitals table (senoclock_ai_response & shen_ai columns)
        try {
            app(SenoclockAiService::class)->processAiVital($ai_vitals, $user, $request);
            $ai_vitals->refresh();
        } catch (\Throwable $e) {
            Log::error('AIVitals Senoclock AI classification trigger error: ' . $e->getMessage());
        }

        if (!empty($user->email)) {
            try {
        Mail::to($user->email)->send(new AiVitalReportMail($user, $uploadedFile));
            } catch (\Throwable $e) {
                Log::warning('AIVitals email send error: ' . $e->getMessage());
            }
        }

        $baseUrl = url('/');
        $pdf_url = $baseUrl . '/api/v1/user/vitalReportPdf?user_id=' . $ai_vitals->user_id .'&report_id=' . $ai_vitals->id;

        return response()->json([
            'status' => true, 
            'pdf_url' => $pdf_url,
            'data' => $ai_vitals,
            'message' => "Data saved successfully",
        ]);
    }

    function AIVitalsLongevity(Request $request)
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

        $ai_vitals = new AI_Vital();
        $ai_vitals->user_id = $request->user_id;
        $ai_vitals->appointment_id = $request->input('appointment_id', 0);
        $ai_vitals->report = is_string($request->report) ? $request->report : json_encode($request->report);
        $ai_vitals->scan_date = $request->input('date') ?? date('Y-m-d H:i:s');

        // Set isLogivity column to 1
        try {
            if (Schema::hasTable('ai_vitals')) {
                if (!Schema::hasColumn('ai_vitals', 'isLogivity')) {
                    Schema::table('ai_vitals', function (\Illuminate\Database\Schema\Blueprint $table) {
                        $table->tinyInteger('isLogivity')->default(0)->after('appointment_id');
                    });
                }
                $ai_vitals->isLogivity = 1;
                if (Schema::hasColumn('ai_vitals', 'is_longevity')) {
                    $ai_vitals->is_longevity = 1;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('AIVitalsLongevity isLogivity column auto-create/set warning: ' . $e->getMessage());
        }

        $user = Users::where('id', $ai_vitals->user_id)->first();
        $pdf_url = null;
        $uploadedFile = null;

        if ($user && view()->exists('pages.vitalScanReport')) {
            try {
                $data = [];
                $data['user'] = $user; 
                $data['scan_date'] = $ai_vitals->scan_date; 
                $data['report'] = json_decode($ai_vitals->report); 
                Log::info('AIVitalsLongevity scan date: ' . $ai_vitals->scan_date);

                $pdf = PDF::loadView('pages.vitalScanReport', $data)
                    ->setPaper('a4', 'portrait')
                    ->setOptions([
                        'dpi' => 150,
                        'isRemoteEnabled' => true,
                        'isHtml5ParserEnabled' => true,
                    ]);

                $filename = 'vitalScanLongevity.pdf';
                $tempPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $filename;
                file_put_contents($tempPath, $pdf->output());

                $uploadedFile = new \Illuminate\Http\UploadedFile(
                    $tempPath,
                    $filename,
                    'application/pdf',
                    null,
                    true
                );

                $saveResult = GlobalFunction::saveFileAndGivePath($uploadedFile);
                $ai_vitals->pdf_file = $saveResult;
            } catch (\Throwable $e) {
                Log::warning('AIVitalsLongevity PDF generation warning: ' . $e->getMessage());
            }
        }

        
        $ai_vitals->is_longevity = 1;
        $ai_vitals->save();

        // Trigger Senoclock AI classification and save response in ai_vitals table (senoclock_ai_response & shen_ai columns)
        try {
            app(SenoclockAiService::class)->processAiVital($ai_vitals, $user, $request);
            $ai_vitals->refresh();
        } catch (\Throwable $e) {
            Log::error('AIVitalsLongevity Senoclock AI classification trigger error: ' . $e->getMessage());
        }

        if ($user && !empty($user->email) && $uploadedFile) {
            try {
                Mail::to($user->email)->send(new AiVitalReportMail($user, $uploadedFile));
            } catch (\Throwable $e) {
                Log::warning('AIVitalsLongevity email send error: ' . $e->getMessage());
            }
        }

        $baseUrl = url('/');
        $pdf_url = $baseUrl . '/api/v1/user/vitalReportPdf?user_id=' . $ai_vitals->user_id . '&report_id=' . $ai_vitals->id;

        return response()->json([
            'status' => true, 
            'pdf_url' => $pdf_url,
            'data' => $ai_vitals,
            'message' => "Longevity AI Vital scan data saved successfully",
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

            $ai_vitals->scan_date   = $request->date;
            // if($request->has('pdf_file'))
            // {
            //     $ai_vitals->pdf_file = GlobalFunction::saveFileAndGivePath($request->pdf_file);
            // }

            // $ai_vital_report = AIVitalScanMisa::where('user_id',$request->user_id)->where('id',$request->report_id)->first();



            $data = [];
            $user = Users::where('id',$ai_vitals->user_id)->first();
            $data['user'] = $user; 
            $data['scan_date'] = $request->date; 
            $data['report'] = json_decode($request->report); 
            Log::info('AI Vitals MIDAS scan date'. $request->date);

            // return $data;
            $filename = "aiVitalMIDAS_Report.pdf";
            // return view('pages.vitalScanReport', $data);
            $pdf = PDF::loadView('pages.vitalScanReport', $data)
                ->setPaper('a4', 'portrait')
                ->setOptions([
                    'dpi' => 150,
                    'isRemoteEnabled' => true,
                    'isHtml5ParserEnabled' => true,
                ]);

            $filename = 'vitalScan.pdf';

            // create temp file path
            $tempPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $filename;

            // write PDF bytes to temp file
            file_put_contents($tempPath, $pdf->output());

            // Create an UploadedFile instance (set $test = true so it bypasses is_uploaded_file checks)
            $uploadedFile = new UploadedFile(
                $tempPath,      // full path to temp file
                $filename,      // original filename
                'application/pdf', // mime type
                null,           // size (null lets PHP handle it)
                true            // $test = true (important)
            );

            // Now call your helper exactly as before
            $saveResult = GlobalFunction::saveFileAndGivePath($uploadedFile);
            $ai_vitals->pdf_file = $saveResult;
            $ai_vitals->save();

            Mail::to($user->email)->send(new AiVitalReportMail($user, $uploadedFile));
        
            $pdf_url = route('aiVitalMesaReportPdf') . '?' . http_build_query([
                'user_id'   => $ai_vitals->user_id,
                'report_id' => $ai_vitals->id,
            ]);


            // Log::info('AI Vitals Response'. $request);
            if($ai_vitals->report_from == "ai_vital"){
                return response()->json([
                    'status' => true,
                    'category_id' => 36,
                    'pdf_url' => $pdf_url,
                    'message' => "Data saved successfully",
                ]);
            }
            return response()->json([
                'status' => true, 
                'pdf_url' => $pdf_url, 
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
        $hostAndConversionRate = Helpers::conversionRate();
        $conversionRate = (float) $hostAndConversionRate['conversionRate'];
        foreach ($categories as $cat) {
            $doctors = Doctors::select('doctors.*',DB::raw("ROUND(consultation_fee * {$conversionRate}) as consultation_fee"))
                ->with('expertise')->where('category_id', $cat->id)
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

    public function checkSenior(Request $request)
    {
        // Validate input
         $rules = [
            'date_of_birth' => 'required|date_format:Y-m-d|before:today', 
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }

        // Parse DOB
        $dob = Carbon::createFromFormat('Y-m-d', $request->date_of_birth);

        // Calculate age
        $age = $dob->age; // Carbon auto-calculates from today

        // Check senior status (50+)
        $isSenior = $age >= 50;


        // Get lang from request, default to English
        $lang = $request->input('lang', 'en');

        $messages = [
            'success' => [
                'en' => 'You are eligible for the senior registration. Opening payment page...',
                'hi' => 'आप वरिष्ठ पंजीकरण के लिए पात्र हैं। पेमेंट पेज खोला जा रहा है...',
                'fr' => 'Vous êtes éligible à l\'inscription senior. Ouverture de la page de paiement...',
                'ar' => 'أنت مؤهل للتسجيل ككبير السن. جارٍ فتح صفحة الدفع...',
                'ur' => 'آپ سینئر رجسٹریشن کے لیے اہل ہیں۔ پیمنٹ پیج کھولا جا رہا ہے...'
            ],
            'not_eligible' => [
                'en' => 'You are not eligible for senior registration.',
                'hi' => 'आप वरिष्ठ पंजीकरण के लिए पात्र नहीं हैं।',
                'fr' => 'Vous n\'êtes pas éligible pour l\'inscription senior.',
                'ar' => 'أنت غير مؤهل للتسجيل ككبير السن.',
                'ur' => 'آپ سینئر رجسٹریشن کے لیے اہل نہیں ہیں۔'
            ],
        ];

        // Decide which message to use
        $key = $isSenior ? 'success' : 'not_eligible';

        // Final message with safe fallback to English
        $message = $messages[$key][$lang] ?? $messages[$key]['en'];

 
        return response()->json([
            "status"=> true,
            "message" => $message,
            'is_senior'  => $isSenior,
        ]);
    }

    public function SaveCardImage(Request $request){

        $rules = [
            'image' => 'required',
            'type' => 'required',
            'card_id' => 'required'
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }

        // $image = GlobalFunction::saveFileAndGivePath($request->image);

           

        $type = $request->type;

        if($type == 'hnh'){
            $hnh_card = HnHCards::find($request->card_id);
            
            GlobalFunction::deleteFile('uploads/cardUploads/'.$hnh_card->id.'/HnhCard'.$hnh_card->image);

            $image = GlobalFunction::saveCardFileAndGivePath(
            $request->image,
             'uploads/cardUploads/'.$hnh_card->id.'/HnhCard',   // folder
            'Mulk Card'           // file name (with spaces)
        );

            $hnh_card->image = $image;
            $hnh_card->save();
        }

        else if($type == 'tourist'){
            $tourist_card = TouristCards::find($request->card_id);

            GlobalFunction::deleteFile('uploads/cardUploads/'.$tourist_card->id.'/HnhCard'.$tourist_card->image);

            $image = GlobalFunction::saveCardFileAndGivePath(
            $request->image,
             'uploads/cardUploads/'.$tourist_card->id.'/TouristCard',   // folder
            'Mulk Card'           // file name (with spaces)
        );

            $tourist_card->image = $image;
            $tourist_card->save();
        }

        else if($type == 'senior'){
            $senior_card = SeniorCards::find($request->card_id);

            GlobalFunction::deleteFile('uploads/cardUploads/'.$senior_card->id.'/HnhCard'.$senior_card->image);

            $image = GlobalFunction::saveCardFileAndGivePath(
            $request->image,
            'uploads/cardUploads/'.$senior_card->id.'/SeniorCard',   // folder
            'Mulk Card'           // file name (with spaces)
        );
            $senior_card->image = $image;
            $senior_card->save();
        }

        return response()->json(['status' => true, 'message' => 'Image Uploaded successfully!']);

    }

    public function showCardImage(Request $request){
         $rules = [
            'card_id' => 'required',
             'type' => 'required',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }

        $type = $request->type;
        $card_id = $request->card_id; 
        $card = "";

        if($type == 'hnh'){
            $card = HnHCards::select('image')->where('id', $card_id)->first();
        }

        else if($type == 'tourist'){
            $card = TouristCards::select('image')->where('id', $card_id)->first();

        }

        else if($type == 'senior'){
            $card = SeniorCards::select('image')->where('id', $card_id)->first();
        }

        return response()->json(['status' => true, 'data' => $card->image]);


    }

    public function showMyCards(Request $request){
         $rules = [
            'user_id' => 'required',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }

        $hnh_cards = HnHCards::where('user_id', $request->user_id)->where('payment_status', 1)->where('is_deleted', 0)->latest()->first();

        $tourist_cards = TouristCards::where('user_id', $request->user_id)->where('payment_status', 1)->where('is_deleted', 0)->latest()->first();

        $senior_cards = SeniorCards::where('user_id', $request->user_id)->where('payment_status', 1)->where('is_deleted', 0)->latest()->first();

        $lang = $request->header('lang', 'en');
        app()->setLocale($lang);

        return response()->json([
            'status' => true,
            'data' => [
                [
                    "section_name" => __("hnh_cards"),
                    "section_type" => "hnh_cards",
                    "section_data" => $hnh_cards ? [$hnh_cards] : []
                ],
                [
                    "section_name" => __("tourist_cards"),
                    "section_type" => "tourist_cards",
                    "section_data" => $tourist_cards ? [$tourist_cards] : []
                ],
                [
                    "section_name" => __("senior_cards"),
                    "section_type" => "senior_cards",
                    "section_data" => $senior_cards ? [$senior_cards] : []
                ],
            ]
        ]);


    }
}
