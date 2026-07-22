<?php

namespace App\Http\Controllers;

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
use App\Models\Doctors;
use App\Models\DoctorServiceLocations;
use App\Models\DoctorServices;
use App\Models\DoctorWalletStatements;
use App\Models\FaqCats;
use App\Models\GlobalFunction;
use App\Models\GlobalSettings;
use App\Models\Hospitals;
use App\Models\Prescriptions;
use App\Models\Users;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Rap2hpoutre\FastExcel\FastExcel;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\MemoryDrawing;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Brian2694\Toastr\Facades\Toastr;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use Stichoza\GoogleTranslate\GoogleTranslate;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Throwable;

class DoctorController extends Controller
{
    //

    function bulkUpdateExperties(Request $request)
    {
        $doctor_expertise = DoctorExpertise::where('id','>',1449)->get();

        foreach ($doctor_expertise as $key => $value) {
            $ta = new GoogleTranslate('ar');
            $value->ar_title = $ta->translate($value->title);
            $tf = new GoogleTranslate('fr');
            $value->fr_title = $tf->translate($value->title);
            $th = new GoogleTranslate('hi');
            $value->hi_title = $th->translate($value->title);
            $tu = new GoogleTranslate('ur');
            $value->ur_title = $tu->translate($value->title);
            $value->save();
        }
        return ("done");
    }

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
        $totalData =  DoctorServiceLocations::where('doctor_id', $request->doctorId)->count();
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
            $result =  DoctorServiceLocations::where('doctor_id', $request->doctorId)
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
            "draw"            => intval($request->input('draw')),
            "recordsTotal"    => intval($totalData),
            "recordsFiltered" => $totalFiltered,
            "data"            => $data
        );
        echo json_encode($json_data);
        exit();
    }
    function fetchDoctorHolidaysList(Request $request)
    {
        $totalData =  DoctorHolidays::where('doctor_id', $request->doctorId)->count();
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
            $result =  DoctorHolidays::where('doctor_id', $request->doctorId)
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
            "draw"            => intval($request->input('draw')),
            "recordsTotal"    => intval($totalData),
            "recordsFiltered" => $totalFiltered,
            "data"            => $data
        );
        echo json_encode($json_data);
        exit();
    }
    function fetchDoctorAwardsList(Request $request)
    {
        $totalData =  DoctorAwards::where('doctor_id', $request->doctorId)->count();
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
            $result =  DoctorAwards::where('doctor_id', $request->doctorId)
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
            "draw"            => intval($request->input('draw')),
            "recordsTotal"    => intval($totalData),
            "recordsFiltered" => $totalFiltered,
            "data"            => $data
        );
        echo json_encode($json_data);
        exit();
    }
    function fetchDoctorExperienceList(Request $request)
    {
        $totalData =  DoctorExperience::where('doctor_id', $request->doctorId)->count();
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
            $result =  DoctorExperience::where('doctor_id', $request->doctorId)
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
            "draw"            => intval($request->input('draw')),
            "recordsTotal"    => intval($totalData),
            "recordsFiltered" => $totalFiltered,
            "data"            => $data
        );
        echo json_encode($json_data);
        exit();
    }
    function fetchDoctorExpertiseList(Request $request)
    {
        $totalData =  DoctorExpertise::where('doctor_id', $request->doctorId)->count();
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
            $result =  DoctorExpertise::where('doctor_id', $request->doctorId)
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

                // Edit button
                $edit = '<a href="" 
                class="mr-2 btn btn-primary text-white expertiseEdit" 
                data-id="' . $item->id . '" 
                data-title="' . e($item->title) . '">' 
                . __("Edit") . 
            '</a>';

            // Delete button
            $delete = '<a href="" class="mr-2 btn btn-danger text-white delete" rel="' . $item->id . '">' . __("Delete") . '</a>';

            // Combine both
            $action = $edit . $delete;

            $data[] = array(
                $item->title,
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
    function fetchDoctorServicesList(Request $request)
    {
        $totalData =  DoctorServices::where('doctor_id', $request->doctorId)->count();
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
            $result =  DoctorServices::where('doctor_id', $request->doctorId)
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
            "draw"            => intval($request->input('draw')),
            "recordsTotal"    => intval($totalData),
            "recordsFiltered" => $totalFiltered,
            "data"            => $data
        );
        echo json_encode($json_data);
        exit();
    }
    
    function fetchDoctorEarningsList(Request $request)
    {
        $totalData =  DoctorEarningHistory::where('doctor_id', $request->doctorId)->with('appointment')->count();
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
            $result =  DoctorEarningHistory::where('doctor_id', $request->doctorId)
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
            "draw"            => intval($request->input('draw')),
            "recordsTotal"    => intval($totalData),
            "recordsFiltered" => $totalFiltered,
            "data"            => $data
        );
        echo json_encode($json_data);
        exit();
    }

    function fetchDoctorPayoutRequestsList(Request $request)
    {
        $totalData =  DoctorPayoutHistory::where('doctor_id', $request->doctorId)->with('doctor')->count();
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
            $result =  DoctorPayoutHistory::where('doctor_id', $request->doctorId)
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
                $account_number = '<span>' . __('Account : ') .  $bankAccount->account_number . '</span>';
                $swift_code = '<span>' . __('Swift Code : ') . $bankAccount->swift_code . '</span></div>';
                $bankDetails = $holder . $bank_title . $account_number . $swift_code;
            }

            $complete = '<a href="" class="mr-2 btn btn-success text-white complete" rel=' . $item->id . ' >' . __("Complete") . '</a>';
            $reject = '<a href="" class="mr-2 btn btn-danger text-white reject" rel=' . $item->id . ' >' . __("Reject") . '</a>';
            $action = '';

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
            "draw"            => intval($request->input('draw')),
            "recordsTotal"    => intval($totalData),
            "recordsFiltered" => $totalFiltered,
            "data"            => $data
        );
        echo json_encode($json_data);
        exit();
    }

    function fetchDoctorWalletStatement(Request $request)
    {
        $totalData =  DoctorWalletStatements::where('doctor_id', $request->doctorId)->count();
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
            $result =  DoctorWalletStatements::where('doctor_id', $request->doctorId)
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
    function fetchDoctorReviewsList(Request $request)
    {
        $totalData =  DoctorReviews::where('doctor_id', $request->doctorId)->with(['doctor', 'appointment'])->count();
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
            $result =  DoctorReviews::where('doctor_id', $request->doctorId)->with(['doctor', 'appointment'])
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
            "draw"            => intval($request->input('draw')),
            "recordsTotal"    => intval($totalData),
            "recordsFiltered" => $totalFiltered,
            "data"            => $data
        );
        echo json_encode($json_data);
        exit();
    }

    function fetchDoctorAppointmentsList(Request $request)
    {
        $totalData =  Appointments::where('doctor_id', $request->doctorId)->count();
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
            $result =  Appointments::where('doctor_id', $request->doctorId)->where(function ($query) use ($search) {
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

            $dateTime =  $item->date . '<br>' . GlobalFunction::formateTimeString($item->time);
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
            "draw"            => intval($request->input('draw')),
            "recordsTotal"    => intval($totalData),
            "recordsFiltered" => $totalFiltered,
            "data"            => $data
        );
        echo json_encode($json_data);
        exit();
    }
    function updateDoctorDetails_Admin(Request $request)
    {

        // dd($request->all());
        $item = Doctors::find($request->id);

        if($request->has('profile_image')){
            $item->image = GlobalFunction::saveFileAndGivePath($request->profile_image);
        }
    
        if($request->has('signature')){
            $item->digital_signature = GlobalFunction::saveFileAndGivePath($request->signature);
        }

        $item->designation = $request->designation;
        $item->languages_spoken = $request->languages_spoken;
        $item->consultation_fee = $request->consultation_fee;
        $item->experience_year = $request->experience_year;
        $item->category_id = $request->category_id;
        $ta = new GoogleTranslate('ar');
        $item->ar_designation = $ta->translate($item->designation);
        $item->ar_languages_spoken = $ta->translate($item->languages_spoken);
        $tf = new GoogleTranslate('fr');
        $item->fr_designation = $tf->translate($item->designation);
        $item->fr_languages_spoken = $tf->translate($item->languages_spoken);
        $th = new GoogleTranslate('hi');
        $item->hi_designation = $th->translate($item->designation);
        $item->hi_languages_spoken = $th->translate($item->languages_spoken);
        $tu = new GoogleTranslate('ur');
        $item->ur_designation = $tu->translate($item->designation);
        $item->ur_languages_spoken = $tu->translate($item->languages_spoken);
        $item->degrees = $request->degrees;
        $item->about_youself = $request->about_youself;
        $item->educational_journey = $request->educational_journey;
        $item->dha_registration_number = $request->dha_registration_number;
        $item->clinic_name = $request->clinic_name;
        $item->clinic_address = $request->clinic_address;
        $item->country = $request->country;
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

    function changeLongevityCareStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'doctor_id' => 'required',
            'is_longevity_care' => 'required|in:0,1',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()]);
        }

        $doctor = Doctors::find($request->doctor_id);
        if ($doctor == null) {
            return GlobalFunction::sendSimpleResponse(false, 'Doctor does not Exists');
        }

        try {
            $doctor->is_longevity_care = (int) $request->is_longevity_care;
            $doctor->save();
        } catch (Throwable $e) {
            Log::error('changeLongevityCareStatus failed: ' . $e->getMessage());
            return GlobalFunction::sendSimpleResponse(false, 'Failed to update longevity care status');
        }

        return GlobalFunction::sendSimpleResponse(true, 'Longevity care status updated successfully!');
    }

    function viewDoctorProfile($doctorId)
    {
        $doctor = Doctors::with(['bankAccount', 'category'])->find($doctorId);
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
            return $slot['weekday'] == 1;
        });
        $tuesdaySlots = array_filter($slots->toArray(), function ($slot) {
            return $slot['weekday'] == 2;
        });
        $wednesdaySlots = array_filter($slots->toArray(), function ($slot) {
            return $slot['weekday'] == 3;
        });
        $thursdaySlots = array_filter($slots->toArray(), function ($slot) {
            return $slot['weekday'] == 4;
        });
        $fridaySlots = array_filter($slots->toArray(), function ($slot) {
            return $slot['weekday'] == 5;
        });
        $saturdaySlots = array_filter($slots->toArray(), function ($slot) {
            return $slot['weekday'] == 6;
        });
        $sundaySlots = array_filter($slots->toArray(), function ($slot) {
            return $slot['weekday'] == 7;
        });

        $doctor_catrogries = DoctorCategories::where('is_deleted', 0)->get();

        // return $slots = array(
        //         'mondaySlots' => $mondaySlots,
        //         'tuesdaySlots' => $tuesdaySlots,
        //         'wednesdaySlots' => $wednesdaySlots,
        //         'thursdaySlots' => $thursdaySlots,
        //         'fridaySlots' => $fridaySlots,
        //         'saturdaySlots' => $saturdaySlots,
        //         'sundaySlots' => $sundaySlots,
        // );
        return view('viewDoctorProfile', [
            'doctor' => $doctor,
            'doctor_catrogries' => $doctor_catrogries,
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
        $totalData =  DoctorPayoutHistory::where('status', Constants::statusWithdrawalRejected)->with('doctor')->count();
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
            $result =  DoctorPayoutHistory::where('status', Constants::statusWithdrawalRejected)
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
                $account_number = '<span>' . __('Account : ') .  $bankAccount->account_number . '</span>';
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
            "draw"            => intval($request->input('draw')),
            "recordsTotal"    => intval($totalData),
            "recordsFiltered" => $totalFiltered,
            "data"            => $data
        );
        echo json_encode($json_data);
        exit();
    }
    function fetchDoctorCompletedWithdrawalsList(Request $request)
    {
        $totalData =  DoctorPayoutHistory::where('status', Constants::statusWithdrawalCompleted)->with('doctor')->count();
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
            $result =  DoctorPayoutHistory::where('status', Constants::statusWithdrawalCompleted)
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
                $account_number = '<span>' . __('Account : ') .  $bankAccount->account_number . '</span>';
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
            "draw"            => intval($request->input('draw')),
            "recordsTotal"    => intval($totalData),
            "recordsFiltered" => $totalFiltered,
            "data"            => $data
        );
        echo json_encode($json_data);
        exit();
    }
    function fetchDoctorPendingWithdrawalsList(Request $request)
    {
        $totalData =  DoctorPayoutHistory::with('doctor')->count();
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
            $result =  DoctorPayoutHistory::where('status', Constants::statusWithdrawalPending)
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
                $account_number = '<span>' . __('Account : ') .  $bankAccount->account_number . '</span>';
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
            $action =  $complete . $reject;

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
            "draw"            => intval($request->input('draw')),
            "recordsTotal"    => intval($totalData),
            "recordsFiltered" => $totalFiltered,
            "data"            => $data
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
        $totalData =  Doctors::where('status', Constants::statusDoctorBanned)->count();
        $rows = Doctors::where('status', Constants::statusDoctorBanned)->orderBy('id', 'DESC')->get();
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
            $result = Doctors::where('status', Constants::statusDoctorBanned)->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
        } else {
            $search = $request->input('search.value');
            $result =  Doctors::where('status', Constants::statusDoctorBanned)->where(function ($query) use ($search) {
                $query->Where('name', 'LIKE', "%{$search}%")
                    ->orWhere('mobile_number', 'LIKE', "%{$search}%")
                    ->orWhere('doctor_number', 'LIKE', "%{$search}%");
            })->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
            $totalFiltered = Doctors::where('status', Constants::statusDoctorBanned)->where(function ($query) use ($search) {
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
                $item->id,
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
            "draw"            => intval($request->input('draw')),
            "recordsTotal"    => intval($totalData),
            "recordsFiltered" => $totalFiltered,
            "data"            => $data
        );
        echo json_encode($json_data);
        exit();
    }
    function fetchPendingDoctorsList(Request $request)
    {
        $totalData =  Doctors::where('status', Constants::statusDoctorPending)->count();
        $rows = Doctors::where('status', Constants::statusDoctorPending)->orderBy('id', 'DESC')->get();
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
            $result = Doctors::where('status', Constants::statusDoctorPending)->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
        } else {
            $search = $request->input('search.value');
            $result =  Doctors::where('status', Constants::statusDoctorPending)->where(function ($query) use ($search) {
                $query->Where('name', 'LIKE', "%{$search}%")
                    ->orWhere('mobile_number', 'LIKE', "%{$search}%")
                    ->orWhere('doctor_number', 'LIKE', "%{$search}%");
            })->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
            $totalFiltered = Doctors::where('status', Constants::statusDoctorPending)->where(function ($query) use ($search) {
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
                $item->id,
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
            "draw"            => intval($request->input('draw')),
            "recordsTotal"    => intval($totalData),
            "recordsFiltered" => $totalFiltered,
            "data"            => $data
        );
        echo json_encode($json_data);
        exit();
    }
    function fetchApprovedDoctorsList(Request $request)
    {
        $totalData =  Doctors::where('status', Constants::statusDoctorApproved)->count();
        $rows = Doctors::where('status', Constants::statusDoctorApproved)->orderBy('id', 'DESC')->get();
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
            $result = Doctors::where('status', Constants::statusDoctorApproved)->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
        } else {
            $search = $request->input('search.value');
            $result =  Doctors::where('status', Constants::statusDoctorApproved)->where(function ($query) use ($search) {
                $query->Where('name', 'LIKE', "%{$search}%")
                    ->orWhere('mobile_number', 'LIKE', "%{$search}%")
                    ->orWhere('doctor_number', 'LIKE', "%{$search}%");
            })->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
            $totalFiltered = Doctors::
            where('status', Constants::statusDoctorApproved)->where(function ($query) use ($search) {
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
                $item->id,
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
            "draw"            => intval($request->input('draw')),
            "recordsTotal"    => intval($totalData),
            "recordsFiltered" => $totalFiltered,
            "data"            => $data
        );
        echo json_encode($json_data);
        exit();
    }
    function fetchAllDoctorsList(Request $request)
    {
        $totalData =  Doctors::count();
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
            $result = Doctors::offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
        } else {
            $search = $request->input('search.value');
            $result =  Doctors::where(function ($query) use ($search) {
                $query->Where('name', 'LIKE', "%{$search}%")
                    ->orWhere('mobile_number', 'LIKE', "%{$search}%")
                    ->orWhere('doctor_number', 'LIKE', "%{$search}%");
            })->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
            $totalFiltered = Doctors::where(function ($query) use ($search) {
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

            // if (request()->has('delete') && request()->get('delete') == 'true') {
                $view .= '<button type="button" class="btn btn-danger text-white delete-doctor" data-url="' . route('deleteDoctor', $item->id) . '" rel="' . $item->id . '">' . __("Delete") . '</button>';
            // }

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
                $item->id,
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
            "draw"            => intval($request->input('draw')),
            "recordsTotal"    => intval($totalData),
            "recordsFiltered" => $totalFiltered,
            "data"            => $data
        );
        echo json_encode($json_data);
        exit();
    }

    function deleteDoctor($id){
        $doctor = Doctors::findOrFail($id);
        $doctorId = $doctor->id;
        $doctor->delete();

        $doc_exp = DoctorExpertise::where('doctor_id', $doctorId)->first();
        if($doc_exp){
            $doc_exp->delete();
        }

        return GlobalFunction::sendSimpleResponse(true, 'Doctor deleted successfully!');
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
        $doctor = Doctors::where('id', $request->doctor_id)->first();
        if ($doctor == null) {
            return GlobalFunction::sendSimpleResponse(false, 'Doctor does not exists!');
        }
        $result =  DoctorReviews::with(['user'])
            ->Where('doctor_id', $request->doctor_id)
            ->whereHas('user')
            ->whereHas('doctor')
            ->orderBy('id', 'DESC')
            ->offset($request->start)
            ->limit($request->count)
            ->get();

        return GlobalFunction::sendDataResponse(true, 'data fetched successfully', $result);
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
        $doctor = Doctors::where('id', $request->doctor_id)->first();
        
        if ($doctor == null) {
            return GlobalFunction::sendSimpleResponse(false, 'Doctor does not exists!');
        }

        $doctor = GlobalFunction::generateDoctorFullData($doctor->id);

        $doctor['image'] = Doctor::find($doctor->id)->getRawOriginal('image');

        return GlobalFunction::sendDataResponse(true, 'data fetched successfully', $doctor);
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
        $query = Doctors::query();

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
    function addAppointmentSlots(Request $request)
    {
        $rules = [
            'time' => 'required',
            'weekday' => 'required',
            'doctor_id' => 'required',
            'booking_limit' => 'required',
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
            $slot->booking_limit = $request->booking_limit;
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

    public function updateExpertise(Request $request)
{
    $request->validate([
        'id'    => 'required|integer|exists:doctor_expertise,id',
        'title' => 'required|string|max:255',
    ]);

    $expertise = DoctorExpertise::findOrFail($request->id);
    $expertise->title = $request->title;
    $expertise->save();

    return response()->json([
        'status'  => true,
        'message' => 'Expertise updated successfully!',
    ]);
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

            $service->title =  GlobalFunction::cleanString($request->title);
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
            'is_login' => [Rule::in(0,1)] //1=login 0=register
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }

        $doctor = Doctors::where('identity', $request->identity)->first();
        if($request->is_login == 1 && $doctor == null){
               return GlobalFunction::sendSimpleResponse(false, 'user not found');
        }

        if ($doctor == null) {
            $doctor = new Doctors();
            $doctor->identity = $request->identity;
            $doctor->name = $request->name;
            $doctor->device_token = $request->device_token;
            $doctor->device_type = $request->device_type;
            $doctor->doctor_number = GlobalFunction::generateDoctorNumber();
            $doctor->save();

            $doctor = Doctors::find($doctor->id);

            return GlobalFunction::sendDataResponse(true, 'Doctor Data fetched successfully', $doctor);
        } else {
            $doctor->device_token = $request->device_token;
            $doctor->device_type = $request->device_type;
            $doctor->save();
            return GlobalFunction::sendDataResponse(true, 'Doctor Data fetched successfully', $doctor);
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
        if ($request->has('country')) {
            $doctor->country = $request->country;
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
        if ($request->has('dha_registration_number')) {
            $doctor->dha_registration_number = $request->dha_registration_number;
        }
        if ($request->has('digital_signature')) {
            $doctor->digital_signature = GlobalFunction::saveFileAndGivePath($request->digital_signature);
        }

        $doctor->is_profile_complete = Constants::profileCompleted;

        $doctor->save();

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

    function viewBulkUploadDoctors(Request $request)
    {
        return view('bulkUploadDoctors');
    }

    function viewBulkUploadDHARegistrationAndSignature(Request $request)
    {
        return view('bulkUploadDHARegistrationAndSignature');
    }

    function bulkUploadDoctors(Request $request)
    {
        // return $request;
        ini_set('max_execution_time', -1);
        try {
             $collections = (new FastExcel)->import($request->file('customer_file'));
        } catch (\Exception $exception) {
            return back()->with('error', 'You have uploaded a wrong format file, please upload the right file.');
        }
        $data = [];
        foreach ($collections as $collection)
        {
            $category = DoctorCategories::where('title',$collection['Specialty/Category'])->where('is_deleted', 0)->first();
            $category_id = 0;
            if($category){
                $category_id = $category->id;
            }

            $ta = new GoogleTranslate('ar');
            $ar_designation = isset($collection['Designation']) ? $ta->translate($collection['Designation']) : null;
            $ar_languages_spoken = isset($collection['Languages Known']) ? $ta->translate($collection['Languages Known']) : null;
            $tf = new GoogleTranslate('fr');
            $fr_designation =  isset($collection['Designation']) ? $tf->translate($collection['Designation']) : null;
            $fr_languages_spoken = isset($collection['Languages Known']) ? $tf->translate($collection['Languages Known']) : null;
            $th = new GoogleTranslate('hi');
            $hi_designation = isset($collection['Designation']) ? $th->translate($collection['Designation']) : null;
            $hi_languages_spoken = $th->translate($collection['Languages Known']);
            $tu = new GoogleTranslate('ur');
            $ur_designation = isset($collection['Designation']) ? $tu->translate($collection['Designation']) : null;
            $ur_languages_spoken = isset($collection['Languages Known']) ? $tu->translate($collection['Languages Known']) : null;

            $supportMail1   = isset($collection['Support mail1']) && trim($collection['Support mail1']) !== '' 
                ? trim($collection['Support mail1']) 
                : null;

            $supportMail2   = isset($collection['Support mail2']) && trim($collection['Support mail2']) !== '' 
                ? trim($collection['Support mail2']) 
                : null;

            $supportMail3   = isset($collection['Support mail3']) && trim($collection['Support mail3']) !== '' 
                ? trim($collection['Support mail3']) 
                : null;

            $supportMail4   = isset($collection['Support mail4']) && trim($collection['Support mail4']) !== '' 
                ? trim($collection['Support mail4']) 
                : null;
            $supportNumber1 = isset($collection['Support number1']) && trim($collection['Support number1']) !== '' 
                            ? trim($collection['Support number1']) 
                            : null;
            $supportNumber2 = isset($collection['Support number2']) && trim($collection['Support number2']) !== '' 
                            ? trim($collection['Support number2']) 
                            : null;

            $c_code = request()->getHost() === 'india.mulkmed.com' ? '91' : '971';

            $id =  DB::table('doctors')->insertGetId([
                "identity" => $collection['Email'],
                "rating" => $collection['Rating'],
                "total_patients_cured" => $collection['No. Of Patients'],
                "status" => 1,
                "doctor_number" => GlobalFunction::generateDoctorNumber(),
                "is_profile_complete" => 1,
                "name" => $collection['Doctor Name'],
                "country_code" => $c_code,
                "mobile_number" => $collection['Phone Number'],
                "designation" => $collection['Designation'],
                "gender" => $collection['Gender'] == 'Male' ? 1 : 0 ,
                "category_id" => $category_id,
                "degrees" => $collection['Qualification/ Degree'],
                "languages_spoken" => $collection['Languages Known'],
                "experience_year" => $collection['Experience (years)'],
                "consultation_fee" => $collection['Consultation Fee in AED'],
                // "consultation_fee" => $collection["Consultation Fee in {$currency}"],
                "about_youself" => $collection['Qualification/ Degree'],
                "educational_journey" => $collection['Qualification/ Degree'],
                "clinic_name" => $collection['Clinic'],
                "email" => $collection['Email'],
                "password" => $collection['Password'],
                "email_2" => $supportMail1,
                "email_3" => $supportMail2,
                "email_4" => $supportMail3,
                "email_5" => $supportMail4,
                "mobile_number_2" => $supportNumber1,
                "mobile_number_3" => $supportNumber2,
                "ar_designation" => $ar_designation,
                "ur_designation" => $ur_designation,  
                "fr_designation" => $fr_designation,
                "hi_designation" => $hi_designation,  
                "ar_languages_spoken" => $ar_languages_spoken,
                "ur_languages_spoken" => $ur_languages_spoken,
                "fr_languages_spoken" => $fr_languages_spoken,  
                "hi_languages_spoken" => $hi_languages_spoken,
                "digital_signature" => $collection['Signature'] ?? null,
                "dha_registration_number" => $collection['DHA Registration Number'] ?? null,
                "is_smo" => $collection['SMO Status'] ?? null,
                "is_mulkmed" => $collection['India Status'] ?? 0,
                "country" => $collection['Country'] ?? null,
            ]);

            if($id){
                $cellValue = $collection['Areas of expertise'];
                $lines = preg_split("/\r\n|\n|\r/", $cellValue);
                foreach ($lines as $line) {
                    if (trim($line) !== '') {
                        $item = new DoctorExpertise();
                        $item->title = GlobalFunction::cleanString($line);
                        $ta = new GoogleTranslate('ar');
                        $item->ar_title = $ta->translate($item->title);
                        $tf = new GoogleTranslate('fr');
                        $item->fr_title = $tf->translate($item->title);
                        $th = new GoogleTranslate('hi');
                        $item->hi_title = $th->translate($item->title);
                        $tu = new GoogleTranslate('ur');
                        $item->ur_title = $tu->translate($item->title);
                        $item->doctor_id = $id;
                        $item->save();
                    }
                }
            }
        }

        return back()->with('success', 'Doctors imported successfully!');
    }

    
    function viewBulkUpdateDoctorMobile(Request $request)
    {
        return view('bulkUpdateDoctorMobile');
    }

   function bulkUpdateDoctorMobile(Request $request){
    ini_set('max_execution_time', -1);

    // Validate file
    $request->validate([
        'update_file' => 'required|mimes:xlsx,xls,csv'
    ]);

    try {
        $collections = (new FastExcel)->import($request->file('update_file'));
    } catch (\Exception $exception) {
        return back()->with('error', 'You have uploaded a wrong format file, please upload the right file.');
    }

    $count = 0;

    foreach ($collections as $collection) {

        $id = $collection['id'] ?? null;
        $country_code = $collection['country_code'] ?? null;
        $mobile_number = $collection['mobile_number'] ?? null;

        if ($id) {

            // Clean country code (remove + if exists)
            $country_code = $country_code ? str_replace('+', '', trim($country_code)) : null;

            $updated = DB::table('doctors')
                ->where('id', $id)
                ->update([
                    'country_code' => $country_code,
                    'mobile_number' => trim($mobile_number),
                    'updated_at' => now()
                ]);

            if ($updated) {
                $count++;
            }
        }
    }

    return back()->with('success', $count . ' doctors updated successfully.');
}


    public function downloadBulkUpdateDoctorMobileFormat()
{
    $filePath = storage_path('app/public/uploads/bulk_update_doctor_mobile_format.xlsx');

    if (!file_exists($filePath)) {
        return back()->with('error', 'File not found!');
    }

    return response()->download(
        $filePath,
        'bulk_update_doctor_mobile_format.xlsx'
    );
}


    public function downloadDHARegistrationAndSignature()
    {
        $filePath = storage_path('app/public/uploads/DHA_Registration_And_Signature.xlsx'); // absolute path

        if (!file_exists($filePath)) {
            return back()->with('error', 'File not found!');
        }

        return response()->download($filePath, 'DHA_Registration_And_Signature.xlsx');
    }

    function bulkUploadDHARegistrationAndSignature(Request $request)
    {
        // Ensure file is uploaded
        if (!$request->hasFile('doctor_file')) {
            return back()->with('error', 'No file uploaded.');
        }

        $file = $request->file('doctor_file');

        // Save temporarily or load directly
        $uploadedXlsxPath = $file->getRealPath();

        // Load using PhpSpreadsheet
        $spreadsheet = IOFactory::load($uploadedXlsxPath);
        $sheet = $spreadsheet->getActiveSheet();

        // Get all rows as array (for text data)
        $rows = $sheet->toArray(null, true, true, true);

        $imageMap = [];
        
        // Extract images
        foreach ($sheet->getDrawingCollection() as $drawing) {
            if ($drawing instanceof Drawing) {
                $coordinates = $drawing->getCoordinates();
                $extension = $drawing->getExtension() ?? 'png';

                // Get image binary
                if ($drawing->getPath()) {
                    $imageContents = file_get_contents($drawing->getPath());
                } elseif ($drawing->getImageResource()) {
                    ob_start();
                    imagepng($drawing->getImageResource());
                    $imageContents = ob_get_clean();
                } else {
                    continue;
                }

                // Create a temporary file from image data
                $tmpPath = tempnam(sys_get_temp_dir(), 'excel_img_');
                file_put_contents($tmpPath, $imageContents);

                // Wrap it as UploadedFile to pass to your global function
                $tmpFile = new \Illuminate\Http\UploadedFile(
                    $tmpPath,
                    'excel_image.' . $extension,
                    null,
                    null,
                    true
                );

                // Save image using your custom global helper
                $savedPath = GlobalFunction::saveFileAndGivePath($tmpFile);

                $imageMap[$coordinates] = $savedPath;
            }
        }

        // Now loop through each row and attach image by cell coordinate
        foreach ($rows as $rowIndex => $row) {
        // if ($rowIndex === $headerIndex) continue;

        $id = isset($row['A']) ? trim((string)$row['A']) : null;
        if (!$id || !is_numeric($id)) {
            Log::warning("Skipping invalid ID", ['rowIndex' => $rowIndex, 'row' => $row]);
            continue;
        }

        $imageCoordinate = 'B' . $rowIndex;
        $imageName = $imageMap[$imageCoordinate] ?? null;

        $imageCoordinate_1 = 'F' . $rowIndex;
        $imageSeal = $imageMap[$imageCoordinate_1] ?? null;

        try {
            $doctor = Doctors::findOrFail((int)$id);

            // assign only when needed
            $changed = false;
            if ($imageName !== null && $doctor->digital_signature !== $imageName) {
                $doctor->digital_signature = $imageName;
                $changed = true;
            }

            $dha = isset($row['C']) ? trim((string)$row['C']) : null;
            if ($dha !== null && $doctor->dha_registration_number !== $dha) {
                $doctor->dha_registration_number = $dha;
                $changed = true;
            }

            $country = isset($row['D']) ? trim((string)$row['D']) : null;
            if ($country !== null && $doctor->country !== $country) {
                $doctor->country = $country;
                $changed = true;
            }

            $clinic_name = isset($row['E']) ? trim((string)$row['E']) : null;
            if ($clinic_name !== null && $doctor->clinic_name !== $clinic_name) {
                $doctor->clinic_name = $clinic_name;
                $changed = true;
            }

            if ($imageSeal !== null && $doctor->doctor_seal !== $imageSeal) {
                $doctor->doctor_seal = $imageSeal;
                $changed = true;
            }

            if (!$changed) {
                Log::debug("No changes for doctor", ['id' => $doctor->id, 'rowIndex' => $rowIndex]);
                continue;
            }

            
            $doctor->saveOrFail();
            Log::info("Updated doctor", ['id' => $doctor->id, 'rowIndex' => $rowIndex]);

        } catch (ModelNotFoundException $e) {
            Log::warning("Doctor not found", ['id' => $id, 'rowIndex' => $rowIndex]);
            continue;
        } catch (QueryException $e) {
            Log::error("DB error while saving doctor", [
                'id' => $id,
                'rowIndex' => $rowIndex,
                'error' => $e->getMessage(),
            ]);
            continue;
        } catch (Throwable $e) {
            Log::error("Unexpected error", ['msg' => $e->getMessage(), 'row' => $row]);
            continue;
        }
}

        return back()->with('success', 'DHA number & signature imported successfully!');
    }

    function viewBulkUploadDoctorSlots(Request $request)
    {
        return view('bulkUploadDoctorSlots');
    }

    public function bulkUploadDoctorSlots(Request $request)
    {
        $request->validate([
            'file' => ['required','file','mimes:xlsx,csv,txt','max:10240'],
            // 'doctor_id' => ['required', 'exists:doctors,id']
        ]);

        $doctorIdFromForm = $request->doctor_id;

        $weekdayMap = [
            'monday'    => 1, 'mon' => 1,
            'tuesday'   => 2, 'tue' => 2, 'tues' => 2,
            'wednesday' => 3, 'wed' => 3,
            'thursday'  => 4, 'thu' => 4, 'thur' => 4, 'thurs' => 4,
            'friday'    => 5, 'fri' => 5,
            'saturday'  => 6, 'sat' => 6,
            'sunday'    => 7, 'sun' => 7,
        ];

        $path = $request->file('file')->getRealPath();

        $rowsProcessed = 0;
        $rowsInserted  = 0;
        $rowsUpdated   = 0; // not used but kept for parity
        $errors        = [];

        // ---- helpers ----------------------------------------------------------

        $extractTimes = function ($cell) {
            // Return an array of normalized "Hi" strings, e.g. ["0911","1011"].
            // Handles DateTime, numeric-ish, and strings with commas/semicolons.
            $pieces = [];

            if ($cell instanceof \DateTimeInterface) {
                $pieces = [Carbon::instance($cell)->format('H:i')];
            } else {
                // to string and split by comma/semicolon
                $str = (string) $cell;
                if (trim($str) === '') {
                    return [];
                }
                $pieces = preg_split('/\s*[,;]\s*/', $str, -1, PREG_SPLIT_NO_EMPTY);
            }

            $normalizeToHi = function (string $t) {
                $t = trim($t);

                // replace common separators
                $t = str_replace('.', ':', $t);
                $t = preg_replace('/\s+/', '', $t);

                // If it's "HH:MM" or "H:MM"
                if (preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', $t, $m)) {
                    return sprintf('%02d%02d', (int)$m[1], (int)$m[2]);
                }

                // If it's "HHMM" or "HMM"
                if (preg_match('/^(\d{3,4})$/', $t)) {
                    $len = strlen($t);
                    $hh  = ($len === 3) ? substr($t, 0, 1) : substr($t, 0, 2);
                    $mm  = ($len === 3) ? substr($t, 1, 2) : substr($t, 2, 2);
                    if ((int)$hh <= 23 && (int)$mm <= 59) {
                        return sprintf('%02d%02d', (int)$hh, (int)$mm);
                    }
                }

                // Try Carbon with common formats incl. AM/PM
                $formats = ['H:i', 'H.i', 'g:i A', 'g:iA', 'g:i a', 'g:ia'];
                foreach ($formats as $fmt) {
                    try {
                        $c = Carbon::createFromFormat($fmt, $t);
                        return $c->format('Hi');
                    } catch (\Throwable $e) {
                        // try next
                    }
                }

                // Last chance: plain Carbon parse (can be loose; keep as fallback)
                try {
                    return Carbon::parse($t)->format('Hi');
                } catch (\Throwable $e) {
                    throw new \Exception("Invalid time value: {$t}");
                }
            };

            $out = [];
            foreach ($pieces as $p) {
                // If segment is a DateTime string like "1899-12-30 09:11:00", keep only time
                if (preg_match('/\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}/', $p)) {
                    try {
                        $out[] = Carbon::parse($p)->format('Hi');
                        continue;
                    } catch (\Throwable $e) {
                        // fallthrough to generic normalization
                    }
                }
                $out[] = $normalizeToHi($p);
            }

            // de-dupe & preserve order
            return array_values(array_unique($out));
        };
        $resolveWeekday = function ($weekday) use ($weekdayMap) {
            if ($weekday === null || $weekday === '') {
                throw new \Exception("weekday missing");
            }
            if (is_numeric($weekday)) {
                $weekdayNum = (int) $weekday;
                if ($weekdayNum < 1 || $weekdayNum > 7) {
                    throw new \Exception("weekday must be 1..7");
                }
                return $weekdayNum;
            }
            $key = strtolower(trim($weekday));
            $weekdayNum = $weekdayMap[$key] ?? null;
            if (!$weekdayNum) {
                throw new \Exception("weekday must be Mon..Sun or 1..7");
            }
            return $weekdayNum;
        };

        // ----------------------------------------------------------------------

        DB::beginTransaction();

        try {
            (new FastExcel)->import($path, function ($row) use (
                &$rowsProcessed, &$rowsInserted, &$errors, $doctorIdFromForm, $extractTimes, $resolveWeekday
            ) {

                $rowsProcessed++;

                // normalize keys to lowercase
                $row = collect($row)->keyBy(fn($v, $k) => strtolower(trim($k)))->all();

                $weekday  = $row['weekday'] ?? null;
                $timeCell = $row['time'] ?? null;

                try {
                    $doctorId = $row['doctor_id'];
                    if (!$doctorId) {
                        throw new \Exception("doctor_id missing from form");
                    }

                    $weekdayNum = $resolveWeekday($weekday);

                    $timeList = $extractTimes($timeCell);
                    if (empty($timeList)) {
                        throw new \Exception("time missing/invalid");
                    }

                    foreach ($timeList as $timeHi) {
                        $payload = [
                            'doctor_id'  => $doctorId,
                            'weekday'    => $weekdayNum,
                            'time'       => $timeHi,   // stored as "Hi" (e.g., "0911")
                            'updated_at' => now(),
                        ];

                        $exists = \App\Models\DoctorAppointmentSlots::where('doctor_id', $payload['doctor_id'])
                            ->where('weekday', $payload['weekday'])
                            ->where('time', $payload['time'])
                            ->exists();

                        if ($exists) {
                            // skip silently; optional: collect a "skipped" array if you want
                            continue;
                        }

                        $payload['created_at'] = now();
                        \App\Models\DoctorAppointmentSlots::create($payload);
                        $rowsInserted++;
                    }

                } catch (\Throwable $e) {
                    $errors[] = [
                        'row'     => $rowsProcessed,
                        'message' => $e->getMessage(),
                        'raw'     => $row,
                        'time'    => $timeCell,
                    ];
                }
            });

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->withErrors(['file' => 'Import failed: ' . $e->getMessage()]);
        }

        // keep your redirect (UI flow), or return JSON for API usage
        return redirect()
        ->back()
        ->with('success', 'Slots imported successfully!');


        // If you ever need JSON:
        // return response()->json([
        //     'status'         => 'ok',
        //     'rows_processed' => $rowsProcessed,
        //     'inserted'       => $rowsInserted,
        //     'updated'        => $rowsUpdated,
        //     'errors'         => $errors,
        // ]);
    }

    public function downloadDoctorSlotFormat()
    {
        $filePath = storage_path('app/public/uploads/doctor_slots_format.xlsx'); // absolute path

        if (!file_exists($filePath)) {
            return back()->with('error', 'File not found!');
        }

        return response()->download($filePath, 'doctor_slots_format.xlsx');
    }

    //     public function downloadBulkUploadDoctors()
    // {
    //     $isIndia = request()->getHost() === 'india.mulkmed.com';

    //     $fileName = $isIndia
    //         ? 'doctor_bulk_upload_doctors_India.xlsx'
    //         : 'doctor_bulk_upload_doctors.xlsx';

    //     $filePath = storage_path("app/public/uploads/{$fileName}");

    //     if (!file_exists($filePath)) {
    //         return back()->with('error', 'File not found!');
    //     }

    //     return response()->download($filePath, $fileName);
    // }
    
    public function downloadBulkUploadDoctors()
{
    $filePath = storage_path('app/public/uploads/doctor_bulk_upload_doctors.xlsx');

    if (!file_exists($filePath)) {
        return back()->with('error', 'File not found!');
    }

    return response()->download(
        $filePath,
        'doctor_bulk_upload_doctors.xlsx'
    );
}



    function viewBulkUploadDoctorCategories(Request $request)
    {
        return view('bulkUploadDoctorCategories');
    }

    function viewBulkUpdateDoctorCategories(Request $request)
    {
        return view('bulkUpdateDoctorCategories');
    }

     function viewBulkUpdateHospitalProcedures(Request $request)
    {
        return view('bulkUpdateHospitalProcedures');
    }

    public function bulkUploadDoctorCategories(Request $request)
    {
        // Ensure file is uploaded
        if (!$request->hasFile('customer_file')) {
            return back()->with('error', 'No file uploaded.');
        }

        $file = $request->file('customer_file');

        // Save temporarily or load directly
        $uploadedXlsxPath = $file->getRealPath();

        // Load using PhpSpreadsheet
        $spreadsheet = IOFactory::load($uploadedXlsxPath);
        $sheet = $spreadsheet->getActiveSheet();

        // Get all rows as array (for text data)
        $rows = $sheet->toArray(null, true, true, true);

        $imageMap = [];
        
        // Extract images
        foreach ($sheet->getDrawingCollection() as $drawing) {
            if ($drawing instanceof Drawing) {
                $coordinates = $drawing->getCoordinates();
                $extension = $drawing->getExtension() ?? 'png';

                // Get image binary
                if ($drawing->getPath()) {
                    $imageContents = file_get_contents($drawing->getPath());
                } elseif ($drawing->getImageResource()) {
                    ob_start();
                    imagepng($drawing->getImageResource());
                    $imageContents = ob_get_clean();
                } else {
                    continue;
                }

                // Create a temporary file from image data
                $tmpPath = tempnam(sys_get_temp_dir(), 'excel_img_');
                file_put_contents($tmpPath, $imageContents);

                // Wrap it as UploadedFile to pass to your global function
                $tmpFile = new \Illuminate\Http\UploadedFile(
                    $tmpPath,
                    'excel_image.' . $extension,
                    null,
                    null,
                    true
                );

                // Save image using your custom global helper
                $savedPath = GlobalFunction::saveFileAndGivePath($tmpFile);

                $imageMap[$coordinates] = $savedPath;
            }
        }

        // Now loop through each row and attach image by cell coordinate
        foreach ($rows as $rowIndex => $row) {
            // skip header if needed
            if (empty($row['B']) || $rowIndex === 1) continue;

            $title = $row['B'] ?? null;
            $info = $row['C'] ?? null;
            $keywords = $row['D'] ?? null;
            
            if($title != null)
            {
                $ta = new GoogleTranslate('ar');
                $ar_title = $ta->translate($title);   
                $tf = new GoogleTranslate('fr');
                $fr_title = $tf->translate($title);
                $th = new GoogleTranslate('hi');
                $hi_title = $th->translate($title);
                $tu = new GoogleTranslate('ur');
                $ur_title = $tu->translate($title);
            }else{
                $ar_title = null;
                $fr_title = null;
                $hi_title = null;
                $ur_title = null;
            }

            if($info != null)
            {
                $ta = new GoogleTranslate('ar');
                $ar_info = $ta->translate($info);   
                $tf = new GoogleTranslate('fr');
                $fr_info = $tf->translate($info);
                $th = new GoogleTranslate('hi');
                $hi_info = $th->translate($info);
                $tu = new GoogleTranslate('ur');
                $ur_info = $tu->translate($info);
            }else{
                $ar_info = null;
                $fr_info = null;
                $hi_info = null;
                $ur_info = null;
            }

            // find image in that row (if in column C)
            $imageCoordinate = 'A' . $rowIndex;
            $imageName = $imageMap[$imageCoordinate] ?? null;
            
            $hospital = DoctorCategories::create([
                    'title' => $title,
                    'image' => $imageName,
                    'info' => $info,
                    'keywords' => $keywords,
                    'ar_title' => $ar_title,
                    'fr_title' => $fr_title,
                    'hi_title' => $hi_title,
                    'ur_title' => $ur_title,
                    'ar_info' => $ar_info,
                    'fr_info' => $fr_info,
                    'hi_info' => $hi_info,
                    'ur_info' => $ur_info
                ]);
        }

        return back()->with('success', 'Doctors By Speciality imported successfully!');
    }

    public function bulkUpdateDoctorCategories(Request $request)
    {
        // Ensure file is uploaded
        if (!$request->hasFile('customer_file')) {
            return back()->with('error', 'No file uploaded.');
        }

        $file = $request->file('customer_file');

        // Save temporarily or load directly
        $uploadedXlsxPath = $file->getRealPath();

        // Load using PhpSpreadsheet
        $spreadsheet = IOFactory::load($uploadedXlsxPath);
        $sheet = $spreadsheet->getActiveSheet();

        // Get all rows as array (for text data)
        $rows = $sheet->toArray(null, true, true, true);


     // $rows is your parsed spreadsheet rows (1-based index assumed)
        $map = []; // id => category_id

        foreach ($rows as $rowIndex => $row) {
            // skip header and empty rows
            if ($rowIndex === 1) continue;
            if (empty($row['A'])) continue;

            $doctorId = intval(trim($row['A']));
            $categoryId = !isset($row['B']) || $row['B'] === '' ? null : intval(trim($row['B']));

            // ignore invalid id
            if ($doctorId <= 0) continue;

            $map[$doctorId] = $categoryId;
        }

        if (empty($map)) {
            // nothing to do
            return;
        }

        // get only existing doctor ids to avoid accidental inserts
        $ids = array_keys($map);
        $existingIds = Doctors::whereIn('id', $ids)
            ->pluck('id')
            ->map(fn($v) => (int)$v)
            ->toArray();

        if (empty($existingIds)) return;

        // prepare payload for upsert: only include existing rows
        $payload = [];
        foreach ($existingIds as $id) {
            $payload[] = [
                'id' => $id,
                'category_id' => $map[$id], // nullable allowed
                'updated_at' => now(),
            ];
        }

        // chunk the upserts to avoid huge queries
        $chunkSize = 500;
        DB::transaction(function() use ($payload, $chunkSize) {
            foreach (array_chunk($payload, $chunkSize) as $chunk) {
                // upsert will update category_id for matching id.
                // uniqueBy ['id'], update columns ['category_id','updated_at']
                Doctors::upsert($chunk, ['id'], ['category_id', 'updated_at']);
            }
        });

        return back()->with('success', 'Doctors By Speciality updated successfully!');
    }

    public function bulkUpdateHospitalProcedures(Request $request)
    {
        // Ensure file is uploaded
        if (!$request->hasFile('customer_file')) {
            return back()->with('error', 'No file uploaded.');
        }

        $file = $request->file('customer_file');

        // Save temporarily or load directly
        $uploadedXlsxPath = $file->getRealPath();

        // Load using PhpSpreadsheet
        $spreadsheet = IOFactory::load($uploadedXlsxPath);
        $sheet = $spreadsheet->getActiveSheet();

        // Get all rows as array (for text data)
        $rows = $sheet->toArray(null, true, true, true);


     // $rows is your parsed spreadsheet rows (1-based index assumed)
        $map = []; // id => category_id

        foreach ($rows as $rowIndex => $row) {
            // skip header and empty rows
            if ($rowIndex === 1) continue;
            if (empty($row['A'])) continue;

           $hospitalId   = intval(trim($row['A']));
            $hospitalId = intval(trim($row['A']));
            $rawProcedures = $row['B'] ?? null;

            // If no procedures provided, skip
            if ($rawProcedures === null || trim($rawProcedures) === '') {
                return;
            }

            // 1) Convert "1, 2,3" → ['1','2','3']
            $newProcedureIds = array_filter(array_map(function ($val) {
                return trim($val);
            }, explode(',', $rawProcedures))); // split by comma

            // 2) Fetch hospital
            $hospital = Hospitals::find($hospitalId);
            if (!$hospital) {
                return; // or handle error
            }

            // 3) Decode existing JSON ["4","6","7","8"] → ['4','6','7','8']
            $existingIds = $hospital->procedure_ids
                ? json_decode($hospital->procedure_ids, true)
                : [];

            // Ensure it's an array
            if (!is_array($existingIds)) {
                $existingIds = [];
            }

            // 4) Merge existing + new, cast to string to keep same format
            $merged = array_map('strval', array_merge($existingIds, $newProcedureIds));

            // 5) Remove duplicates + reindex
            $merged = array_values(array_unique($merged));

            // 6) Save back as JSON
            $hospital->procedure_ids = json_encode($merged);
            $hospital->save();


           
        }

        return back()->with('success', 'Hospitals imported successfully!');
    }

    public function downloadDoctorUpdateCategoriesFormat()
    {
        $filePath = storage_path('app/public/uploads/speciality_bulk_update_format.xlsx'); // absolute path

        if (!file_exists($filePath)) {
            return back()->with('error', 'File not found!');
        }

        return response()->download($filePath, 'speciality_bulk_update_format.xlsx');
    }

     public function downloadHospitalUpdateProceduresFormat()
    {
        $filePath = storage_path('app/public/uploads/hospital_bulk_update_procedures.xlsx'); // absolute path

        if (!file_exists($filePath)) {
            return back()->with('error', 'File not found!');
        }

        return response()->download($filePath, 'hospital_bulk_update_procedures.xlsx');
    }

    public function downloadDoctorCategoriesFormat()
    {
        $filePath = storage_path('app/public/uploads/speciality_bulk_upload_format.xlsx'); // absolute path

        if (!file_exists($filePath)) {
            return back()->with('error', 'File not found!');
        }

        return response()->download($filePath, 'speciality_bulk_upload_format.xlsx');
    }

    function toHi($val, $tz = 'Asia/Kolkata'): string
    {
        // 1) If it's already DateTime/Carbon
        if ($val instanceof \DateTimeInterface) {
            return Carbon\Carbon::instance($val)->setTimezone($tz)->format('Hi');
        }

        // 2) Array like ['date' => '...']
        if (is_array($val) && isset($val['date'])) {
            return Carbon\Carbon::parse($val['date'], $tz)->setTimezone($tz)->format('Hi');
        }

        // 3) JSON string with { "date": "..." }
        if (is_string($val) && str_starts_with(trim($val), '{')) {
            $decoded = json_decode($val, true);
            if (is_array($decoded) && isset($decoded['date'])) {
                return Carbon\Carbon::parse($decoded['date'], $tz)->setTimezone($tz)->format('Hi');
            }
        }

        // 4) Plain time strings like "19:00", "09:00 AM"
        if (is_string($val)) {
            try { return Carbon\Carbon::parse($val, $tz)->setTimezone($tz)->format('Hi'); } catch (\Throwable $e) {}
        }

        // 5) Excel serial / float (e.g., 0.791666... => 19:00)
        if (is_numeric($val)) {
            $seconds = (int) round(fmod((float)$val, 1.0) * 86400); // keep only time-of-day
            return gmdate('Hi', $seconds);
        }

        throw new \Exception('Invalid time format');
    }

    function changeExpertise(){
        return DoctorExpertise::all();
    }

    
    function updateDoctorPassword(Request $request)
    {
        $request->validate([
            'id' => 'required',
            'password' => 'required',
            'confirm_password' => 'required|same:password',
        ]);

        $doctor = Doctors::find($request->id);
        if ($doctor) {
            $doctor->password = $request->password;
            $doctor->save();
            return GlobalFunction::sendSimpleResponse(true, 'Password updated successfully');
        }

        return GlobalFunction::sendSimpleResponse(false, 'Doctor not found');
    }
}

