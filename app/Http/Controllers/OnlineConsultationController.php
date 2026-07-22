<?php

namespace App\Http\Controllers;

use App\Classes\AgoraDynamicKey\RtcTokenBuilder;

use App\Models\DoctorPlans;
use App\Models\CommonHealthProblems;
use App\Models\DoctorCategories;
use App\Models\SpecialityWiseDisease;
use App\Models\GlobalFunction;
use App\Models\GlobalSettings;
use App\Models\Banners;
use App\Models\Doctors;
use App\Models\Constants;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Google\Client;
use Stichoza\GoogleTranslate\GoogleTranslate;

// include "./app/Class/AgoraDynamicKey/RtcTokenBuilder.php";

class OnlineConsultationController extends Controller
{
    function doctorPlans()
    {
        return view('doctorPlans');
    }  

    function fetchDoctorPlansList(Request $request)
    {
        $totalData =  DoctorPlans::where('is_deleted', 0)->count();
        $rows = DoctorPlans::where('is_deleted', 0)->orderBy('id', 'DESC')->get();
        $settings = GlobalSettings::first();

        $result = $rows;

        $columns = array(
            0 => 'id',
            1 => 'plan_name',
            2 => 'original_price',
            3 => 'discount',
            4 => 'discount_type',
            5 => 'hh_price',
            6 => 'number_of_consultations',
            7 => 'number_of_days',
            8 => 'consultation_text',
        );

        $limit = $request->input('length');
        $start = $request->input('start');
        $order = $columns[$request->input('order.0.column')];
        $dir = $request->input('order.0.dir');

        $totalFiltered = $totalData;
        if (empty($request->input('search.value'))) {
            $result = DoctorPlans::where('is_deleted', 0)->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
        } else {
            $search = $request->input('search.value');
            $result =  DoctorPlans::where('is_deleted', 0)
                ->Where('plan_name', 'LIKE', "%{$search}%")
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
            $totalFiltered = DoctorPlans::where('is_deleted', 0)
                ->Where('plan_name', 'LIKE', "%{$search}%")
                ->count();
        }
        // $currency_symbol = Settings::first();
        $data = array();
        foreach ($result as $item) {

            $settings = GlobalSettings::first();

            $currency_symbol = $settings->currency;
            
            $original_price = $currency_symbol . $item->original_price;
            $discount = $currency_symbol . $item->discount;
            $hh_price = $currency_symbol . $item->hh_price;


           $edit = '<a href="#" 
            class="mr-2 btn btn-primary text-white edit" 
            data-plan_name="' . htmlspecialchars($item->plan_name) . '" 
            data-original_price="' . $item->original_price . '" 
            data-discount="' . $item->discount . '" 
            data-discount_type="' . $item->discount_type . '" 
            data-hh_price="' . $item->hh_price . '" 
            data-number_of_consultations="' . $item->number_of_consultations . '" 
            data-number_of_days="' . $item->number_of_days . '"
            data-consultation_text="' . $item->consultation_text . '" 
            rel="' . $item->id . '">' . __("Edit") . '</a>';

            $delete = '<a href="" class="mr-2 btn btn-danger text-white delete" rel=' . $item->id . ' >' . __("Delete") . '</a>';
            $action =  $edit . $delete;


            $data[] = array(
                $item->plan_name,
                $original_price,
                $discount,
                $item->discount_type,
                $hh_price,
                $item->number_of_consultations,
                $item->number_of_days,
                $item->consultation_text,
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

    function addDoctorPlan(Request $request)
    {
        $item = new DoctorPlans();
        $item->plan_name = $request->plan_name;
        $item->doctor_ids = $request->doctor_ids;
        $item->original_price = $request->original_price;
        $item->discount = $request->discount;
        $item->discount_type = $request->discount_type;
        $item->hh_price = $request->hh_price;
        $item->number_of_consultations = $request->number_of_consultations;
        $item->number_of_days = $request->number_of_days;
        $item->consultation_text = $request->consultation_text;
        $ta = new GoogleTranslate('ar');
        $item->ar_plan_name = $ta->translate($request->plan_name);
        $item->ar_number_of_days = $request->number_of_days != null ? $ta->translate($request->number_of_days) : null;
        $item->ar_consultation_text = $request->consultation_text != null ? $ta->translate($request->consultation_text) : null;
        $tf = new GoogleTranslate('fr');
        $item->fr_plan_name = $tf->translate($request->plan_name);
        $item->fr_number_of_days = $request->number_of_days != null ? $tf->translate($request->number_of_days) : null;
        $item->fr_consultation_text = $request->consultation_text != null ? $tf->translate($request->consultation_text) : null;
        $th = new GoogleTranslate('hi');
        $item->hi_plan_name = $th->translate($request->plan_name);
        $item->hi_number_of_days = $request->number_of_days != null ? $th->translate($request->number_of_days) : null;
        $item->hi_consultation_text = $request->consultation_text != null ? $th->translate($request->consultation_text) : null;
        $tu = new GoogleTranslate('ur');
        $item->ur_plan_name = $tu->translate($request->plan_name);
        $item->ur_number_of_days = $request->number_of_days != null ? $tu->translate($request->number_of_days) : null;
        $item->ur_consultation_text = $request->consultation_text != null ? $tu->translate($request->consultation_text) : null;
        $item->save();

        return GlobalFunction::sendSimpleResponse(true, 'plan added successfully');
    }

    function editDoctorPlan(Request $request)
    {   
        $item = DoctorPlans::find($request->id);
        $item->plan_name = $request->plan_name;
        $item->doctor_ids = $request->doctor_ids;
        $item->original_price = $request->original_price;
        $item->discount = $request->discount;
        $item->discount_type = $request->discount_type;
        $item->hh_price = $request->hh_price;
        $item->number_of_consultations = $request->number_of_consultations;
        $item->number_of_days = $request->number_of_days;
        $item->consultation_text = $request->consultation_text;
        $ta = new GoogleTranslate('ar');
        $item->ar_plan_name = $ta->translate($request->plan_name);
        $item->ar_number_of_days = $request->number_of_days != null ? $ta->translate($request->number_of_days) : null;
        $item->ar_consultation_text = $request->consultation_text != null ? $ta->translate($request->consultation_text) : null;
        $tf = new GoogleTranslate('fr');
        $item->fr_plan_name = $tf->translate($request->plan_name);
        $item->fr_number_of_days = $request->number_of_days != null ? $tf->translate($request->number_of_days) : null;
        $item->fr_consultation_text = $request->consultation_text != null ? $tf->translate($request->consultation_text) : null;
        $th = new GoogleTranslate('hi');
        $item->hi_plan_name = $th->translate($request->plan_name);
        $item->hi_number_of_days = $request->number_of_days != null ? $th->translate($request->number_of_days) : null;
        $item->hi_consultation_text = $request->consultation_text != null ? $th->translate($request->consultation_text) : null;
        $tu = new GoogleTranslate('ur');
        $item->ur_plan_name = $tu->translate($request->plan_name);
        $item->ur_number_of_days = $request->number_of_days != null ? $tu->translate($request->number_of_days) : null;
        $item->ur_consultation_text = $request->consultation_text != null ? $tu->translate($request->consultation_text) : null;
        $item->save();
        return GlobalFunction::sendSimpleResponse(true, 'Cat edited successfully');
    }

    function deleteDoctorPlan($id)
    {
        $cat = DoctorPlans::find($id);
        $cat->is_deleted = 1;
        $cat->save();

        return GlobalFunction::sendSimpleResponse(true, 'cat deleted successfully');
    }

    function getDoctors(Request $request)
    {
        $limit = $request->input('length');
        $start = $request->input('start');
        if (empty($request->input('search.value'))) {
            $result = Doctors::select('id','name')->where('status', Constants::statusDoctorApproved)
                        ->limit($limit)
                        ->orderBy('id', 'DESC')
                        ->get();
        } else {
            $search = $request->input('search.value');
            $result = Doctors::select('id','name')->where('status', Constants::statusDoctorApproved)->where(function ($query) use ($search) {
                            $query->Where('name', 'LIKE', "%{$search}%")
                                ->orWhere('mobile_number', 'LIKE', "%{$search}%")
                                ->orWhere('doctor_number', 'LIKE', "%{$search}%");
                        })
                            ->limit($limit)
                            ->orderBy('id', 'DESC')
                            ->get();
        }

        return GlobalFunction::sendDataResponse(true, 'fetched successfully!', $result);
    }
    
    function commonHealthProblems()
    {
        return view('commonHealthProblems');
    }  

    function fetchCommonHealthProblems(Request $request)
    {
        $totalData =  CommonHealthProblems::where('is_deleted', 0)->count();
        $rows = CommonHealthProblems::where('is_deleted', 0)->orderBy('id', 'DESC')->get();
        $settings = GlobalSettings::first();

        $result = $rows;

        $columns = array(
            0 => 'id',
            1 => 'problem',
            2 => 'speciality',
            3 => 'priority'
        );

        $limit = $request->input('length');
        $start = $request->input('start');
        $order = $columns[$request->input('order.0.column')];
        $dir = $request->input('order.0.dir');

        $totalFiltered = $totalData;
        if (empty($request->input('search.value'))) {
            $result = CommonHealthProblems::where('is_deleted', 0)->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
        } else {
            $search = $request->input('search.value');
            $result =  CommonHealthProblems::where('is_deleted', 0)
                ->Where('problem', 'LIKE', "%{$search}%")
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
            $totalFiltered = CommonHealthProblems::where('is_deleted', 0)
                ->Where('problem', 'LIKE', "%{$search}%")
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
            // normalize ids (int array)
            $ids = is_string($item->speciality) && str_starts_with(trim($item->speciality), '[')
                ? json_decode($item->speciality, true)
                : [(int) $item->speciality];

            // fetch only id & title
            $specialities = DoctorCategories::whereIn('id', $ids)->get(['id', 'title']);

            // prepare values for HTML/JS safely
            // data-speciality will be a JSON string like: "[1,2,3]" -> parseable in JS
            $dataSpecialityAttr = htmlspecialchars($specialities->pluck('id')->toJson(), ENT_QUOTES, 'UTF-8');

            // human readable titles for table cell, e.g. "Cardiology, Dermatology"
            $titleDisplay = htmlspecialchars($specialities->pluck('title')->implode(', '), ENT_QUOTES, 'UTF-8');

            // other attrs - escape them too
            $imgUrlEscaped = htmlspecialchars($imgUrl, ENT_QUOTES, 'UTF-8');
            $problemEscaped = htmlspecialchars($item->problem, ENT_QUOTES, 'UTF-8');
            $priorityEscaped = htmlspecialchars($item->priority, ENT_QUOTES, 'UTF-8');
            $infoEscaped = htmlspecialchars($item->info, ENT_QUOTES, 'UTF-8');

            $edit = '<a href="#" 
                class="mr-2 btn btn-primary text-white edit" 
                data-icon="' . $imgUrlEscaped . '"
                data-problem="' . $problemEscaped . '" 
                data-speciality=\'' . $dataSpecialityAttr . '\' 
                data-priority="' . $priorityEscaped . '" 
                data-info="' . $infoEscaped . '" 
                rel="' . (int)$item->id . '">' . __("Edit") . '</a>';

            $delete = '<a href="#" class="mr-2 btn btn-danger text-white delete" rel="' . (int)$item->id . '">' . __("Delete") . '</a>';
            $action = $edit . $delete;

            $data[] = array(
                $img,
                $item->problem,
                $titleDisplay, // comma separated titles
                $item->priority,
                $item->info,
                $action,
            );

        }

        $cat = DoctorCategories::where('is_deleted', 0)->get();

        $json_data = array(
            "draw"            => intval($request->input('draw')),
            "recordsTotal"    => intval($totalData),
            "recordsFiltered" => $totalFiltered,
            "data"            => $data,
            'specialities'    => $cat
        );
        echo json_encode($json_data);
        exit();
    }

    function addCommonHealthProblems(Request $request)
    {
        $item = new CommonHealthProblems();
        $item->problem = $request->problem;
        $item->image = GlobalFunction::saveFileAndGivePath($request->image);;
        $item->speciality = $request->speciality;
        $item->priority = $request->priority;
        $item->info = $request->info;
        $ta = new GoogleTranslate('ar');
        $item->ar_problem = $ta->translate($request->problem);
        $item->ar_info = $request->info != null ? $ta->translate($request->info) : null;
        $tf = new GoogleTranslate('fr');
        $item->fr_problem = $tf->translate($request->problem);
        $item->fr_info = $request->info != null ? $tf->translate($request->info) : null;
        $th = new GoogleTranslate('hi');
        $item->hi_problem = $th->translate($request->problem);
        $item->hi_info = $request->info != null ? $th->translate($request->info) : null;
        $tu = new GoogleTranslate('ur');
        $item->ur_problem = $tu->translate($request->problem);
        $item->ur_info = $request->info != null ? $tu->translate($request->info) : null;
        $item->save();

        return GlobalFunction::sendSimpleResponse(true, 'added successfully');
    }

    function editCommonHealthProblems(Request $request)
    {   
        $item = CommonHealthProblems::find($request->id);
        $item->problem = $request->problem;
        if ($request->has('image')) {
            $item->image = GlobalFunction::saveFileAndGivePath($request->image);
        }
        $item->speciality = $request->speciality;
        $item->priority = $request->priority;
        $item->info = $request->info;
        $ta = new GoogleTranslate('ar');
        $item->ar_problem = $ta->translate($request->problem);
        $item->ar_info = $request->info != null ? $ta->translate($request->info) : null;
        $tf = new GoogleTranslate('fr');
        $item->fr_problem = $tf->translate($request->problem);
        $item->fr_info = $request->info != null ? $tf->translate($request->info) : null;
        $th = new GoogleTranslate('hi');
        $item->hi_problem = $th->translate($request->problem);
        $item->hi_info = $request->info != null ? $th->translate($request->info) : null;
        $tu = new GoogleTranslate('ur');
        $item->ur_problem = $tu->translate($request->problem);
        $item->ur_info = $request->info != null ? $tu->translate($request->info) : null;
        $item->save();
        return GlobalFunction::sendSimpleResponse(true, 'problem edited successfully');
    }

    function deleteCommonHealthProblems($id)
    {
        $health_problem = CommonHealthProblems::find($id);
        $health_problem->is_deleted = 1;
        $health_problem->save();

        return GlobalFunction::sendSimpleResponse(true, 'health problem deleted successfully');
    }

    function SpecialityWiseDisease()
    {
        return view('SpecialityWiseDisease');
    }  

    function fetchSpecialityWiseDisease(Request $request)
    {
        $totalData =  SpecialityWiseDisease::where('is_deleted', 0)->count();
        $rows = SpecialityWiseDisease::where('is_deleted', 0)->orderBy('id', 'DESC')->get();
        $settings = GlobalSettings::first();

        $result = $rows;

        $columns = array(
            0 => 'id',
            1 => 'problem',
            2 => 'speciality',
            3 => 'priority'
        );

        $limit = $request->input('length');
        $start = $request->input('start');
        $order = $columns[$request->input('order.0.column')];
        $dir = $request->input('order.0.dir');

        $totalFiltered = $totalData;
        if (empty($request->input('search.value'))) {
            $result = SpecialityWiseDisease::where('is_deleted', 0)->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
        } else {
            $search = $request->input('search.value');
            $result =  SpecialityWiseDisease::where('is_deleted', 0)
                ->Where('problem', 'LIKE', "%{$search}%")
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
            $totalFiltered = SpecialityWiseDisease::where('is_deleted', 0)
                ->Where('problem', 'LIKE', "%{$search}%")
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

            $speciality = DoctorCategories::find($item->speciality);
            $edit = '<a href="#" 
            class="mr-2 btn btn-primary text-white edit" 
            data-icon="' . $imgUrl .'"
            data-problem="' . htmlspecialchars($item->problem) . '" 
            data-speciality="' . $speciality->id . '" 
            data-priority="' . $item->priority . '" 
            data-info="' . $item->info . '" 
            rel="' . $item->id . '">' . __("Edit") . '</a>';

            $delete = '<a href="" class="mr-2 btn btn-danger text-white delete" rel=' . $item->id . ' >' . __("Delete") . '</a>';
            $action =  $edit . $delete;


            $data[] = array(
                $img,
                $item->problem,
                $speciality->title,
                $item->priority,
                $item->info,
                $action,
            );
        }

        $cat = DoctorCategories::where('is_deleted', 0)->get();

        $json_data = array(
            "draw"            => intval($request->input('draw')),
            "recordsTotal"    => intval($totalData),
            "recordsFiltered" => $totalFiltered,
            "data"            => $data,
            'specialities'    => $cat
        );
        echo json_encode($json_data);
        exit();
    }

    function addSpecialityWiseDisease(Request $request)
    {
        $item = new SpecialityWiseDisease();
        $item->problem = $request->problem;
        $item->image = GlobalFunction::saveFileAndGivePath($request->image);
        $item->speciality = $request->speciality;
        $item->priority = $request->priority;
        $item->info = $request->info;
        $item->save();

        return GlobalFunction::sendSimpleResponse(true, 'added successfully');
    }

    function editSpecialityWiseDisease(Request $request)
    {   
        $item = SpecialityWiseDisease::find($request->id);
        $item->problem = $request->problem;
        if ($request->has('image')) {
            $item->image = GlobalFunction::saveFileAndGivePath($request->image);
        }
        $item->speciality = $request->speciality;
        $item->priority = $request->priority;
        $item->info = $request->info;
        $item->save();
        return GlobalFunction::sendSimpleResponse(true, 'problem edited successfully');
    }

    function deleteSpecialityWiseDisease($id)
    {
        $health_problem = SpecialityWiseDisease::find($id);
        $health_problem->is_deleted = 1;
        $health_problem->save();

        return GlobalFunction::sendSimpleResponse(true, 'health problem deleted successfully');
    }

    function banners()
    {
        return view('banners');
    }  

    function fetchBanners(Request $request)
    {
        $totalData =  Banners::where('is_deleted', 0)->count();
        $rows = Banners::where('is_deleted', 0)->orderBy('id', 'DESC')->get();
        $settings = GlobalSettings::first();

        $result = $rows;

        $result = $rows;

        $columns = array(
            0 => 'id',
            1 => 'section',
            2 => 'sub_section',
        );

        $limit = $request->input('length');
        $start = $request->input('start');
        $order = $columns[$request->input('order.0.column')];
        $dir = $request->input('order.0.dir');

        $totalFiltered = $totalData;
        if (empty($request->input('search.value'))) {
            $result = Banners::where('is_deleted', 0)->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
        } else {
            $search = $request->input('search.value');
            $result =  Banners::where('is_deleted', 0)
                ->Where('section', 'LIKE', "%{$search}%")
                ->Where('sub_section', 'LIKE', "%{$search}%")
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
            $totalFiltered = Banners::where('is_deleted', 0)
                ->Where('section', 'LIKE', "%{$search}%")
                ->Where('sub_section', 'LIKE', "%{$search}%")
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
            $sub_section = $item->sub_section;
            $page = $item->page;
            $section_name = " ";
            if($sub_section == "Speciality wise")
            {
                $section_name = DoctorCategories::find($item->section_id)->title;
            }elseif($sub_section == "Problem wise"){
                $section_name = CommonHealthProblems::find($item->section_id)->problem;
            }
            $edit = '<a href="#" 
            class="mr-2 btn btn-primary text-white edit" 
            data-icon="' . $imgUrl .'"
            data-section="' . htmlspecialchars($item->section) . '"
            data-sub_section="' . htmlspecialchars($item->sub_section) . '"
            data-page="' . htmlspecialchars($item->page) . '"
            data-section_name="' . htmlspecialchars($section_name) . '"
            data-section_id="' . htmlspecialchars($item->section_id) . '"
            rel="' . $item->id . '">' . __("Edit") . '</a>';

            $delete = '<a href="" class="mr-2 btn btn-danger text-white delete" rel=' . $item->id . ' >' . __("Delete") . '</a>';
            $action =  $edit . $delete;


            $data[] = array(
                $img,
                $item->section,
                $sub_section,
                $page,
                $section_name,
                $action,
            );
        }

        $cat = DoctorCategories::where('is_deleted', 0)->get();

        $json_data = array(
            "draw"            => intval($request->input('draw')),
            "recordsTotal"    => intval($totalData),
            "recordsFiltered" => $totalFiltered,
            "data"            => $data,
            'location'    => $cat
        );
        echo json_encode($json_data);
        exit();
    }

    function addBanner(Request $request)
    {
        $item = new Banners();
        $item->section = $request->section;
        $item->sub_section = $request->sub_section;
        $item->section_id = $request->section_id;
        $item->page = $request->page;
        $item->image = GlobalFunction::saveFileAndGivePath($request->image);;
        $item->save();

        return GlobalFunction::sendSimpleResponse(true, 'plan added successfully');
    }

    function editBanner(Request $request)
    {   
        $item = Banners::find($request->id);
        $item->section = $request->section;
        $item->sub_section = $request->sub_section;
        $item->section_id = $request->section_id;
        $item->page = $request->page;
        if ($request->has('image')) {
            $item->image = GlobalFunction::saveFileAndGivePath($request->image);
        }
        $item->save();
        return GlobalFunction::sendSimpleResponse(true, 'Cat edited successfully');
    }

    function deleteBanner($id)
    {
        $cat = Banners::find($id);
        $cat->is_deleted = 1;
        $cat->save();

        return GlobalFunction::sendSimpleResponse(true, 'cat deleted successfully');
    }

    function getSpecialities()
    {
        $specialities = DoctorCategories::where("is_deleted",0)->get();
        return GlobalFunction::sendDataResponse(true, 'fetched successfully!', $specialities);
    }

    function getCommonHealthProblems()
    {
        $common_health_problems = CommonHealthProblems::where("is_deleted",0)->get();
        return GlobalFunction::sendDataResponse(true, 'fetched successfully!', $common_health_problems);
    }

    function getSpecialityWiseDisease()
    {
        $speciality_wise_disease = SpecialityWiseDisease::where("is_deleted",0)->get();
        return GlobalFunction::sendDataResponse(true, 'fetched successfully!', $speciality_wise_disease);
    }
}
