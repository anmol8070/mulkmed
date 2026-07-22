<?php

namespace App\Http\Controllers;

use App\Models\TravelFlowBanner;
use App\Models\AgencyType;
use App\Models\Agencies;
use App\Models\RiderAllocation;
use App\Models\GlobalFunction;
use App\Models\ProductPlan;
use App\Models\TransactionHistory;
use App\Models\AgencySubscriptionPlans;
use App\Models\AgencyRidersUsage;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use App\Models\TouristList;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Support\Facades\DB;

class TravelFlowBannerController extends Controller
{
    public function travelFlowBanner()
    {
        return view('travel.flow_banner');
    }
         public function touristList()
    {
        return view('travel.tourist_list');
    }

    public function fetchTouristList(Request $request)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $touristQuery = TouristList::select(
                        'id',
                        'first_name',
                        'last_name',
                        'country_code',
                        'contact_number',
                        'check_in_time',
                        'check_out_time',
                        'pnr_number',
                        'fly_in',
                        'fly_out'
                    )
                    ->where('self_registered', 1)
                    ->orderBy('id', 'desc');

        if (!empty($startDate)) {
            $touristQuery->whereDate(DB::raw('COALESCE(check_in_time, fly_in)'), '>=', $startDate);
        }

        if (!empty($endDate)) {
            $touristQuery->whereDate(DB::raw('COALESCE(check_in_time, fly_in)'), '<=', $endDate);
        }

        $tourists = $touristQuery->get()
                    ->map(function ($item) {
                        $fullName = trim(($item->first_name ?? '') . ' ' . ($item->last_name ?? ''));
                        $countryCode = trim((string) ($item->country_code ?? ''));
                        $countryCode = ltrim($countryCode, '+');
                        $countryCode = $countryCode !== '' ? '+' . $countryCode : '';
                        $contactNumber = trim((string) ($item->contact_number ?? ''));

                        return [
                            'id' => $item->id,
                            'full_name' => $fullName !== '' ? $fullName : '-',
                            'phone_number' => trim($countryCode . ' ' . $contactNumber),
                            'check_in_time' => $item->check_in_time,
                            'check_out_time' => $item->check_out_time,
                            'pnr_number' => $item->pnr_number,
                            'fly_in' => $item->fly_in,
                            'fly_out' => $item->fly_out,
                        ];
                    });

