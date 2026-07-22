<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\BiddingBanners;
use App\Models\BestOfferPlans;
use App\Models\BestOfferPlanOrders;
use App\Models\Users;
use App\Models\BiddingServices;
use App\Models\GlobalFunction;
use App\Models\GlobalSettings;
use App\Models\BiddingSubmissions;
use App\Models\BiddingSubmissionDocs;
use Rap2hpoutre\FastExcel\FastExcel;
use Illuminate\Support\Facades\DB;
use App\Helpers\Crypto; 
use Carbon\Carbon;
use Stichoza\GoogleTranslate\GoogleTranslate;

class BestOffersController extends Controller
{
    function viewBestOffersPlans()
    {
        return view('best_offers.viewBestOffersPlans');
    } 

    function fetchBestOffersPlans(Request $request)
    {
        $totalData =  BestOfferPlans::where('is_deleted', 0)->count();
        $rows = BestOfferPlans::where('is_deleted', 0)->orderBy('id', 'DESC')->get();

        $result = $rows;

        $columns = array(
            0 => 'id',
            1 => 'name'
        );

        $limit = $request->input('length');
        $start = $request->input('start');
        $order = $columns[$request->input('order.0.column')];
        $dir = $request->input('order.0.dir');

        $totalFiltered = $totalData;
        if (empty($request->input('search.value'))) {
            $result = BestOfferPlans::where('is_deleted', 0)->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
        } else {
            $search = $request->input('search.value');
            $result =  BestOfferPlans::where('is_deleted', 0)
                ->where(function ($query) use ($search) {
                    $query->where('title', 'LIKE', "%{$search}%")
                          ->orWhere('description', 'LIKE', "%{$search}%")
                          ->orWhere('price', 'LIKE', "%{$search}%");
                })
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
            $totalFiltered = BestOfferPlans::where('is_deleted', 0)
                ->where(function ($query) use ($search) {
                    $query->where('title', 'LIKE', "%{$search}%")
                          ->orWhere('description', 'LIKE', "%{$search}%")
                          ->orWhere('price', 'LIKE', "%{$search}%");
                })
                ->count();
        }
        $data = array();
        foreach ($result as $item) {

            $image = "http://placehold.jp/150x150.png";
            if ($item->image == null) {
                $img = '<img src="http://placehold.jp/150x150.png" width="50" height="50">';
            } else {
                $image = GlobalFunction::createMediaUrl($item->image);
                $img = '<img src="' . $image . '" width="50" height="50">';
            }  

             $detail_image = "http://placehold.jp/150x150.png";
            if ($item->detail_image == null) {
                $detail_img = '<img src="http://placehold.jp/150x150.png" width="50" height="50">';
            } else {
                $detail_image = GlobalFunction::createMediaUrl($item->detail_image);
                $detail_img = '<img src="' . $detail_image . '" width="50" height="50">';
            }  

           $edit = '<a href="#" 
            class="mr-2 btn btn-primary text-white edit" 
            data-image="' . $image .'"
            data-detail_image="' . $detail_image .'"
            data-title="' . htmlspecialchars($item->title) . '" 
            data-price="' . $item->price . '" 
            data-price_description="' . $item->price_description . '" 
            data-description="' . $item->description . '" 
            data-benefit="' . $item->benefits . '" 
            rel="' . $item->id . '">' . __("Edit") . '</a>';

            $delete = '<a href="" class="mr-2 btn btn-danger text-white delete" rel=' . $item->id . ' >' . __("Delete") . '</a>';
            $action =  $edit . $delete;


            $data[] = array(
                $img,
                $item->title,
                $item->price,
                $action,
            );
        }


        $json_data = array(
            "draw"            => intval($request->input('draw')),
            "recordsTotal"    => intval($totalData),
            "recordsFiltered" => $totalFiltered,
            "data"            => $data,
        );
        echo json_encode($json_data);
        exit();
    }

    function addBestOffersPlans(Request $request)
    {
        $item = new BestOfferPlans();
        $item->image = GlobalFunction::saveFileAndGivePath($request->image);
        $item->detail_image = GlobalFunction::saveFileAndGivePath($request->detail_image);
        $item->title = $request->title;

        $ta = new GoogleTranslate('ar');
        $item->ar_title = $ta->translate($request->title);        
        $tf = new GoogleTranslate('fr');
        $item->fr_title = $tf->translate($request->title);        
        $th = new GoogleTranslate('hi');
        $item->hi_title = $th->translate($request->title);        
        $tu = new GoogleTranslate('ur');
        $item->ur_title = $tu->translate($request->title);

        $item->price_description = $request->price_description;

        $ta = new GoogleTranslate('ar');
        $item->ar_price_description = $ta->translate($request->price_description);        
        $tf = new GoogleTranslate('fr');
        $item->fr_price_description = $tf->translate($request->price_description);        
        $th = new GoogleTranslate('hi');
        $item->hi_price_description = $th->translate($request->price_description);        
        $tu = new GoogleTranslate('ur');
        $item->ur_price_description = $tu->translate($request->price_description);

        $item->price = $request->price;
        $item->description = $request->description;

        $ta = new GoogleTranslate('ar');
        $item->ar_description = $ta->translate($request->description);        
        $tf = new GoogleTranslate('fr');
        $item->fr_description = $tf->translate($request->description);        
        $th = new GoogleTranslate('hi');
        $item->hi_description = $th->translate($request->description);        
        $tu = new GoogleTranslate('ur');
        $item->ur_description = $tu->translate($request->description);

        $item->benefits = $request->benefits;

        $ta = new GoogleTranslate('ar');
        $item->ar_benefits = $ta->translate($request->benefits);        
        $tf = new GoogleTranslate('fr');
        $item->fr_benefits = $tf->translate($request->benefits);        
        $th = new GoogleTranslate('hi');
        $item->hi_benefits = $th->translate($request->benefits);        
        $tu = new GoogleTranslate('ur');
        $item->ur_benefits = $tu->translate($request->benefits);

        $item->save();

        return GlobalFunction::sendSimpleResponse(true, 'added successfully');
    }
  
