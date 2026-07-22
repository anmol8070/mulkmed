<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Session;
use App\Models\GlobalSettings;
use App\Models\SmoSectionSequence;
use App\Models\TopHospitals;
use App\Models\TrustedHealthcarePartners;
use App\Models\WhySecondOpinionMatters;
use App\Models\Hospitals;
use App\Models\Doctor;
use App\Models\HospitalCategories;
use App\Models\HospitalProcedures;
use App\Models\QueryProcedures;
use App\Models\TopProcedures;
use App\Models\MulkmedChoiceOfDoctors;
use App\Models\HospitalImages;
use App\Models\UnlockMoreBenefitsCard;
use App\Models\SubmitYourQuery;
use App\Models\GlobalFunction;
use App\Models\SMOQueryDocs;
use App\Models\SMOQueries;
use App\Helpers\Helpers;
use Illuminate\Support\Facades\Storage;
use Rap2hpoutre\FastExcel\FastExcel;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\MemoryDrawing;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Brian2694\Toastr\Facades\Toastr;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use Stichoza\GoogleTranslate\GoogleTranslate;

class SMOController extends Controller
{
    function sectionSequence()
    {
        return view('smo.smoSectionSequence');
    }

    function fetchSectionSequence(Request $request)
    {
        $totalData =  SmoSectionSequence::where('is_deleted', 0)->count();

        $rows = SmoSectionSequence::where('is_deleted', 0)->orderBy('position', 'ASC')->get();

        $result = $rows;

        $columns = array(
            0 => 'position'
        );

        $limit = $request->input('length');
        $start = $request->input('start');
        $order = $columns[$request->input('order.0.column')];
        $dir = $request->input('order.0.dir');

        $totalFiltered = $totalData;
        $result = SmoSectionSequence::where('is_deleted', 0)->offset($start)
                ->limit($limit)
                ->orderBy($order, 'ASC')
                ->get();
        $data = array();
        foreach ($result as $item) {
            $arrow = '<a href="" class="update_position" data-id="' . $item->id . '" data-position="' . $item->position . '">
                  <i class="fas fa-arrow-up"></i>
              </a>';
            $status_update = '<label class="switch">
                  <input type="checkbox" class="status_toggle" data-id="' . $item->id . '" ' . ($item->status ? 'checked' : '') . '>
                  <span class="slider round"></span>
               </label>';
            $delete = '<a href="" class="mr-2 btn btn-danger text-white delete" rel=' . $item->id . ' >' . __("Delete") . '</a>';
            
            $action =  $delete;


            $data[] = array(
                $item->section_name,
                $item->position == 1  ? $item->position  : $item->position  . ' ' . $arrow,
                $status_update,
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

    function sequenceUpdate(Request $request)
    {
        $section_position_increase           = SmoSectionSequence::where('position',$request->position-1)->first();
        $section_position_increase->position = $request->position;
        $section_position_increase->save();

        $section_position_decrease           = SmoSectionSequence::find($request->id);
        $section_position_decrease->position   = $request->position-1;
        $section_position_decrease->save();

        return response()->json(['status'=>true , 'message'=>"Position Updated Successfully"]);
    }

    function sequenceStatusUpdate(Request $request)
    {
        $section_position_increase           = SmoSectionSequence::where('id',$request->id)->first();
        $section_position_increase->status  = $request->status;
        $section_position_increase->save();

        return response()->json(['status'=>true , 'message'=>"Status Updated Successfully"]);
    }

    function deleteSection($id)
    {
        $section_sequence = SmoSectionSequence::find($id);
        $section_sequence->is_deleted = 1;
        $section_sequence->save();

        return GlobalFunction::sendSimpleResponse(true, 'health problem deleted successfully');
    }

    function topHospitals()
    {
        return view('smo.topHospitals');
    }  

    function fetchTopHospitals(Request $request)
    {
        $totalData =  TopHospitals::where('is_deleted', 0)->count();
        $rows = TopHospitals::where('is_deleted', 0)->orderBy('id', 'DESC')->get();
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
            $result = TopHospitals::where('is_deleted', 0)->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
        } else {
            $search = $request->input('search.value');
            $result =  TopHospitals::where('is_deleted', 0)
                ->Where('name', 'LIKE', "%{$search}%")
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
            $totalFiltered = TopHospitals::where('is_deleted', 0)
                ->Where('name', 'LIKE', "%{$search}%")
                ->count();
        }
        // $currency_symbol = Settings::first();
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
                data-icon="' . $imgUrl . '"
                data-hospital_id="' . $item->hospital_id . '" 
                data-rating="' . $item->rating . '" 
                data-priority="' . $item->priority . '" 
                rel="' . (int)$item->id . '">' . __("Edit") . '</a>';

            $delete = '<a href="#" class="mr-2 btn btn-danger text-white delete" rel="' . (int)$item->id . '">' . __("Delete") . '</a>';
            $action = $edit . $delete;

            $data[] = array(
                $img,
                $item->name,
                $item->rating,
                $item->priority,
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

    function getHospitals(Request $request)
    {
        $query = Hospitals::whereRaw("JSON_CONTAINS(category, '\"6\"')")->where('is_deleted', 0);

        if ($request->has('search')) {
            $query->where('name', 'LIKE', "%{$request->search}%");
        }

        $hospitals = $query->orderBy('id', 'DESC')->get();
        return response()->json(['status' => true, 'hospitals' => $hospitals]);
    }

    function getHospitalCategories(Request $request)
    {
        $query = HospitalCategories::where('is_deleted', 0);

        if ($request->has('search')) {
            $query->where('name', 'LIKE', "%{$request->search}%");
        }

        $hospitalCategories = $query->get();
        return response()->json(['status' => true, 'hospitalCategories' => $hospitalCategories]);
    }

        function getDoctors(Request $request)
    {
        $query = Doctor::get();

        return response()->json(['status' => true, 'doctors' => $query]);
    }

    function addTopHospitals(Request $request)
    {
        $item = new TopHospitals();
        $item->name = $request->name;
        $item->hospital_id = $request->hospital_id;
        $item->rating = $request->rating;
        $item->priority = $request->priority;
        $ta = new GoogleTranslate('ar');
        $item->ar_name = $ta->translate($item->name);
        $tf = new GoogleTranslate('fr');
        $item->fr_name = $tf->translate($item->name);
        $th = new GoogleTranslate('hi');
        $item->hi_name = $th->translate($item->name);
        $tu = new GoogleTranslate('ur');
        $item->ur_name = $tu->translate($item->name);
        $item->image = GlobalFunction::saveFileAndGivePath($request->image);
        $item->save();

        return GlobalFunction::sendSimpleResponse(true, 'Healthcare Partner added successfully');
    }

    function editTopHospitals(Request $request)
    {   
        $item = TopHospitals::find($request->id);
        $item->hospital_id = $request->hospital_id;
        $item->name = $request->name;
        $item->rating = $request->rating;
        $item->priority = $request->priority;
        $ta = new GoogleTranslate('ar');
        $item->ar_name = $ta->translate($item->name);
        $tf = new GoogleTranslate('fr');
        $item->fr_name = $tf->translate($item->name);
        $th = new GoogleTranslate('hi');
        $item->hi_name = $th->translate($item->name);
        $tu = new GoogleTranslate('ur');
        $item->ur_name = $tu->translate($item->name);
        if ($request->has('image')) {
            $item->image = GlobalFunction::saveFileAndGivePath($request->image);
        }
        $item->save();
        return GlobalFunction::sendSimpleResponse(true, 'Healthcare Partner edited successfully');
    }

    function deleteTopHospitals($id)
    {
        $item = TopHospitals::find($id);
        $oldData = $item ? $item->toArray() : null;
        $item->is_deleted = 1;
        $item->save();

        activity_log(
            'deleted',
            'TopHospitals',
            $id,
            'Top hospital marked as deleted',
            $oldData,
            ['is_deleted' => 1]
        );

        return GlobalFunction::sendSimpleResponse(true, 'Healthcare Partner deleted successfully');
    }

    function hospitalCategories()
    {
        return view('smo.hospitalCategories');
    }  

    function fetchHospitalCategories(Request $request)
    {
        $totalData =  HospitalCategories::where('is_deleted', 0)->count();
        $rows = HospitalCategories::where('is_deleted', 0)->orderBy('id', 'DESC')->get();
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
            $result = HospitalCategories::where('is_deleted', 0)->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
        } else {
            $search = $request->input('search.value');
            $result =  HospitalCategories::where('is_deleted', 0)
                ->Where('name', 'LIKE', "%{$search}%")
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
            $totalFiltered = HospitalCategories::where('is_deleted', 0)
                ->Where('name', 'LIKE', "%{$search}%")
                ->count();
        }
        // $currency_symbol = Settings::first();
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
                data-icon="' . $imgUrl . '"
                data-name="' . $item->name . '" 
                rel="' . (int)$item->id . '">' . __("Edit") . '</a>';

            $delete = '<a href="#" class="mr-2 btn btn-danger text-white delete" rel="' . (int)$item->id . '">' . __("Delete") . '</a>';
            $action = $edit . $delete;

            $data[] = array(
                $item->id,
                $img,
                $item->name,
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

    function addHospitalCategories(Request $request)
    {
        $item = new HospitalCategories();
        $item->name = $request->name;
        $ta = new GoogleTranslate('ar');
        $item->ar_name = $ta->translate($item->name);
        $tf = new GoogleTranslate('fr');
        $item->fr_name = $tf->translate($item->name);
        $th = new GoogleTranslate('hi');
        $item->hi_name = $th->translate($item->name);
        $tu = new GoogleTranslate('ur');
        $item->ur_name = $tu->translate($item->name);
        $item->image = GlobalFunction::saveFileAndGivePath($request->image);
        $item->save();

        return GlobalFunction::sendSimpleResponse(true, 'Healthcare Partner added successfully');
    }

    function editHospitalCategories(Request $request)
    {   
        $item = HospitalCategories::find($request->id);
        $item->name = $request->name;
        $ta = new GoogleTranslate('ar');
        $item->ar_name = $ta->translate($item->name);
        $tf = new GoogleTranslate('fr');
        $item->fr_name = $tf->translate($item->name);
        $th = new GoogleTranslate('hi');
        $item->hi_name = $th->translate($item->name);
        $tu = new GoogleTranslate('ur');
        $item->ur_name = $tu->translate($item->name);
        if ($request->has('image')) {
            $item->image = GlobalFunction::saveFileAndGivePath($request->image);
        }
        $item->save();
        return GlobalFunction::sendSimpleResponse(true, 'Healthcare Partner edited successfully');
    }

    function deleteHospitalCategories($id)
    {
        $item = HospitalCategories::find($id);
        $item->is_deleted = 1;
        $item->save();

        return GlobalFunction::sendSimpleResponse(true, 'Healthcare Partner deleted successfully');
    }

    function submitYourQuery()
    {
        return view('smo.submitYourQuery');
    }  

    function fetchSubmitYourQuery(Request $request)
    {
        $totalData =  SubmitYourQuery::where('is_deleted', 0)->count();
        $rows = SubmitYourQuery::where('is_deleted', 0)->orderBy('id', 'DESC')->get();
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
        // $currency_symbol = Settings::first();
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
                data-icon="' . $imgUrl . '" 
                rel="' . (int)$item->id . '">' . __("Edit") . '</a>';

            $delete = '<a href="#" class="mr-2 btn btn-danger text-white delete" rel="' . (int)$item->id . '">' . __("Delete") . '</a>';
            $action = $edit . $delete;

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


    function unlockMoreBenefitsCard()
    {
        return view('smo.unlockMoreBenefitsCard');
    } 
    
    function fetchUnlockMoreBenefitsCard(Request $request)
    {
        $totalData =  UnlockMoreBenefitsCard::where('is_deleted', 0)->count();
        $rows = UnlockMoreBenefitsCard::where('is_deleted', 0)->orderBy('id', 'DESC')->get();
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
        // $currency_symbol = Settings::first();
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
                data-icon="' . $imgUrl . '" 
                rel="' . (int)$item->id . '">' . __("Edit") . '</a>';

            $delete = '<a href="#" class="mr-2 btn btn-danger text-white delete" rel="' . (int)$item->id . '">' . __("Delete") . '</a>';
            $action = $edit . $delete;

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

    function addUnlockMoreBenefitsCard(Request $request)
    {
        $item = new UnlockMoreBenefitsCard();
        $item->image = GlobalFunction::saveFileAndGivePath($request->image);
        $item->save();

        return GlobalFunction::sendSimpleResponse(true, 'Healthcare Partner added successfully');
    }

    function addSubmitYourQuery(Request $request)
    {
        $item = new SubmitYourQuery();
        $item->image = GlobalFunction::saveFileAndGivePath($request->image);
        $item->save();

        return GlobalFunction::sendSimpleResponse(true, 'Submit Query added successfully');
    }

    function editUnlockMoreBenefitsCard(Request $request)
    {   
        $item = UnlockMoreBenefitsCard::find($request->id);
        if ($request->has('image')) {
            $item->image = GlobalFunction::saveFileAndGivePath($request->image);
        }
        $item->save();
        return GlobalFunction::sendSimpleResponse(true, 'Healthcare Partner edited successfully');
    }

     function editSubmitYourQuery(Request $request)
    {   
        $item = SubmitYourQuery::find($request->id);
        if ($request->has('image')) {
            $item->image = GlobalFunction::saveFileAndGivePath($request->image);
        }
        $item->save();
        return GlobalFunction::sendSimpleResponse(true, 'Submit Query edited successfully');
    }

    function deleteUnlockMoreBenefitsCard($id)
    {
        $item = UnlockMoreBenefitsCard::find($id);
        $item->is_deleted = 1;
        $item->save();

        return GlobalFunction::sendSimpleResponse(true, 'Healthcare Partner deleted successfully');
    }

    function deleteSubmitYourQuery($id)
    {
        $item = SubmitYourQuery::find($id);
        $item->is_deleted = 1;
        $item->save();

        return GlobalFunction::sendSimpleResponse(true, 'Healthcare Partner deleted successfully');
    }
    
    function hospitalProcedures()
    {
        ini_set('max_execution_time', -1);

        // $rows = HospitalProcedures::where('is_deleted', 0)->where('id', '>',1299)->where('id','<',1350)->get();
        // foreach ($rows as $key => $value) {
        //     $ta = new GoogleTranslate('ar');
        //     $ar_procedure = isset($value['procedure']) ? $ta->translate($value['procedure']) : null;
        //     $value->ar_procedure = $ar_procedure;
        //     $tf = new GoogleTranslate('fr');
        //     $fr_procedure = isset($value['procedure']) ? $tf->translate($value['procedure']) : null;
        //     $value->fr_procedure = $fr_procedure;
        //     $th = new GoogleTranslate('hi');
        //     $hi_procedure = isset($value['procedure']) ? $th->translate($value['procedure']) : null;
        //     $value->hi_procedure = $hi_procedure;
        //     $tu = new GoogleTranslate('ur');
        //     $ur_procedure = isset($value['procedure']) ? $tu->translate($value['procedure']) : null;
        //     $value->ur_procedure = $ur_procedure;
        //     $value->save();
        // }
        return view('smo.hospitalProcedures');
    }  

    function fetchHospitalProcedures(Request $request)
    {
        $totalData =  HospitalProcedures::where('is_deleted', 0)->count();
        $rows = HospitalProcedures::where('is_deleted', 0)->orderBy('id', 'DESC')->get();
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
            $result = HospitalProcedures::where('is_deleted', 0)->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
        } else {
            $search = $request->input('search.value');
            $result =  HospitalProcedures::where('is_deleted', 0)
                ->Where('procedure', 'LIKE', "%{$search}%")
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
            $totalFiltered = HospitalProcedures::where('is_deleted', 0)
                ->Where('procedure', 'LIKE', "%{$search}%")
                ->count();
        }
        // $currency_symbol = Settings::first();
        $data = array();
        foreach ($result as $item) {

            $edit = '<a href="#" 
                class="mr-2 btn btn-primary text-white edit" 
                data-procedure="' . $item->procedure . '" 
                data-mulk_price="' . $item->mulk_price . '" 
                data-market_price="' . $item->market_price . '" 
                rel="' . (int)$item->id . '">' . __("Edit") . '</a>';

            $delete = '<a href="#" class="mr-2 btn btn-danger text-white delete" rel="' . (int)$item->id . '">' . __("Delete") . '</a>';
            $action = $edit . $delete;

            $data[] = array(
                $item->id,
                $item->procedure,
                // $item->mulk_price,
                // $item->market_price,
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

    function addHospitalProcedures(Request $request)
    {
        $item = new HospitalProcedures();
        $item->procedure = $request->procedure;
        // $item->market_price = $request->market_price;
        // $item->mulk_price = $request->mulk_price;
        $ta = new GoogleTranslate('ar');
        $item->ar_procedure = $ta->translate($request->procedure);        
        $tf = new GoogleTranslate('fr');
        $item->fr_procedure = $tf->translate($request->procedure);        
        $th = new GoogleTranslate('hi');
        $item->hi_procedure = $th->translate($request->procedure);        
        $tu = new GoogleTranslate('ur');
        $item->ur_procedure = $tu->translate($request->procedure);
        $item->save();

        return GlobalFunction::sendSimpleResponse(true, 'Healthcare Partner added successfully');
    }

    function editHospitalProcedures(Request $request)
    {   
        $item = HospitalProcedures::find($request->id);
        $item->procedure = $request->procedure;
        // $item->market_price = $request->market_price;
        // $item->mulk_price = $request->mulk_price;
        $ta = new GoogleTranslate('ar');
        $item->ar_procedure = $ta->translate($request->procedure);        
        $tf = new GoogleTranslate('fr');
        $item->fr_procedure = $tf->translate($request->procedure);        
        $th = new GoogleTranslate('hi');
        $item->hi_procedure = $th->translate($request->procedure);        
        $tu = new GoogleTranslate('ur');
        $item->ur_procedure = $tu->translate($request->procedure);
        $item->save();
        return GlobalFunction::sendSimpleResponse(true, 'Healthcare Partner edited successfully');
    }

    function deleteHospitalProcedures($id)
    {
        $item = HospitalProcedures::find($id);
        $item->is_deleted = 1;
        $item->save();

        return GlobalFunction::sendSimpleResponse(true, 'Healthcare Partner deleted successfully');
    }

    function viewBulkUploadHospitalProcedures(Request $request)
    {
        return view('smo.bulkUploadHospitalProcedures');
    }

    function viewBulkUploadHospitalProcedurePrice(Request $request)
    {
        return view('smo.viewBulkUploadHospitalProcedurePrice');
    }

    public function bulkUploadHospitalProcedures(Request $request)
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
            $ar_procedure = isset($collection['Procedure']) ? $ta->translate($collection['Procedure']) : null;
            $tf = new GoogleTranslate('fr');
            $fr_procedure = isset($collection['Procedure']) ? $tf->translate($collection['Procedure']) : null;
            $th = new GoogleTranslate('hi');
            $hi_procedure = isset($collection['Procedure']) ? $th->translate($collection['Procedure']) : null;
            $tu = new GoogleTranslate('ur');
            $ur_procedure = isset($collection['Procedure']) ? $tu->translate($collection['Procedure']) : null;

            $id =  DB::table('hospital_procedures')->insertGetId([
                "procedure" => $collection['Procedure'],
                "ar_procedure" => $ar_procedure,
                "ur_procedure" => $ur_procedure,
                "fr_procedure" => $fr_procedure,
                "hi_procedure" => $hi_procedure
            ]);
        }

        return back()->with('success', 'Hospital procedures imported successfully!');
    }

    // public function bulkUploadHospitalProcedures(Request $request)
    // {
    //     ini_set('max_execution_time', -1);
    //     try {
    //          $collections = (new FastExcel)->import($request->file('customer_file'));
    //     } catch (\Exception $exception) {
    //         Toastr::error('You have uploaded a wrong format file, please upload the right file.','',['timeOut' => 5000]);
    //         return back();
    //     }
    //     $data = [];
    //     $hospitalProcedures = [];

    //     foreach ($collections as $collection) {
    //         $hospitalId  = $collection['Hospital ID'];   // from excel
    //         $procedureId = (string) $collection['Procedure IDs']; // force string

    //         $hospitalProcedures[$hospitalId][] = $procedureId;
    //     }

    //     foreach ($hospitalProcedures as $hospitalId => $procedureIds) {

    //         DB::table('hospitals')
    //             ->where('id', $hospitalId)
    //             ->update([
    //                 'procedure_ids' => json_encode(array_values(array_unique($procedureIds)))
    //             ]);
    //     }

    //     return view('smo.bulkUploadHospitalProcedures');
    // }

    public function bulkUploadHospitalProcedurePrice(Request $request)
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

            $id =  DB::table('hospital_procedure_prices')->insertGetId([
                "hospital_id" => $collection['hospital_id'],
                "procedure_id" => $collection['procedure_id'],
                "price" => $collection['price'],
            ]);
        }

        return back()->with('success', 'Hospital procedure prices imported successfully!');
    }

    public function downloadHospitalProceduresFormat()
    {
        $filePath = storage_path('app/public/uploads/hospital_procedure_format.xlsx'); // absolute path

        if (!file_exists($filePath)) {
            return back()->with('error', 'File not found!');
        }

        return response()->download($filePath, 'hospital_procedure_format.xlsx');
    }

    public function downloadHospitalProcedurePriceFormat()
    {
        $filePath = storage_path('app/public/uploads/hospital_procedure_price.xlsx'); // absolute path

        if (!file_exists($filePath)) {
            return back()->with('error', 'File not found!');
        }

        return response()->download($filePath, 'hospital_procedure_price.xlsx');
    }

    function viewBulkUploadQueryProcedures(Request $request)
    {
        return view('smo.bulkUploadQueryProcedures');
    }

    public function bulkUploadQueryProcedures(Request $request)
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
            $ar_procedure = isset($collection['Procedure']) ? $ta->translate($collection['Procedure']) : null;
            $tf = new GoogleTranslate('fr');
            $fr_procedure = isset($collection['Procedure']) ? $tf->translate($collection['Procedure']) : null;
            $th = new GoogleTranslate('hi');
            $hi_procedure = isset($collection['Procedure']) ? $th->translate($collection['Procedure']) : null;
            $tu = new GoogleTranslate('ur');
            $ur_procedure = isset($collection['Procedure']) ? $tu->translate($collection['Procedure']) : null;

            $id =  DB::table('query_procedures')->insertGetId([
                "procedure" => $collection['Procedure'],
                "ar_procedure" => $ar_procedure,
                "ur_procedure" => $ur_procedure,
                "fr_procedure" => $fr_procedure,
                "hi_procedure" => $hi_procedure
            ]);
        }

        return back()->with('success', 'Query procedures imported successfully!');
    }

    public function downloadQueryProceduresFormat()
    {
        $filePath = storage_path('app/public/uploads/query_procedure_format.xlsx'); // absolute path

        if (!file_exists($filePath)) {
            return back()->with('error', 'File not found!');
        }

        return response()->download($filePath, 'query_procedure_format.xlsx');
    }

    function SMOQueries()
    {
        return view('smo.SMOQueries');
    }  

    function fetchSMOQueries(Request $request)
    {
        $totalData =  SMOQueries::where('is_deleted', 0)->count();
        $rows = SMOQueries::where('is_deleted', 0)->orderBy('id', 'DESC')->get();
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
            $result = SMOQueries::where('is_deleted', 0)->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
        } else {
            $search = $request->input('search.value');
            $result =  SMOQueries::where('is_deleted', 0)
                ->Where('full_name', 'LIKE', "%{$search}%")
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
            $totalFiltered = SMOQueries::where('is_deleted', 0)
                ->Where('full_name', 'LIKE', "%{$search}%")
                ->count();
        }
        // $currency_symbol = Settings::first();
        $data = array();
        foreach ($result as $item) {

            $procedure = QueryProcedures::where('id',$item->query_id)->pluck('procedure')->first();

            $smo_docs = SMOQueryDocs::where('smo_query_id', $item->id)->pluck('document')->toArray();
            $doc_list = implode(',', $smo_docs); 

            $edit = '<a href="#" 
                class="mr-2 btn btn-primary text-white edit" 
                data-procedure="' . $procedure . '" 
                data-full_name="' . $item->full_name . '" 
                data-medical_report="' . $item->medical_report . '" 
                data-contact_number="' . $item->contact_number . '" 
                data-email="' . $item->email . '" 
                data-location="' . $item->location . '" 
                data-comment="' . $item->comment . '" 
                data-docs="' . $doc_list . '"
                rel="' . (int)$item->id . '">' . __("View") . '</a>';

            // $delete = '<a href="#" class="mr-2 btn btn-danger text-white delete" rel="' . (int)$item->id . '">' . __("Delete") . '</a>';
            // $action = $edit . $delete;
            $action = $edit;

            

            $data[] = array(
                $item->id,
                $procedure,
                $item->full_name,
                $item->medical_report,
                $item->contact_number,
                $item->email,
                $item->location,
                $item->comment,
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

     function addSMOQueries(Request $request)
    {
        $item = new QueryProcedures();
        $item->procedure = $request->procedure;
        $ta = new GoogleTranslate('ar');
        $item->ar_procedure = $ta->translate($request->procedure);        
        $tf = new GoogleTranslate('fr');
        $item->fr_procedure = $tf->translate($request->procedure);        
        $th = new GoogleTranslate('hi');
        $item->hi_procedure = $th->translate($request->procedure);        
        $tu = new GoogleTranslate('ur');
        $item->ur_procedure = $tu->translate($request->procedure);
        $item->save();

        return GlobalFunction::sendSimpleResponse(true, 'Healthcare Partner added successfully');
    }

    function editSMOQueries(Request $request)
    {   
        $item = QueryProcedures::find($request->id);
        $item->procedure = $request->procedure;
        $ta = new GoogleTranslate('ar');
        $item->ar_procedure = $ta->translate($request->procedure);        
        $tf = new GoogleTranslate('fr');
        $item->fr_procedure = $tf->translate($request->procedure);        
        $th = new GoogleTranslate('hi');
        $item->hi_procedure = $th->translate($request->procedure);        
        $tu = new GoogleTranslate('ur');
        $item->ur_procedure = $tu->translate($request->procedure);
        $item->save();
        return GlobalFunction::sendSimpleResponse(true, 'Healthcare Partner edited successfully');
    }

    function deleteSMOQueries($id)
    {
        $item = QueryProcedures::find($id);
        $item->is_deleted = 1;
        $item->save();

        return GlobalFunction::sendSimpleResponse(true, 'Healthcare Partner deleted successfully');
    }

    function queryProcedures()
    {
        // $rows = QueryProcedures::where('is_deleted', 0)->get();

        // foreach ($rows as $key => $value) {
        //     $value->procedure = $value->procedure;
        //     $ta = new GoogleTranslate('ar');
        //     $value->ar_procedure = $ta->translate($value->procedure);        
        //     $tf = new GoogleTranslate('fr');
        //     $value->fr_procedure = $tf->translate($value->procedure);        
        //     $th = new GoogleTranslate('hi');
        //     $value->hi_procedure = $th->translate($value->procedure);        
        //     $tu = new GoogleTranslate('ur');
        //     $value->ur_procedure = $tu->translate($value->procedure);
        //     $value->save();
        // }
        return view('smo.queryProcedures');
    }  

    function fetchQueryProcedures(Request $request)
    {
        $totalData =  QueryProcedures::where('is_deleted', 0)->count();
        $rows = QueryProcedures::where('is_deleted', 0)->orderBy('id', 'DESC')->get();
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
            $result = QueryProcedures::where('is_deleted', 0)->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
        } else {
            $search = $request->input('search.value');
            $result =  QueryProcedures::where('is_deleted', 0)
                ->Where('procedure', 'LIKE', "%{$search}%")
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
            $totalFiltered = QueryProcedures::where('is_deleted', 0)
                ->Where('procedure', 'LIKE', "%{$search}%")
                ->count();
        }
        // $currency_symbol = Settings::first();
        $data = array();
        foreach ($result as $item) {

            $edit = '<a href="#" 
                class="mr-2 btn btn-primary text-white edit" 
                data-procedure="' . $item->procedure . '" 
                rel="' . (int)$item->id . '">' . __("Edit") . '</a>';

            $delete = '<a href="#" class="mr-2 btn btn-danger text-white delete" rel="' . (int)$item->id . '">' . __("Delete") . '</a>';
            $action = $edit . $delete;

            $data[] = array(
                $item->id,
                $item->procedure,
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

    function addQueryProcedures(Request $request)
    {
        $item = new QueryProcedures();
        $item->procedure = $request->procedure;
        $ta = new GoogleTranslate('ar');
        $item->ar_procedure = $ta->translate($request->procedure);        
        $tf = new GoogleTranslate('fr');
        $item->fr_procedure = $tf->translate($request->procedure);        
        $th = new GoogleTranslate('hi');
        $item->hi_procedure = $th->translate($request->procedure);        
        $tu = new GoogleTranslate('ur');
        $item->ur_procedure = $tu->translate($request->procedure);
        $item->save();

        return GlobalFunction::sendSimpleResponse(true, 'Healthcare Partner added successfully');
    }

    function editQueryProcedures(Request $request)
    {   
        $item = QueryProcedures::find($request->id);
        $item->procedure = $request->procedure;
        $ta = new GoogleTranslate('ar');
        $item->ar_procedure = $ta->translate($request->procedure);        
        $tf = new GoogleTranslate('fr');
        $item->fr_procedure = $tf->translate($request->procedure);        
        $th = new GoogleTranslate('hi');
        $item->hi_procedure = $th->translate($request->procedure);        
        $tu = new GoogleTranslate('ur');
        $item->ur_procedure = $tu->translate($request->procedure);
        $item->save();
        return GlobalFunction::sendSimpleResponse(true, 'Healthcare Partner edited successfully');
    }

    function deleteQueryProcedures($id)
    {
        $item = QueryProcedures::find($id);
        $item->is_deleted = 1;
        $item->save();

        return GlobalFunction::sendSimpleResponse(true, 'Healthcare Partner deleted successfully');
    }
    
    function trustedHealthcarePartners()
    {        
        return view('smo.trustedHealthcarePartners');
    }  

    function fetchTrustedHealthcarePartners(Request $request)
    {
        $totalData =  TrustedHealthcarePartners::where('is_deleted', 0)->count();
        $rows = TrustedHealthcarePartners::where('is_deleted', 0)->orderBy('id', 'DESC')->get();
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
            $result = TrustedHealthcarePartners::where('is_deleted', 0)->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
        } else {
            $search = $request->input('search.value');
            $result =  TrustedHealthcarePartners::where('is_deleted', 0)
                ->Where('name', 'LIKE', "%{$search}%")
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
            $totalFiltered = TrustedHealthcarePartners::where('is_deleted', 0)
                ->Where('name', 'LIKE', "%{$search}%")
                ->count();
        }
        // $currency_symbol = Settings::first();
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
                data-icon="' . $imgUrl . '"
                data-name="' . $item->name . '" 
                data-rating="' . $item->rating . '" 
                rel="' . (int)$item->id . '">' . __("Edit") . '</a>';

            $delete = '<a href="#" class="mr-2 btn btn-danger text-white delete" rel="' . (int)$item->id . '">' . __("Delete") . '</a>';
            $action = $edit . $delete;

            $data[] = array(
                $img,
                $item->name,
                $item->rating,
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

    function addTrustedHealthcarePartners(Request $request)
    {
        $item = new TrustedHealthcarePartners();
        $item->name = $request->name;
        $item->rating = $request->rating;
        $ta = new GoogleTranslate('ar');
        $item->ar_name = $ta->translate($request->name);        
        $tf = new GoogleTranslate('fr');
        $item->fr_name = $tf->translate($request->name);        
        $th = new GoogleTranslate('hi');
        $item->hi_name = $th->translate($request->name);        
        $tu = new GoogleTranslate('ur');
        $item->ur_name = $tu->translate($request->name);
        $item->image = GlobalFunction::saveFileAndGivePath($request->image);
        $item->save();

        return GlobalFunction::sendSimpleResponse(true, 'Healthcare Partner added successfully');
    }

    function editTrustedHealthcarePartners(Request $request)
    {   
        $item = TrustedHealthcarePartners::find($request->id);
        $item->name = $request->name;
        $item->rating = $request->rating;
        $ta = new GoogleTranslate('ar');
        $item->ar_name = $ta->translate($request->name);        
        $tf = new GoogleTranslate('fr');
        $item->fr_name = $tf->translate($request->name);        
        $th = new GoogleTranslate('hi');
        $item->hi_name = $th->translate($request->name);        
        $tu = new GoogleTranslate('ur');
        $item->ur_name = $tu->translate($request->name);
        if ($request->has('image')) {
            $item->image = GlobalFunction::saveFileAndGivePath($request->image);
        }
        $item->save();
        return GlobalFunction::sendSimpleResponse(true, 'Healthcare Partner edited successfully');
    }

    function deleteTrustedHealthcarePartners($id)
    {
        $item = TrustedHealthcarePartners::find($id);
        $item->is_deleted = 1;
        $item->save();

        return GlobalFunction::sendSimpleResponse(true, 'Healthcare Partner deleted successfully');
    }

    function topProcedures()
    {
        return view('smo.topProcedures');
    }  

    function fetchTopProcedures(Request $request)
    {
        $totalData =  TopProcedures::where('is_deleted', 0)->count();
        $rows = TopProcedures::where('is_deleted', 0)->orderBy('id', 'DESC')->get();
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
            $result = TopProcedures::where('is_deleted', 0)->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
        } else {
            $search = $request->input('search.value');
            $result =  TopProcedures::where('is_deleted', 0)
                ->Where('name', 'LIKE', "%{$search}%")
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
            $totalFiltered = TopProcedures::where('is_deleted', 0)
                ->Where('name', 'LIKE', "%{$search}%")
                ->count();
        }
        // $currency_symbol = Settings::first();
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
                data-icon="' . $imgUrl . '"
                data-name="' . $item->name . '" 
                data-description="' . $item->description . '" 
                rel="' . (int)$item->id . '">' . __("Edit") . '</a>';

            $delete = '<a href="#" class="mr-2 btn btn-danger text-white delete" rel="' . (int)$item->id . '">' . __("Delete") . '</a>';
            $action = $edit . $delete;

            $data[] = array(
                $img,
                $item->name,
                $item->description,
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

    function addTopProcedures(Request $request)
    {
        $item = new TopProcedures();
        $item->name = $request->name;
        $item->description = $request->description;
        $ta = new GoogleTranslate('ar');
        $item->ar_name = $ta->translate($item->name);
        $item->ar_description = $ta->translate($item->description);
        $tf = new GoogleTranslate('fr');
        $item->fr_name = $tf->translate($item->name);
        $item->fr_description = $tf->translate($item->description);
        $th = new GoogleTranslate('hi');
        $item->hi_name = $th->translate($item->name);
        $item->hi_description = $th->translate($item->description);
        $tu = new GoogleTranslate('ur');
        $item->ur_name = $tu->translate($item->name);
        $item->ur_description = $tu->translate($item->description);
        $item->image = GlobalFunction::saveFileAndGivePath($request->image);
        $item->save();

        return GlobalFunction::sendSimpleResponse(true, 'Healthcare Partner added successfully');
    }

    function editTopProcedures(Request $request)
    {   
        $item = TopProcedures::find($request->id);
        $item->name = $request->name;
        $item->description = $request->description;
        $ta = new GoogleTranslate('ar');
        $item->ar_name = $ta->translate($item->name);
        $item->ar_description = $ta->translate($item->description);
        $tf = new GoogleTranslate('fr');
        $item->fr_name = $tf->translate($item->name);
        $item->fr_description = $tf->translate($item->description);
        $th = new GoogleTranslate('hi');
        $item->hi_name = $th->translate($item->name);
        $item->hi_description = $th->translate($item->description);
        $tu = new GoogleTranslate('ur');
        $item->ur_name = $tu->translate($item->name);
        $item->ur_description = $tu->translate($item->description);
        if ($request->has('image')) {
            $item->image = GlobalFunction::saveFileAndGivePath($request->image);
        }
        $item->save();
        return GlobalFunction::sendSimpleResponse(true, 'Healthcare Partner edited successfully');
    }

    function deleteTopProcedures($id)
    {
        $item = TopProcedures::find($id);
        $item->is_deleted = 1;
        $item->save();

        return GlobalFunction::sendSimpleResponse(true, 'Healthcare Partner deleted successfully');
    }

    function mulkmedChoiceOfDoctors()
    {
        return view('smo.mulkmedChoiceOfDoctors');
    }  

    function fetchMulkmedChoiceOfDoctors(Request $request)
    {
        $totalData =  MulkmedChoiceOfDoctors::where('is_deleted', 0)->count();
        $rows = MulkmedChoiceOfDoctors::where('is_deleted', 0)->orderBy('id', 'DESC')->get();
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
            $result = MulkmedChoiceOfDoctors::where('is_deleted', 0)->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
        } else {
            $search = $request->input('search.value');
            $result =  MulkmedChoiceOfDoctors::where('is_deleted', 0)
                ->Where('name', 'LIKE', "%{$search}%")
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
            $totalFiltered = MulkmedChoiceOfDoctors::where('is_deleted', 0)
                ->Where('name', 'LIKE', "%{$search}%")
                ->count();
        }
        // $currency_symbol = Settings::first();
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
                data-icon="' . $imgUrl . '"
                data-doctor_id="' . $item->doctor_id . '" 
                data-description="' . $item->description . '" 
                rel="' . (int)$item->id . '">' . __("Edit") . '</a>';

            $delete = '<a href="#" class="mr-2 btn btn-danger text-white delete" rel="' . (int)$item->id . '">' . __("Delete") . '</a>';
            $action = $edit . $delete;

            $data[] = array(
                $img,
                $item->name,
                $item->description,
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

    function addMulkmedChoiceOfDoctors(Request $request)
    {
        $item = new MulkmedChoiceOfDoctors();
        $item->name = $request->name;
        $item->description = $request->description;
        $item->doctor_id = $request->doctor_id;
        $ta = new GoogleTranslate('ar');
        $item->ar_description = $ta->translate($item->description);
        $tf = new GoogleTranslate('fr');
        $item->fr_description = $tf->translate($item->description);
        $th = new GoogleTranslate('hi');
        $item->hi_description = $th->translate($item->description);
        $tu = new GoogleTranslate('ur');
        $item->ur_description = $tu->translate($item->description);
        $item->image = GlobalFunction::saveFileAndGivePath($request->image);
        $item->save();

        return GlobalFunction::sendSimpleResponse(true, 'Healthcare Partner added successfully');
    }

    function editMulkmedChoiceOfDoctors(Request $request)
    {   
        $item = MulkmedChoiceOfDoctors::find($request->id);
        $item->name = $request->name;
        $item->description = $request->description;
        $item->doctor_id = $request->doctor_id;
        $ta = new GoogleTranslate('ar');
        $item->ar_description = $ta->translate($item->description);
        $tf = new GoogleTranslate('fr');
        $item->fr_description = $tf->translate($item->description);
        $th = new GoogleTranslate('hi');
        $item->hi_description = $th->translate($item->description);
        $tu = new GoogleTranslate('ur');
        $item->ur_description = $tu->translate($item->description);
        if ($request->has('image')) {
            $item->image = GlobalFunction::saveFileAndGivePath($request->image);
        }
        $item->save();
        return GlobalFunction::sendSimpleResponse(true, 'Healthcare Partner edited successfully');
    }

    function deleteMulkmedChoiceOfDoctors($id)
    {
        $item = MulkmedChoiceOfDoctors::find($id);
        $item->is_deleted = 1;
        $item->save();

        return GlobalFunction::sendSimpleResponse(true, 'Healthcare Partner deleted successfully');
    }

    function whySecondOpinionMatters()
    {
        return view('smo.whySecondOpinionMatters');
    }  

    function fetchWhySecondOpinionMatters(Request $request)
    {
        $totalData =  WhySecondOpinionMatters::where('is_deleted', 0)->count();
        $rows = WhySecondOpinionMatters::where('is_deleted', 0)->orderBy('id', 'DESC')->get();
        $settings = GlobalSettings::first();

        $result = $rows;

        $columns = array(
            0 => 'id',
            1 => 'title'
        );

        $limit = $request->input('length');
        $start = $request->input('start');
        $order = $columns[$request->input('order.0.column')];
        $dir = $request->input('order.0.dir');

        $totalFiltered = $totalData;
        if (empty($request->input('search.value'))) {
            $result = WhySecondOpinionMatters::where('is_deleted', 0)->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
        } else {
            $search = $request->input('search.value');
            $result =  WhySecondOpinionMatters::where('is_deleted', 0)
                ->Where('title', 'LIKE', "%{$search}%")
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
            $totalFiltered = WhySecondOpinionMatters::where('is_deleted', 0)
                ->Where('title', 'LIKE', "%{$search}%")
                ->count();
        }
        // $currency_symbol = Settings::first();
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
                data-icon="' . $imgUrl . '"
                data-title="' . $item->title . '" 
                data-info="' . $item->info . '" 
                data-url="' . $item->url . '" 
                rel="' . (int)$item->id . '">' . __("Edit") . '</a>';

            $delete = '<a href="#" class="mr-2 btn btn-danger text-white delete" rel="' . (int)$item->id . '">' . __("Delete") . '</a>';
            $action = $edit . $delete;

            $data[] = array(
                $img,
                $item->name,
                $item->info,
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

    function addWhySecondOpinionMatters(Request $request)
    {
        $item = new WhySecondOpinionMatters();
        $item->title = $request->title;
        $item->image = GlobalFunction::saveFileAndGivePath($request->image);
        $item->info = $request->info;
        $item->url = $request->url;
        $item->save();

        return GlobalFunction::sendSimpleResponse(true, 'Healthcare Partner added successfully');
    }

    function editWhySecondOpinionMatters(Request $request)
    {   
        $item = WhySecondOpinionMatters::find($request->id);
        $item->title = $request->title;
        if ($request->has('image')) {
            $item->image = GlobalFunction::saveFileAndGivePath($request->image);
        }
        $item->info = $request->info;
        $item->url = $request->url;
        $item->save();
        return GlobalFunction::sendSimpleResponse(true, 'Healthcare Partner edited successfully');
    }

    function deleteWhySecondOpinionMatters($id)
    {
        $item = WhySecondOpinionMatters::find($id);
        $item->is_deleted = 1;
        $item->save();

        return GlobalFunction::sendSimpleResponse(true, 'Healthcare Partner deleted successfully');
    }

    function hospitals()
    {
        return view('smo.hospitals');
    }  

    function fetchHospitals(Request $request)
    {
        $totalData =  Hospitals::where('is_deleted', 0)->count();
        $rows = Hospitals::where('is_deleted', 0)->orderBy('id', 'DESC')->get();
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
            $result = Hospitals::where('is_deleted', 0)->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
        } else {
            $search = $request->input('search.value');
            $result =  Hospitals::where('is_deleted', 0)
                ->Where('name', 'LIKE', "%{$search}%")
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
            $totalFiltered = Hospitals::where('is_deleted', 0)
                ->Where('name', 'LIKE', "%{$search}%")
                ->count();
        }
        // $currency_symbol = Settings::first();
        $data = array();
        foreach ($result as $item) {

            $imgUrl = "http://placehold.jp/150x150.png";
            if ($item->image == null) {
                $img = '<img src="http://placehold.jp/150x150.png" width="50" height="50">';
            } else {
                $imgUrl = GlobalFunction::createMediaUrl($item->image);
                $img = '<img src="' . $imgUrl . '" width="50" height="50">';
            }

            // $category = HospitalCategories::whereIn('id', json_decode($item->category))->first();
            // $procedures = HospitalProcedures::whereIn('id', json_decode($item->procedure_ids))->get();
            $images = HospitalImages::where('is_deleted', 0)->where('hospital_id', $item->id)
                ->get(['id','image']); // adjust columns

            $imagePayload = $images->map(fn($img) => [
                'id'  => $img->id,
                'url' => asset('storage/'.$img->image), // or absolute URL if you store it directly
            ])->values();

            $edit = '<a href="#" 
                class="mr-2 btn btn-primary text-white edit" 
                data-icon="' . $imgUrl . '"
                data-name="' . $item->name . '" 
                data-rating="' . $item->rating . '" 
                data-rating_count="' . $item->rating_count . '" 
                data-country="' . $item->country . '" 
                data-address="' . $item->address . '" 
                data-longitude="' . $item->longitude . '" 
                data-latitude="' . $item->latitude . '" 
                data-website="' . $item->website . '" 
                data-contact_number="' . $item->contact_number . '" 
                data-category="' . htmlspecialchars(json_encode($item->category), ENT_QUOTES, 'UTF-8') . '" 
                data-procedure_ids="' . htmlspecialchars(json_encode($item->procedure_ids), ENT_QUOTES, 'UTF-8') . '" 
                data-clinic_timing="' . $item->clinic_timing . '" 
                data-services_offered="' . $item->services_offered . '" 
                data-exclusive_mulkmed_benefits="' . $item->exclusive_mulkmed_benefits . '"
                data-images=\'' . e($imagePayload->toJson()) . '\'
                rel="' . (int)$item->id . '">' . __("Edit") . '</a>';

            $delete = '<a href="#" class="mr-2 btn btn-danger text-white delete" rel="' . (int)$item->id . '">' . __("Delete") . '</a>';
            $action = $edit . $delete;

            $data[] = array(
                $item->id,
                $img,
                $item->name,
                $item->rating,
                $item->rating_count,
                $item->country,
                $item->address,
                $item->website,
                $item->contact_number,
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

    function addHospitals(Request $request)
    {
        $item = new Hospitals();
        $item->image = GlobalFunction::saveFileAndGivePath($request->image);
        $item->name = $request->name;
        $item->rating = $request->rating;
        $item->rating_count = $request->rating_count;
        $item->country = $request->country;
        $item->address = $request->address;
        $item->longitude = $request->longitude;
        $item->latitude = $request->latitude;
        $item->website = $request->website;
        $item->contact_number = $request->contact_number;
        $item->category = json_encode($request->category);
        $item->clinic_timing = $request->clinic_timing;
        $item->services_offered = $request->services_offered;
        $item->exclusive_mulkmed_benefits = $request->exclusive_mulkmed_benefits;
        $item->procedure_ids = json_encode($request->procedure_ids);
        if($item->country != null)
        {
            $ta = new GoogleTranslate('ar');
            $item->ar_country = $ta->translate($item->country);   
            $tf = new GoogleTranslate('fr');
            $item->fr_country = $tf->translate($item->country);
            $th = new GoogleTranslate('hi');
            $item->hi_country = $th->translate($item->country);
            $tu = new GoogleTranslate('ur');
            $item->ur_country = $tu->translate($item->country);
        }

        if($item->clinic_timing != null)
        {
            $ta = new GoogleTranslate('ar');
            $item->ar_clinic_timing = $ta->translate($item->clinic_timing);   
            $tf = new GoogleTranslate('fr');
            $item->fr_clinic_timing = $tf->translate($item->clinic_timing);
            $th = new GoogleTranslate('hi');
            $item->hi_clinic_timing = $th->translate($item->clinic_timing);
            $tu = new GoogleTranslate('ur');
            $item->ur_clinic_timing = $tu->translate($item->clinic_timing);
        }

        if($item->name != null)
        {
            $ta = new GoogleTranslate('ar');
            $item->ar_name = $ta->translate($item->name);   
            $tf = new GoogleTranslate('fr');
            $item->fr_name = $tf->translate($item->name);
            $th = new GoogleTranslate('hi');
            $item->hi_name = $th->translate($item->name);
            $tu = new GoogleTranslate('ur');
            $item->ur_name = $tu->translate($item->name);
        }
        if($item->address != null)
        {
            $ta = new GoogleTranslate('ar');
            $item->ar_address = $ta->translate($item->address);   
            $tf = new GoogleTranslate('fr');
            $item->fr_address = $tf->translate($item->address);
            $th = new GoogleTranslate('hi');
            $item->hi_address = $th->translate($item->address);
            $tu = new GoogleTranslate('ur');
            $item->ur_address = $tu->translate($item->address);
        }
        if($item->services_offered != null)
        {
            $ta = new GoogleTranslate('ar');
            $item->ar_services_offered = $ta->translate($item->services_offered);   
            $tf = new GoogleTranslate('fr');
            $item->fr_services_offered = $tf->translate($item->services_offered);
            $th = new GoogleTranslate('hi');
            $item->hi_services_offered = $th->translate($item->services_offered);
            $tu = new GoogleTranslate('ur');
            $item->ur_services_offered = $tu->translate($item->services_offered);
        }
        if($item->exclusive_mulkmed_benefits != null)
        {
            $ta = new GoogleTranslate('ar');
            $item->ar_exclusive_mulkmed_benefits = $ta->translate($item->exclusive_mulkmed_benefits);   
            $tf = new GoogleTranslate('fr');
            $item->fr_exclusive_mulkmed_benefits = $tf->translate($item->exclusive_mulkmed_benefits);
            $th = new GoogleTranslate('hi');
            $item->hi_exclusive_mulkmed_benefits = $th->translate($item->exclusive_mulkmed_benefits);
            $tu = new GoogleTranslate('ur');
            $item->ur_exclusive_mulkmed_benefits = $tu->translate($item->exclusive_mulkmed_benefits);
        }
        $item->save();

        if ($request->has('photos')) {
            foreach ($request->photos as $photo) {
                $hospital_image = new HospitalImages();
                $hospital_image->hospital_id = $item->id;
                $hospital_image->image = GlobalFunction::saveFileAndGivePath($photo);
                $hospital_image->save();
            }
        }

        return GlobalFunction::sendSimpleResponse(true, 'Hospital added successfully');
    }

    function editHospitals(Request $request)
    {   
        // return $request;
        $item = Hospitals::find($request->id);

        if($request->hasFile('image')){
            $item->image = GlobalFunction::saveFileAndGivePath($request->image);
        }

        $item->name = $request->name;
        $item->rating = $request->rating;
        $item->rating_count = $request->rating_count;
        $item->country = $request->country;
        $item->address = $request->address;
        $item->longitude = $request->longitude;
        $item->latitude = $request->latitude;
        $item->website = $request->website;
        $item->contact_number = $request->contact_number;
        $item->category = $request->category;
        $item->clinic_timing = $request->clinic_timing;
        $item->services_offered = $request->services_offered;
        $item->exclusive_mulkmed_benefits = $request->exclusive_mulkmed_benefits;
        $item->procedure_ids = $request->procedure_ids;
        if($item->country != null)
        {
            $ta = new GoogleTranslate('ar');
            $item->ar_country = $ta->translate($item->country);   
            $tf = new GoogleTranslate('fr');
            $item->fr_country = $tf->translate($item->country);
            $th = new GoogleTranslate('hi');
            $item->hi_country = $th->translate($item->country);
            $tu = new GoogleTranslate('ur');
            $item->ur_country = $tu->translate($item->country);
        }
        if($item->clinic_timing != null)
        {
            $ta = new GoogleTranslate('ar');
            $item->ar_clinic_timing = $ta->translate($item->clinic_timing);   
            $tf = new GoogleTranslate('fr');
            $item->fr_clinic_timing = $tf->translate($item->clinic_timing);
            $th = new GoogleTranslate('hi');
            $item->hi_clinic_timing = $th->translate($item->clinic_timing);
            $tu = new GoogleTranslate('ur');
            $item->ur_clinic_timing = $tu->translate($item->clinic_timing);
        }
        if($item->name != null)
        {
            $ta = new GoogleTranslate('ar');
            $item->ar_name = $ta->translate($item->name);   
            $tf = new GoogleTranslate('fr');
            $item->fr_name = $tf->translate($item->name);
            $th = new GoogleTranslate('hi');
            $item->hi_name = $th->translate($item->name);
            $tu = new GoogleTranslate('ur');
            $item->ur_name = $tu->translate($item->name);
        }
        if($item->address != null)
        {
            $ta = new GoogleTranslate('ar');
            $item->ar_address = $ta->translate($item->address);   
            $tf = new GoogleTranslate('fr');
            $item->fr_address = $tf->translate($item->address);
            $th = new GoogleTranslate('hi');
            $item->hi_address = $th->translate($item->address);
            $tu = new GoogleTranslate('ur');
            $item->ur_address = $tu->translate($item->address);
        }
        if($item->services_offered != null)
        {
            $ta = new GoogleTranslate('ar');
            $item->ar_services_offered = $ta->translate($item->services_offered);   
            $tf = new GoogleTranslate('fr');
            $item->fr_services_offered = $tf->translate($item->services_offered);
            $th = new GoogleTranslate('hi');
            $item->hi_services_offered = $th->translate($item->services_offered);
            $tu = new GoogleTranslate('ur');
            $item->ur_services_offered = $tu->translate($item->services_offered);
        }
        if($item->exclusive_mulkmed_benefits != null)
        {
            $ta = new GoogleTranslate('ar');
            $item->ar_exclusive_mulkmed_benefits = $ta->translate($item->exclusive_mulkmed_benefits);   
            $tf = new GoogleTranslate('fr');
            $item->fr_exclusive_mulkmed_benefits = $tf->translate($item->exclusive_mulkmed_benefits);
            $th = new GoogleTranslate('hi');
            $item->hi_exclusive_mulkmed_benefits = $th->translate($item->exclusive_mulkmed_benefits);
            $tu = new GoogleTranslate('ur');
            $item->ur_exclusive_mulkmed_benefits = $tu->translate($item->exclusive_mulkmed_benefits);
        }
        $item->save();

        if ($request->hasFile('replace_images')) {
            foreach ($request->file('replace_images') as $id => $file) {
                if ($file) {
                    $path = GlobalFunction::saveFileAndGivePath($file);
                    HospitalImages::where('id', (int)$id)->update(['image' => $path]);
                }
            }
        }

        if ($request->has('photos')) {
            foreach ($request->photos as $photo) {
                $hospital_image = new HospitalImages();
                $hospital_image->hospital_id = $item->id;
                $hospital_image->image = GlobalFunction::saveFileAndGivePath($photo);
                $hospital_image->save();
            }
        }

        if ($request->has('remove_images')) {
                $ids = $request->input('remove_images', []);

                $ids = array_values(array_map('intval', array_filter($ids, function ($v) {
                    return !is_null($v) && $v !== '' && $v !== 'null';
                })));

                if (!empty($ids)) {
                    // return $ids;
                    HospitalImages::whereIn('id', $ids)->update(['is_deleted' => 1]);
                }

            }


        return GlobalFunction::sendSimpleResponse(true, 'Hospital edited successfully');
    }

    function deleteHospitals($id)
    {
        $item = Hospitals::find($id);
        $item->is_deleted = 1;
        $item->save();

        return GlobalFunction::sendSimpleResponse(true, 'Hospital deleted successfully');
    }

    function getCategories(Request $request)
    {
        if($request->has('search'))
        {
            $hospital_categories = HospitalCategories::where('name','LIKE', "%{$request->search}%")->where('is_deleted', 0)->orderBy('id', 'DESC')->get();
        }else{
            $hospital_categories = HospitalCategories::where('is_deleted', 0)->orderBy('id', 'DESC')->get();
        }
        return response()->json(['status' => true, 'hospital_categories' => $hospital_categories]);
    }

    function getProcedures(Request $request)
    {
        if($request->has('search'))
        {
            $hospital_categories = HospitalProcedures::where('procedure','LIKE', "%{$request->search}%")->where('is_deleted', 0)->orderBy('id', 'DESC')->get();
        }else{
            $hospital_categories = HospitalProcedures::where('is_deleted', 0)->orderBy('id', 'DESC')->get();
        }
        return response()->json(['status' => true, 'hospital_categories' => $hospital_categories]);
    }

    function viewBulkUploadHospitals(Request $request)
    {
        return view('smo.bulkUploadHospitals');
    }

    function viewBulkUploadTopHospital(Request $request)
    {
        return view('smo.bulkUploadTopHospital');
    }

    function viewBulkUploadWhySecondOpinionMatters(Request $request)
    {
        return view('smo.bulkUploadwhySecondOpinionMatters');
    }

    function viewBulkUploadTrustedHealthcarePartners(Request $request)
    {
        return view('smo.bulkUploadTrustedHealthcarePartners');
    }

    function viewBulkUploadHospitalCategories(Request $request)
    {
        return view('smo.bulkUploadHospitalCategories');
    }

    function viewBulkUploadTopProcedures(Request $request)
    {
        return view('smo.bulkUploadTopProcedures');
    }

    public function bulkUploadTopHospitals(Request $request)
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
            // return $rows;
            if (empty($row['B']) || $rowIndex === 1) continue;

            $hospitalName = $row['B'] ?? null;
            $hospitalId = $row['C'] ?? null;
            $rating = $row['D'] ?? null;
            $priority = $row['E'] ?? null;

            // find image in that row (if in column C)
            $imageCoordinate = 'A' . $rowIndex;
            $imageName = $imageMap[$imageCoordinate] ?? null;

            $ta = new GoogleTranslate('ar');
            $ar_name = $ta->translate($hospitalName);   
            $tf = new GoogleTranslate('fr');
            $fr_name = $tf->translate($hospitalName);
            $th = new GoogleTranslate('hi');
            $hi_name = $th->translate($hospitalName);
            $tu = new GoogleTranslate('ur');
            $ur_name = $tu->translate($hospitalName);
            
            $hospital = TopHospitals::create([
                    'name' => $hospitalName,
                    'hospital_id' => $hospitalId,
                    'image' => $imageName,
                    'rating' => $rating,
                    'priority' => $priority,
                    'ar_name' => $ar_name,
                    'fr_name' => $fr_name,
                    'hi_name' => $hi_name,
                    'ur_name' => $ur_name

                ]);
        }

        return back()->with('success', 'Top Hospitals imported successfully!');
    }

    public function bulkUploadWhySecondOpinionMatters(Request $request)
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
            // return $rows;
            if (empty($row['B']) || $rowIndex === 1) continue;

            $title = $row['B'] ?? null;
            $info = $row['C'] ?? null;
            $url = $row['D'] ?? null;

            // find image in that row (if in column C)
            $imageCoordinate = 'A' . $rowIndex;
            $imageName = $imageMap[$imageCoordinate] ?? null;
            
            $hospital = WhySecondOpinionMatters::create([
                    'title' => $title,
                    'image' => $imageName,
                    'info' => $info,
                    'url' => $url,

                ]);
        }

        return back()->with('success', 'WhySecondOpinionMatters imported successfully!');
    }

    public function bulkUploadTrustedHealthcarePartners(Request $request)
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
            // return $rows;
            if (empty($row['B']) || $rowIndex === 1) continue;

            $name = $row['B'] ?? null;
            $rating = $row['C'] ?? null;
            $url = $row['D'] ?? null;

            // find image in that row (if in column C)
            $imageCoordinate = 'A' . $rowIndex;
            $imageName = $imageMap[$imageCoordinate] ?? null;

            $ta = new GoogleTranslate('ar');
            $ar_name = $ta->translate($name);   
            $tf = new GoogleTranslate('fr');
            $fr_name = $tf->translate($name);
            $th = new GoogleTranslate('hi');
            $hi_name = $th->translate($name);
            $tu = new GoogleTranslate('ur');
            $ur_name = $tu->translate($name);
            
            $hospital = TrustedHealthcarePartners::create([
                    'name' => $name,
                    'image' => $imageName,
                    'rating' => $rating,
                    'url' => $url,
                    'ar_name' => $ar_name,
                    'fr_name' => $fr_name,
                    'hi_name' => $hi_name,
                    'ur_name' => $ur_name

                ]);
        }

        return back()->with('success', 'TrustedHealthcarePartners imported successfully!');
    }

    public function bulkUploadHospitalCategories(Request $request)
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
            // return $rows;
            if (empty($row['B']) || $rowIndex === 1) continue;

            $name = $row['B'] ?? null;

            // find image in that row (if in column C)
            $imageCoordinate = 'A' . $rowIndex;
            $imageName = $imageMap[$imageCoordinate] ?? null;

            $ta = new GoogleTranslate('ar');
            $ar_name = $ta->translate($name);   
            $tf = new GoogleTranslate('fr');
            $fr_name = $tf->translate($name);
            $th = new GoogleTranslate('hi');
            $hi_name = $th->translate($name);
            $tu = new GoogleTranslate('ur');
            $ur_name = $tu->translate($name);
            
            $hospital = HospitalCategories::create([
                    'name' => $name,
                    'image' => $imageName,
                    'ar_name' => $ar_name,
                    'fr_name' => $fr_name,
                    'hi_name' => $hi_name,
                    'ur_name' => $ur_name

                ]);
        }

        return back()->with('success', 'Hospital Categories imported successfully!');
    }

    public function bulkUploadTopProcedures(Request $request)
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
            // return $rows;
            if (empty($row['B']) || $rowIndex === 1) continue;

            $name = $row['B'] ?? null;
            $description = $row['C'] ?? null;

            // find image in that row (if in column C)
            $imageCoordinate = 'A' . $rowIndex;
            $imageName = $imageMap[$imageCoordinate] ?? null;

            $ta = new GoogleTranslate('ar');
            $ar_name = $ta->translate($name);   
            $tf = new GoogleTranslate('fr');
            $fr_name = $tf->translate($name);
            $th = new GoogleTranslate('hi');
            $hi_name = $th->translate($name);
            $tu = new GoogleTranslate('ur');
            $ur_name = $tu->translate($name);

            $ta = new GoogleTranslate('ar');
            $ar_description = $ta->translate($description);   
            $tf = new GoogleTranslate('fr');
            $fr_description = $tf->translate($description);
            $th = new GoogleTranslate('hi');
            $hi_description = $th->translate($description);
            $tu = new GoogleTranslate('ur');
            $ur_description = $tu->translate($description);
            
            $hospital = TopProcedures::create([
                    'name' => $name,
                    'image' => $imageName,
                    'description' => $description,
                    'ar_name' => $ar_name,
                    'fr_name' => $fr_name,
                    'hi_name' => $hi_name,
                    'ur_name' => $ur_name,
                    'ar_description' => $ar_description,
                    'fr_description' => $fr_description,
                    'hi_description' => $hi_description,
                    'ur_description' => $ur_description

                ]);
        }

        return back()->with('success', 'Top Procedures imported successfully!');
    }

    public function downloadTopHospitalFormat()
    {
        $filePath = storage_path('app/public/uploads/top_hospital_format.xlsx'); // absolute path

        if (!file_exists($filePath)) {
            return back()->with('error', 'File not found!');
        }

        return response()->download($filePath, 'top_hospital_format.xlsx');
    }

    public function downloadWhySecondOpinionMattersFormat()
    {
        $filePath = storage_path('app/public/uploads/why_second_opinion_matters.xlsx'); // absolute path

        if (!file_exists($filePath)) {
            return back()->with('error', 'File not found!');
        }

        return response()->download($filePath, 'why_second_opinion_matters.xlsx');
    }

    public function downloadTrustedHealthcarePartnersFormat()
    {
        $filePath = storage_path('app/public/uploads/trusted_healthcare_partners.xlsx'); // absolute path

        if (!file_exists($filePath)) {
            return back()->with('error', 'File not found!');
        }
        
        return response()->download($filePath, 'trusted_healthcare_partners.xlsx');
    }

    public function downloadHospitalCategoriesFormat()
    {
        $filePath = storage_path('app/public/uploads/hospital_categories.xlsx'); // absolute path

        if (!file_exists($filePath)) {
            return back()->with('error', 'File not found!');
        }
        
        return response()->download($filePath, 'hospital_categories.xlsx');
    }

        public function downloadTopProceduresFormat()
    {
        $filePath = storage_path('app/public/uploads/top_procedures.xlsx'); // absolute path

        if (!file_exists($filePath)) {
            return back()->with('error', 'File not found!');
        }
        
        return response()->download($filePath, 'top_procedures.xlsx');
    }

    public function bulkUploadHospitals(Request $request)
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

            $hospitalName = $row['B'] ?? null;
            $rating = $row['C'] ?? null;
            $rating_count = $row['D'] ?? null;
            $country = $row['E'] ?? null;
            $address = $row['F'] ?? null;
            $latitude = $row['G'] ?? null;
            $longitude = $row['H'] ?? null;
            $website = $row['I'] ?? null;
            $contact_number = $row['J'] ?? null;
            $clinic_timing = $row['L'] ?? null;
            $services_offered = $row['M'] ?? null;
            $exclusive_mulkmed_benefits = $row['N'] ?? null;
            $procedures = $row['O'] ?? null;
            

            // find image in that row (if in column C)
            $imageCoordinate = 'A' . $rowIndex;
            $imageName = $imageMap[$imageCoordinate] ?? null;

            // parse CSV-like columns into arrays, then JSON-encode for DB
            $categories = $row['K'];
            // collect(explode(',', $row['K'] ?? ''))
            // ->map(fn($v) => trim($v))
            // ->filter()
            // ->values()
            // ->all();

            // $procedures = $row['O'];
            $procedures = collect(explode(',', $row['O'] ?? ''))
            ->map(fn($v) => trim($v))
            ->filter()
            ->values()
            ->all();

            if($country != null)
            {
                $ta = new GoogleTranslate('ar');
                $ar_country = $ta->translate($country);   
                $tf = new GoogleTranslate('fr');
                $fr_country = $tf->translate($country);
                $th = new GoogleTranslate('hi');
                $hi_country = $th->translate($country);
                $tu = new GoogleTranslate('ur');
                $ur_country = $tu->translate($country);
            }else{
                $ar_country = null;
                $fr_country = null;
                $hi_country = null;
                $ur_country = null;
            }

            if($clinic_timing != null)
            {
                $ta = new GoogleTranslate('ar');
                $ar_clinic_timing = $ta->translate($clinic_timing);   
                $tf = new GoogleTranslate('fr');
                $fr_clinic_timing = $tf->translate($clinic_timing);
                $th = new GoogleTranslate('hi');
                $hi_clinic_timing = $th->translate($clinic_timing);
                $tu = new GoogleTranslate('ur');
                $ur_clinic_timing = $tu->translate($clinic_timing);
            }else{
                $ar_clinic_timing = null;
                $fr_clinic_timing = null;
                $hi_clinic_timing = null;
                $ur_clinic_timing = null;
            }

            if($hospitalName != null)
            {
                $ta = new GoogleTranslate('ar');
                $ar_name = $ta->translate($hospitalName);   
                $tf = new GoogleTranslate('fr');
                $fr_name = $tf->translate($hospitalName);
                $th = new GoogleTranslate('hi');
                $hi_name = $th->translate($hospitalName);
                $tu = new GoogleTranslate('ur');
                $ur_name = $tu->translate($hospitalName);
            }else{
                $ar_name = null;
                $fr_name = null;
                $hi_name = null;
                $ur_name = null;
            }

            if($address != null)
            {
                $ta = new GoogleTranslate('ar');
                $ar_address = $ta->translate($address);   
                $tf = new GoogleTranslate('fr');
                $fr_address = $tf->translate($address);
                $th = new GoogleTranslate('hi');
                $hi_address = $th->translate($address);
                $tu = new GoogleTranslate('ur');
                $ur_address = $tu->translate($address);
            }else{
                $ar_address = null;
                $fr_address = null;
                $hi_address = null;
                $ur_address = null;
            }

            if($services_offered != null)
            {
                $ta = new GoogleTranslate('ar');
                $ar_services_offered = $ta->translate($services_offered);   
                $tf = new GoogleTranslate('fr');
                $fr_services_offered = $tf->translate($services_offered);
                $th = new GoogleTranslate('hi');
                $hi_services_offered = $th->translate($services_offered);
                $tu = new GoogleTranslate('ur');
                $ur_services_offered = $tu->translate($services_offered);
            }else{
                $ar_services_offered = null;
                $fr_services_offered = null;
                $hi_services_offered = null;
                $ur_services_offered = null;
            }

            if($exclusive_mulkmed_benefits != null)
            {
                $ta = new GoogleTranslate('ar');
                $ar_exclusive_mulkmed_benefits = $ta->translate($exclusive_mulkmed_benefits);   
                $tf = new GoogleTranslate('fr');
                $fr_exclusive_mulkmed_benefits = $tf->translate($exclusive_mulkmed_benefits);
                $th = new GoogleTranslate('hi');
                $hi_exclusive_mulkmed_benefits = $th->translate($exclusive_mulkmed_benefits);
                $tu = new GoogleTranslate('ur');
                $ur_exclusive_mulkmed_benefits = $tu->translate($exclusive_mulkmed_benefits);
            }else{
                $ar_exclusive_mulkmed_benefits = null;
                $fr_exclusive_mulkmed_benefits = null;
                $hi_exclusive_mulkmed_benefits = null;
                $ur_exclusive_mulkmed_benefits = null;
            }
            
            $hospital = Hospitals::create([
                    'name' => $hospitalName,
                    'image' => $imageName,
                    'rating' => $rating,
                    'rating_count' => $rating_count,
                    'country' => $country,
                    'address' => $address,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'website' => $website,
                    'contact_number' => $contact_number,
                    'category' => $categories,
                    'clinic_timing' => $clinic_timing,
                    'services_offered' => $services_offered,
                    'exclusive_mulkmed_benefits' => $exclusive_mulkmed_benefits,
                    'procedure_ids' => json_encode($procedures),
                    'ar_address' => $ar_address,
                    'fr_address' => $fr_address,
                    'hi_address' => $hi_address,
                    'ur_address' => $ur_address,
                    'ar_name' => $ar_name,
                    'fr_name' => $fr_name,
                    'hi_name' => $hi_name,
                    'ur_name' => $ur_name,
                    'ar_country' => $ar_country,
                    'fr_country' => $fr_country,
                    'hi_country' => $hi_country,
                    'ur_country' => $ur_country,
                    'ar_clinic_timing' => $ar_clinic_timing,
                    'fr_clinic_timing' => $fr_clinic_timing,
                    'hi_clinic_timing' => $hi_clinic_timing,
                    'ur_clinic_timing' => $ur_clinic_timing,
                    'ar_services_offered' => $ar_services_offered,
                    'fr_services_offered' => $fr_services_offered,
                    'hi_services_offered' => $hi_services_offered,
                    'ur_services_offered' => $ur_services_offered,
                    'ar_exclusive_mulkmed_benefits' => $ar_exclusive_mulkmed_benefits,
                    'fr_exclusive_mulkmed_benefits' => $fr_exclusive_mulkmed_benefits,
                    'hi_exclusive_mulkmed_benefits' => $hi_exclusive_mulkmed_benefits,
                    'ur_exclusive_mulkmed_benefits' => $ur_exclusive_mulkmed_benefits,
                ]);
        $startColAscii = ord('P');
        $endColAscii = ord(array_key_last($row)); // last column in the row (dynamic)

        for ($ascii = $startColAscii; $ascii <= $endColAscii; $ascii++) {
            $col = chr($ascii);
            $cellCoordinate = $col . $rowIndex;

            // --- Embedded Excel Images ---
            $embeddedPaths = $imageMap[$cellCoordinate] ?? [];

            if(empty($embeddedPaths)) break;
            
        
                HospitalImages::create([
                    'hospital_id' => $hospital->id,
                    'image'  => $embeddedPaths,
                ]);
        
            }
        }

        return back()->with('success', 'Hospitals imported successfully!');
    }

    public function downloadHospitalFormat()
    {
        $filePath = storage_path('app/public/uploads/hospital_format.xlsx'); // absolute path

        if (!file_exists($filePath)) {
            return back()->with('error', 'File not found!');
        }

        return response()->download($filePath, 'hospital_format.xlsx');
    }

}
