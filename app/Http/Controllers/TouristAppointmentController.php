<?php

namespace App\Http\Controllers;

use App\Models\TouristAppointments;
use App\Models\GlobalSettings;
use App\Models\GlobalFunction;
use App\Models\Constants;
use App\Models\AgencySubscriptionPlans;
use App\Models\RiderAllocation;
use App\Models\AgencyRidersUsage;
use App\Models\TransactionHistory;
use Illuminate\Support\Facades\Validator;
use App\Helpers\Crypto;
use Carbon\Carbon;
use Illuminate\Http\Request;

class TouristAppointmentController extends Controller
{
    private const SORT_COLUMNS = [
        0 => 'appointment_number',
        1 => 'tourist_id',
        2 => 'doctor_id',
        3 => 'status',
        4 => 'date',
        5 => 'created_at',
    ];

    function touristAppointments(Request $request)
    {
        return view('touristAppointments');
    }

    private function getTouristAppointmentSortParams(Request $request): array
    {
        $columnIndex = (int) $request->input('order.0.column', 4);
        if (!in_array($columnIndex, [4, 5], true)) {
            $columnIndex = 4;
        }

        $dir = strtolower((string) $request->input('order.0.dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        return [$columnIndex, $dir];
    }

    private function applyTouristAppointmentSort($query, int $columnIndex, string $dir)
    {
        $order = self::SORT_COLUMNS[$columnIndex] ?? 'date';
        $direction = $dir === 'asc' ? 'asc' : 'desc';

        if ($order === 'date') {
            return $query->orderByRaw(
                "TIMESTAMP(date, SEC_TO_TIME(TIME_TO_SEC(STR_TO_DATE(time, '%H%i')))) " . ($direction === 'asc' ? 'ASC' : 'DESC')
            );
        }

        return $query->orderBy($order, $direction);
    }

    private function applyTouristAppointmentSearch($query, $search)
    {
        if (empty($search)) {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $q->where('appointment_number', 'LIKE', "%{$search}%")
              ->orWhereHas('tourist', function ($sq) use ($search) {
                  $sq->where('first_name', 'LIKE', "%{$search}%")
                     ->orWhere('last_name', 'LIKE', "%{$search}%")
                     ->orWhere(\DB::raw("CONCAT(first_name, ' ', last_name)"), 'LIKE', "%{$search}%");
              });
        });
    }

    private function formatAppointmentDateTime(TouristAppointments $item): string
    {
        $formattedDate = '-';
        if (!empty($item->date)) {
            try {
                $formattedDate = Carbon::parse($item->date)->format('d M, Y');
            } catch (\Exception $e) {
                $formattedDate = $item->date;
            }
        }

        $formattedTime = GlobalFunction::formateTimeString($item->time) ?: '-';

        return $formattedDate . '<br>' . $formattedTime;
    }

    function fetchAllTouristAppointmentsList(Request $request)
    {
        $totalData =  TouristAppointments::count();
        $settings = GlobalSettings::first();

        $limit = $request->input('length');
        $start = $request->input('start');
        [$columnIndex, $dir] = $this->getTouristAppointmentSortParams($request);

        $search = $request->input('search.value');
        $query = TouristAppointments::with('tourist', 'doctor');

        if (!empty($search)) {
            $query = $this->applyTouristAppointmentSearch($query, $search);
            $totalFiltered = $this->applyTouristAppointmentSearch(TouristAppointments::query(), $search)->count();
        } else {
            $totalFiltered = $totalData;
        }

        $result = $this->applyTouristAppointmentSort($query, $columnIndex, $dir)
            ->offset($start)
            ->limit($limit)
            ->get();
        $data = array();
        foreach ($result as $item) {

            $doctor = "";
            if ($item->doctor != null) {
                $doctor = '<a href="' . route('viewDoctorProfile', $item->doctor->id) . '"><span class="badge bg-primary text-white">' . $item->doctor->name . '</span></a>';
            }
            $tourist = "";
            if ($item->tourist != null) {
                $tourist = '<span class="badge bg-primary text-white">' . $item->tourist->first_name .' '. $item->tourist->last_name . '</span>';
            }

            $view = '<a href="' . route('viewTouristAppointment', $item->id) . '" class="mr-2 btn btn-info text-white " rel=' . $item->id . ' >' . __("View") . '</a>';

            $status = GlobalFunction::returnAppointmentStatus($item->status);

            $action = $view;

            $dateTime = $this->formatAppointmentDateTime($item);
            $payableAmount = $settings->currency . $item->payable_amount;

            $data[] = array(
                $item->appointment_number,
                $tourist,
                $doctor,
                $status,
                $dateTime,
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

    function fetchPendingTouristAppointmentsList(Request $request)
    {
        $totalData =  TouristAppointments::where('status', Constants::orderPlacedPending)->count();
        $settings = GlobalSettings::first();

        $limit = $request->input('length');
        $start = $request->input('start');
        [$columnIndex, $dir] = $this->getTouristAppointmentSortParams($request);

        $search = $request->input('search.value');
        $baseQuery = TouristAppointments::where('status', Constants::orderPlacedPending);
        $query = TouristAppointments::with('tourist', 'doctor')->where('status', Constants::orderPlacedPending);

        if (!empty($search)) {
            $query = $this->applyTouristAppointmentSearch($query, $search);
            $totalFiltered = $this->applyTouristAppointmentSearch($baseQuery, $search)->count();
        } else {
            $totalFiltered = $totalData;
        }

        $result = $this->applyTouristAppointmentSort($query, $columnIndex, $dir)
            ->offset($start)
            ->limit($limit)
            ->get();
        $data = array();
        foreach ($result as $item) {


            $doctor = "";
            if ($item->doctor != null) {
                $doctor = '<a href="' . route('viewDoctorProfile', $item->doctor->id) . '"><span class="badge bg-primary text-white">' . $item->doctor->name . '</span></a>';
            }
            $tourist = "";
            if ($item->tourist != null) {
                $tourist = '<span class="badge bg-primary text-white">' . $item->tourist->first_name .' '. $item->tourist->last_name . '</span>';
            }

            $view = '<a href="' . route('viewTouristAppointment', $item->id) . '" class="mr-2 btn btn-info text-white " rel=' . $item->id . ' >' . __("View") . '</a>';

            $status = GlobalFunction::returnAppointmentStatus($item->status);

            $action = $view;

            $dateTime = $this->formatAppointmentDateTime($item);
            $payableAmount = $settings->currency . $item->payable_amount;

            $data[] = array(
                $item->appointment_number,
                $tourist,
                $doctor,
                $status,
                $dateTime,
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

    function fetchAcceptedTouristAppointmentsList(Request $request)
    {
        $totalData =  TouristAppointments::where('status', Constants::orderAccepted)->count();
        $settings = GlobalSettings::first();

        $limit = $request->input('length');
        $start = $request->input('start');
        [$columnIndex, $dir] = $this->getTouristAppointmentSortParams($request);

        $search = $request->input('search.value');
        $baseQuery = TouristAppointments::where('status', Constants::orderAccepted);
        $query = TouristAppointments::with('tourist', 'doctor')->where('status', Constants::orderAccepted);

        if (!empty($search)) {
            $query = $this->applyTouristAppointmentSearch($query, $search);
            $totalFiltered = $this->applyTouristAppointmentSearch($baseQuery, $search)->count();
        } else {
            $totalFiltered = $totalData;
        }

        $result = $this->applyTouristAppointmentSort($query, $columnIndex, $dir)
            ->offset($start)
            ->limit($limit)
            ->get();
        $data = array();
        foreach ($result as $item) {


            $doctor = "";
            if ($item->doctor != null) {
                $doctor = '<a href="' . route('viewDoctorProfile', $item->doctor->id) . '"><span class="badge bg-primary text-white">' . $item->doctor->name . '</span></a>';
            }
            $tourist = "";
            if ($item->tourist != null) {
                $tourist = '<span class="badge bg-primary text-white">' . $item->tourist->first_name .' '. $item->tourist->last_name . '</span>';
            }

            $view = '<a href="' . route('viewTouristAppointment', $item->id) . '" class="mr-2 btn btn-info text-white " rel=' . $item->id . ' >' . __("View") . '</a>';

            $status = GlobalFunction::returnAppointmentStatus($item->status);

            $action = $view;

            $dateTime = $this->formatAppointmentDateTime($item);
            $payableAmount = $settings->currency . $item->payable_amount;

            $data[] = array(
                $item->appointment_number,
                $tourist,
                $doctor,
                $status,
                $dateTime,
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

    function fetchCompletedTouristAppointmentsList(Request $request)
    {
        $totalData =  TouristAppointments::where('status', Constants::orderCompleted)->count();
        $settings = GlobalSettings::first();

        $limit = $request->input('length');
        $start = $request->input('start');
        [$columnIndex, $dir] = $this->getTouristAppointmentSortParams($request);

        $search = $request->input('search.value');
        $baseQuery = TouristAppointments::where('status', Constants::orderCompleted);
        $query = TouristAppointments::with('tourist', 'doctor')->where('status', Constants::orderCompleted);

        if (!empty($search)) {
            $query = $this->applyTouristAppointmentSearch($query, $search);
            $totalFiltered = $this->applyTouristAppointmentSearch($baseQuery, $search)->count();
        } else {
            $totalFiltered = $totalData;
        }

        $result = $this->applyTouristAppointmentSort($query, $columnIndex, $dir)
            ->offset($start)
            ->limit($limit)
            ->get();
        $data = array();
        foreach ($result as $item) {


            $doctor = "";
            if ($item->doctor != null) {
                $doctor = '<a href="' . route('viewDoctorProfile', $item->doctor->id) . '"><span class="badge bg-primary text-white">' . $item->doctor->name . '</span></a>';
            }
            $tourist = "";
            if ($item->tourist != null) {
                $tourist = '<span class="badge bg-primary text-white">' . $item->tourist->first_name .' '. $item->tourist->last_name . '</span>';
            }

            $view = '<a href="' . route('viewTouristAppointment', $item->id) . '" class="mr-2 btn btn-info text-white " rel=' . $item->id . ' >' . __("View") . '</a>';

            $status = GlobalFunction::returnAppointmentStatus($item->status);

            $action = $view;

            $dateTime = $this->formatAppointmentDateTime($item);
            $payableAmount = $settings->currency . $item->payable_amount;

            $data[] = array(
                $item->appointment_number,
                $tourist,
                $doctor,
                $status,
                $dateTime,
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

    function fetchCancelledTouristAppointmentsList(Request $request)
    {
        $totalData =  TouristAppointments::where('status', Constants::orderCancelled)->count();
        $settings = GlobalSettings::first();

        $limit = $request->input('length');
        $start = $request->input('start');
        [$columnIndex, $dir] = $this->getTouristAppointmentSortParams($request);

        $search = $request->input('search.value');
        $baseQuery = TouristAppointments::where('status', Constants::orderCancelled);
        $query = TouristAppointments::with('tourist', 'doctor')->where('status', Constants::orderCancelled);

        if (!empty($search)) {
            $query = $this->applyTouristAppointmentSearch($query, $search);
            $totalFiltered = $this->applyTouristAppointmentSearch($baseQuery, $search)->count();
        } else {
            $totalFiltered = $totalData;
        }

        $result = $this->applyTouristAppointmentSort($query, $columnIndex, $dir)
            ->offset($start)
            ->limit($limit)
            ->get();
        $data = array();
        foreach ($result as $item) {


            $doctor = "";
            if ($item->doctor != null) {
                $doctor = '<a href="' . route('viewDoctorProfile', $item->doctor->id) . '"><span class="badge bg-primary text-white">' . $item->doctor->name . '</span></a>';
            }
            $tourist = "";
            if ($item->tourist != null) {
                $tourist = '<span class="badge bg-primary text-white">' . $item->tourist->first_name .' '. $item->tourist->last_name . '</span>';
            }

            $view = '<a href="' . route('viewTouristAppointment', $item->id) . '" class="mr-2 btn btn-info text-white " rel=' . $item->id . ' >' . __("View") . '</a>';

            $status = GlobalFunction::returnAppointmentStatus($item->status);

            $action = $view;

            $dateTime = $this->formatAppointmentDateTime($item);
            $payableAmount = $settings->currency . $item->payable_amount;

            $data[] = array(
                $item->appointment_number,
                $tourist,
                $doctor,
                $status,
                $dateTime,
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

    function fetchMissedTouristAppointmentsList(Request $request)
    {
        $totalData =  TouristAppointments::where('status', Constants::orderMissed)->count();
        $settings = GlobalSettings::first();

        $limit = $request->input('length');
        $start = $request->input('start');
        [$columnIndex, $dir] = $this->getTouristAppointmentSortParams($request);

        $search = $request->input('search.value');
        $baseQuery = TouristAppointments::where('status', Constants::orderMissed);
        $query = TouristAppointments::with('tourist', 'doctor')->where('status', Constants::orderMissed);

        if (!empty($search)) {
            $query = $this->applyTouristAppointmentSearch($query, $search);
            $totalFiltered = $this->applyTouristAppointmentSearch($baseQuery, $search)->count();
        } else {
            $totalFiltered = $totalData;
        }

        $result = $this->applyTouristAppointmentSort($query, $columnIndex, $dir)
            ->offset($start)
            ->limit($limit)
            ->get();
        $data = array();
        foreach ($result as $item) {


            $doctor = "";
            if ($item->doctor != null) {
                $doctor = '<a href="' . route('viewDoctorProfile', $item->doctor->id) . '"><span class="badge bg-primary text-white">' . $item->doctor->name . '</span></a>';
            }
            $tourist = "";
            if ($item->tourist != null) {
                $tourist = '<span class="badge bg-primary text-white">' . $item->tourist->first_name .' '. $item->tourist->last_name . '</span>';
            }

            $view = '<a href="' . route('viewTouristAppointment', $item->id) . '" class="mr-2 btn btn-info text-white " rel=' . $item->id . ' >' . __("View") . '</a>';

            $status = GlobalFunction::returnAppointmentStatus($item->status);

            $action = $view;

            $dateTime = $this->formatAppointmentDateTime($item);
            $payableAmount = $settings->currency . $item->payable_amount;

            $data[] = array(
                $item->appointment_number,
                $tourist,
                $doctor,
                $status,
                $dateTime,
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

    function fetchDeclinedTouristAppointmentsList(Request $request)
    {
        $totalData =  TouristAppointments::where('status', Constants::orderDeclined)->count();
        $settings = GlobalSettings::first();

        $limit = $request->input('length');
        $start = $request->input('start');
        [$columnIndex, $dir] = $this->getTouristAppointmentSortParams($request);

        $search = $request->input('search.value');
        $baseQuery = TouristAppointments::where('status', Constants::orderDeclined);
        $query = TouristAppointments::with('tourist', 'doctor')->where('status', Constants::orderDeclined);

        if (!empty($search)) {
            $query = $this->applyTouristAppointmentSearch($query, $search);
            $totalFiltered = $this->applyTouristAppointmentSearch($baseQuery, $search)->count();
        } else {
            $totalFiltered = $totalData;
        }

        $result = $this->applyTouristAppointmentSort($query, $columnIndex, $dir)
            ->offset($start)
            ->limit($limit)
            ->get();
        $data = array();
        foreach ($result as $item) {


            $doctor = "";
            if ($item->doctor != null) {
                $doctor = '<a href="' . route('viewDoctorProfile', $item->doctor->id) . '"><span class="badge bg-primary text-white">' . $item->doctor->name . '</span></a>';
            }
            $tourist = "";
            if ($item->tourist != null) {
                $tourist = '<span class="badge bg-primary text-white">' . $item->tourist->first_name .' '. $item->tourist->last_name . '</span>';
            }

            $view = '<a href="' . route('viewTouristAppointment', $item->id) . '" class="mr-2 btn btn-info text-white " rel=' . $item->id . ' >' . __("View") . '</a>';

            $status = GlobalFunction::returnAppointmentStatus($item->status);

            $action = $view;

            $dateTime = $this->formatAppointmentDateTime($item);
            $payableAmount = $settings->currency . $item->payable_amount;

            $data[] = array(
                $item->appointment_number,
                $tourist,
                $doctor,
                $status,
                $dateTime,
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

    function viewTouristAppointment($id)
    {
        $item = TouristAppointments::where('id', $id)
            ->with(['tourist', 'doctor', 'documents', 'prescription', 'rating'])
            ->first();

        // return $item;

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
        
        $prescription = null;
        if ($item->prescription != null) {
            $prescription = json_decode($item->prescription->medicine, true);
        }

        return view('viewTouristAppointment', [
            'appointment' => $item,
            'ratingBar' => $ratingBar,
            'settings' => $settings,
            'prescription' => $prescription,
        ]);
    }
}