    function editBestOffersPlans(Request $request)
    {   
        $item = BestOfferPlans::find($request->id);
        
        if ($request->has('image')) {
            $item->image = GlobalFunction::saveFileAndGivePath($request->image);
        }

         if ($request->has('detail_image')) {
            $item->detail_image = GlobalFunction::saveFileAndGivePath($request->detail_image);
        }

        $item->title = $request->title;

        $ta = new GoogleTranslate('ar');
        $item->ar_title = $ta->translate($request->title);        
        $tf = new GoogleTranslate('fr');
        $item->fr_title = $tf->translate($request->title);        
        $th = new GoogleTranslate('hi');
        $item->hi_title = $th->translate($request->title);        
        $tu = new GoogleTranslate('ur');
        $item->ur_title = $tu->translate($request->title);

        $item->price_description = $request->price_description;

        $ta = new GoogleTranslate('ar');
        $item->ar_price_description = $ta->translate($request->price_description);        
        $tf = new GoogleTranslate('fr');
        $item->fr_price_description = $tf->translate($request->price_description);        
        $th = new GoogleTranslate('hi');
        $item->hi_price_description = $th->translate($request->price_description);        
        $tu = new GoogleTranslate('ur');
        $item->ur_price_description = $tu->translate($request->price_description);


        $item->price = $request->price;
        $item->description = $request->description;

        $ta = new GoogleTranslate('ar');
        $item->ar_description = $ta->translate($request->description);        
        $tf = new GoogleTranslate('fr');
        $item->fr_description = $tf->translate($request->description);        
        $th = new GoogleTranslate('hi');
        $item->hi_description = $th->translate($request->description);        
        $tu = new GoogleTranslate('ur');
        $item->ur_description = $tu->translate($request->description);

        $item->benefits = $request->benefits;

        $ta = new GoogleTranslate('ar');
        $item->ar_benefits = $ta->translate($request->benefits);        
        $tf = new GoogleTranslate('fr');
        $item->fr_benefits = $tf->translate($request->benefits);        
        $th = new GoogleTranslate('hi');
        $item->hi_benefits = $th->translate($request->benefits);        
        $tu = new GoogleTranslate('ur');
        $item->ur_benefits = $tu->translate($request->benefits);

        $item->save();
        return GlobalFunction::sendSimpleResponse(true, 'problem edited successfully');
    }

    function deleteBestOffersPlans($id)
    {
        $health_problem = BestOfferPlans::find($id);
        $health_problem->is_deleted = 1;
        $health_problem->save();

        return GlobalFunction::sendSimpleResponse(true, 'health problem deleted successfully');
    }

    public function createCcavenuePaymentUrl(Request $request)
{
    $order_id     = 'TESTORDER_' . time();
    $total_charge = '10.00';

    $data = [
        "merchant_id"     => env('CCAVENUE_MERCHANT_ID'),
        "order_id"        => $order_id,
        "currency"        => "AED",
        "amount"          => $total_charge,
        "merchant_param5" => 'TEST',
        "redirect_url"    => env('CCAVENUE_REDIRECT_URL'),
        "cancel_url"      => env('CCAVENUE_CANCEL_URL'),
        "language"        => "EN",
    ];

    $merchant_data = "";
    foreach ($data as $key => $value) {
        $merchant_data .= $key . '=' . $value . '&';
    }

    // Log once to validate
    \Log::info('CCAvenue merchant_data: ' . $merchant_data);

    $encrypted_data = Crypto::encrypt($merchant_data, env('CCAVENUE_WORKING_KEY'));

    return response()->json([
        'status'      => true,
        'encRequest'  => $encrypted_data,
        'access_code' => env('CCAVENUE_ACCESS_CODE'),
    ]);
}

    function viewBestOffersPlanUsers()
    {
        return view('best_offers.viewBestOffersPlanUsers');
    } 

