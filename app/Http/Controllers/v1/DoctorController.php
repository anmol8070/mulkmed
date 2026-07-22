<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use App\Models\Appointments;
use App\Models\Constants;
use App\Models\DoctorAppointmentSlots;
use App\Models\DoctorAwards;
use App\Models\DoctorBankAccount;
use App\Models\DoctorCategories;
use App\Models\DoctorCatSuggestions;
use App\Models\DoctorEarningHistory;
use App\Models\DoctorExperience;
use App\Models\DoctorExpertise;
use App\Models\DoctorHolidays;
use App\Models\DoctorNotifications;
use App\Models\DoctorPayoutHistory;
use App\Models\DoctorReviews;
use App\Models\Doctor;
use App\Models\Doctors;
use App\Models\DoctorServiceLocations;
use App\Models\DoctorServices;
use App\Models\DoctorWalletStatements;
use App\Models\FaqCats;
use App\Models\GlobalFunction;
use App\Models\GlobalSettings;
use App\Models\Prescriptions;
use App\Models\Users;
use App\Models\DoctorPlans;
use App\Models\Banners;
use App\Models\AppointmentEmrs;
use App\Helpers\Helpers;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;
use Stichoza\GoogleTranslate\GoogleTranslate;
use DB;

class DoctorController extends Controller
{
    //

    function fetchUserDetails(Request $request)
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

