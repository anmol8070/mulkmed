<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\BiddingBanners;
use App\Models\BiddingServices;
use App\Models\GlobalFunction;
use App\Models\GlobalSettings;
use App\Models\BiddingSubmissions;
use App\Models\BiddingSubmissionDocs;
use App\Models\BiddingSubmitBanner;
use Rap2hpoutre\FastExcel\FastExcel;
use Illuminate\Support\Facades\DB;
use Stichoza\GoogleTranslate\GoogleTranslate;

class BiddingController extends Controller
{
    function biddingBanners()
    {
        return view('bidding.banners');
    } 

    function biddingSubmitBanners()
    {
        return view('bidding.submitBanners');
    } 

    function fetchBiddingBanners(Request $request)
    {
        $totalData =  BiddingBanners::where('is_deleted', 0)->count();
        $rows = BiddingBanners::where('is_deleted', 0)->orderBy('id', 'DESC')->get();

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
            $result = BiddingBanners::where('is_deleted', 0)->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
        } else {
            $search = $request->input('search.value');
            $result =  BiddingBanners::where('is_deleted', 0)
                ->Where('name', 'LIKE', "%{$search}%")
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
            $totalFiltered = BiddingBanners::where('is_deleted', 0)
                ->Where('name', 'LIKE', "%{$search}%")
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

           $edit = '<a href="#" 
            class="mr-2 btn btn-primary text-white edit" 
            data-icon="' . $imgUrl .'"
            data-name="' . htmlspecialchars($item->name) . '" 
            data-redirection="' . $item->redirection . '" 
            data-url="' . $item->url . '" 
            rel="' . $item->id . '">' . __("Edit") . '</a>';

            $delete = '<a href="" class="mr-2 btn btn-danger text-white delete" rel=' . $item->id . ' >' . __("Delete") . '</a>';
            $action =  $edit . $delete;


            $data[] = array(
                $img,
                $item->name,
                $item->redirection,
                $item->url,
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

    function fetchBiddingSubmitBanners(Request $request)
    {
        $totalData =  BiddingSubmitBanner::where('is_deleted', 0)->count();
        $rows = BiddingSubmitBanner::where('is_deleted', 0)->orderBy('id', 'DESC')->get();

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
            $result = BiddingSubmitBanner::where('is_deleted', 0)->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
        } else {
            $search = $request->input('search.value');
            $result =  BiddingSubmitBanner::where('is_deleted', 0)
                ->Where('name', 'LIKE', "%{$search}%")
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
            $totalFiltered = BiddingSubmitBanner::where('is_deleted', 0)
                ->Where('name', 'LIKE', "%{$search}%")
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

           $edit = '<a href="#" 
            class="mr-2 btn btn-primary text-white edit" 
            data-icon="' . $imgUrl .'"
            data-name="' . htmlspecialchars($item->name) . '" 
            data-redirection="' . $item->redirection . '" 
            data-url="' . $item->url . '" 
            rel="' . $item->id . '">' . __("Edit") . '</a>';

            $delete = '<a href="" class="mr-2 btn btn-danger text-white delete" rel=' . $item->id . ' >' . __("Delete") . '</a>';
            $action =  $edit . $delete;


            $data[] = array(
                $img,
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

    function addBiddingBanners(Request $request)
    {
        $item = new BiddingBanners();
        $item->name = $request->name;
        $item->image = GlobalFunction::saveFileAndGivePath($request->image);;
        $item->redirection = $request->redirection;
        $item->url = $request->url;
        $item->save();

        return GlobalFunction::sendSimpleResponse(true, 'added successfully');
    }

    function addBiddingSubmitBanners(Request $request)
    {
        $item = new BiddingSubmitBanner();
        $item->image = GlobalFunction::saveFileAndGivePath($request->image);
        $item->save();

        return GlobalFunction::sendSimpleResponse(true, 'added successfully');
    }

    function editBiddingBanners(Request $request)
    {   
        $item = BiddingBanners::find($request->id);
        $item->name = $request->name;
        if ($request->has('image')) {
            $item->image = GlobalFunction::saveFileAndGivePath($request->image);
        }
        $item->redirection = $request->redirection;
        $item->url = $request->url;
        $item->save();
        return GlobalFunction::sendSimpleResponse(true, 'problem edited successfully');
    }

        function editBiddingSubmitBanners(Request $request)
    {   
        $item = BiddingSubmitBanner::find($request->id);
        if ($request->has('image')) {
            $item->image = GlobalFunction::saveFileAndGivePath($request->image);
        }
        $item->save();
        return GlobalFunction::sendSimpleResponse(true, 'banner edited successfully');
    }

    function deleteBiddingBanners($id)
    {
        $banner = BiddingBanners::find($id);
        $banner->is_deleted = 1;
        $banner->save();

        return GlobalFunction::sendSimpleResponse(true, 'banner deleted successfully');
    }

     function deleteBiddingSubmitBanners($id)
    {
        $banner = BiddingSubmitBanner::find($id);
        $banner->is_deleted = 1;
        $banner->save();

        return GlobalFunction::sendSimpleResponse(true, 'banner deleted successfully');
    }

    function biddingServices(Request $request){
        return view('bidding.services');
    }

    function fetchBiddingServices(Request $request)
    {
        $totalData =  BiddingServices::where('is_deleted', 0)->count();
        $rows = BiddingServices::where('is_deleted', 0)->orderBy('id', 'DESC')->get();
        $settings = GlobalSettings::first();

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
            $result = BiddingServices::where('is_deleted', 0)->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
        } else {
            $search = $request->input('search.value');
            $result =  BiddingServices::where('is_deleted', 0)
                ->Where('service', 'LIKE', "%{$search}%")
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
            $totalFiltered = BiddingServices::where('is_deleted', 0)
                ->Where('service', 'LIKE', "%{$search}%")
                ->count();
        }
        // $currency_symbol = Settings::first();
        $data = array();
        foreach ($result as $item) {

            $edit = '<a href="#" 
                class="mr-2 btn btn-primary text-white edit" 
                data-service="' . $item->service . '" 
                rel="' . (int)$item->id . '">' . __("Edit") . '</a>';

            $delete = '<a href="#" class="mr-2 btn btn-danger text-white delete" rel="' . (int)$item->id . '">' . __("Delete") . '</a>';
            $action = $edit . $delete;

            $data[] = array(
                $item->id,
                $item->service,
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

    function addBiddingServices(Request $request)
    {
        $item = new BiddingServices(); 
        $item->service = $request->service;
        $ta = new GoogleTranslate('ar');
        $item->service_ar = $ta->translate($request->service);        
        $tf = new GoogleTranslate('fr');
        $item->service_fr = $tf->translate($request->service);        
        $th = new GoogleTranslate('hi');
        $item->service_hi = $th->translate($request->service);        
        $tu = new GoogleTranslate('ur');
        $item->service_ur = $tu->translate($request->service);
        $item->save();

        return GlobalFunction::sendSimpleResponse(true, 'Healthcare Partner added successfully');
    }

    function editBiddingServices(Request $request)
    {   
        $item = BiddingServices::find($request->id);
        $item->service = $request->service;
        $ta = new GoogleTranslate('ar');
        $item->service_ar = $ta->translate($request->service);        
        $tf = new GoogleTranslate('fr');
        $item->service_fr = $tf->translate($request->service);        
        $th = new GoogleTranslate('hi');
        $item->service_hi = $th->translate($request->service);        
        $tu = new GoogleTranslate('ur');
        $item->service_ur = $tu->translate($request->service);
        $item->save();
        return GlobalFunction::sendSimpleResponse(true, 'Healthcare Partner edited successfully');
    }

    function deleteBiddingServices($id)
    {
        $item = BiddingServices::find($id);
        $item->is_deleted = 1;
        $item->save();

        return GlobalFunction::sendSimpleResponse(true, 'Healthcare Partner deleted successfully');
    }

    function bidSubmitted()
    { 
     
        return view('bidding.bidSubmitted');
    }

    function fetchBidData(Request $request)
    {
        $totalData =  BiddingSubmissions::where('is_deleted', 0)->count();
        $rows = BiddingSubmissions::where('is_deleted', 0)->orderBy('id', 'DESC')->get();
        $settings = GlobalSettings::first();

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
            $result = BiddingSubmissions::where('is_deleted', 0)->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
        } else {
            $search = $request->input('search.value');
            $result =  BiddingSubmissions::where('is_deleted', 0)
                ->Where('full_name', 'LIKE', "%{$search}%")
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
            $totalFiltered = BiddingSubmissions::where('is_deleted', 0)
                ->Where('full_name', 'LIKE', "%{$search}%")
                ->count();
        }
        // $currency_symbol = Settings::first();
        $data = array();
        foreach ($result as $item) {


            $bid_docs = BiddingSubmissionDocs::where('bidding_submission_id', $item->id)->pluck('document')->toArray();
            $doc_list = implode(',', $bid_docs); 

            $edit = '<a href="#" 
                class="mr-2 btn btn-primary text-white edit" 
                data-service="' . $item->service . '" 
                data-budget="' . $item->budget . '" 
                data-country="' . $item->country . '" 
                data-city="' . $item->city . '" 
                data-date="' . $item->date . '" 
                data-comments="' . $item->comments . '" 
                data-other_service="' . $item->other_service . '" 
                data-docs="' . $doc_list . '"
                rel="' . (int)$item->id . '">' . __("View") . '</a>';

            // $delete = '<a href="#" class="mr-2 btn btn-danger text-white delete" rel="' . (int)$item->id . '">' . __("Delete") . '</a>';
            // $action = $edit . $delete;
            $action = $edit;

            

            $data[] = array(
                $item->id,
                $item->service,
                $item->budget,
                $item->other_service,
                $item->country,
                $item->city,
                $item->date,
                $item->comments,
               
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

     function addBidData(Request $request)
    {
        $item = new QueryProcedures();
        $item->procedure = $request->procedure;
        // $ta = new GoogleTranslate('ar');
        // $item->procedure_ar = $ta->translate($request->procedure);        
        // $tf = new GoogleTranslate('fr');
        // $item->procedure_fr = $tf->translate($request->procedure);        
        // $th = new GoogleTranslate('hi');
        // $item->procedure_hi = $th->translate($request->procedure);        
        // $tu = new GoogleTranslate('ur');
        // $item->procedure_ur = $tu->translate($request->procedure);
        $item->save();

        return GlobalFunction::sendSimpleResponse(true, 'Healthcare Partner added successfully');
    }

    function editBidData(Request $request)
    {   
        $item = QueryProcedures::find($request->id);
        $item->procedure = $request->procedure;
        // $ta = new GoogleTranslate('ar');
        // $item->procedure_ar = $ta->translate($request->procedure);        
        // $tf = new GoogleTranslate('fr');
        // $item->procedure_fr = $tf->translate($request->procedure);        
        // $th = new GoogleTranslate('hi');
        // $item->procedure_hi = $th->translate($request->procedure);        
        // $tu = new GoogleTranslate('ur');
        // $item->procedure_ur = $tu->translate($request->procedure);
        $item->save();
        return GlobalFunction::sendSimpleResponse(true, 'Healthcare Partner edited successfully');
    }

    function deleteBidData($id)
    {
        $item = QueryProcedures::find($id);
        $item->is_deleted = 1;
        $item->save();

        return GlobalFunction::sendSimpleResponse(true, 'Healthcare Partner deleted successfully');
    }

    function viewBulkUploadBiddingServices(Request $request)
    {
        return view('bidding.bulkUploadBiddingService');
    }

    public function bulkUploadBiddingServices(Request $request)
    {
        ini_set('max_execution_time', -1);
        try {
             $collections = (new FastExcel)->import($request->file('customer_file'));
        } catch (\Exception $exception) {
            return back()->with('error', 'You have uploaded a wrong format file, please upload the right file.');
        }
        $data = [];
        foreach ($collections as $collection)
        {
            $ta = new GoogleTranslate('ar');
            $service_ar = isset($collection['Service']) ? $ta->translate($collection['Service']) : null;
            $tf = new GoogleTranslate('fr');
            $service_fr = isset($collection['Service']) ? $tf->translate($collection['Service']) : null;
            $th = new GoogleTranslate('hi');
            $service_hi = isset($collection['Service']) ? $th->translate($collection['Service']) : null;
            $tu = new GoogleTranslate('ur');
            $service_ur = isset($collection['Service']) ? $tu->translate($collection['Service']) : null;

            $id =  DB::table('bidding_services')->insertGetId([
                "service" => $collection['Service'],
                "service_ar" => $service_ar,
                "service_ur" => $service_ur,
                "service_fr" => $service_fr,
                "service_hi" => $service_hi
            ]);
        }

        return back()->with('success', 'Bidding services imported successfully!');
    }

    public function downloadBiddingServicesFormat()
    {
        $filePath = storage_path('app/public/uploads/bidding_service_format.xlsx'); // absolute path

        if (!file_exists($filePath)) {
            return back()->with('error', 'File not found!');
        }

        return response()->download($filePath, 'bidding_service_format.xlsx');
    }

}