    function fetchBestOffersPlanUsers(Request $request)
    {
        $sub = BestOfferPlanOrders::select('user_id', DB::raw('MAX(id) as last_order_id'))
            ->where('status', 1)
            ->groupBy('user_id');

        $totalData = Users::joinSub($sub, 'latest_orders', function ($join) {
                $join->on('users.id', '=', 'latest_orders.user_id');
            })->count();

        $columns = array(
            0 => 'users.fullname',
            1 => 'users.email',
            2 => 'users.phone_number',
            3 => 'latest_orders.last_order_id'
        );

        $limit = $request->input('length');
        $start = $request->input('start');
        $orderIndex = $request->input('order.0.column');
        $order = isset($columns[$orderIndex]) ? $columns[$orderIndex] : 'latest_orders.last_order_id';
        $dir = $request->input('order.0.dir') ?: 'DESC';

        $totalFiltered = $totalData;
        if (empty($request->input('search.value'))) {
            $result = Users::joinSub($sub, 'latest_orders', function ($join) {
                        $join->on('users.id', '=', 'latest_orders.user_id');
                    })
                    ->offset($start)
                    ->limit($limit)
                    ->orderBy($order, $dir)
                    ->get();
        } else {
            $search = $request->input('search.value');
            $result =  Users::joinSub($sub, 'latest_orders', function ($join) {
                        $join->on('users.id', '=', 'latest_orders.user_id');
                        })
                        ->where(function($query) use ($search) {
                            $query->where('users.fullname', 'LIKE', "%{$search}%")
                                  ->orWhere('users.email', 'LIKE', "%{$search}%")
                                  ->orWhere('users.phone_number', 'LIKE', "%{$search}%");
                        })
                        ->offset($start)
                        ->limit($limit)
                        ->orderBy($order, $dir)
                        ->get();

            $totalFiltered = Users::joinSub($sub, 'latest_orders', function ($join) {
                        $join->on('users.id', '=', 'latest_orders.user_id');
                        })
                        ->where(function($query) use ($search) {
                            $query->where('users.fullname', 'LIKE', "%{$search}%")
                                  ->orWhere('users.email', 'LIKE', "%{$search}%")
                                  ->orWhere('users.phone_number', 'LIKE', "%{$search}%");
                        })
                ->count();
        }
        $data = array();
        foreach ($result as $item) {

           $view = '<a href="' . route('bestOffers.viewUserPlans', $item->id) . '" class="mr-2 btn btn-info text-white " rel=' . $item->id . ' >' . __("View") . '</a>';


            $data[] = array(
                $item->fullname,
                $item->email,
                $item->phone_number,
                $view,
            );
        }


        $json_data = array(
            "draw"            => intval($request->input('draw')),
            "recordsTotal"    => intval($totalData),
            "recordsFiltered" => $totalFiltered,
            "data"            => $data,
        );
        echo json_encode($json_data);
        exit();
    }
    
        function viewUserPlans($user_id)
    {
        $orders = BestOfferPlanOrders::where('user_id', $user_id)->where('status', 1)->get();
        return view('best_offers.viewUserPlans', [
            'orders' => $orders,
        ]);
    }

    function fetchUserOrders(Request $request, $user_id)
    {
        $totalData =  BestOfferPlanOrders::where('status', 1)->where('user_id', $user_id)->count();
        $rows = BestOfferPlanOrders::where('status', 1)->where('user_id', $user_id)->orderBy('best_offer_plan_orders.id', 'DESC')->get();
  
        $result = $rows;

        $columns = array(
            0 => 'id',
            1 => 'offer_name'
        );

        $limit = $request->input('length');
        $start = $request->input('start');
        $order = $columns[$request->input('order.0.column')];
        $dir = $request->input('order.0.dir');

        $totalFiltered = $totalData;
        if (empty($request->input('search.value'))) {
            $result = BestOfferPlanOrders::where('status', 1)->where('user_id', $user_id)
                ->limit($limit)
                ->orderBy('best_offer_plan_orders.id', 'DESC')
                ->get();
        } else {
            $search = $request->input('search.value');
            $result =  BestOfferPlanOrders::where('status', 1)->where('user_id', $user_id)
                ->Where('offer_name', 'LIKE', "%{$search}%")
                ->offset($start)
                ->limit($limit)
                ->orderBy('best_offer_plan_orders.id', 'DESC')
                ->get();
            $totalFiltered = BestOfferPlanOrders::where('status', 1)
                ->Where('offer_name', 'LIKE', "%{$search}%")
                ->count();
        }
        $data = array();
        $tz = $request->input('timezone', 'UTC');
     
        foreach ($result as $key => $item) {

        // assume DB stored "2025-12-05 10:14:01" in UTC
        $localPurchasedAt = null;
        if (!empty($item->purchased_at)) {
            // create from format in UTC then convert to user tz
            $localPurchasedAt = Carbon::createFromFormat('Y-m-d H:i:s', $item->purchased_at, 'UTC')
                ->setTimezone($tz)
                ->format('d M Y, h:i A');
        }

            $data[] = array(
                $item->offer_name,
                $item->total_price,
                $localPurchasedAt
            );
        }


         $json_data = array(
            "draw"            => intval($request->input('draw')),
            "recordsTotal"    => intval($totalData),
            "recordsFiltered" => $totalFiltered,
            "data"            => $data,
        );
        echo json_encode($json_data);
        exit();
    }

}