        return Globalfunction::sendDataResponse(true, 'details fetched successfully', $user);
    }

    function deleteExperience($id)
    {
        $item = DoctorExperience::find($id);
        $item->delete();

        return Globalfunction::sendSimpleResponse(true, 'deleted successfully');
    }

    function deleteAwards($id)
    {
        $item = DoctorAwards::find($id);
        $item->delete();

        return Globalfunction::sendSimpleResponse(true, 'deleted successfully');
    }

    function deleteExpertise($id)
    {
        $item = DoctorExpertise::find($id);
        $item->delete();

        return Globalfunction::sendSimpleResponse(true, 'deleted successfully');
    }

    function deleteService($id)
    {
        $item = DoctorServices::find($id);
        $item->delete();

        return Globalfunction::sendSimpleResponse(true, 'deleted successfully');
    }

    function deleteServiceLocation($id)
    {
        $item = DoctorServiceLocations::find($id);
        $item->delete();

        return Globalfunction::sendSimpleResponse(true, 'item deleted successfully');
    }

    function deleteDoctorHoliday($id)
    {
        $item = DoctorHolidays::find($id);
        $item->delete();

        return Globalfunction::sendSimpleResponse(true, 'item deleted successfully');
    }

    function fetchDoctorServiceLocationList(Request $request)
    {
        $totalData = DoctorServiceLocations::where('doctor_id', $request->doctorId)->count();
        $rows = DoctorServiceLocations::where('doctor_id', $request->doctorId)->orderBy('id', 'DESC')->get();

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
            $result = DoctorServiceLocations::where('doctor_id', $request->doctorId)
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
        } else {
            $search = $request->input('search.value');
            $result = DoctorServiceLocations::where('doctor_id', $request->doctorId)
                ->where(function ($query) use ($search) {
                    $query->Where('hospital_title', 'LIKE', "%{$search}%")
                        ->orWhere('hospital_address', 'LIKE', "%{$search}%");
                })
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
            $totalFiltered = DoctorServiceLocations::where('doctor_id', $request->doctorId)
                ->where(function ($query) use ($search) {
                    $query->Where('hospital_title', 'LIKE', "%{$search}%")
                        ->orWhere('hospital_address', 'LIKE', "%{$search}%");
                })
                ->count();
        }
        $data = array();
        foreach ($result as $item) {

            $delete = '<a href="" class="mr-2 btn btn-danger text-white delete" rel=' . $item->id . ' >' . __("Delete") . '</a>';
            $data[] = array(
                $item->hospital_title,
                $item->hospital_address,
                $delete,
            );
        }
        $json_data = array(
            "draw" => intval($request->input('draw')),
            "recordsTotal" => intval($totalData),
            "recordsFiltered" => $totalFiltered,
            "data" => $data
        );
        echo json_encode($json_data);
        exit();
    }

    function fetchDoctorHolidaysList(Request $request)
    {
        $totalData = DoctorHolidays::where('doctor_id', $request->doctorId)->count();
        $rows = DoctorHolidays::where('doctor_id', $request->doctorId)->orderBy('id', 'DESC')->get();

        $result = $rows;

        $columns = array(
            0 => 'id',
            1 => 'fullname',
        );

        $limit = $request->input('length');
        $start = $request->input('start');
        $order = $columns[$request->input('order.0.column')];
        $dir = $request->input('order.0.dir');

        $totalFiltered = $totalData;
        if (empty($request->input('search.value'))) {
            $result = DoctorHolidays::where('doctor_id', $request->doctorId)
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
        } else {
            $search = $request->input('search.value');
            $result = DoctorHolidays::where('doctor_id', $request->doctorId)
                ->where(function ($query) use ($search) {
                    $query->Where('date', 'LIKE', "%{$search}%");
                })
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
            $totalFiltered = DoctorHolidays::where('doctor_id', $request->doctorId)
                ->where(function ($query) use ($search) {
                    $query->Where('date', 'LIKE', "%{$search}%");
                })
                ->count();
        }
        $data = array();
        foreach ($result as $item) {

            $delete = '<a href="" class="mr-2 btn btn-danger text-white delete" rel=' . $item->id . ' >' . __("Delete") . '</a>';
            $data[] = array(
                date('d-m-Y', strtotime($item->date)),
                $delete,
            );
        }
        $json_data = array(
            "draw" => intval($request->input('draw')),
            "recordsTotal" => intval($totalData),
            "recordsFiltered" => $totalFiltered,
            "data" => $data
        );
        echo json_encode($json_data);
        exit();
    }

    function fetchDoctorAwardsList(Request $request)
    {
        $totalData = DoctorAwards::where('doctor_id', $request->doctorId)->count();
        $rows = DoctorAwards::where('doctor_id', $request->doctorId)->orderBy('id', 'DESC')->get();

        $result = $rows;

        $columns = array(
            0 => 'id',
            1 => 'fullname',
        );

        $limit = $request->input('length');
        $start = $request->input('start');
        $order = $columns[$request->input('order.0.column')];
        $dir = $request->input('order.0.dir');

        $totalFiltered = $totalData;
        if (empty($request->input('search.value'))) {
            $result = DoctorAwards::where('doctor_id', $request->doctorId)
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
        } else {
            $search = $request->input('search.value');
            $result = DoctorAwards::where('doctor_id', $request->doctorId)
                ->where(function ($query) use ($search) {
                    $query->Where('title', 'LIKE', "%{$search}%");
                })
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
            $totalFiltered = DoctorAwards::where('doctor_id', $request->doctorId)
                ->where(function ($query) use ($search) {
                    $query->Where('title', 'LIKE', "%{$search}%");
                })
                ->count();
        }
        $data = array();
        foreach ($result as $item) {

            $delete = '<a href="" class="mr-2 btn btn-danger text-white delete" rel=' . $item->id . ' >' . __("Delete") . '</a>';
            $data[] = array(
                $item->title,
                $delete,
            );
        }
        $json_data = array(
            "draw" => intval($request->input('draw')),
            "recordsTotal" => intval($totalData),
            "recordsFiltered" => $totalFiltered,
            "data" => $data
        );
        echo json_encode($json_data);
        exit();
    }

    function fetchDoctorExperienceList(Request $request)
    {
        $totalData = DoctorExperience::where('doctor_id', $request->doctorId)->count();
        $rows = DoctorExperience::where('doctor_id', $request->doctorId)->orderBy('id', 'DESC')->get();
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
            $result = DoctorExperience::where('doctor_id', $request->doctorId)
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
        } else {
            $search = $request->input('search.value');
            $result = DoctorExperience::where('doctor_id', $request->doctorId)
                ->where(function ($query) use ($search) {
                    $query->Where('title', 'LIKE', "%{$search}%");
                })
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
            $totalFiltered = DoctorExperience::where('doctor_id', $request->doctorId)
                ->where(function ($query) use ($search) {
                    $query->Where('title', 'LIKE', "%{$search}%");
                })
                ->count();
        }
        $data = array();
        foreach ($result as $item) {

            $delete = '<a href="" class="mr-2 btn btn-danger text-white delete" rel=' . $item->id . ' >' . __("Delete") . '</a>';
            $data[] = array(
                $item->title,
                $delete,
            );
        }
        $json_data = array(
            "draw" => intval($request->input('draw')),
            "recordsTotal" => intval($totalData),
            "recordsFiltered" => $totalFiltered,
            "data" => $data
        );
        echo json_encode($json_data);
        exit();
    }

    function fetchDoctorExpertiseList(Request $request)
    {
        $totalData = DoctorExpertise::where('doctor_id', $request->doctorId)->count();
        $rows = DoctorExpertise::where('doctor_id', $request->doctorId)->orderBy('id', 'DESC')->get();
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
            $result = DoctorExpertise::where('doctor_id', $request->doctorId)
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
        } else {
            $search = $request->input('search.value');
            $result = DoctorExpertise::where('doctor_id', $request->doctorId)
                ->where(function ($query) use ($search) {
                    $query->Where('title', 'LIKE', "%{$search}%");
                })
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
            $totalFiltered = DoctorExpertise::where('doctor_id', $request->doctorId)
                ->where(function ($query) use ($search) {
                    $query->Where('title', 'LIKE', "%{$search}%");
                })
                ->count();
        }
        $data = array();
        foreach ($result as $item) {

            $delete = '<a href="" class="mr-2 btn btn-danger text-white delete" rel=' . $item->id . ' >' . __("Delete") . '</a>';
            $data[] = array(
                $item->title,
                $delete,
            );
        }
        $json_data = array(
            "draw" => intval($request->input('draw')),
            "recordsTotal" => intval($totalData),
            "recordsFiltered" => $totalFiltered,
            "data" => $data
        );
        echo json_encode($json_data);
        exit();
    }

    function fetchDoctorServicesList(Request $request)
    {
        $totalData = DoctorServices::where('doctor_id', $request->doctorId)->count();
        $rows = DoctorServices::where('doctor_id', $request->doctorId)->orderBy('id', 'DESC')->get();
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
            $result = DoctorServices::where('doctor_id', $request->doctorId)
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
        } else {
            $search = $request->input('search.value');
            $result = DoctorServices::where('doctor_id', $request->doctorId)
                ->where(function ($query) use ($search) {
                    $query->Where('title', 'LIKE', "%{$search}%");
                })
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
            $totalFiltered = DoctorServices::where('doctor_id', $request->doctorId)
                ->where(function ($query) use ($search) {
                    $query->Where('title', 'LIKE', "%{$search}%");
                })
                ->count();
        }
        $data = array();
        foreach ($result as $item) {

            $delete = '<a href="" class="mr-2 btn btn-danger text-white delete" rel=' . $item->id . ' >' . __("Delete") . '</a>';
            $data[] = array(
                $item->title,
                $delete,
            );
        }
        $json_data = array(
            "draw" => intval($request->input('draw')),
            "recordsTotal" => intval($totalData),
            "recordsFiltered" => $totalFiltered,
            "data" => $data
        );
        echo json_encode($json_data);
        exit();
    }

    function fetchDoctorEarningsList(Request $request)
    {
        $totalData = DoctorEarningHistory::where('doctor_id', $request->doctorId)->with('appointment')->count();
        $rows = DoctorEarningHistory::where('doctor_id', $request->doctorId)->with('appointment')->orderBy('id', 'DESC')->get();
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
            $result = DoctorEarningHistory::where('doctor_id', $request->doctorId)->with('appointment')
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
        } else {
            $search = $request->input('search.value');
            $result = DoctorEarningHistory::where('doctor_id', $request->doctorId)
                ->with('appointment')
                ->where(function ($query) use ($search) {
                    $query->Where('earning_number', 'LIKE', "%{$search}%")
                        ->orWhere('amount', 'LIKE', "%{$search}%");
                })
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
            $totalFiltered = DoctorEarningHistory::where('doctor_id', $request->doctorId)
                ->with('appointment')
                ->where(function ($query) use ($search) {
                    $query->Where('earning_number', 'LIKE', "%{$search}%")
                        ->orWhere('amount', 'LIKE', "%{$search}%");
                })
                ->orWhere('amount', 'LIKE', "%{$search}%")
                ->count();
        }
        $data = array();
        foreach ($result as $item) {

            $data[] = array(
                $item->earning_number,
                $item->appointment->appointment_number,
                $settings->currency . $item->amount,
                GlobalFunction::formateTimeString($item->created_at),
            );
        }
        $json_data = array(
            "draw" => intval($request->input('draw')),
            "recordsTotal" => intval($totalData),
            "recordsFiltered" => $totalFiltered,
            "data" => $data
        );
        echo json_encode($json_data);
        exit();
    }

    function fetchDoctorPayoutRequestsList(Request $request)
    {
        $totalData = DoctorPayoutHistory::where('doctor_id', $request->doctorId)->with('doctor')->count();
        $rows = DoctorPayoutHistory::where('doctor_id', $request->doctorId)->with('doctor')->orderBy('id', 'DESC')->get();
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
            $result = DoctorPayoutHistory::where('doctor_id', $request->doctorId)->with('doctor')
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
        } else {
            $search = $request->input('search.value');
            $result = DoctorPayoutHistory::where('doctor_id', $request->doctorId)
                ->where(function ($query) use ($search) {
                    $query->where('request_number', 'LIKE', "%{$search}%")
                        ->orWhere('amount', 'LIKE', "%{$search}%")
                        ->orWhere('summary', 'LIKE', "%{$search}%")
                        ->orWhereHas('doctor', function ($query) use ($search) {
                            $query->Where('name', 'LIKE', "%{$search}%");
                        });
                })
                ->with('doctor')
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
            $totalFiltered = DoctorPayoutHistory::where('doctor_id', $request->doctorId)->with('doctor')
                ->where(function ($query) use ($search) {
                    $query->where('request_number', 'LIKE', "%{$search}%")
                        ->orWhere('amount', 'LIKE', "%{$search}%")
                        ->orWhere('summary', 'LIKE', "%{$search}%")
                        ->orWhereHas('doctor', function ($query) use ($search) {
                            $query->Where('name', 'LIKE', "%{$search}%");
                        });
                })
                ->count();
        }
        $data = array();
        foreach ($result as $item) {

            $bankAccount = $item->doctor->bankAccount;

            $bankDetails = "";

            if ($bankAccount != null) {
                $holder = '<span class="text-dark font-weight-bold font-14">' . $bankAccount->holder . '</span>';
                $bank_title = '<div class="bank-details"><span>' . $bankAccount->bank_name . '</span>';
                $account_number = '<span>' . __('Account : ') . $bankAccount->account_number . '</span>';
                $swift_code = '<span>' . __('Swift Code : ') . $bankAccount->swift_code . '</span></div>';
                $bankDetails = $holder . $bank_title . $account_number . $swift_code;
            }

            $complete = '<a href="" class="mr-2 btn btn-success text-white complete" rel=' . $item->id . ' >' . __("Complete") . '</a>';
            $reject = '<a href="" class="mr-2 btn btn-danger text-white reject" rel=' . $item->id . ' >' . __("Reject") . '</a>';
            $action = '';

            if ($item->status == Constants::statusWithdrawalPending) {
                $status = '<span class="badge bg-warning text-white"rel="' . $item->id . '">' . __('Pending') . '</span>';
                $action = $complete . $reject;
            }
            if ($item->status == Constants::statusWithdrawalCompleted) {
                $status = '<span class="badge bg-success text-white"rel="' . $item->id . '">' . __('Completed') . '</span>';
            }
            if ($item->status == Constants::statusWithdrawalRejected) {
                $status = '<span class="badge bg-danger text-white"rel="' . $item->id . '">' . __('Rejected') . '</span>';
            }

            // Amount & Status
            $amount = '<span class="text-dark font-weight-bold font-16">' . $settings->currency . $item->amount . '</span><br>';
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
            "draw" => intval($request->input('draw')),
            "recordsTotal" => intval($totalData),
            "recordsFiltered" => $totalFiltered,
            "data" => $data
        );
        echo json_encode($json_data);
        exit();
    }

    function fetchDoctorWalletStatement(Request $request)
    {
        $totalData = DoctorWalletStatements::where('doctor_id', $request->doctorId)->count();
        $rows = DoctorWalletStatements::where('doctor_id', $request->doctorId)->orderBy('id', 'DESC')->get();
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
            $result = DoctorWalletStatements::where('doctor_id', $request->doctorId)
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
        } else {
            $search = $request->input('search.value');
            $result = DoctorWalletStatements::where('doctor_id', $request->doctorId)
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
            $totalFiltered = DoctorWalletStatements::where('doctor_id', $request->doctorId)
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
                $icon = '<i class="fas fa-plus-circle m-1 ic-credit"></i>';
                $textClass = 'text-credit';
                $crDrBadge = '<span  class="badge bg-success text-white ">' . __("Credit") . '</span>';
            } else {
                $icon = '<i class="fas fa-minus-circle m-1 ic-debit"></i>';
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
            "draw" => intval($request->input('draw')),
            "recordsTotal" => intval($totalData),
            "recordsFiltered" => $totalFiltered,
            "data" => $data
        );
        echo json_encode($json_data);
        exit();
    }

    function fetchDoctorReviewsList(Request $request)
    {
        $totalData = DoctorReviews::where('doctor_id', $request->doctorId)->with(['doctor', 'appointment'])->count();
        $rows = DoctorReviews::where('doctor_id', $request->doctorId)->with(['doctor', 'appointment'])->orderBy('id', 'DESC')->get();

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
            $result = DoctorReviews::where('doctor_id', $request->doctorId)->with(['doctor', 'appointment'])

                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
        } else {
            $search = $request->input('search.value');
            $result = DoctorReviews::where('doctor_id', $request->doctorId)->with(['doctor', 'appointment'])
                ->whereHas('appointment', function ($q) use ($search) {
                    $q->where('appointment_number', 'LIKE', "%{$search}%");
                })
                ->orWhere('comment', 'LIKE', "%{$search}%")
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
            $totalFiltered = DoctorReviews::where('doctor_id', $request->doctorId)->with(['doctor', 'appointment'])
                ->whereHas('appointment', function ($q) use ($search) {
                    $q->where('appointment_number', 'LIKE', "%{$search}%");
                })
                ->orWhere('comment', 'LIKE', "%{$search}%")
                ->count();
        }
        $data = array();
        foreach ($result as $item) {
            $delete = '<a href="" class="mr-2 btn btn-danger text-white delete" rel=' . $item->id . ' >' . __("Delete") . '</a>';

            $starDisabled = '<i class="fas fa-star starDisabled"></i>';
            $starActive = '<i class="fas fa-star starActive"></i>';

            $ratingBar = '';
            for ($i = 0; $i < 5; $i++) {
                if ($item->rating > $i) {
                    $ratingBar = $ratingBar . $starActive;
                } else {
                    $ratingBar = $ratingBar . $starDisabled;
                }
            }

            $doctor = '<a href="' . route('viewDoctorProfile', $item->doctor->id) . '"><span class="badge bg-primary text-white">
                        ' . $item->doctor->name . '</span></a>';

            $action = $delete;
            $data[] = array(
                $ratingBar,
                $item->comment,
                $item->appointment != null ? $item->appointment->appointment_number : '',
                $doctor,
                $action,
            );
        }
        $json_data = array(
            "draw" => intval($request->input('draw')),
            "recordsTotal" => intval($totalData),
            "recordsFiltered" => $totalFiltered,
            "data" => $data
        );
        echo json_encode($json_data);
        exit();
    }

    function fetchDoctorAppointmentsList(Request $request)
    {
        $totalData = Appointments::where('doctor_id', $request->doctorId)->count();
        $rows = Appointments::where('doctor_id', $request->doctorId)->orderBy('id', 'DESC')->get();
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
            $result = Appointments::where('doctor_id', $request->doctorId)->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
        } else {
            $search = $request->input('search.value');
            $result = Appointments::where('doctor_id', $request->doctorId)->where(function ($query) use ($search) {
                $query->Where('appointment_number', 'LIKE', "%{$search}%")
                    ->orWhere('payable_amount', 'LIKE', "%{$search}%");
            })->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
            $totalFiltered = Appointments::where('doctor_id', $request->doctorId)->where(function ($query) use ($search) {
                $query->Where('appointment_number', 'LIKE', "%{$search}%")
                    ->orWhere('payable_amount', 'LIKE', "%{$search}%");
            })->count();
        }
        $data = array();
        foreach ($result as $item) {

            $user = "";
            if ($item->user != null) {
                $user = '<a href="' . route('viewUserProfile', $item->user->id) . '"><span class="badge bg-primary text-white">' . $item->user->fullname . '</span></a>';
            }

            $view = '<a href="' . route('viewAppointment', $item->id) . '" class="mr-2 btn btn-info text-white " rel=' . $item->id . ' >' . __("View") . '</a>';

            $status = GlobalFunction::returnAppointmentStatus($item->status);

            $action = $view;

            $dateTime = $item->date . '<br>' . GlobalFunction::formateTimeString($item->time);
            $payableAmount = $settings->currency . $item->payable_amount;

            $data[] = array(
                $item->appointment_number,
                $user,
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
            "draw" => intval($request->input('draw')),
            "recordsTotal" => intval($totalData),
            "recordsFiltered" => $totalFiltered,
            "data" => $data
        );
        echo json_encode($json_data);
        exit();
    }
    function updateDoctorDetails_Admin(Request $request)
    {
        $item = Doctors::find($request->id);
        $item->designation = $request->designation;
        $item->languages_spoken = $request->languages_spoken;
        $item->consultation_fee = $request->consultation_fee;
        $item->experience_year = $request->experience_year;

        $item->degrees = $request->degrees;
        $item->about_youself = $request->about_youself;
        $item->educational_journey = $request->educational_journey;
        $item->save();

        return Globalfunction::sendSimpleResponse(true, 'Details Updated successfully');
    }

    function banDoctor($id)
    {
        $item = Doctors::find($id);
        $item->status = Constants::statusDoctorBanned;
        $item->save();
        return GlobalFunction::sendSimpleResponse(true, 'Doctor banned successfully!');
    }

    function activateDoctor($id)
    {
        $item = Doctors::find($id);
        $item->status = Constants::statusDoctorApproved;
        $item->save();
        return GlobalFunction::sendSimpleResponse(true, 'Doctor activated successfully!');
    }

    function viewDoctorProfile($doctorId)
    {
        $hostAndConversionRate = Helpers::conversionRate();
        $conversionRate = (float) $hostAndConversionRate['conversionRate'];
        $doctor = Doctors::select('doctors.*',DB::raw("ROUND(consultation_fee * {$conversionRate}) as consultation_fee"))
                    ->with(['bankAccount', 'category'])->find($doctorId);
        $settings = GlobalSettings::first();

        // $slots = DoctorAppointmentSlots::where('doctor_id', $doctorId)->get();
        $slots = DoctorAppointmentSlots::where('doctor_id', $doctorId)
            ->orderBy('weekday')
            ->orderByRaw("CAST(LPAD(REPLACE(time, ':', ''), 4, '0') AS UNSIGNED) ASC")
            ->get();

        foreach ($slots as $slot) {
            $slot->time = GlobalFunction::formateTimeString($slot->time);
        }

        $doctor->visible_mobile_number = GlobalFunction::decodeDoctorsMobileNumber($doctor);

        $mondaySlots = array_filter($slots->toArray(), function ($slot) {
            return $slot['weekday'] === 1;
        });
        $tuesdaySlots = array_filter($slots->toArray(), function ($slot) {
            return $slot['weekday'] === 2;
        });
        $wednesdaySlots = array_filter($slots->toArray(), function ($slot) {
            return $slot['weekday'] === 3;
        });
        $thursdaySlots = array_filter($slots->toArray(), function ($slot) {
            return $slot['weekday'] === 4;
        });
        $fridaySlots = array_filter($slots->toArray(), function ($slot) {
            return $slot['weekday'] === 5;
        });
        $saturdaySlots = array_filter($slots->toArray(), function ($slot) {
            return $slot['weekday'] === 6;
        });
        $sundaySlots = array_filter($slots->toArray(), function ($slot) {
            return $slot['weekday'] === 7;
        });

        return view('viewDoctorProfile', [
            'doctor' => $doctor,
            'settings' => $settings,
            'doctorStatus' => array(
                'statusDoctorPending' => Constants::statusDoctorPending,
                'statusDoctorApproved' => Constants::statusDoctorApproved,
                'statusDoctorBanned' => Constants::statusDoctorBanned,
            ),
            'slots' => array(
                'mondaySlots' => $mondaySlots,
                'tuesdaySlots' => $tuesdaySlots,
                'wednesdaySlots' => $wednesdaySlots,
                'thursdaySlots' => $thursdaySlots,
                'fridaySlots' => $fridaySlots,
                'saturdaySlots' => $saturdaySlots,
                'sundaySlots' => $sundaySlots,
            )
        ]);
    }

    function fetchDoctorPlansAndSlots(Request $request)
    {
        $allowedSlotOrder = [
            '06:00', '06:30', '07:00', '07:30', '08:00', '08:30', '09:00', '09:30',
            '10:00', '10:30', '11:00', '11:30', '12:00', '12:30', '13:00', '13:30',
            '14:00', '14:30', '15:00', '15:30', '16:00', '16:30', '17:00', '17:30',
            '18:00', '18:30', '19:00', '19:30', '20:00', '20:30', '21:00', '21:30',
            '22:00', '22:30', '23:00', '23:30', '00:00', '00:30', '01:00', '01:30',
            '02:00', '02:30', '03:00', '03:30', '04:00', '04:30', '05:00', '05:30'
        ];
        $allowedSlotPosition = array_flip($allowedSlotOrder);
        $normalizeSlotTime = function ($time) {
            $parsed = strtotime($time);
            if ($parsed !== false) {
                return date('H:i', $parsed);
            }

            $digits = preg_replace('/\D/', '', (string) $time);
            if ($digits === '') {
                return null;
            }

            return substr(str_pad($digits, 4, '0', STR_PAD_LEFT), 0, 2) . ':' . substr(str_pad($digits, 4, '0', STR_PAD_LEFT), 2, 2);
        };


        $hostAndConversionRate = Helpers::conversionRate();
        $conversionRate = (float) $hostAndConversionRate['conversionRate'];
        $doctor = Doctors::select('doctors.*',DB::raw("ROUND(consultation_fee * {$conversionRate}) as consultation_fee"))
                    ->with(['bankAccount', 'category'])->find($request->doctor_id);

        // $slots = DoctorAppointmentSlots::where('doctor_id', $request->doctor_id)->get();
        $slots = DoctorAppointmentSlots::where('doctor_id', $request->doctor_id)
            ->orderBy('weekday')
            ->orderByRaw("CAST(LPAD(REPLACE(time, ':', ''), 4, '0') AS UNSIGNED) ASC")
            ->get();

        foreach ($slots as $slot) {
            $slot->time = GlobalFunction::formateTimeString($slot->time);
        }

        $allowedSlotOrder = [
            '06:00', '06:30', '07:00', '07:30', '08:00', '08:30',
            '09:00', '09:30', '10:00', '10:30', '11:00', '11:30',
            '12:00', '12:30', '13:00', '13:30', '14:00', '14:30',
            '15:00', '15:30', '16:00', '16:30', '17:00', '17:30',
            '18:00', '18:30', '19:00', '19:30', '20:00', '20:30',
            '21:00', '21:30', '22:00', '22:30', '23:00', '23:30',
            '00:00', '00:30', '01:00', '01:30', '02:00', '02:30',
            '03:00', '03:30', '04:00', '04:30', '05:00', '05:30',
        ];
        $allowedSlotLookup = array_flip($allowedSlotOrder);
        $allFormattedSlots = $slots->toArray();

        $buildWeekdaySlots = function (int $weekday) use ($allFormattedSlots, $allowedSlotLookup) {
            $daySlots = array_values(array_filter($allFormattedSlots, function ($slot) use ($weekday, $allowedSlotLookup) {
                return (int) $slot['weekday'] === $weekday && isset($allowedSlotLookup[$slot['time']]);
        }));

            usort($daySlots, function ($a, $b) use ($allowedSlotLookup) {
                return $allowedSlotLookup[$a['time']] <=> $allowedSlotLookup[$b['time']];
            });

            return $daySlots;
        };

        $mondaySlots = $buildWeekdaySlots(1);
        $tuesdaySlots = $buildWeekdaySlots(2);
        $wednesdaySlots = $buildWeekdaySlots(3);
        $thursdaySlots = $buildWeekdaySlots(4);
        $fridaySlots = $buildWeekdaySlots(5);
        $saturdaySlots = $buildWeekdaySlots(6);
        $sundaySlots = $buildWeekdaySlots(7);

        // $doctorPlans = DoctorPlans::where('is_deleted',0)->get();
        $doctorPlans = DoctorPlans::where('is_deleted', 0)->get()->map(function ($plan) use ($request) {
            $discountValue = 0;

            if ($plan->discount && $plan->original_price) {
                if ($plan->discount_type === 'percent') {
                    $discountValue = ($plan->original_price * $plan->discount) / 100;
                } elseif ($plan->discount_type === 'flat') {
                    $discountValue = $plan->discount;
                }
            }

            // $plan->discount_value = round($discountValue, 2); // optional: round to 2 decimal places
            $plan->final_price = round($plan->original_price - round($discountValue, 2), 2);

            $lang = $request->header('lang','en');
            {
                if($lang == 'en')
                {
                    $plan->plan_name = $plan->plan_name;
                    $plan->number_of_days = $plan->number_of_days;
                    $plan->consultation_text = $plan->consultation_text;
                }
                if($lang == 'hi')
                {
                    $plan->plan_name = $plan->hi_plan_name;
                    $plan->number_of_days = $plan->hi_number_of_days;
                    $plan->consultation_text = $plan->hi_consultation_text;
                }
                if($lang == 'ur')
                {
                    $plan->plan_name = $plan->ur_plan_name;
                    $plan->number_of_days = $plan->ur_number_of_days;
                    $plan->consultation_text = $plan->ur_consultation_text;
                }
                if($lang == 'ar')
                {
                    $plan->plan_name = $plan->ar_plan_name;
                    $plan->number_of_days = $plan->ar_number_of_days;
                    $plan->consultation_text = $plan->ar_consultation_text;
                }
                if($lang == 'fr')
                {
                    $plan->plan_name = $plan->fr_plan_name;
                    $plan->number_of_days = $plan->fr_number_of_days;
                    $plan->consultation_text = $plan->fr_plan_name;
                }
            }
            return $plan;
        });


        return response()->json([
            'doctor' => $doctor,
            'plans' => $doctorPlans,
            'slots' => array(
                'mondaySlots' => $mondaySlots,
                'tuesdaySlots' => $tuesdaySlots,
                'wednesdaySlots' => $wednesdaySlots,
                'thursdaySlots' => $thursdaySlots,
                'fridaySlots' => $fridaySlots,
                'saturdaySlots' => $saturdaySlots,
                'sundaySlots' => $sundaySlots,
            )
        ]);
    }

    function date_wise_slot(Request $request)
    {
        $allowedSlotOrder = [
            '06:00', '06:30', '07:00', '07:30', '08:00', '08:30', '09:00', '09:30',
            '10:00', '10:30', '11:00', '11:30', '12:00', '12:30', '13:00', '13:30',
            '14:00', '14:30', '15:00', '15:30', '16:00', '16:30', '17:00', '17:30',
            '18:00', '18:30', '19:00', '19:30', '20:00', '20:30', '21:00', '21:30',
            '22:00', '22:30', '23:00', '23:30', '00:00', '00:30', '01:00', '01:30',
            '02:00', '02:30', '03:00', '03:30', '04:00', '04:30', '05:00', '05:30'
        ];
        $allowedSlotPosition = array_flip($allowedSlotOrder);
        $normalizeSlotTime = function ($time) {
            $parsed = strtotime($time);
            if ($parsed !== false) {
                return date('H:i', $parsed);
            }

            $digits = preg_replace('/\D/', '', (string) $time);
            if ($digits === '') {
                return null;
            }

            return substr(str_pad($digits, 4, '0', STR_PAD_LEFT), 0, 2) . ':' . substr(str_pad($digits, 4, '0', STR_PAD_LEFT), 2, 2);
        };

        $rules = [
            'doctor_id' => 'required',
            'date' => 'required',
            'weekday' => 'required'
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }

        // $doctor_slots = DoctorAppointmentSlots::where('doctor_id', $request->doctor_id)->where('weekday', $request->weekday)->get();
        $doctor_slots = DoctorAppointmentSlots::where('doctor_id', $request->doctor_id)
            ->where('weekday', $request->weekday)
            ->orderByRaw("CAST(LPAD(REPLACE(time, ':', ''), 4, '0') AS UNSIGNED) ASC")
            ->get();

        $bookingDate = GlobalFunction::normalizeDateToYmd($request->date) ?? $request->date;
        $bookedSlotTimes = GlobalFunction::getActiveBookedSlotTimesForDoctorDate($request->doctor_id, $bookingDate);
        $slots = [];
        foreach ($doctor_slots as $slot) {
            if (GlobalFunction::isDoctorSlotOverlappingAppointment($slot->time, $bookedSlotTimes)) {
                continue;
            }

            $slot->is_booked = 0;
                $slot->time = GlobalFunction::formateTimeString($slot->time);
            $slots[] = $slot;
        }
        $slots = array_values(array_filter($slots, function ($slot) use ($allowedSlotPosition, $normalizeSlotTime) {
            $normalized = $normalizeSlotTime($slot->time);
            return $normalized !== null && isset($allowedSlotPosition[$normalized]);
        }));

        usort($slots, function ($a, $b) use ($allowedSlotPosition, $normalizeSlotTime) {
            return $allowedSlotPosition[$normalizeSlotTime($a->time)] <=> $allowedSlotPosition[$normalizeSlotTime($b->time)];
        });

        return $slots;

    }

    function rejectDoctorWithdrawal(Request $request)
    {
        $item = DoctorPayoutHistory::find($request->id);
        if ($request->has('summary')) {
            $item->summary = $request->summary;
        }
        $item->status = Constants::statusWithdrawalRejected;
        $item->save();

        $summary = '(Rejected) Withdraw request :' . $item->request_number;
        // Adding wallet statement
        GlobalFunction::addDoctorStatementEntry(
            $item->doctor->id,
            null,
            $item->amount,
            Constants::credit,
            Constants::doctorWalletPayoutReject,
            $summary
        );

        //adding money to user wallet
        $item->doctor->wallet = $item->doctor->wallet + $item->amount;
        $item->doctor->save();

        return GlobalFunction::sendSimpleResponse(true, 'request rejected successfully');
    }

    function completeDoctorWithdrawal(Request $request)
    {
        $item = DoctorPayoutHistory::find($request->id);
        if ($request->has('summary')) {
            $item->summary = $request->summary;
        }
        $item->status = Constants::statusWithdrawalCompleted;
        $item->save();

        return GlobalFunction::sendSimpleResponse(true, 'request completed successfully');
    }

    function fetchDoctorRejectedWithdrawalsList(Request $request)
    {
        $totalData = DoctorPayoutHistory::where('status', Constants::statusWithdrawalRejected)->with('doctor')->count();
        $rows = DoctorPayoutHistory::where('status', Constants::statusWithdrawalRejected)->with('doctor')->orderBy('id', 'DESC')->get();
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
            $result = DoctorPayoutHistory::where('status', Constants::statusWithdrawalRejected)
                ->with('doctor')
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
        } else {
            $search = $request->input('search.value');
            $result = DoctorPayoutHistory::where('status', Constants::statusWithdrawalRejected)
                ->where(function ($query) use ($search) {
                    $query->where('request_number', 'LIKE', "%{$search}%")
                        ->orWhere('amount', 'LIKE', "%{$search}%")
                        ->orWhere('summary', 'LIKE', "%{$search}%")
                        ->orWhereHas('doctor', function ($query) use ($search) {
                            $query->Where('name', 'LIKE', "%{$search}%");
                        });
                })
                ->with('doctor')
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
            $totalFiltered = DoctorPayoutHistory::where('status', Constants::statusWithdrawalRejected)
                ->with('doctor')
                ->where(function ($query) use ($search) {
                    $query->where('request_number', 'LIKE', "%{$search}%")
                        ->orWhere('amount', 'LIKE', "%{$search}%")
                        ->orWhere('summary', 'LIKE', "%{$search}%")
                        ->orWhereHas('doctor', function ($query) use ($search) {
                            $query->Where('name', 'LIKE', "%{$search}%");
                        });
                })
                ->count();
        }
        $data = array();
        foreach ($result as $item) {

            $bankAccount = $item->doctor->bankAccount;

            $bankDetails = "";

            if ($bankAccount != null) {
                $holder = '<span class="text-dark font-weight-bold font-14">' . $bankAccount->holder . '</span>';
                $bank_title = '<div class="bank-details"><span>' . $bankAccount->bank_title . '</span>';
                $account_number = '<span>' . __('Account : ') . $bankAccount->account_number . '</span>';
                $swift_code = '<span>' . __('Swift Code : ') . $bankAccount->swift_code . '</span></div>';
                $bankDetails = $holder . $bank_title . $account_number . $swift_code;
            }

            // Amount & Status
            $amount = '<span class="text-dark font-weight-bold font-16">' . $settings->currency . $item->amount . '</span><br>';
            $status = '<span class="badge bg-danger text-white"rel="' . $item->id . '">' . __('Rejected') . '</span>';
            $amountData = $amount . $status;

            $doctor = "";
            if ($item->doctor != null) {
                $doctor = '<a href="' . route('viewDoctorProfile', $item->doctor->id) . '"><span class="badge bg-primary text-white">' . $item->doctor->name . '</span></a>';
            }



            $data[] = array(
                $item->request_number,
                $bankDetails,
                $amountData,
                $doctor,
                $item->summary
            );
        }
        $json_data = array(
            "draw" => intval($request->input('draw')),
            "recordsTotal" => intval($totalData),
            "recordsFiltered" => $totalFiltered,
            "data" => $data
        );
        echo json_encode($json_data);
        exit();
    }

    function fetchDoctorCompletedWithdrawalsList(Request $request)
    {
        $totalData = DoctorPayoutHistory::where('status', Constants::statusWithdrawalCompleted)->with('doctor')->count();
        $rows = DoctorPayoutHistory::where('status', Constants::statusWithdrawalCompleted)->with('doctor')->orderBy('id', 'DESC')->get();
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
            $result = DoctorPayoutHistory::where('status', Constants::statusWithdrawalCompleted)
                ->with('doctor')
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
        } else {
            $search = $request->input('search.value');
            $result = DoctorPayoutHistory::where('status', Constants::statusWithdrawalCompleted)
                ->where(function ($query) use ($search) {
                    $query->where('request_number', 'LIKE', "%{$search}%")
                        ->orWhere('amount', 'LIKE', "%{$search}%")
                        ->orWhere('summary', 'LIKE', "%{$search}%")
                        ->orWhereHas('doctor', function ($query) use ($search) {
                            $query->Where('name', 'LIKE', "%{$search}%");
                        });
                })
                ->with('doctor')
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
            $totalFiltered = DoctorPayoutHistory::where('status', Constants::statusWithdrawalCompleted)
                ->with('doctor')
                ->where(function ($query) use ($search) {
                    $query->where('request_number', 'LIKE', "%{$search}%")
                        ->orWhere('amount', 'LIKE', "%{$search}%")
                        ->orWhere('summary', 'LIKE', "%{$search}%")
                        ->orWhereHas('doctor', function ($query) use ($search) {
                            $query->Where('name', 'LIKE', "%{$search}%");
                        });
                })
                ->count();
        }
        $data = array();
        foreach ($result as $item) {

            $bankAccount = $item->doctor->bankAccount;

            $bankDetails = "";

            if ($bankAccount != null) {
                $holder = '<span class="text-dark font-weight-bold font-14">' . $bankAccount->holder . '</span>';
                $bank_title = '<div class="bank-details"><span>' . $bankAccount->bank_title . '</span>';
                $account_number = '<span>' . __('Account : ') . $bankAccount->account_number . '</span>';
                $swift_code = '<span>' . __('Swift Code : ') . $bankAccount->swift_code . '</span></div>';
                $bankDetails = $holder . $bank_title . $account_number . $swift_code;
            }

            // Amount & Status
            $amount = '<span class="text-dark font-weight-bold font-16">' . $settings->currency . $item->amount . '</span><br>';
            $status = '<span class="badge bg-success text-white"rel="' . $item->id . '">' . __('Completed') . '</span>';
            $amountData = $amount . $status;

            $doctor = "";
            if ($item->doctor != null) {
                $doctor = '<a href="' . route('viewDoctorProfile', $item->doctor->id) . '"><span class="badge bg-primary text-white">' . $item->doctor->name . '</span></a>';
            }

            $data[] = array(
                $item->request_number,
                $bankDetails,
                $amountData,
                $doctor,
                $item->summary
            );
        }
        $json_data = array(
            "draw" => intval($request->input('draw')),
            "recordsTotal" => intval($totalData),
            "recordsFiltered" => $totalFiltered,
            "data" => $data
        );
        echo json_encode($json_data);
        exit();
    }

    function fetchDoctorPendingWithdrawalsList(Request $request)
    {
        $totalData = DoctorPayoutHistory::with('doctor')->count();
        $rows = DoctorPayoutHistory::where('status', Constants::statusWithdrawalPending)->with('doctor')->orderBy('id', 'DESC')->get();
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
            $result = DoctorPayoutHistory::where('status', Constants::statusWithdrawalPending)
                ->with('doctor')
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
        } else {
            $search = $request->input('search.value');
            $result = DoctorPayoutHistory::where('status', Constants::statusWithdrawalPending)
                ->where(function ($query) use ($search) {
                    $query->where('request_number', 'LIKE', "%{$search}%")
                        ->orWhere('amount', 'LIKE', "%{$search}%")
                        ->orWhere('summary', 'LIKE', "%{$search}%")
                        ->orWhereHas('doctor', function ($query) use ($search) {
                            $query->Where('name', 'LIKE', "%{$search}%");
                        });
                })
                ->with('doctor')
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
            $totalFiltered = DoctorPayoutHistory::where('status', Constants::statusWithdrawalPending)
                ->with('doctor')
                ->where(function ($query) use ($search) {
                    $query->where('request_number', 'LIKE', "%{$search}%")
                        ->orWhere('amount', 'LIKE', "%{$search}%")
                        ->orWhere('summary', 'LIKE', "%{$search}%")
                        ->orWhereHas('doctor', function ($query) use ($search) {
                            $query->Where('name', 'LIKE', "%{$search}%");
                        });
                })
                ->count();
        }
        $data = array();
        foreach ($result as $item) {

            $bankAccount = $item->doctor->bankAccount;

            $bankDetails = "";

            if ($bankAccount != null) {
                $holder = '<span class="text-dark font-weight-bold font-14">' . $bankAccount->holder . '</span>';
                $bank_title = '<div class="bank-details"><span>' . $bankAccount->bank_title . '</span>';
                $account_number = '<span>' . __('Account : ') . $bankAccount->account_number . '</span>';
                $swift_code = '<span>' . __('Swift Code : ') . $bankAccount->swift_code . '</span></div>';
                $bankDetails = $holder . $bank_title . $account_number . $swift_code;
            }

            // Amount & Status
            $amount = '<span class="text-dark font-weight-bold font-16">' . $settings->currency . $item->amount . '</span><br>';
            $status = '<span class="badge bg-warning text-white"rel="' . $item->id . '">' . __('Pending') . '</span>';
            $amountData = $amount . $status;

            $complete = '<a href="" class="mr-2 btn btn-success text-white complete" rel=' . $item->id . ' >' . __("Complete") . '</a>';
            $reject = '<a href="" class="mr-2 btn btn-danger text-white reject" rel=' . $item->id . ' >' . __("Reject") . '</a>';
            // $delete = '<a href="" class="mr-2 btn btn-danger text-white delete" rel=' . $item->id . ' >' . __("Delete") . '</a>';
            $action = $complete . $reject;

            $doctor = "";
            if ($item->doctor != null) {
                $doctor = '<a href="' . route('viewDoctorProfile', $item->doctor->id) . '"><span class="badge bg-primary text-white">' . $item->doctor->name . '</span></a>';
            }


            $data[] = array(
                $item->request_number,
                $bankDetails,
                $amountData,
                $doctor,
                $item->created_at->format('d M, Y'),
                $action
            );
        }
        $json_data = array(
            "draw" => intval($request->input('draw')),
            "recordsTotal" => intval($totalData),
            "recordsFiltered" => $totalFiltered,
            "data" => $data
        );
        echo json_encode($json_data);
        exit();
    }

    function doctorWithdraws()
    {
        return view('doctorWithdraws');
    }

    function doctors()
    {
        return view('doctors');
    }

    function fetchBannedDoctorsList(Request $request)
    {
        $hostAndConversionRate = Helpers::conversionRate();
        $conversionRate = (float) $hostAndConversionRate['conversionRate'];
        $totalData = Doctors::select('doctors.*',DB::raw("ROUND(consultation_fee * {$conversionRate}) as consultation_fee"))
                        ->where('status', Constants::statusDoctorBanned)->count();
        $rows = Doctors::select('doctors.*',DB::raw("ROUND(consultation_fee * {$conversionRate}) as consultation_fee"))
                    ->where('status', Constants::statusDoctorBanned)->orderBy('id', 'DESC')->get();
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
            $result = select('doctors.*',DB::raw("ROUND(consultation_fee * {$conversionRate}) as consultation_fee"))
                ->where('status', Constants::statusDoctorBanned)->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
        } else {
            $search = $request->input('search.value');
            $result = select('doctors.*',DB::raw("ROUND(consultation_fee * {$conversionRate}) as consultation_fee"))
                ->where('status', Constants::statusDoctorBanned)->where(function ($query) use ($search) {
                $query->Where('name', 'LIKE', "%{$search}%")
                    ->orWhere('mobile_number', 'LIKE', "%{$search}%")
                    ->orWhere('doctor_number', 'LIKE', "%{$search}%");
            })->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
            $totalFiltered = select('doctors.*',DB::raw("ROUND(consultation_fee * {$conversionRate}) as consultation_fee"))
                ->where('status', Constants::statusDoctorBanned)->where(function ($query) use ($search) {
                $query->Where('name', 'LIKE', "%{$search}%")
                    ->orWhere('mobile_number', 'LIKE', "%{$search}%")
                    ->orWhere('doctor_number', 'LIKE', "%{$search}%");
            })->count();
        }
        $data = array();
        foreach ($result as $item) {

            if ($item->image == null) {
                $image = '<img src="http://placehold.jp/150x150.png" width="50" height="50">';
            } else {
                $imgUrl = GlobalFunction::createMediaUrl($item->image);
                $image = '<img src="' . $imgUrl . '" width="50" height="50">';
            }

            $view = '<a href="' . route('viewDoctorProfile', $item->id) . '" class="mr-2 btn btn-info text-white " rel=' . $item->id . ' >' . __("View") . '</a>';

            $status = "";
            if ($item->status == Constants::statusDoctorPending) {
                $status = '<span  class="badge bg-warning text-white ">' . __("Pending") . '</span>';
            }
            if ($item->status == Constants::statusDoctorApproved) {
                $status = '<span  class="badge bg-success text-white ">' . __("Approved") . '</span>';
            }
            if ($item->status == Constants::statusDoctorBanned) {
                $status = '<span  class="badge bg-danger text-white ">' . __("Banned") . '</span>';
            }

            $gender = '';
            if ($item->gender == Constants::genderMale) {
                $gender = '<span  class="badge bg-primary text-white ">' . __("Male") . '</span>';
            }
            if ($item->gender == Constants::genderFemale) {
                $gender = '<span  class="badge bg-info text-white ">' . __("Female") . '</span>';
            }


            $action = $view;

            $category = $item->category == null ? '' : $item->category->title;

            $data[] = array(
                $image,
                $item->name,
                $item->doctor_number,
                $status,
                $gender,
                $category,
                $item->experience_year,
                $item->total_patients_cured,
                $settings->currency . $item->lifetime_earnings,
                GlobalFunction::decodeDoctorsMobileNumber($item),
                $action,
            );
        }
        $json_data = array(
            "draw" => intval($request->input('draw')),
            "recordsTotal" => intval($totalData),
            "recordsFiltered" => $totalFiltered,
            "data" => $data
        );
        echo json_encode($json_data);
        exit();
    }

    function fetchPendingDoctorsList(Request $request)
    {
        $hostAndConversionRate = Helpers::conversionRate();
        $conversionRate = (float) $hostAndConversionRate['conversionRate'];
        $totalData = Doctors::select('doctors.*',DB::raw("ROUND(consultation_fee * {$conversionRate}) as consultation_fee"))
                    ->where('status', Constants::statusDoctorPending)->count();
        $rows = Doctors::select('doctors.*',DB::raw("ROUND(consultation_fee * {$conversionRate}) as consultation_fee"))
                    ->where('status', Constants::statusDoctorPending)->orderBy('id', 'DESC')->get();
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
            $result = Doctors::select('doctors.*',DB::raw("ROUND(consultation_fee * {$conversionRate}) as consultation_fee"))
                        ->where('status', Constants::statusDoctorPending)->offset($start)
                        ->limit($limit)
                        ->orderBy($order, $dir)
                        ->get();
        } else {
            $search = $request->input('search.value');
            $result = Doctors::select('doctors.*',DB::raw("ROUND(consultation_fee * {$conversionRate}) as consultation_fee"))
                        ->where('status', Constants::statusDoctorPending)->where(function ($query) use ($search) {
                            $query->Where('name', 'LIKE', "%{$search}%")
                                ->orWhere('mobile_number', 'LIKE', "%{$search}%")
                                ->orWhere('doctor_number', 'LIKE', "%{$search}%");
                        })->offset($start)
                        ->limit($limit)
                        ->orderBy($order, $dir)
                        ->get();
            $totalFiltered = Doctors::select('doctors.*',DB::raw("ROUND(consultation_fee * {$conversionRate}) as consultation_fee"))
                        ->where('status', Constants::statusDoctorPending)->where(function ($query) use ($search) {
                            $query->Where('name', 'LIKE', "%{$search}%")
                                ->orWhere('mobile_number', 'LIKE', "%{$search}%")
                                ->orWhere('doctor_number', 'LIKE', "%{$search}%");
                        })->count();
        }
        $data = array();
        foreach ($result as $item) {

            if ($item->image == null) {
                $image = '<img src="http://placehold.jp/150x150.png" width="50" height="50">';
            } else {
                $imgUrl = GlobalFunction::createMediaUrl($item->image);
                $image = '<img src="' . $imgUrl . '" width="50" height="50">';
            }

            $view = '<a href="' . route('viewDoctorProfile', $item->id) . '" class="mr-2 btn btn-info text-white " rel=' . $item->id . ' >' . __("View") . '</a>';

            $status = "";
            if ($item->status == Constants::statusDoctorPending) {
                $status = '<span  class="badge bg-warning text-white ">' . __("Pending") . '</span>';
            }
            if ($item->status == Constants::statusDoctorApproved) {
                $status = '<span  class="badge bg-success text-white ">' . __("Approved") . '</span>';
            }
            if ($item->status == Constants::statusDoctorBanned) {
                $status = '<span  class="badge bg-danger text-white ">' . __("Banned") . '</span>';
            }

            $gender = '';
            if ($item->gender == Constants::genderMale) {
                $gender = '<span  class="badge bg-primary text-white ">' . __("Male") . '</span>';
            }
            if ($item->gender == Constants::genderFemale) {
                $gender = '<span  class="badge bg-info text-white ">' . __("Female") . '</span>';
            }


            $action = $view;

            $category = $item->category == null ? '' : $item->category->title;

            $data[] = array(
                $image,
                $item->name,
                $item->doctor_number,
                $status,
                $gender,
                $category,
                $item->experience_year,
                $item->total_patients_cured,
                $settings->currency . $item->lifetime_earnings,
                GlobalFunction::decodeDoctorsMobileNumber($item),
                $action,
            );
        }
        $json_data = array(
            "draw" => intval($request->input('draw')),
            "recordsTotal" => intval($totalData),
            "recordsFiltered" => $totalFiltered,
            "data" => $data
        );
        echo json_encode($json_data);
        exit();
    }

    function fetchApprovedDoctorsList(Request $request)
    {
        $hostAndConversionRate = Helpers::conversionRate();
        $conversionRate = (float) $hostAndConversionRate['conversionRate'];
        $totalData = Doctors::select('doctors.*',DB::raw("ROUND(consultation_fee * {$conversionRate}) as consultation_fee"))
                        ->where('status', Constants::statusDoctorApproved)->count();
        $rows = Doctors::select('doctors.*',DB::raw("ROUND(consultation_fee * {$conversionRate}) as consultation_fee"))
                        ->where('status', Constants::statusDoctorApproved)->orderBy('id', 'DESC')->get();
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
            $result = Doctors::select('doctors.*',DB::raw("ROUND(consultation_fee * {$conversionRate}) as consultation_fee"))
                        ->where('status', Constants::statusDoctorApproved)->offset($start)
                        ->limit($limit)
                        ->orderBy($order, $dir)
                        ->get();
        } else {
            $search = $request->input('search.value');
            $result = Doctors::select('doctors.*',DB::raw("ROUND(consultation_fee * {$conversionRate}) as consultation_fee"))
                        ->where('status', Constants::statusDoctorApproved)->where(function ($query) use ($search) {
                            $query->Where('name', 'LIKE', "%{$search}%")
                                ->orWhere('mobile_number', 'LIKE', "%{$search}%")
                                ->orWhere('doctor_number', 'LIKE', "%{$search}%");
                        })->offset($start)
                        ->limit($limit)
                        ->orderBy($order, $dir)
                        ->get();
            $totalFiltered  = Doctors::select('doctors.*',DB::raw("ROUND(consultation_fee * {$conversionRate}) as consultation_fee"))
                                ->where('status', Constants::statusDoctorApproved)->where(function ($query) use ($search) {
                                    $query->Where('name', 'LIKE', "%{$search}%")
                                        ->orWhere('mobile_number', 'LIKE', "%{$search}%")
                                        ->orWhere('doctor_number', 'LIKE', "%{$search}%");
                                })->count();
        }
        $data = array();
        foreach ($result as $item) {

            if ($item->image == null) {
                $image = '<img src="http://placehold.jp/150x150.png" width="50" height="50">';
            } else {
                $imgUrl = GlobalFunction::createMediaUrl($item->image);
                $image = '<img src="' . $imgUrl . '" width="50" height="50">';
            }

            $view = '<a href="' . route('viewDoctorProfile', $item->id) . '" class="mr-2 btn btn-info text-white " rel=' . $item->id . ' >' . __("View") . '</a>';

            $status = "";
            if ($item->status == Constants::statusDoctorPending) {
                $status = '<span  class="badge bg-warning text-white ">' . __("Pending") . '</span>';
            }
            if ($item->status == Constants::statusDoctorApproved) {
                $status = '<span  class="badge bg-success text-white ">' . __("Approved") . '</span>';
            }
            if ($item->status == Constants::statusDoctorBanned) {
                $status = '<span  class="badge bg-danger text-white ">' . __("Banned") . '</span>';
            }

            $gender = '';
            if ($item->gender == Constants::genderMale) {
                $gender = '<span  class="badge bg-primary text-white ">' . __("Male") . '</span>';
            }
            if ($item->gender == Constants::genderFemale) {
                $gender = '<span  class="badge bg-info text-white ">' . __("Female") . '</span>';
            }


            $action = $view;

            $category = $item->category == null ? '' : $item->category->title;

            $data[] = array(
                $image,
                $item->name,
                $item->doctor_number,
                $status,
                $gender,
                $category,
                $item->experience_year,
                $item->total_patients_cured,
                $settings->currency . $item->lifetime_earnings,
                GlobalFunction::decodeDoctorsMobileNumber($item),
                $action,
            );
        }
        $json_data = array(
            "draw" => intval($request->input('draw')),
            "recordsTotal" => intval($totalData),
            "recordsFiltered" => $totalFiltered,
            "data" => $data
        );
        echo json_encode($json_data);
        exit();
    }

    function fetchAllDoctorsList(Request $request)
    {
        $hostAndConversionRate = Helpers::conversionRate();
        $conversionRate = (float) $hostAndConversionRate['conversionRate'];
        $totalData = Doctors::count();
        $rows = Doctors::orderBy('id', 'DESC')->get();
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
            $result = Doctors::select('doctors.*',DB::raw("ROUND(consultation_fee * {$conversionRate}) as consultation_fee"))
                        ->offset($start)
                        ->limit($limit)
                        ->orderBy($order, $dir)
                        ->get();
        } else {
            $search = $request->input('search.value');
            $result = Doctors::select('doctors.*',DB::raw("ROUND(consultation_fee * {$conversionRate}) as consultation_fee"))
                        ->where(function ($query) use ($search) {
                            $query->Where('name', 'LIKE', "%{$search}%")
                                ->orWhere('mobile_number', 'LIKE', "%{$search}%")
                                ->orWhere('doctor_number', 'LIKE', "%{$search}%");
                        })->offset($start)
                        ->limit($limit)
                        ->orderBy($order, $dir)
                        ->get();
            $totalFiltered = Doctors::select('doctors.*',DB::raw("ROUND(consultation_fee * {$conversionRate}) as consultation_fee"))
                                ->where(function ($query) use ($search) {
                                    $query->Where('name', 'LIKE', "%{$search}%")
                                        ->orWhere('mobile_number', 'LIKE', "%{$search}%")
                                        ->orWhere('doctor_number', 'LIKE', "%{$search}%");
                                })->count();
        }
        $data = array();
        foreach ($result as $item) {

            if ($item->image == null) {
                $image = '<img src="http://placehold.jp/150x150.png" width="50" height="50">';
            } else {
                $imgUrl = GlobalFunction::createMediaUrl($item->image);
                $image = '<img src="' . $imgUrl . '" width="50" height="50">';
            }

            $view = '<a href="' . route('viewDoctorProfile', $item->id) . '" class="mr-2 btn btn-info text-white " rel=' . $item->id . ' >' . __("View") . '</a>';

            $status = "";
            if ($item->status == Constants::statusDoctorPending) {
                $status = '<span  class="badge bg-warning text-white ">' . __("Pending") . '</span>';
            }
            if ($item->status == Constants::statusDoctorApproved) {
                $status = '<span  class="badge bg-success text-white ">' . __("Approved") . '</span>';
            }
            if ($item->status == Constants::statusDoctorBanned) {
                $status = '<span  class="badge bg-danger text-white ">' . __("Banned") . '</span>';
            }

            $gender = '';
            if ($item->gender == Constants::genderMale) {
                $gender = '<span  class="badge bg-primary text-white ">' . __("Male") . '</span>';
            }
            if ($item->gender == Constants::genderFemale) {
                $gender = '<span  class="badge bg-info text-white ">' . __("Female") . '</span>';
            }


            $action = $view;

            $category = $item->category == null ? '' : $item->category->title;

            $data[] = array(
                $image,
                $item->name,
                $item->doctor_number,
                $status,
                $gender,
                $category,
                $item->experience_year,
                $item->total_patients_cured,
                $settings->currency . $item->lifetime_earnings,
                GlobalFunction::decodeDoctorsMobileNumber($item),
                $action,
            );
        }
        $json_data = array(
            "draw" => intval($request->input('draw')),
            "recordsTotal" => intval($totalData),
            "recordsFiltered" => $totalFiltered,
            "data" => $data
        );
        echo json_encode($json_data);
        exit();
    }

    function checkMobileNumberExists(Request $request)
    {
        $rules = [
            'mobile_number' => 'required',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }

        $doctor = Doctors::where('mobile_number', $request->mobile_number)->first();

        if ($doctor == null) {
            return GlobalFunction::sendSimpleResponse(true, 'number available to use');
        } else {
            return GlobalFunction::sendSimpleResponse(false, 'mobile number in use already!');
        }
    }

    function changeOnlineStatus(Request $request)
    {
        $rules = [
            'doctor_id' => 'required',
            'status' => 'required',
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
        $doctor->is_online = $request->status;
        $doctor->save();
        return GlobalFunction::sendSimpleResponse(true, 'Status updated successfully');
    }

    function fetchDoctorReviews(Request $request)
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
        $hostAndConversionRate = Helpers::conversionRate();
        $conversionRate = (float) $hostAndConversionRate['conversionRate'];
        $doctor = Doctors::select('doctors.*',DB::raw("ROUND(consultation_fee * {$conversionRate}) as consultation_fee"))
                        ->where('id', $request->doctor_id)->first();
        if ($doctor == null) {
            return GlobalFunction::sendSimpleResponse(false, 'Doctor does not exists!');
        }
        // return $result =  DoctorReviews::with(['user'])
        //     ->Where('doctor_id', $request->doctor_id)
        //     ->whereHas('user')
        //     ->whereHas('doctor')
        //     ->orderBy('id', 'DESC')
        //     ->offset($request->start)
        //     ->limit($request->count)
        //     ->get();

        $result = [];

        $lang = $request->header('lang', 'en');
        if ($lang == "hi") {
            $firstRecord_hi = [
                "comment" => "मुल्क HnH कार्ड से मेरी MRI पर 50% बचत हुई। छूट तुरंत मिल गई और बहुत फर्क पड़ा!",
                "rating" => 5,
                "user" => [
                        "fullname" => "अब्दुल",
                        "gender"=> 1,
                        "profile_image" => "uploads/1430453.png"
                    ]
            ];

            $secondRecord_hi = [
                "comment" => "टेलीहेल्थ परामर्श बहुत सुविधाजनक था। मैंने घर बैठे विशेषज्ञ से बात की और मेरे सभी सवालों के जवाब मिले।",
                "rating" => 4,
                "user" => [
                        "fullname" => "विजिजा",
                        "gender"=> 0,
                        "profile_image" => "uploads/avatar.png"
                    ]
            ];

            $thirdRecord_hi = [
                "comment" => "सीटी स्कैन के लिए HnH कार्ड का उपयोग किया। बचत तुरंत मिल गई और प्रक्रिया बहुत आसान थी। शानदार सेवा।",
                "rating" => 4.5,
                "user" => [
                        "fullname" => "फयाज़",
                        "gender" => 1,
                        "profile_image" => "uploads/1430453.png"
                    ]
            ];

            $forthRecord_hi = [
                "comment" => "टेलीहेल्थ सेवा एक बहुत बड़ी मदद है। यह जानकर सुकून है कि मैं घर से ही प्रोफेशनल मेडिकल सलाह ले सकता हूँ।",
                "rating" => 5,
                "user" => [
                        "fullname" => "प्राशुम",
                        "gender" => 1,
                        "profile_image" => "uploads/1430453.png"
                    ]
            ];

            $fifthRecord_hi = [
                "comment" => "फॉलो-अप के लिए टेलीहेल्थ फीचर बढ़िया है। मैंने घर बैठे डॉक्टर से बात की और अपनी दवा फिर से लिखवाई। जोरदार सिफारिश करता हूँ।",
                "rating" => 4,
                "user" => [
                        "fullname" => "मैरी",
                        "gender" => 0,
                        "profile_image" => "uploads/avatar.png"
                    ]
            ];

            $sixRecord_hi = [
                "comment" => "HnH कार्ड से अल्ट्रासाउंड में मुझे पूरे 50% की छूट मिली। यह शानदार सेवा है जो मेडिकल खर्चों में सचमुच मदद करती है।",
                "rating" => 4.5,
                "user" => [
                        "fullname" => "आदम",
                        "gender" => 1,
                        "profile_image" => "uploads/1430453.png"
                    ]
            ];

            $seventhRecord_hi = [
                "comment" => "मेरी टेलीहेल्थ कंसल्टेशन बेहतरीन थी। डॉक्टर ने ध्यान से सुना और मुझे साफ योजना दी। व्यक्तिगत मिलन से कहीं आसान।",
                "rating" => 4,
                "user" => [
                        "fullname" => "सकीना",
                        "gender" => 0,
                        "profile_image" => "uploads/avatar.png"
                    ]
            ];


            array_push($result, $firstRecord_hi);
            array_push($result, $secondRecord_hi);
            array_push($result, $thirdRecord_hi);
            array_push($result, $forthRecord_hi);
            array_push($result, $fifthRecord_hi);
            array_push($result, $sixRecord_hi);
            array_push($result, $seventhRecord_hi);
        } else if ($lang == "fr") {
            $firstRecord_fr = [
                "comment" => "J’ai économisé 50% sur mon IRM avec la carte Mulk HnH. La remise était instantanée et cela a vraiment fait la différence !",
                "rating" => 5,
                "user" => [
                    "fullname" => "Abdul",
                    "gender"=> 1,
                    "profile_image" => "uploads/1430453.png"
                ]
            ];

            $secondRecord_fr = [
                "comment" => "La consultation en télémédecine était très pratique. J'ai parlé à un spécialiste depuis chez moi et toutes mes questions ont reçu une réponse.",
                "rating" => 4,
                "user" => [
                    "fullname" => "Visija",
                    "gender"=> 0,
                    "profile_image" => "uploads/avatar.png"
                ]
            ];

            $thirdRecord_fr = [
                "comment" => "J'ai utilisé la carte HnH pour mon scanner. Les économies étaient immédiates et le processus très simple. Excellent service.",
                "rating" => 4.5,
                "user" => [
                    "fullname" => "Fayaz",
                    "gender"=> 1,
                    "profile_image" => "uploads/1430453.png"
                ]
            ];

            $forthRecord_fr = [
                "comment" => "Le service de télémédecine est un énorme avantage. C’est rassurant de savoir que je peux obtenir des conseils médicaux professionnels depuis chez moi.",
                "rating" => 5,
                "user" => [
                    "fullname" => "Prashum",
                    "gender"=> 1,
                    "profile_image" => "uploads/1430453.png"
                ]
            ];

            $fifthRecord_fr = [
                "comment" => "La fonction télémédecine est parfaite pour les suivis. J’ai parlé à un médecin, confortablement depuis mon domicile, et j’ai renouvelé mon ordonnance. Je recommande vivement.",
                "rating" => 4,
                "user" => [
                    "fullname" => "Mary",
                    "gender"=> 0,
                    "profile_image" => "uploads/avatar.png"
                ]
            ];

            $sixRecord_fr = [
                "comment" => "J’ai reçu 50% de réduction sur mon échographie avec la carte HnH. C’est un service fantastique qui aide vraiment à couvrir les frais médicaux.",
                "rating" => 4.5,
                "user" => [
                    "fullname" => "Adam",
                    "gender"=> 1,
                    "profile_image" => "uploads/1430453.png"
                ]
            ];

            $seventhRecord_fr = [
                "comment" => "Ma consultation en télémédecine était excellente. Le médecin m’a écouté attentivement et m’a donné un plan clair. Beaucoup plus facile qu’une visite en personne.",
                "rating" => 4,
                "user" => [
                    "fullname" => "Sakina",
                    "gender"=> 0,
                    "profile_image" => "uploads/avatar.png"
                ]
            ];


            array_push($result, $firstRecord_fr);
            array_push($result, $secondRecord_fr);
            array_push($result, $thirdRecord_fr);
            array_push($result, $forthRecord_fr);
            array_push($result, $fifthRecord_fr);
            array_push($result, $sixRecord_fr);
            array_push($result, $seventhRecord_fr);
        } else if ($lang == "ur") {
            $firstRecord_ur = [
                "comment" => "ملک HnH کارڈ سے میری MRI پر 50٪ کی بچت ہوئی۔ رعایت فوری تھی اور بہت فائدہ مند ثابت ہوئی!",
                "rating" => 5,
                "user" => [
                    "fullname" => "عبدال",
                    "gender"=> 1,
                    "profile_image" => "uploads/1430453.png"
                ]
            ];

            $secondRecord_ur = [
                "comment" => "ٹیلی ہیلتھ مشاورت بہت سہل تھی۔ میں نے گھر سے ماہر سے بات کی اور اپنے تمام سوالات کے جواب پائے۔",
                "rating" => 4,
                "user" => [
                    "fullname" => "ویسجا",
                    "gender"=> 0,
                    "profile_image" => "uploads/avatar.png"
                ]
            ];

            $thirdRecord_ur = [
                "comment" => "سی ٹی اسکین کے لئے HnH کارڈ استعمال کیا۔ بچت فوری ملی اور عمل بہت آسان تھا۔ بہترین سروس۔",
                "rating" => 4.5,
                "user" => [
                    "fullname" => "فیاض",
                    "gender"=> 1,
                    "profile_image" => "uploads/1430453.png"
                ]
            ];

            $forthRecord_ur = [
                "comment" => "ٹیلی ہیلتھ سروس ایک بڑا فائدہ ہے۔ اطمینان بخش ہے کہ میں گھر سے ہی پروفیشنل طبی مشورہ لے سکتا ہوں۔",
                "rating" => 5,
                "user" => [
                    "fullname" => "پراشوم",
                    "gender"=> 1,
                    "profile_image" => "uploads/1430453.png"
                ]
            ];

            $fifthRecord_ur = [
                "comment" => "فالو اپ کے لئے ٹیلی ہیلتھ فیچر بہترین ہے۔ میں نے گھر بیٹھے ڈاکٹر سے بات کی اور نسخہ دوبارہ حاصل کیا۔ بہت سفارش کرتا ہوں۔",
                "rating" => 4,
                "user" => [
                    "fullname" => "میری",
                    "gender"=> 0,
                    "profile_image" => "uploads/avatar.png"
                ]
            ];

            $sixRecord_ur = [
                "comment" => "کارڈ سے الٹراساؤنڈ پر پورے 50٪ رعایت ملی۔ یہ شاندار سروس ہے جو طبی اخراجات میں واقعی مدد کرتی ہے۔",
                "rating" => 4.5,
                "user" => [
                    "fullname" => "آدم",
                    "gender"=> 1,
                    "profile_image" => "uploads/1430453.png"
                ]
            ];

            $seventhRecord_ur = [
                "comment" => "میری ٹیلی ہیلتھ مشاورت شاندار تھی۔ ڈاکٹر نے غور سے سنا اور واضح منصوبہ دیا۔ ذاتی ملاقات سے کہیں آسان۔",
                "rating" => 4,
                "user" => [
                    "fullname" => "سکینہ",
                    "gender"=> 0,
                    "profile_image" => "uploads/avatar.png"
                ]
            ];

            array_push($result, $firstRecord_ur);
            array_push($result, $secondRecord_ur);
            array_push($result, $thirdRecord_ur);
            array_push($result, $forthRecord_ur);
            array_push($result, $fifthRecord_ur);
            array_push($result, $sixRecord_ur);
            array_push($result, $seventhRecord_ur);
        } else if ($lang == "ar") {
            $firstRecord_ar = [
                "comment" => "وفرت 50٪ على فحص الرنين المغناطيسي ببطاقة ملك HnH. الخصم كان فوري وأحدث فرقاً كبيراً!",
                "rating" => 5,
                "user" => [
                    "fullname" => "عبدول",
                    "gender"=> 1,
                    "profile_image" => "uploads/1430453.png"
                ]
            ];



            $secondRecord_ar = [
                "comment" => "كان الاستشارة الصحية عن بُعد مريح جداً. تحدثت مع أخصائي من المنزل وتمت الإجابة على كل أسئلتي.",
                "rating" => 4,
                "user" => [
                    "fullname" => "فيزيجا",
                    "gender"=> 0,
                    "profile_image" => "uploads/avatar.png"
                ]
            ];



            $thirdRecord_ar = [
                "comment" => "استخدمت بطاقة HnH لفحص الأشعة المقطعية. التوفير كان فورياً وكانت العملية سهلة جداً. خدمة رائعة.",
                "rating" => 4.5,
                "user" => [
                    "fullname" => "فياض",
                    "gender"=> 1,
                    "profile_image" => "uploads/1430453.png"
                ]
            ];



            $forthRecord_ar = [
                "comment" => "الخدمة الصحية عن بُعد ميزة كبيرة. يبعث على الطمأنينة أنني أستطيع الحصول على نصيحة طبية احترافية من المنزل.",
                "rating" => 5,
                "user" => [
                    "fullname" => "براشوم",
                    "gender"=> 1,
                    "profile_image" => "uploads/1430453.png"
                ]
            ];



            $fifthRecord_ar = [
                "comment" => "ميزة الصحة عن بعد ممتازة للمتابعة. تحدثت مع طبيب وأنا في منزلي وتم تجديد وصفيتي الطبية. أوصي بها بشدة.",
                "rating" => 4,
                "user" => [
                    "fullname" => "ماري",
                    "gender"=> 0,
                    "profile_image" => "uploads/avatar.png"
                ]
            ];



            $sixRecord_ar = [
                "comment" => "حصلت على خصم 50٪ كامل في فحص الموجات فوق الصوتية ببطاقة HnH. إنها خدمة رائعة وتساعد حقاً في تكاليف الطب.",
                "rating" => 4.5,
                "user" => [
                    "fullname" => "آدم",
                    "gender"=> 1,
                    "profile_image" => "uploads/1430453.png"
                ]
            ];



            $seventhRecord_ar = [
                "comment" => "كانت استشارتي الصحية عن بعد ممتازة. استمع الطبيب بعناية وأعطاني خطة واضحة. أسهل بكثير من زيارة شخصية.",
                "rating" => 4,
                "user" => [
                    "fullname" => "سكينة",
                    "gender"=> 0,
                    "profile_image" => "uploads/avatar.png"
                ]
            ];



            array_push($result, $firstRecord_ar);
            array_push($result, $secondRecord_ar);
            array_push($result, $thirdRecord_ar);
            array_push($result, $forthRecord_ar);
            array_push($result, $fifthRecord_ar);
            array_push($result, $sixRecord_ar);
            array_push($result, $seventhRecord_ar);
        } else {

            $firstRecord = [
                "comment" => "Saved 50% on my MRI with the Mulk HnH Card. The discount was instant and made a huge difference!",
                "rating" => 5,
                "user" => [
                    "fullname" => "Abdul",
                    "gender"=> 1,
                    "profile_image" => "uploads/1430453.png"
                ]
            ];

            $secondRecord = [
                "comment" => "The telehealth consultation was so convenient. I spoke with a specialist from home and got all my questions answered.",
                "rating" => 4,
                "user" => [
                    "fullname" => "Visija",
                    "gender"=> 0,
                    "profile_image" => "uploads/avatar.png"
                ]
            ];

            $thirdRecord = [
                "comment" => "Used the HnH Card for a CT scan. The savings were immediate, and the process was so simple. Great service",
                "rating" => 4.5,
                "user" => [
                    "fullname" => "Fayaz",
                    "gender"=> 1,
                    "profile_image" => "uploads/1430453.png"
                ]
            ];
            $forthRecord = [
                "comment" => "The telehealth service is a huge plus. Its comforting to know I can get professional medical advice from home.",
                "rating" => 5,
                "user" => [
                    "fullname" => "Prashum",
                    "gender"=> 1,
                    "profile_image" => "uploads/1430453.png"
                ]
            ];

            $fifthRecord = [
                "comment" => "The telehealth feature is perfect for follow-ups. I spoke to a doctor from the comfort of my home. Got my prescription refilled.Highly recommend it.",
                "rating" => 4,
                "user" => [
                    "fullname" => "Mary",
                    "gender"=> 0,
                    "profile_image" => "uploads/avatar.png"
                ]
            ];
            $sixRecord = [
                "comment" => "Got a full 50% off my ultrasound with the HnH Card. Its a fantastic service that really helps with medical costs.",
                "rating" => 4.5,
                "user" => [
                    "fullname" => "Adam",
                    "gender"=> 1,
                    "profile_image" => "uploads/1430453.png"
                ]
            ];

            $seventhRecord = [
                "comment" => "My telehealth consultation was excellent. The doctor listened carefully and gave me a clear plan. So much easier than an in-person visit",
                "rating" => 4,
                "user" => [
                    "fullname" => "Sakina",
                    "gender"=> 0,
                    "profile_image" => "uploads/avatar.png"
                ]
            ];

            array_push($result, $firstRecord);
            array_push($result, $secondRecord);
            array_push($result, $thirdRecord);
            array_push($result, $forthRecord);
            array_push($result, $fifthRecord);
            array_push($result, $sixRecord);
            array_push($result, $seventhRecord);
        }

        return GlobalFunction::sendDataResponse(true, 'data fetched successfully', $result);
    }

    function submitDoctorReviews(Request $request)
    {
        $rules = [
            'appointment_id' => 'required',
            'rating' => 'required',
            'comment' => 'required',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }
        $appointment = Appointments::find($request->appointment_id);
        if ($appointment == null) {
            return GlobalFunction::sendSimpleResponse(false, 'Appointment does not exists!');
        }

        $review = new DoctorReviews();
        $review->user_id = $appointment->user_id;
        $review->doctor_id = $appointment->doctor_id;
        $review->appointment_id = $request->appointment_id;
        $review->rating = $request->rating;
        $review->comment = $request->comment;
        $review->created_at = now();
        $review->save();

        return response()->json(['status' => true, 'message' => 'Review submitted successfully', 'data' => $review]);

    }

    function addAppointmentEmrs(Request $request)
    {
        try {
            $rules = [
                'appointment_id' => 'required',
                'documents' => 'required|array',
                'documents.*' => 'file|mimes:jpg,jpeg,png,pdf|max:5120',
            ];

            $messages = [
                'documents.*.max' => 'Each document must not be larger than 5 MB.',
                'documents.*.mimes' => 'Only JPG, JPEG, PNG, and PDF files are allowed.',
                'documents.required' => 'Please upload at least one document.',
            ];

            $validator = Validator::make($request->all(), $rules, $messages);

            if ($validator->fails()) {
                $messages = $validator->errors()->all();
                $msg = $messages[0];
                return response()->json([
                    'status' => false,
                    'message' => $msg,
                ]);
            }

            if ($request->hasFile('documents')) {
                foreach ($request->file('documents') as $document) {
                    $docs = new AppointmentEmrs();
                    $docs->appointment_id = $request->appointment_id;
                    $docs->image = GlobalFunction::saveFileAndGivePath($document);
                    $docs->save();
                }
            }
            return response()->json(['status' => true, 'message' => 'Documents Saved successfully']);

        } catch (\Throwable $th) {

            return ['status' => false, 'message' => $th->getMessage()];
        }
    }

    function deleteAppointmentEmrs(Request $request)
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

            $appointment_docs = AppointmentEmrs::find($request->document_id);

            if ($appointment_docs) {
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

    function fetchDoctorProfile(Request $request)
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

        $hostAndConversionRate = Helpers::conversionRate();
        $conversionRate = (float) $hostAndConversionRate['conversionRate'];
        $doctor = Doctors::select('doctors.*',DB::raw("ROUND(consultation_fee * {$conversionRate}) as consultation_fee"))
                    ->with([
                        'services',
                        'experience',
                        'expertise',
                        'serviceLocations',
                        'awards',
                        'slots',
                        'holidays',
                        'bankAccount',
                    ])->where('id', $request->doctor_id)->first();
        $lang = $request->header('lang', 'en');
        if ($lang == 'ar') {
            $doctor->languages_spoken = $doctor->ar_languages_spoken;
            $doctor->designation = $doctor->ar_designation;
        }
        if ($lang == 'ur') {
            $doctor->languages_spoken = $doctor->ur_languages_spoken;
            $doctor->designation = $doctor->ur_designation;
        }
        if ($lang == 'fr') {
            $doctor->languages_spoken = $doctor->fr_languages_spoken;
            $doctor->designation = $doctor->fr_designation;
        }
        if ($lang == 'hi') {
            $doctor->languages_spoken = $doctor->hi_languages_spoken;
            $doctor->designation = $doctor->hi_designation;
        }

        $expertiseColumn = match ($lang) {
            'ar' => 'ar_title',
            'ur' => 'ur_title',
            'fr' => 'fr_title',
            'hi' => 'hi_title',
            default => 'title',
        };

        $doctorExpertise = DoctorExpertise::where('doctor_id', $doctor->id)
            ->get(['id', 'doctor_id', 'title', 'ar_title', 'ur_title', 'fr_title', 'hi_title'])
            ->map(function ($item) use ($expertiseColumn) {
                $localized = trim((string) ($item->{$expertiseColumn} ?? ''));
                $fallback = trim((string) ($item->title ?? ''));

                return [
                    'id' => $item->id,
                    'doctor_id' => $item->doctor_id,
                    'title' => $localized !== '' ? $localized : $fallback,
                ];
            })
            ->values();

        $doctor->setRelation('expertise', $doctorExpertise);

        // $doctor = GlobalFunction::generateDoctorFullData($doctor->id);

        $doctor_with_same_category = Doctors::select('doctors.*',DB::raw("ROUND(consultation_fee * {$conversionRate}) as consultation_fee"))
                                        ->where('category_id', $doctor->category_id)->where('id', '!=', $doctor->id)->get();

        $banners = [];
        if ($request->has('from_home') && $request->from_home == 1) {
            $banners = Banners::where('section', 'Top specialities')
                ->where('section_id', $doctor->category_id)
                ->where('page', 'Doctor details page')
                ->where('is_deleted', 0)
                ->get();
        }

        if ($request->has('speciality_id')) {
            $banners = Banners::where('section', 'Top specialities')
                ->where('section_id', $request->speciality_id)
                ->where('page', 'Doctor details page')
                ->where('is_deleted', 0)
                ->get();
        }
        if ($request->has('problem_id')) {
            $banners = Banners::where('section', 'Common health Problems')
                ->where('section_id', $request->problem_id)
                ->where('page', 'Doctor details page')
                ->where('is_deleted', 0)
                ->get();
        }
        if ($request->has('disease_id')) {
            $banners = Banners::where('section', 'Specialitywise disease')
                ->where('section_id', $request->disease_id)
                ->where('page', 'Doctor details page')
                ->where('is_deleted', 0)
                ->get();
        }

        return response()->json(['status' => true, 'message' => 'data fetched successfully', 'data' => $doctor, 'doctor_with_same_category' => $doctor_with_same_category, 'banners' => $banners]);
        // return GlobalFunction::sendDataResponse(true, 'data fetched successfully', $doctor, $doctor_with_same_category);
    }

    function searchDoctor(Request $request)
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
        $hostAndConversionRate = Helpers::conversionRate();
        $conversionRate = (float) $hostAndConversionRate['conversionRate'];
        $query = Doctors::select('doctors.*',DB::raw("ROUND(consultation_fee * {$conversionRate}) as consultation_fee"));

        if ($request->has('gender')) {
            $query->where('gender', $request->gender);
        }
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }
        if ($request->has('sort_type')) {
            if ($request->sort_type == Constants::sortTypePriceLow) {
                $query->orderBy('consultation_fee', 'ASC');
            }
            if ($request->sort_type == Constants::sortTypePriceHigh) {
                $query->orderBy('consultation_fee', 'DESC');
            }
            if ($request->sort_type == Constants::sortTypeRating) {
                $query->orderBy('rating', 'DESC');
            }
        }
        $doctors = $query
            ->where('name', 'LIKE', "%{$request->keyword}%")
            ->where('status', Constants::statusDoctorApproved)
            ->where('on_vacation', Constants::doctorNotOnVacation)
            ->offset($request->start)
            ->limit($request->count)
            ->get();

        return GlobalFunction::sendDataResponse(true, 'Data fetched successfully', $doctors);
    }

    function manageDrBankAccount(Request $request)
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
        $doctor = Doctors::where('id', $request->doctor_id)->first();
        if ($doctor == null) {
            return GlobalFunction::sendSimpleResponse(false, 'Doctor does not exists!');
        }
        $bankAcc = $doctor->bankAccount;
        if ($bankAcc == null) {
            $rules = [
                'bank_name' => 'required',
                'account_number' => 'required',
                'holder' => 'required',
                'swift_code' => 'required',
                'cheque_photo' => 'required',
            ];
            $validator = Validator::make($request->all(), $rules);
            if ($validator->fails()) {
                $messages = $validator->errors()->all();
                $msg = $messages[0];
                return response()->json(['status' => false, 'message' => $msg]);
            }
            $bankAcc = new DoctorBankAccount();
            $bankAcc->bank_name = GlobalFunction::cleanString($request->bank_name);
            $bankAcc->account_number = GlobalFunction::cleanString($request->account_number);
            $bankAcc->holder = GlobalFunction::cleanString($request->holder);
            $bankAcc->swift_code = GlobalFunction::cleanString($request->swift_code);
            $bankAcc->doctor_id = $request->doctor_id;
            $bankAcc->cheque_photo = GlobalFunction::saveFileAndGivePath($request->cheque_photo);
            $bankAcc->save();
        } else {
            if ($request->has('bank_name')) {
                $bankAcc->bank_name =
                    GlobalFunction::cleanString($request->bank_name);
            }
            if ($request->has('account_number')) {
                $bankAcc->account_number =
                    GlobalFunction::cleanString($request->account_number);
            }
            if ($request->has('holder')) {
                $bankAcc->holder =
                    GlobalFunction::cleanString($request->holder);
            }
            if ($request->has('swift_code')) {
                $bankAcc->swift_code =
                    GlobalFunction::cleanString($request->swift_code);
            }
            if ($request->has('cheque_photo')) {
                $bankAcc->cheque_photo = GlobalFunction::saveFileAndGivePath($request->cheque_photo);
            }
            $bankAcc->save();
        }

        $doctor = Globalfunction::generateDoctorFullData($request->doctor_id);

        return GlobalFunction::sendDataResponse(true, 'bank details updated successfully', $doctor);
    }

    function fetchFaqCats(Request $request)
    {
        $faqCats = FaqCats::with('faqs')->get();

        return GlobalFunction::sendDataResponse(true, 'Data fetch successfully', $faqCats);
    }

    function deleteHoliday(Request $request)
    {
        $rules = [
            'holiday_id' => 'required',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }
        $item = DoctorHolidays::find($request->holiday_id);
        if ($item == null) {
            return GlobalFunction::sendSimpleResponse(false, 'Holiday does not Exists');
        }
        $item->delete();
        return GlobalFunction::sendSimpleResponse(false, 'Holiday deleted successfully!');
    }

    function addHoliday(Request $request)
    {
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
        $holiday = DoctorHolidays::where('date', $request->date)
            ->where('doctor_id', $request->doctor_id)
            ->first();
        if ($holiday == null) {
            $holiday = new DoctorHolidays();
            $holiday->date = $request->date;
            $holiday->doctor_id = $request->doctor_id;
            $holiday->save();
            return GlobalFunction::sendSimpleResponse(true, 'Holiday added successfully');
        }
        return GlobalFunction::sendSimpleResponse(false, 'Holiday exists already!');
    }

    function deleteAppointmentSlot(Request $request)
    {
        $rules = [
            'slot_id' => 'required',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }
        $slot = DoctorAppointmentSlots::find($request->slot_id);
        if ($slot == null) {
            return GlobalFunction::sendSimpleResponse(false, 'Slot does not Exists');
        }
        $slot->delete();
        return GlobalFunction::sendSimpleResponse(true, 'This Slot deleted successfully!');
    }

    function changeSmoStatus(Request $request)
    {
        $rules = [
            'doctor_id' => 'required',
            'is_smo' => 'required',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }
        $doctor = Doctors::find($request->doctor_id);
        if ($doctor == null) {
            return GlobalFunction::sendSimpleResponse(false, 'Doctor does not Exists');
        }
        $doctor->is_smo = $request->is_smo;
        $doctor->save();
        return GlobalFunction::sendSimpleResponse(true, 'SMO status updated successfully!');
    }

    function changeMulkmedStatus(Request $request)
    {
        $rules = [
            'doctor_id' => 'required',
            'is_mulkmed' => 'required',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }
        $doctor = Doctors::find($request->doctor_id);
        if ($doctor == null) {
            return GlobalFunction::sendSimpleResponse(false, 'Doctor does not Exists');
        }
        $doctor->is_mulkmed = $request->is_mulkmed;
        $doctor->save();
        return GlobalFunction::sendSimpleResponse(true, 'SMO status updated successfully!');
    }


    function changeTravelVisibleStatus(Request $request)
    {
        $rules = [
            'doctor_id' => 'required',
            'travel_visible' => 'required',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }
        $doctor = Doctors::find($request->doctor_id);
        if ($doctor == null) {
            return GlobalFunction::sendSimpleResponse(false, 'Doctor does not Exists');
        }
        $doctor->travel_visible = $request->travel_visible;
        $doctor->save();
        return GlobalFunction::sendSimpleResponse(true, 'Travel visible status updated successfully!');
    }

    function addAppointmentSlots(Request $request)
    {
        $rules = [
            'time' => 'required',
            'weekday' => 'required',
            'doctor_id' => 'required',
            // 'booking_limit' => 'required',
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

        $slot = DoctorAppointmentSlots::where('time', $request->time)
            ->where('weekday', $request->weekday)
            ->where('doctor_id', $doctor->id)
            ->first();

        if ($slot == null) {
            $slot = new DoctorAppointmentSlots();
            $slot->time = $request->time;
            $slot->weekday = $request->weekday;
            $slot->doctor_id = $request->doctor_id;
            $slot->booking_limit = $request->booking_limit ?? 1;
            $slot->save();

            // $slots = DoctorAppointmentSlots::where('doctor_id', $request->doctor_id)->get();
               $slots = DoctorAppointmentSlots::where('doctor_id', $request->doctor_id)
                ->orderBy('weekday')
                ->orderByRaw("CAST(LPAD(REPLACE(time, ':', ''), 4, '0') AS UNSIGNED) ASC")
                ->get();
            
            return GlobalFunction::sendDataResponse(true, 'Slot added successfully', $slots);
        } else {
            return GlobalFunction::sendSimpleResponse(false, 'This Slot is available already!');
        }
    }

    function addEditServiceLocations(Request $request)
    {
        $rules = [
            'type' => 'required',
            'doctor_id' => 'required',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }

        $hostAndConversionRate = Helpers::conversionRate();
        $conversionRate = (float) $hostAndConversionRate['conversionRate'];

        // 1= Add Service
        if ($request->type == 1) {
            $rules = [
                'doctor_id' => 'required',
                'hospital_title' => 'required',
                'hospital_address' => 'required',
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

            $item = new DoctorServiceLocations();
            $item->hospital_title = GlobalFunction::cleanString($request->hospital_title);
            $item->hospital_address = $request->hospital_address;
            $item->doctor_id = $doctor->id;

            if ($request->has('hospital_long')) {
                $item->hospital_long = $request->hospital_long;
            }
            if ($request->has('hospital_lat')) {
                $item->hospital_lat = $request->hospital_lat;
            }
            $item->save();

            $doctor = GlobalFunction::generateDoctorFullData($request->doctor_id);

            return response()->json(['status' => true, 'message' => 'Service location added successfully !', 'data' => $doctor]);
        }
        if ($request->type == 2) {
            // 2 = edit
            $rules = [
                'doctor_id' => 'required',
                'serviceLocation_id' => 'required',
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
            $item = DoctorServiceLocations::where('id', $request->serviceLocation_id)->first();
            if ($item == null) {
                return GlobalFunction::sendSimpleResponse(false, 'Service location does not exists!');
            }
            if ($request->has('hospital_title')) {
                $item->hospital_title = GlobalFunction::cleanString($request->hospital_title);
            }
            if ($request->has('hospital_address')) {
                $item->hospital_address = $request->hospital_address;
            }
            if ($request->has('hospital_long')) {
                $item->hospital_long = $request->hospital_long;
            }
            if ($request->has('hospital_lat')) {
                $item->hospital_lat = $request->hospital_lat;
            }

            $item->save();

            $doctor = GlobalFunction::generateDoctorFullData($request->doctor_id);

            return response()->json(['status' => true, 'message' => 'Service location edited successfully !', 'data' => $doctor]);
        }

        if ($request->type == 3) {
            $rules = [
                'serviceLocation_id' => 'required',
            ];

            $validator = Validator::make($request->all(), $rules);
            if ($validator->fails()) {
                $messages = $validator->errors()->all();
                $msg = $messages[0];
                return response()->json(['status' => false, 'message' => $msg]);
            }
            $item = DoctorServiceLocations::where('id', $request->serviceLocation_id)->first();
            if ($item == null) {
                return GlobalFunction::sendSimpleResponse(false, 'service Location does not exists!');
            }
            $item->delete();
            $doctor = GlobalFunction::generateDoctorFullData($request->doctor_id);
            return response()->json(['status' => true, 'message' => 'service Location deleted successfully !', 'data' => $doctor]);
        }
    }

    function addEditExperience(Request $request)
    {
        $rules = [
            'type' => 'required',
            'doctor_id' => 'required',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }

        // 1= Add Service
        if ($request->type == 1) {
            $rules = [
                'doctor_id' => 'required',
                'title' => 'required'
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

            $item = new DoctorExperience();
            $item->title =
                GlobalFunction::cleanString($request->title);
            $item->doctor_id = $doctor->id;
            $item->save();

            $doctor = GlobalFunction::generateDoctorFullData($request->doctor_id);

            return response()->json(['status' => true, 'message' => 'Experience added successfully !', 'data' => $doctor]);
        }
        if ($request->type == 2) {
            // 2 = edit
            $rules = [
                'doctor_id' => 'required',
                'experience_id' => 'required',
                'title' => 'required'
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
            $item = DoctorExperience::where('id', $request->experience_id)->first();
            if ($item == null) {
                return GlobalFunction::sendSimpleResponse(false, 'Experience does not exists!');
            }

            $item->title =
                GlobalFunction::cleanString($request->title);
            $item->save();

            $doctor = GlobalFunction::generateDoctorFullData($request->doctor_id);

            return response()->json(['status' => true, 'message' => 'Experience edited successfully !', 'data' => $doctor]);
        }
        if ($request->type == 3) {
            $rules = [
                'experience_id' => 'required',
            ];

            $validator = Validator::make($request->all(), $rules);
            if ($validator->fails()) {
                $messages = $validator->errors()->all();
                $msg = $messages[0];
                return response()->json(['status' => false, 'message' => $msg]);
            }
            $item = DoctorExperience::where('id', $request->experience_id)->first();
            if ($item == null) {
                return GlobalFunction::sendSimpleResponse(false, 'Experience does not exists!');
            }
            $item->delete();

            $doctor = GlobalFunction::generateDoctorFullData($request->doctor_id);
            return response()->json(['status' => true, 'message' => 'Experience deleted successfully !', 'data' => $doctor]);
        }
    }

    function addEditAwards(Request $request)
    {
        $rules = [
            'type' => 'required',
            'doctor_id' => 'required',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }

        // 1= Add Service
        if ($request->type == 1) {
            $rules = [
                'doctor_id' => 'required',
                'title' => 'required'
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

            $item = new DoctorAwards();
            $item->title = GlobalFunction::cleanString($request->title);
            $item->doctor_id = $doctor->id;
            $item->save();

            $doctor = GlobalFunction::generateDoctorFullData($request->doctor_id);

            return response()->json(['status' => true, 'message' => 'Award added successfully !', 'data' => $doctor]);
        }
        if ($request->type == 2) {
            // 2 = edit
            $rules = [
                'doctor_id' => 'required',
                'award_id' => 'required',
                'title' => 'required'
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
            $item = DoctorAwards::where('id', $request->award_id)->first();
            if ($item == null) {
                return GlobalFunction::sendSimpleResponse(false, 'Award does not exists!');
            }

            $item->title =
                GlobalFunction::cleanString($request->title);
            $item->save();

            $doctor = GlobalFunction::generateDoctorFullData($request->doctor_id);

            return response()->json(['status' => true, 'message' => 'Award edited successfully !', 'data' => $doctor]);
        }
        if ($request->type == 3) {
            $rules = [
                'award_id' => 'required',
            ];

            $validator = Validator::make($request->all(), $rules);
            if ($validator->fails()) {
                $messages = $validator->errors()->all();
                $msg = $messages[0];
                return response()->json(['status' => false, 'message' => $msg]);
            }
            $item = DoctorAwards::where('id', $request->award_id)->first();
            if ($item == null) {
                return GlobalFunction::sendSimpleResponse(false, 'Award does not exists!');
            }
            $item->delete();

            $doctor = GlobalFunction::generateDoctorFullData($request->doctor_id);

            return response()->json(['status' => true, 'message' => 'Award deleted successfully !', 'data' => $doctor]);
        }
    }

    function addEditExpertise(Request $request)
    {
        $rules = [
            'type' => 'required',
            'doctor_id' => 'required',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }

        // 1= Add Service
        if ($request->type == 1) {
            $rules = [
                'doctor_id' => 'required',
                'title' => 'required'
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

            $item = new DoctorExpertise();
            $item->title =
                GlobalFunction::cleanString($request->title);
            $item->doctor_id = $doctor->id;
            $ta = new GoogleTranslate('ar');
            $item->ar_title = $ta->translate($item->title);
            $tf = new GoogleTranslate('fr');
            $item->fr_title = $tf->translate($item->title);
            $th = new GoogleTranslate('hi');
            $item->hi_title = $th->translate($item->title);
            $tu = new GoogleTranslate('ur');
            $item->ur_title = $tu->translate($item->title);
            $item->save();
            $doctor = GlobalFunction::generateDoctorFullData($request->doctor_id);

            return response()->json(['status' => true, 'message' => 'Expertise added successfully !', 'data' => $doctor]);
        }
        if ($request->type == 2) {
            // 2 = edit
            $rules = [
                'doctor_id' => 'required',
                'expertise_id' => 'required',
                'title' => 'required'
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
            $item = DoctorExpertise::where('id', $request->expertise_id)->first();
            if ($item == null) {
                return GlobalFunction::sendSimpleResponse(false, 'Expertise does not exists!');
            }

            $item->title =
                GlobalFunction::cleanString($request->title);
            $ta = new GoogleTranslate('ar');
            $item->ar_title = $ta->translate($item->title);
            $tf = new GoogleTranslate('fr');
            $item->fr_title = $tf->translate($item->title);
            $th = new GoogleTranslate('hi');
            $item->hi_title = $th->translate($item->title);
            $tu = new GoogleTranslate('ur');
            $item->ur_title = $tu->translate($item->title);
            $item->save();

            $doctor = GlobalFunction::generateDoctorFullData($request->doctor_id);

            return response()->json(['status' => true, 'message' => 'Expertise edited successfully !', 'data' => $doctor]);
        }
        if ($request->type == 3) {
            $rules = [
                'expertise_id' => 'required',
            ];

            $validator = Validator::make($request->all(), $rules);
            if ($validator->fails()) {
                $messages = $validator->errors()->all();
                $msg = $messages[0];
                return response()->json(['status' => false, 'message' => $msg]);
            }
            $item = DoctorExpertise::where('id', $request->expertise_id)->first();
            if ($item == null) {
                return GlobalFunction::sendSimpleResponse(false, 'Expertise does not exists!');
            }
            $item->delete();

            $doctor = GlobalFunction::generateDoctorFullData($request->doctor_id);

            return response()->json(['status' => true, 'message' => 'Expertise deleted successfully !', 'data' => $doctor]);
        }
    }

    function addEditService(Request $request)
    {
        $rules = [
            'type' => 'required',
            'doctor_id' => 'required',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }

        // 1= Add Service
        if ($request->type == 1) {
            $rules = [
                'doctor_id' => 'required',
                'title' => 'required'
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

            $service = new DoctorServices();
            $service->title = GlobalFunction::cleanString($request->title);
            $service->doctor_id = $doctor->id;
            $service->save();

            $doctor = GlobalFunction::generateDoctorFullData($request->doctor_id);

            return response()->json(['status' => true, 'message' => 'Service added successfully !', 'data' => $doctor]);
        }
        if ($request->type == 2) {
            // 2 = edit
            $rules = [
                'doctor_id' => 'required',
                'service_id' => 'required',
                'title' => 'required'
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
            $service = DoctorServices::where('id', $request->service_id)->first();
            if ($service == null) {
                return GlobalFunction::sendSimpleResponse(false, 'Service does not exists!');
            }

            $service->title = GlobalFunction::cleanString($request->title);
            $service->save();

            $doctor = GlobalFunction::generateDoctorFullData($request->doctor_id);

            return response()->json(['status' => true, 'message' => 'Service edited successfully !', 'data' => $doctor]);
        }
        if ($request->type == 3) {
            $rules = [
                'service_id' => 'required',
            ];

            $validator = Validator::make($request->all(), $rules);
            if ($validator->fails()) {
                $messages = $validator->errors()->all();
                $msg = $messages[0];
                return response()->json(['status' => false, 'message' => $msg]);
            }
            $service = DoctorServices::where('id', $request->service_id)->first();
            if ($service == null) {
                return GlobalFunction::sendSimpleResponse(false, 'Service does not exists!');
            }
            $service->delete();

            $doctor = GlobalFunction::generateDoctorFullData($request->doctor_id);

            return response()->json(['status' => true, 'message' => 'Service deleted successfully !', 'data' => $doctor]);
        }
    }

    function fetchMyDoctorProfile(Request $request)
    {

        $rules = [
            'doctor_id' => 'required'
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

        $doctor = GlobalFunction::generateDoctorFullData($doctor->id);

        return response()->json(['status' => true, 'message' => 'Data fetched successfully !', 'data' => $doctor]);
    }

    function fetchDoctorNotifications(Request $request)
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

        $doctorNotifications = DoctorNotifications::offset($request->start)
            ->limit($request->count)
            ->orderBy('id', 'DESC')
            ->get();

        return response()->json(['status' => true, 'message' => 'Data fetched successfully !', 'data' => $doctorNotifications]);
    }

    function fetchDoctorCategories(Request $request)
    {
        $cats = DoctorCategories::where('is_deleted', 0)->get();

        return GlobalFunction::sendDataResponse(true, 'data fetched successfully', $cats);
    }

    function doctorRegistration(Request $request)
    {
        $rules = [
            'identity' => 'required',
            'device_token' => 'required',
            'device_type' => 'required',
            // 'password' => 'required',
            'is_login' => [Rule::in(0, 1)] //1=login 0=register
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }

        $doctor = Doctors::where('identity', $request->identity)->first();
        if ($request->is_login == 1 && $doctor == null) {
            return GlobalFunction::sendSimpleResponse(false, 'user not found');
        }

        if ($doctor == null) {
            $doctor = new Doctors();
            $doctor->identity = $request->identity;
            $doctor->name = $request->name;
            $doctor->device_token = $request->device_token;
            $doctor->device_type = $request->device_type;
            $doctor->doctor_number = GlobalFunction::generateDoctorNumber();
            if ($request->has('password')) {
                $doctor->password = $request->password;
            }
            $doctor->save();

            // $doctor_number = GlobalFunction::generateDoctorNumber();
            // $data = [
            //             'name'          => $request->name,
            //             'identity'      => $request->identity,
            //             'device_token'  => $request->device_token,
            //             'device_type'   => $request->device_type,
            //             'doctor_number' => $doctor_number,
            //             'password'      => $request->has('password') ? $request->password : null,
            //         ];

            // if (\Schema::connection('mysql') && \Schema::connection('mysql')->hasTable("doctors")){
            //     DB::connection('mysql')->table('doctors')->insert($data);
            // }
            // if (\Schema::connection('mulkmed_india') && \Schema::connection('mulkmed_india')->hasTable("doctors")){
            //     DB::connection('mulkmed_india')->table('doctors')->insert($data);
            // }

            $hostAndConversionRate = Helpers::conversionRate();
            $conversionRate = (float) $hostAndConversionRate['conversionRate'];
            $doctor = Doctors::select('doctors.*',DB::raw("ROUND(consultation_fee * {$conversionRate}) as consultation_fee"))->where('id',$doctor->id)->first();

            return GlobalFunction::sendDataResponse(true, 'Doctor Data fetched successfully', $doctor);
        } else {
            $doctor->device_token = $request->device_token;
            $doctor->device_type = $request->device_type;
            $doctor->save();
            return GlobalFunction::sendDataResponse(true, 'Doctor Data fetched successfully', $doctor);
        }
    }

    function doctorLogin(Request $request)
    {
        $hostAndConversionRate = Helpers::conversionRate();
        $conversionRate = (float) $hostAndConversionRate['conversionRate'];
        $doctor = Doctors::select('doctors.*',DB::raw("ROUND(consultation_fee * {$conversionRate}) as consultation_fee"))
                    ->where('identity', $request->identity)->first();
        if ($doctor) {
            if ($doctor->status == 1) {
                $doctor = Doctors::select('doctors.*',DB::raw("ROUND(consultation_fee * {$conversionRate}) as consultation_fee"))
                            ->where('status', 1)->where('identity', $request->identity)->where('password', $request->password)->first();
                if ($doctor) {
                    // $token  = $doctor->createToken('auth_token')->plainTextToken;
                    // $doctor->app_version  = $request->app_version;
                    // $doctor->device_details  = $request->device_details;
                    // $doctor->save();
                    return response()->json(['status' => true, 'data' => $doctor]);
                } else {
                    return response()->json(['message' => 'Invalid credentials'], 200);
                }
            } else {
                return response()->json(['status' => false, 'message' => "Doctor is not approved. Please contact with administration department"]);
            }
        } else {
            return response()->json(['status' => false, 'message' => "Doctor does not exist"]);
        }
    }

    function suggestDoctorCategory(Request $request)
    {
        $rules = [
            'title' => 'required',
            'about' => 'required',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }

        $item = new DoctorCatSuggestions();
        $item->title = GlobalFunction::cleanString($request->title);
        $item->about = GlobalFunction::cleanString($request->about);
        $item->save();

        return GlobalFunction::sendSimpleResponse(true, 'suggestion stored successfully');
    }

    function deleteDoctorAccount(Request $request)
    {
        $rules = [
            'doctor_id' => 'required'
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

        if (in_array($doctor->mobile_number, ["+91 8160530517", "+91 7096404485"])) {
            return GlobalFunction::sendSimpleResponse(false, 'This account can not be deleted! Please log out.');
        }

        DoctorBankAccount::where('doctor_id', $doctor->id)->delete();
        DoctorHolidays::where('doctor_id', $doctor->id)->delete();
        DoctorAppointmentSlots::where('doctor_id', $doctor->id)->delete();
        DoctorAwards::where('doctor_id', $doctor->id)->delete();
        DoctorServices::where('doctor_id', $doctor->id)->delete();
        DoctorExpertise::where('doctor_id', $doctor->id)->delete();
        DoctorExperience::where('doctor_id', $doctor->id)->delete();
        DoctorServiceLocations::where('doctor_id', $doctor->id)->delete();
        DoctorEarningHistory::where('doctor_id', $doctor->id)->delete();
        DoctorPayoutHistory::where('doctor_id', $doctor->id)->delete();
        DoctorWalletStatements::where('doctor_id', $doctor->id)->delete();

        $doctor->delete();

        return GlobalFunction::sendSimpleResponse(true, 'Doctor account deleted successfully');
    }

    function updateDoctorDetails(Request $request)
    {
        $rules = [
            'doctor_id' => 'required'
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
        if ($request->has('name')) {
            $doctor->name = GlobalFunction::cleanString($request->name);
        }
        if ($request->has('saved_reels')) {
            $doctor->saved_reels = $request->saved_reels;
        }
        if ($request->has('country_code')) {
            $doctor->country_code = $request->country_code;
        }
        if ($request->has('mobile_number')) {
            $doctor->mobile_number = $request->mobile_number;
        }
        if ($request->has('image')) {
            $doctor->image = GlobalFunction::saveFileAndGivePath($request->image);
        }
        if ($request->has('gender')) {
            $doctor->gender = $request->gender;
        }
        if ($request->has('category_id')) {
            $doctor->category_id = $request->category_id;
        }
        if ($request->has('designation')) {
            $doctor->designation = GlobalFunction::cleanString($request->designation);
        }
        if ($request->has('degrees')) {
            $doctor->degrees = GlobalFunction::cleanString($request->degrees);
        }
        if ($request->has('languages_spoken')) {
            $doctor->languages_spoken = GlobalFunction::cleanString($request->languages_spoken);
        }
        if ($request->has('experience_year')) {
            $doctor->experience_year = $request->experience_year;
        }
        if ($request->has('consultation_fee')) {
            $doctor->consultation_fee = $request->consultation_fee;
        }
        if ($request->has('about_youself')) {
            $doctor->about_youself = GlobalFunction::cleanString($request->about_youself);
        }
        if ($request->has('educational_journey')) {
            $doctor->educational_journey = GlobalFunction::cleanString($request->educational_journey);
        }
        if ($request->has('online_consultation')) {
            $doctor->online_consultation = $request->online_consultation;
        }
        if ($request->has('clinic_consultation')) {
            $doctor->clinic_consultation = $request->clinic_consultation;
        }
        if ($request->has('clinic_name')) {
            $doctor->clinic_name = GlobalFunction::cleanString($request->clinic_name);
        }
        if ($request->has('clinic_address')) {
            $doctor->clinic_address = $request->clinic_address;
        }
        if ($request->has('clinic_lat')) {
            $doctor->clinic_lat = $request->clinic_lat;
        }
        if ($request->has('clinic_long')) {
            $doctor->clinic_long = $request->clinic_long;
        }
        if ($request->has('is_notification')) {
            $doctor->is_notification = $request->is_notification;
        }
        if ($request->has('on_vacation')) {
            $doctor->on_vacation = $request->on_vacation;
        }
        if ($request->has('password')) {
            $doctor->password = $request->password;
        }
         if ($request->has('dha_registration_number')) {
            $doctor->dha_registration_number = $request->dha_registration_number;
        }
        if ($request->has('digital_signature')) {
            $doctor->digital_signature = GlobalFunction::saveFileAndGivePath($request->digital_signature);
        }

        if ($request->has('doctor_seal')) {
            $doctor->doctor_seal = GlobalFunction::saveFileAndGivePath($request->doctor_seal);
        }


        $doctor->is_profile_complete = Constants::profileCompleted;

        $doctor->save();

        // $data = [];

        // if ($request->filled('name')) {
        //     $data['name'] = GlobalFunction::cleanString($request->name);
        // }

        // if ($request->has('saved_reels')) {
        //     $data['saved_reels'] = $request->saved_reels;
        // }

        // if ($request->filled('country_code')) {
        //     $data['country_code'] = $request->country_code;
        // }

        // if ($request->filled('mobile_number')) {
        //     $data['mobile_number'] = $request->mobile_number;
        // }

        // if ($request->hasFile('image')) {
        //     $data['image'] = GlobalFunction::saveFileAndGivePath($request->image);
        // }

        // if ($request->filled('gender')) {
        //     $data['gender'] = $request->gender;
        // }

        // if ($request->filled('category_id')) {
        //     $data['category_id'] = $request->category_id;
        // }

        // if ($request->filled('designation')) {
        //     $data['designation'] = GlobalFunction::cleanString($request->designation);
        // }

        // if ($request->filled('degrees')) {
        //     $data['degrees'] = GlobalFunction::cleanString($request->degrees);
        // }

        // if ($request->filled('languages_spoken')) {
        //     $data['languages_spoken'] = GlobalFunction::cleanString($request->languages_spoken);
        // }

        // if ($request->filled('experience_year')) {
        //     $data['experience_year'] = $request->experience_year;
        // }

        // if ($request->filled('consultation_fee')) {
        //     $data['consultation_fee'] = $request->consultation_fee;
        // }

        // if ($request->filled('about_youself')) {
        //     $data['about_youself'] = GlobalFunction::cleanString($request->about_youself);
        // }

        // if ($request->filled('educational_journey')) {
        //     $data['educational_journey'] = GlobalFunction::cleanString($request->educational_journey);
        // }

        // if ($request->has('online_consultation')) {
        //     $data['online_consultation'] = $request->online_consultation;
        // }

        // if ($request->has('clinic_consultation')) {
        //     $data['clinic_consultation'] = $request->clinic_consultation;
        // }

        // if ($request->filled('clinic_name')) {
        //     $data['clinic_name'] = GlobalFunction::cleanString($request->clinic_name);
        // }

        // if ($request->filled('clinic_address')) {
        //     $data['clinic_address'] = GlobalFunction::cleanString($request->clinic_address);
        // }

        // if ($request->filled('clinic_lat')) {
        //     $data['clinic_lat'] = $request->clinic_lat;
        // }

        // if ($request->filled('clinic_long')) {
        //     $data['clinic_long'] = $request->clinic_long;
        // }

        // if ($request->has('is_notification')) {
        //     $data['is_notification'] = $request->is_notification;
        // }

        // if ($request->has('on_vacation')) {
        //     $data['on_vacation'] = $request->on_vacation;
        // }

        // if ($request->filled('password')) {
        //     $data['password'] = bcrypt($request->password);
        // }

        // if ($request->filled('dha_registration_number')) {
        //     $data['dha_registration_number'] = $request->dha_registration_number;
        // }

        // if ($request->hasFile('digital_signature')) {
        //     $data['digital_signature'] = GlobalFunction::saveFileAndGivePath($request->digital_signature);
        // }

        // if ($request->hasFile('doctor_seal')) {
        //     $data['doctor_seal'] = GlobalFunction::saveFileAndGivePath($request->doctor_seal);
        // }

        // $data['is_profile_complete'] = Constants::profileCompleted;

        // if (\Schema::connection('mysql') && \Schema::connection('mysql')->hasTable("doctors")){
        //     DB::connection('mysql')->table('doctors')->where('id', $doctor->id)->update($data);
        // }
        // if (\Schema::connection('mulkmed_india') && \Schema::connection('mulkmed_india')->hasTable("doctors")){
        //     DB::connection('mulkmed_india')->table('doctors')->where('id', $doctor->id)->update($data);
        // }

        $doctor = GlobalFunction::generateDoctorFullData($doctor->id);

        return GlobalFunction::sendDataResponse(true, 'Doctor details updated successfully', $doctor);
    }
    function logOutDoctor(Request $request)
    {
        $rules = [
            'doctor_id' => 'required'
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
        $doctor->device_token = null;
        $doctor->save();

        $doctor = GlobalFunction::generateDoctorFullData($doctor->id);

        return GlobalFunction::sendSimpleResponse(true, 'Doctor log out successfully');
    }

    function updateDoctorFcm(Request $request)
    {
        $rules = [
            'doctor_id'     => 'required',
            'device_token'  => 'required'
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
        $doctor->device_token = $request->device_token;
        $doctor->save();


        return GlobalFunction::sendSimpleResponse(true, 'Doctor FCM token updated successfully');
    }
}