        return response()->json([
            'status' => true,
            'data' => $tourists,
        ]);
    }


    public function fetchTravelFlowBanner(Request $request)
    {
        $totalData = TravelFlowBanner::count();
        $totalFiltered = $totalData;

        $query = TravelFlowBanner::orderBy('id', 'DESC');

        if (!empty($request->input('search.value'))) {
            $search = $request->input('search.value');
            $searchWithUnderscores = str_replace(' ', '_', $search);
            
            $query->where(function($q) use ($search, $searchWithUnderscores) {
                $q->where('banner_type', 'LIKE', "%{$search}%")
                  ->orWhere('banner_type', 'LIKE', "%{$searchWithUnderscores}%");
            });

            $totalFiltered = $query->count();
        }

        $limit = $request->input('length');
        $start = $request->input('start');
        
        if (isset($limit) && $limit != -1) {
            $query->offset($start)->limit($limit);
        }

        $rows = $query->get();

        $data = [];

        foreach ($rows as $item) {

            $imgUrl = "https://placehold.co/150x150";

            if (!empty($item->tourist_partner_banner)) {
                $imgUrl = GlobalFunction::createMediaUrl($item->tourist_partner_banner);
            }

            $img = '<img src="'.$imgUrl.'" width="60" height="60" style="object-fit:cover;border-radius:6px;">';

            $edit = '<a href="#" 
                        class="btn btn-sm btn-primary edit"
                        data-icon="'.$imgUrl.'"
                        rel="'.$item->id.'">Edit</a>';

            $delete = '<a href="#" 
                        class="btn btn-sm btn-danger delete ml-1"
                        rel="'.$item->id.'">Delete</a>';

            $data[] = [
                $img,
                $item->banner_type,
                $edit . ' ' . $delete
            ];
        }

        return response()->json([
            "draw" => intval($request->input('draw')),
            "recordsTotal" => intval($totalData),
            "recordsFiltered" => intval($totalFiltered),
            "data" => $data,
        ]);
    }

    public function addTravelFlowBanner(Request $request)
    {
        $rules = [
            'image' => 'required|image|mimes:jpg,jpeg,png|max:2048',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }

    //    $item = TravelFlowBanner::where('banner_type',$request->banner_type)->first();

    //     // If no record, create new
    //     if (!$item) {
    //         $item = new TravelFlowBanner();
    //     }
    // Always create a new row so multiple banners can exist
        // for the same banner_type without replacing old ones.
            $item = new TravelFlowBanner();

        if ($request->hasFile('image')) {
            $item->tourist_partner_banner = GlobalFunction::saveFileAndGivePath($request->image);
            // $item->banner_type            = $request->banner_type; 

        }
         $item->banner_type = $request->banner_type;

        $item->save();

        return response()->json([
            'success' => true,
            'message' => 'Travel Flow Banner added successfully'
        ]);
    }

    public function editTravelFlowBanner(Request $request)
    {
        $item = TravelFlowBanner::findOrFail($request->id);

        if ($request->hasFile('image')) {
            $item->tourist_partner_banner =
                GlobalFunction::saveFileAndGivePath($request->image);
        }

        $item->save();

        return response()->json([
            'success' => true,
            'message' => 'Travel Flow Banner updated successfully'
        ]);
    }

    public function deleteTravelFlowBanner($id)
    {
        TravelFlowBanner::where('id', $id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Travel Flow Banner deleted successfully'
        ]);
    }

    public function addAgencyType(Request $request)
    {
        $rules = [
            'image' => 'required|image|mimes:jpg,jpeg,png|max:2048',
            'name' => 'required',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }
        $item = new AgencyType();
        $item->name = $request->name;
        if ($request->hasFile('image')) {
            $item->image = GlobalFunction::saveFileAndGivePath($request->image);
        }
        $item->save();
        $agency_types = AgencyType::get();

        return response()->json([
            'success' => true,
            'message' => 'Agency type added successfully',
            'agency_types' => $agency_types
        ]);
    }

    public function getAgencyType(Request $request)
    {
        $agency_types = AgencyType::get();

        return response()->json([
            'success' => true,
            'agency_types' => $agency_types
        ]);
    }

    public function addAgency(Request $request)
    {
        $rules = [
            'logo' => 'required|image|mimes:jpg,jpeg,png|max:2048',
            'name' => 'required',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['success' => false, 'status' => false, 'message' => $msg], 422);
        }
        $item                   = new Agencies();
        $item->name             = $request->name;
        $item->agency_type      = $request->agency_type;
        $item->address          = $request->address;
        $item->email            = $request->email;
        $item->contact_number   = $request->contact_number;
        $item->password         = $request->password;
        if ($request->hasFile('logo')) {
            $item->logo = GlobalFunction::saveFileAndGivePath($request->logo);
        }
        $item->save();

        return response()->json([
            'success' => true,
            'status' => true,
            'message' => 'Agency added successfully'
        ]);
    }

    public function getAgency(Request $request)
    {
        $rules = [
            'agency_type_id' => 'required',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }

        $agencies  = Agencies::select('agencies.*','agency_type.name as agency_type_name','agency_type.image as agency_type_image')
                        ->join('agency_type','agency_type.id','agencies.agency_type')
                        ->where('agency_type',$request->agency_type_id)->get();

        return response()->json(['status' => true, 'agencies' => $agencies]);
    }

    public function fetchAllAgencies(Request $request)
    {
        if($request->has('agency_type_id'))
        {
            $agencies  = Agencies::select('agencies.*','agency_type.name as agency_type_name','agency_type.image as agency_type_image')
                            ->join('agency_type','agency_type.id','agencies.agency_type')
                            ->where('is_deleted',0)->where('agency_type',$request->agency_type_id)->get();
        }
        else{
            $agencies  = Agencies::select('agencies.*','agency_type.name as agency_type_name','agency_type.image as agency_type_image')
                            ->join('agency_type','agency_type.id','agencies.agency_type')
                            ->where('is_deleted',0)->get();
        }
        return response()->json(['status' => true, 'agencies' => $agencies]);
    }

    public function editAgency($id)
    {
        $agency  = Agencies::select('agencies.*','agency_type.name as agency_type_name','agency_type.image as agency_type_image')
                        ->join('agency_type','agency_type.id','agencies.agency_type')
                        ->where('agencies.id',$id)->get();
        return response()->json(['status' => true, 'agency' => $agency]);
    }

    public function updateAgency(Request $request)
    {
        $rules = [
            'id' => 'required',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['success' => false, 'status' => false, 'message' => $msg], 422);
        }

        $item                   = Agencies::find($request->id);
        $item->name             = $request->name;
        $item->agency_type      = $request->agency_type;
        $item->address          = $request->address;
        $item->email            = $request->email;
        $item->contact_number   = $request->contact_number;
        $item->password         = $request->password;
        if ($request->hasFile('logo')) {
            $item->logo = GlobalFunction::saveFileAndGivePath($request->logo);
        }
        $item->save();

        return response()->json([
            'success' => true,
            'status' => true,
            'message' => 'Agency updated successfully'
        ]);
    }

    public function deleteAgency($id)
    {
        $agency = Agencies::where('id', $id)->first();
        $agency->is_deleted = 1;
        $agency->save();
        return response()->json([
            'success' => true,
            'message' => 'Angency deleted successfully'
        ]);
    }

    public function addProductPlan(Request $request)
    {
        $product_plan = new ProductPlan();
        $product_plan->name = $request->name;
        $product_plan->description = $request->description;
        $product_plan->is_deleted = 0;
        $product_plan->save();
        return response()->json(['status' => true, 'message' => 'Product plan added successfully']);
    }

    public function getProductPlan(Request $request)
    {
        $product_plans  = ProductPlan::where('is_deleted',0)->get();
        return response()->json(['status' => true, 'product_plans' => $product_plans]);
    }

    public function editProductPlan($id)
    {
        $product_plans  = ProductPlan::where('id',$id)->first();
        return response()->json(['status' => true, 'product_plan' => $product_plan]);
    }

    public function updateProductPlan(Request $request)
    {
        $rules = [
            'id' => 'required',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }
        $product_plan = ProductPlan::where('id',$request->id)->first();
        $product_plan->name = $request->name;
        $product_plan->description = $request->description;
        $product_plan->is_deleted = 0;
        $product_plan->save();
        return response()->json(['status' => true, 'message' => 'Product plan updated successfully']);
    }

    public function deleteProductPlan($id)
    {
        $product_plans = ProductPlan::where('id', $id)->first();
        $product_plans->is_deleted = 1;
        $product_plans->save();
        return response()->json([
            'success' => true,
            'message' => 'Product plan deleted successfully'
        ]);
    }
 
    public function addRiderAllocation(Request $request)
    {
        $rules = [
            'product_plan_id' => 'required',
            'agency_type' => 'required',
            'agency_id' => 'required',
            'payment_type' => 'required',
            // 'number_of_rider_plan' => 'required',
            // 'amount' => 'required',
            'expiry_date' => 'required',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }
        $item                       = new RiderAllocation();
        $item->product_plan_id      = $request->product_plan_id;
        $item->agency_type          = $request->agency_type;
        $item->agency_id            = $request->agency_id;
        $item->payment_type         = $request->payment_type;
        $item->expiry_date          = $request->expiry_date;
        $item->inbound              = $request->inbound ?? 0;
        $item->inbound_rider_number = $request->inbound_rider_number ?? 0;
        $item->inbound_amount       = $request->inbound_amount ?? 0;
        $item->outbound              = $request->outbound ?? 0;
        $item->outbound_rider_number = $request->outbound_rider_number ?? 0;
        $item->outbound_amount      = $request->outbound_amount ?? 0;
        $item->amount               = $request->amount ?? 0;
        $item->save();

        return response()->json([
            'success' => true,
            'message' => 'Plan added successfully'
        ]);
    }

    public function getRiderAllocation()
    {
        $rider_allocations = RiderAllocation::select('agencies.name as agency_name','agency_type.name as agency_type_name',
                                    'agency_type.image as agency_type_image','rider_allocations.*')
                                ->join('agencies','agencies.id','rider_allocations.agency_id')
                                ->join('agency_type','agency_type.id','rider_allocations.agency_type')
                                ->where('rider_allocations.is_deleted',0)->get();

        return response()->json([
            'success' => true,
            'rider_allocations' => $rider_allocations
        ]);
    }

    public function updateRiderAllocation(Request $request)
    {
        $rules = [
            'id' => 'required',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }
        $item                       = RiderAllocation::find($request->id);
        $item->product_plan_id      = $request->product_plan_id;
        $item->agency_type          = $request->agency_type;
        $item->agency_id            = $request->agency_id;
        $item->payment_type         = $request->payment_type;
        $item->expiry_date          = $request->expiry_date;
        $item->inbound              = $request->inbound ?? 0;
        $item->inbound_rider_number = $request->inbound_rider_number ?? 0;
        $item->inbound_amount       = $request->inbound_amount ?? 0;
        $item->outbound              = $request->outbound ?? 0;
        $item->outbound_rider_number = $request->outbound_rider_number ?? 0;
        $item->outbound_amount      = $request->outbound_amount ?? 0;
        $item->amount               = $request->amount ?? 0;
        $item->save();

        return response()->json([
            'success' => true,
            'message' => 'Plan updated successfully'
        ]);
    }

    public function getTransactionHistory(Request $request)
    {
        $transaction_history = TransactionHistory::select('agency_subscription_plans.id','agencies.name','agency_type.name as agency_type','agency_subscription_plans.payment_type',
                                        'transaction_history.amount','transaction_history.created_at',
                                        'rider_allocations.inbound','rider_allocations.outbound')
                                ->join('agency_subscription_plans','agency_subscription_plans.id','transaction_history.plan_id')
                                ->join('rider_allocations','rider_allocations.id','agency_subscription_plans.subscription_id')
                                ->join('agencies','agencies.id','transaction_history.agency_id')
                                ->join('agency_type','agency_type.id','agencies.agency_type')
                                ->get();

        return response()->json([
                'status' => true,
                'transaction_history'   => $transaction_history
            ]);
    }

    public function getAgencyCount()
    {
        $agency_count = Agencies::count();
        return response()->json([
                'status' => true,
                'agency_count'   => $agency_count
            ]);
    }

    public function getAgencyInfo(Request $request)
    {
        $agency_id      = $request->agency_id;
        $agency_type_id = $request->agency_type_id;

        $subscribed_riders  = 0;
        $remaining_riders   = 0;
        $allocated_riders   = 0;

        $partner_info = Agencies::where('id',$agency_id)->first();
        $has_subscription_plan = 0;
        $subscription_plan  = AgencySubscriptionPlans::select('rider_allocations.*','agency_subscription_plans.remaining_riders',
                                    'agency_subscription_plans.outbound_remaining_riders','agency_subscription_plans.inbound_remaining_riders',
                                    'agency_subscription_plans.id as plan_id')
                                // ->whereMonth('agency_subscription_plans.expiry_date', Carbon::now()->month)
                                // ->whereYear('agency_subscription_plans.expiry_date', Carbon::now()->year)
                                ->where('agency_subscription_plans.expiry_date', '>', now())
                                ->where('agency_subscription_plans.agency_id',$agency_id)
                                ->join('rider_allocations','rider_allocations.id','agency_subscription_plans.subscription_id')
                                ->first();

        if(isset($subscription_plan))
        {
            $has_subscription_plan = 1;
            if($subscription_plan->payment_type == "Prepaid")
            {
                $subscribed_riders  = intval($subscription_plan->inbound_rider_number) + intval($subscription_plan->outbound_rider_number);
                $remaining_riders   = intval($subscription_plan->inbound_remaining_riders) + intval($subscription_plan->outbound_remaining_riders);
                $allocated_riders   = $subscribed_riders - $remaining_riders;
                $inbound_allocated_riders   = intval($subscription_plan->inbound_rider_number) - intval($subscription_plan->inbound_remaining_riders);
                $outbound_allocated_riders  = intval($subscription_plan->outbound_rider_number) - intval($subscription_plan->outbound_remaining_riders);
                return  response()->json(['total_riders'=>$subscribed_riders ,
                                            'used_riders' => $allocated_riders ,
                                            'remaining_riders' => $remaining_riders,]);
            }

            elseif($subscription_plan->payment_type == "Postpaid")
            {
                $usage = AgencyRidersUsage::where('plan_id', $subscription_plan->plan_id)
                        ->selectRaw('
                            SUM(inbound_riders)  as total_inbound_riders,
                            SUM(outbound_riders) as total_outbound_riders
                        ')
                        ->first();

                $inbound_used_rider         = intval($usage->total_inbound_riders);
                $outbound_used_rider        = intval($usage->total_outbound_riders);
                $total_used_rider           = $inbound_used_rider + $outbound_used_rider;
                $inbound_price_per_rider    = intval($subscription_plan->inbound_amount);
                $outbound_price_per_rider   = intval($subscription_plan->outbound_amount);
                $total_amount               = ($inbound_used_rider * $inbound_price_per_rider) + ($outbound_used_rider * $outbound_price_per_rider);

                return  response()->json(['used_riders' => $total_used_rider]);
            }
        }
        return  response()->json(['message' => "no subscription plan"]);
    }    
    
    public function downloadTouristExcel(Request $request)
{
    $agent_type = TouristList::where('import_log_id', $request->id)
                    ->first()
                    ->agent_type;

    $query = TouristList::where('import_log_id', $request->id);

    // Select fields based on agent_type
    if ($agent_type == 1) {
        // TRAVEL
        $data = $query->get([
            'first_name',
            'last_name',
            'fly_in',
            'fly_out',
            'country_code',
            'contact_number',
            'service_type'
        ]);

        $headings = [
            'first_name',
            'last_name',
            'fly_in',
            'fly_out',
            'country_code',
            'mobile_number',
            'service_type'
        ];

    } elseif ($agent_type == 2) {
        // HOTEL
        $data = $query->get([
            'first_name',
            'last_name',
            'check_in_time',
            'check_out_time',
            'country_code',
            'contact_number',
            'service_type'
        ]);

        $headings = [
            'first_name',
            'last_name',
            'check_in_date',
            'check_out_date',
            'country_code',
            'mobile_number',
            'service_type'
        ];

    } else {
    // VISA
    $data = $query->get([
        'first_name',
        'last_name',
        'start_date',
        'visa_expiry_days',
        'country_code',
        'contact_number',
        'service_type'
    ])->map(function ($row) {

        // Convert visa_expiry_days to actual expiry DATE
        if (!empty($row->start_date) && !empty($row->visa_expiry_days)) {
            $row->visa_expiry_days = \Carbon\Carbon::parse($row->start_date)
                ->addDays((int) $row->visa_expiry_days)
                ->format('Y-m-d');
        }

        return $row;
    });

    $headings = [
        'first_name',
        'last_name',
        'start_date',
        'visa_expiry_date',   // updated label
        'country_code',
        'mobile_number',
        'service_type'
    ];
}


    // Excel download (UNCHANGED)
    return Excel::download(
        new class($data, $headings) implements FromCollection, WithHeadings {

            protected $data;
            protected $headings;

            public function __construct($data, $headings)
            {
                $this->data = $data;
                $this->headings = $headings;
            }

            public function collection()
            {
                return $this->data;
            }

            public function headings(): array
            {
                return $this->headings;
            }
        },
        'tourist_list.xlsx'
    );
}

}
