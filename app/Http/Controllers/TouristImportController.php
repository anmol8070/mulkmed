<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\TouristImport;
use App\Models\ImportLog;
use App\Models\TouristList;
use Carbon\Carbon;
use App\Models\Agencies;
use App\Models\AgencyType;
use Illuminate\Support\Collection;

class TouristImportController extends Controller
{
    public function index()
    {
        return view('tourist.import');
    }

    public function store(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv'
        ]);

         // $import = new TouristImport;

         // 🔹 Create import log FIRST
         $importLog = ImportLog::create([
            'sheet_name'  => $request->file('file')->getClientOriginalName(),
            'imported_by' => session('agency_id'), // ya auth()->id()
            'imported_at' => now(),
        ]);

        // 🔹 Pass import_log_id to import class
        $import = new TouristImport($importLog->id);

        Excel::import($import, $request->file('file'));

        // 🔹 Check if there are any import errors
        if ($errors = $import->getErrors()) {
            return response()->json([
                'status' => false,
                'error'   => implode("\n", $errors)
            ]);
            return back()->withErrors(['error' => implode("\n", $errors)]);
        }

        return response()->json([
                'status' => true,
                'message'   => "Excel import successful"
            ]);
        return back()->with('success', 'Excel import successful');
    }

    public function logsByAgent(Request $request)
    {   
        $agent_id = session('agency_id');
        $query = ImportLog::select('import_logs.*')
        ->join('tourist_list','import_logs.id','tourist_list.import_log_id')
        ->where('imported_by', $agent_id);

        // 🔍 Search by file name
        if ($request->filled('search')) {
            $query->where('sheet_name', 'like', '%' . $request->search . '%');
        }

        // 📅 Filter by date
        if ($request->filled('date')) {
            $query->whereDate('imported_at', $request->date);
        }

        // 📄 Pagination (10 records per page)
        $logs = $query->groupBy('import_logs.id')->orderBy('imported_at', 'desc')
            ->paginate(10);

        // 🎯 Format data
        $logs->getCollection()->transform(function ($log) {
            return [
                'log_id'    => $log->id,
                'date'      => Carbon::parse($log->imported_at)->format('dS M Y'),
                'time'      => Carbon::parse($log->imported_at)->format('h:i A'),
                'file_name' => $log->sheet_name,
                'view_url'  => url('/partner/import-log/'.$log->id.'/tourists')
            ];
        });

        return response()->json([
            'status' => true,
            'data'   => $logs
        ]);
    }

    


    public function touristsByLog($log_id)
    {
        $agency_id   = session('agency_id');
        $partnerInfo = Agencies::find($agency_id);

        if (!$partnerInfo) {
            return response()->json([
                'status'  => false,
                'message' => 'Agency not found'
            ], 404);
        }

        $tourists = TouristList::where('import_log_id', $log_id)
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($tourist) use ($partnerInfo) {

                /* ---------------- Expiry Date ---------------- */
                $expiry_date = null;

                if ($partnerInfo->agency_type == 2 && $tourist->check_out_time) {
                    $expiry_date = Carbon::parse($tourist->check_out_time);
                }

                if ($partnerInfo->agency_type == 1 && $tourist->fly_out) {
                    $expiry_date = Carbon::parse($tourist->fly_out);
                }

                /* ---------------- Riders Calculation ---------------- */
                $allocated_riders = ($tourist->inbound_riders ?? 0) + ($tourist->outbound_riders ?? 0);
                $assign_ride      = $allocated_riders * 3;

                $use_ride = 
                    ($tourist->number_of_consultation ?? 0) +
                    ($tourist->number_of_ai_health_check ?? 0) +
                    ($tourist->number_of_midas ?? 0);

                /* ---------------- Rider Status ---------------- */
                $status = 'Not Used';

                if ($tourist->status == 0) {
                    $status = 'Not Used';
                }
                elseif ($expiry_date && $expiry_date->lt(now())) {
                    $status = 'Used';
                }
                elseif ($use_ride == 0) {
                    $status = 'Not Used';
                }
                elseif ($use_ride < $assign_ride) {
                    $status = 'In Use';
                }
                elseif ($use_ride >= $assign_ride) {
                    $status = 'Used';
                }

                return [
                    'customer_name'    => trim($tourist->first_name . ' ' . $tourist->last_name),
                    'booking_id'       => $tourist->booking_id,
                    'mobile_number' => trim(($tourist->country_code ?? '') . ' ' . ($tourist->contact_number ?? '')),
                    'allocated_riders' => $allocated_riders,
                    'rider_status'     => $status,
                    'service_type'     => $tourist->service_type,
                    'check_in_time'    => $tourist->check_in_time
                        ? Carbon::parse($tourist->check_in_time)->format('jS M Y')
                        : null,
                    'check_out_time'   => $tourist->check_out_time
                        ? Carbon::parse($tourist->check_out_time)->format('jS M Y')
                        : null,
                    'fly_in_time'    => $tourist->fly_in
                        ? Carbon::parse($tourist->fly_in)->format('jS M Y')
                        : null,
                    'fly_out_time'   => $tourist->fly_out
                        ? Carbon::parse($tourist->fly_out)->format('jS M Y')
                        : null, 
                    'start_date'     => Carbon::parse($tourist->start_date)->format('jS M Y'),            
                    // 'validity_days'     => trim(Carbon::parse($tourist->start_date)->format('jS M Y') . ' ' . $tourist->visa_expiry_days ),
                    'validity_days' => (function() use ($tourist) {
                        $days = (int) $tourist->visa_expiry_days;
                        $addDays = $days == 30 ? 90 : ($days == 60 ? 120 : $days);
                        return Carbon::parse($tourist->start_date)->addDays($addDays)->format('jS M Y') . ' (' . $days . ' Days)';
                    })(),

                ];
            });

        return response()->json([
            'status' => true,
            'data'   => $tourists
        ]);
    }

    
    public function touristsAdminByLog($log_id)
    {
        $agency_id   = session('agency_id');
        // $partnerInfo = Agencies::find($agency_id);

        // if (!$partnerInfo) {
        //     return response()->json([
        //         'status'  => false,
        //         'message' => 'Agency not found'
        //     ], 404);
        // }

         $partnerInfo = '';

        $tourists = TouristList::where('import_log_id', $log_id)
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($tourist) use ($partnerInfo) {

                 $partnerInfo = Agencies::find($tourist->agent_id);

                /* ---------------- Expiry Date ---------------- */
                $expiry_date = null;

                if ($partnerInfo->agency_type == 2 && $tourist->check_out_time) {
                    $expiry_date = Carbon::parse($tourist->check_out_time);
                }

                if ($partnerInfo->agency_type == 1 && $tourist->fly_out) {
                    $expiry_date = Carbon::parse($tourist->fly_out);
                }

                /* ---------------- Riders Calculation ---------------- */
                $allocated_riders = ($tourist->inbound_riders ?? 0) + ($tourist->outbound_riders ?? 0);
                $assign_ride      = $allocated_riders * 3;

                $use_ride = 
                    ($tourist->number_of_consultation ?? 0) +
                    ($tourist->number_of_ai_health_check ?? 0) +
                    ($tourist->number_of_midas ?? 0);

                /* ---------------- Rider Status ---------------- */
                $status = 'Not Used';

                if ($tourist->status == 0) {
                    $status = 'Not Used';
                }
                elseif ($expiry_date && $expiry_date->lt(now())) {
                    $status = 'Used';
                }
                elseif ($use_ride == 0) {
                    $status = 'Not Used';
                }
                elseif ($use_ride < $assign_ride) {
                    $status = 'In Use';
                }
                elseif ($use_ride >= $assign_ride) {
                    $status = 'Used';
                }

                return [
                    'customer_name'    => trim($tourist->first_name . ' ' . $tourist->last_name),
                    'booking_id'       => $tourist->booking_id,
                    'mobile_number' => trim(($tourist->country_code ?? '') . ' ' . ($tourist->contact_number ?? '')),
                    'allocated_riders' => $allocated_riders,
                    'rider_status'     => $status,
                    'service_type'     => $tourist->service_type,
                    'check_in_time'    => $tourist->check_in_time
                        ? Carbon::parse($tourist->check_in_time)->format('jS M Y')
                        : null,
                    'check_out_time'   => $tourist->check_out_time
                        ? Carbon::parse($tourist->check_out_time)->format('jS M Y')
                        : null,
                    'fly_in_time'    => $tourist->fly_in
                        ? Carbon::parse($tourist->fly_in)->format('jS M Y')
                        : null,
                    'fly_out_time'   => $tourist->fly_out
                        ? Carbon::parse($tourist->fly_out)->format('jS M Y')
                        : null, 
                    'start_date'     => Carbon::parse($tourist->start_date)->format('jS M Y'),            
                    // 'validity_days'     => trim(Carbon::parse($tourist->start_date)->format('jS M Y') . ' ' . $tourist->visa_expiry_days ),
                    'validity_days' => (function() use ($tourist) {
                        $days = (int) $tourist->visa_expiry_days;
                        $addDays = $days == 30 ? 90 : ($days == 60 ? 120 : $days);
                        return Carbon::parse($tourist->start_date)->addDays($addDays)->format('jS M Y') . ' (' . $days . ' Days)';
                    })(),

                ];
            });

        return response()->json([
            'status' => true,
            'data'   => $tourists
        ]);
    }


    public function touristsList_old(Request $request)
    {
       return $agency_id   = session('agency_id');
        $partnerInfo = Agencies::find($agency_id);

        if (!$partnerInfo) {
            return response()->json([
                'status'  => false,
                'message' => 'Agency not found'
            ], 404);
        }

        $search = $request->search; // tourist name search

        $tourists = TouristList::where('agent_id', $agency_id)
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('first_name', 'like', "%$search%")
                        ->orWhere('last_name', 'like', "%$search%")
                        ->orWhereRaw("CONCAT(first_name,' ',last_name) LIKE ?", ["%$search%"]);
                });
            })
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($tourist) use ($partnerInfo) {

                /* -------- Expiry Date -------- */
                $expiry_date = null;

                if ($partnerInfo->agency_type == 2 && $tourist->check_out_time) {
                    $expiry_date = Carbon::parse($tourist->check_out_time);
                }

                if ($partnerInfo->agency_type == 1 && $tourist->fly_out) {
                    $expiry_date = Carbon::parse($tourist->fly_out);
                }

                /* -------- Riders Calculation -------- */
                $allocated_riders = ($tourist->inbound_riders ?? 0) + ($tourist->outbound_riders ?? 0);
                $assign_ride      = $allocated_riders * 3;

                $use_ride =
                    ($tourist->number_of_consultation ?? 0) +
                    ($tourist->number_of_ai_health_check ?? 0) +
                    ($tourist->number_of_midas ?? 0);

                /* -------- Rider Status -------- */
                if ($tourist->status == 0) {
                    $status = 'Not Used';
                } elseif ($expiry_date && $expiry_date->lt(now())) {
                    $status = 'Used';
                } elseif ($use_ride == 0) {
                    $status = 'Not Used';
                } elseif ($use_ride < $assign_ride) {
                    $status = 'In Use';
                } else {
                    $status = 'Used';
                }

                return [
                    'customer_name'    => trim($tourist->first_name . ' ' . $tourist->last_name),
                    'booking_id'       => $tourist->booking_id,
                    'allocated_riders' => $allocated_riders,
                    'rider_status'     => $status,
                    'service_type'     => $tourist->service_type,
                    'check_in_time'    => $tourist->check_in_time
                        ? Carbon::parse($tourist->check_in_time)->format('jS M Y \a\t h:i A')
                        : null,
                    'check_out_time'   => $tourist->check_out_time
                        ? Carbon::parse($tourist->check_out_time)->format('jS M Y \a\t h:i A')
                        : null,
                    'fly_in'           => $tourist->fly_in
                        ? Carbon::parse($tourist->fly_in)->format('jS M Y \a\t h:i A')
                        : null,
                    'fly_out'          => $tourist->fly_out
                        ? Carbon::parse($tourist->fly_out)->format('jS M Y \a\t h:i A')
                        : null,
                ];
            });

        return response()->json([
            'status' => true,
            'data'   => $tourists
        ]);
    }

    public function touristsList(Request $request)
    {
        $agency_id = session('agency_id');

        if (!$agency_id) {
            return response()->json([
                'status'  => false,
                'message' => 'Agency not logged in'
            ], 401);
        }

        $partnerInfo = Agencies::find($agency_id);

        if (!$partnerInfo) {
            return response()->json([
                'status'  => false,
                'message' => 'Agency not found'
            ], 404);
        }

        $search   = $request->search;     // tourist name search
        $perPage  = $request->per_page ?? 10;

        $tourists = TouristList::where('agent_id', $agency_id)
            ->when($search, function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhereRaw(
                            "CONCAT(first_name,' ',last_name) LIKE ?",
                            ["%{$search}%"]
                        );
                });
            })
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        // 🔁 Transform paginated collection
        $tourists->getCollection()->transform(function ($tourist) use ($partnerInfo) {

            /* -------- Expiry Date -------- */
            $expiry_date = null;

            if ($partnerInfo->agency_type == 2 && $tourist->check_out_time) {
                $expiry_date = Carbon::parse($tourist->check_out_time);
            }

            if ($partnerInfo->agency_type == 1 && $tourist->fly_out) {
                $expiry_date = Carbon::parse($tourist->fly_out);
            }

            /* -------- Riders Calculation -------- */
            $allocated_riders = ($tourist->inbound_riders ?? 0) + ($tourist->outbound_riders ?? 0);
            $assign_ride      = $allocated_riders * 3;

            $use_ride =
                ($tourist->number_of_consultation ?? 0) +
                ($tourist->number_of_ai_health_check ?? 0) +
                ($tourist->number_of_midas ?? 0);

            /* -------- Rider Status -------- */
            if ($tourist->status == 0) {
                $status = 'Not Used';
            } elseif ($expiry_date && $expiry_date->lt(now())) {
                $status = 'Used';
            } elseif ($use_ride == 0) {
                $status = 'Not Used';
            } elseif ($use_ride < $assign_ride) {
                $status = 'In Use';
            } else {
                $status = 'Used';
            }

            return [
                'customer_name'    => trim($tourist->first_name . ' ' . $tourist->last_name),
                'booking_id'       => $tourist->booking_id,
                'mobile_number' => trim(($tourist->country_code ?? '') . ' ' . ($tourist->contact_number ?? '')),
                'allocated_riders' => $allocated_riders,
                'rider_status'     => $status,
                'service_type'     => $tourist->service_type,
                'check_in_time'    => $tourist->check_in_time
                    ? Carbon::parse($tourist->check_in_time)->format('jS M Y ')
                    : null,
                'check_out_time'   => $tourist->check_out_time
                    ? Carbon::parse($tourist->check_out_time)->format('jS M Y ')
                    : null,
                'fly_in_time'           => $tourist->fly_in
                    ? Carbon::parse($tourist->fly_in)->format('jS M Y ')
                    : null,
                'fly_out_time'          => $tourist->fly_out
                    ? Carbon::parse($tourist->fly_out)->format('jS M Y ')
                    : null,
                'start_date'     => Carbon::parse($tourist->start_date)->format('jS M Y'),            
                // 'validity'     => trim(Carbon::parse($tourist->start_date)->format('jS M Y') . ' ' . $tourist->visa_expiry_days ),   
                'validity' => (function() use ($tourist) {
                    $days = (int) $tourist->visa_expiry_days;
                    $addDays = $days == 30 ? 90 : ($days == 60 ? 120 : $days);
                    return Carbon::parse($tourist->start_date)->addDays($addDays)->format('jS M Y') . ' (' . $days . ')';
                })(),

  
            ];
        });

        return response()->json([
            'status' => true,
            'data'   => $tourists
        ]);
    }   

    public function logsByAdmin_old(Request $request)
    {
        $query = ImportLog::query();

        // 🔹 Filter by Agency
        if ($request->filled('agency_id')) {
            $query->where('imported_by', $request->agency_id);
        }

        // 📅 Filter by date
        if ($request->filled('date')) {
            $query->whereDate('imported_at', $request->date);
        }

        // 📄 Pagination
        $logs = $query->orderBy('imported_at', 'desc')
            ->paginate(10);

        $logs->getCollection()->transform(function ($log) {

            $partnerInfo = Agencies::find($log->imported_by);
            $agencyType  = $partnerInfo
                ? AgencyType::find($partnerInfo->agency_type)
                : null;

            return [
                'log_id'        => $log->id,
                'date'          => Carbon::parse($log->imported_at)->format('dS M Y'),
                'time'          => Carbon::parse($log->imported_at)->format('h:i A'),
                'agency_name'   => $partnerInfo->name ?? 'NA',
                'agency_type'   => $agencyType->name ?? 'NA',
                'agency_image'  => $agencyType && $agencyType->image
                                    ? url($agencyType->image)
                                    : null,
                'file_name'     => $log->sheet_name,
                'view_url'      => url('/partner/import-log/' . $log->id . '/tourists'),
            ];
        });

        return response()->json([
            'status' => true,
            'data'   => $logs
        ]);
    }

    public function logsByAdmin(Request $request)
    {
        $query = ImportLog::query()
            ->join('agencies', 'agencies.id', '=', 'import_logs.imported_by')
            ->join('tourist_list', 'tourist_list.import_log_id', '=', 'import_logs.id')
            ->select('import_logs.*'); // important

        // 🔹 Filter by Agency ID
        if ($request->filled('agency_id')) {
            $query->where('import_logs.imported_by', $request->agency_id);
        }

        // 🔹 Filter by Agency Type
        if ($request->filled('agency_type')) {
            $query->where('agencies.agency_type', $request->agency_type);
        }

        // 📅 Filter by Date
        if ($request->filled('date')) {
            $query->whereDate('import_logs.imported_at', $request->date);
        }

        // 📄 Pagination
        $logs = $query->groupBy('import_logs.id')->orderBy('import_logs.imported_at', 'desc')
            ->paginate(10);

        $logs->getCollection()->transform(function ($log) {

            $partnerInfo = Agencies::find($log->imported_by);
            $agencyType  = $partnerInfo
                ? AgencyType::find($partnerInfo->agency_type)
                : null;

            return [
                'log_id'        => $log->id,
                'date'          => Carbon::parse($log->imported_at)->format('dS M Y'),
                'time'          => Carbon::parse($log->imported_at)->format('h:i A'),
                'agency_name'   => $partnerInfo->name ?? 'NA',
                'agency_type'   => $agencyType->name ?? 'NA',
                'agency_image'  => $agencyType && $agencyType->image
                                    ? url($agencyType->image)
                                    : null,
                'file_name'     => $log->sheet_name,
                'view_url'      => url('/admin/import-log/' . $log->id . '/tourists'),
            ];
        });

        return response()->json([
            'status' => true,
            'data'   => $logs
        ]);
    }


}
