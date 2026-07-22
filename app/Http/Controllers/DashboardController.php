<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Session;
use App\Models\GlobalSettings;
use App\Models\DashboardBanners;
use App\Models\SectionSequence;
use App\Models\GlobalFunction;
use App\Models\DoctorsBySymptoms;
use App\Models\DoctorCategories;
use App\Helpers\Helpers;


class DashboardController extends Controller
{
    function dashboardBanners()
    {
        return view('user-dashboard.index');
    } 

    function fetchDashboardBanners(Request $request)
    {
        $totalData =  DashboardBanners::where('is_deleted', 0)->count();
        $rows = DashboardBanners::where('is_deleted', 0)->orderBy('id', 'DESC')->get();

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
            $result = DashboardBanners::where('is_deleted', 0)->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
        } else {
            $search = $request->input('search.value');
            $result =  DashboardBanners::where('is_deleted', 0)
                ->Where('name', 'LIKE', "%{$search}%")
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
            $totalFiltered = DashboardBanners::where('is_deleted', 0)
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

    function addDashboardBanners(Request $request)
    {
        $item = new DashboardBanners();
        $item->name = $request->name;
        $item->image = GlobalFunction::saveFileAndGivePath($request->image);;
        $item->redirection = $request->redirection;
        $item->url = $request->url;
        $item->save();

        return GlobalFunction::sendSimpleResponse(true, 'added successfully');
    }

    function editDashboardBanners(Request $request)
    {   
        $item = DashboardBanners::find($request->id);
        $item->name = $request->name;
        if ($request->has('image')) {
            $item->image = GlobalFunction::saveFileAndGivePath($request->image);
        }
        $item->redirection = $request->redirection;
        $item->url = $request->url;
        $item->save();
        return GlobalFunction::sendSimpleResponse(true, 'problem edited successfully');
    }

    function deleteDashboardBanners($id)
    {
        $health_problem = DashboardBanners::find($id);
        $health_problem->is_deleted = 1;
        $health_problem->save();

        return GlobalFunction::sendSimpleResponse(true, 'health problem deleted successfully');
    }

    function sectionSequence()
    {
        // return Helpers::module_permission_check("Home_Page");
        return view('sectionSequence');
    }

    function fetchSectionSequence(Request $request)
    {
        $totalData =  SectionSequence::where('is_deleted', 0)->count();
        $rows = SectionSequence::where('is_deleted', 0)->orderBy('position', 'ASC')->get();

        $result = $rows;

        $columns = array(
            0 => 'position'
        );

        $limit = $request->input('length');
        $start = $request->input('start');
        $order = $columns[$request->input('order.0.column')];
        $dir = $request->input('order.0.dir');

        $totalFiltered = $totalData;
        $result = SectionSequence::where('is_deleted', 0)->offset($start)
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
        $section_position_increase           = SectionSequence::where('position',$request->position-1)->first();
        $section_position_increase->position = $request->position;
        $section_position_increase->save();

        $section_position_decrease           = SectionSequence::find($request->id);
        $section_position_decrease->position   = $request->position-1;
        $section_position_decrease->save();

        return response()->json(['status'=>true , 'message'=>"Position Updated Successfully"]);
    }

    function sequenceStatusUpdate(Request $request)
    {
        $section_position_increase           = SectionSequence::where('id',$request->id)->first();
        $section_position_increase->status  = $request->status;
        $section_position_increase->save();

        return response()->json(['status'=>true , 'message'=>"Status Updated Successfully"]);
    }

    function deleteSection($id)
    {
        $section_sequence = SectionSequence::find($id);
        $section_sequence->is_deleted = 1;
        $section_sequence->save();

        return GlobalFunction::sendSimpleResponse(true, 'health problem deleted successfully');
    }

    function doctorsBySymptoms()
    {
        return view('doctorsBySymptoms');
    }  

    function fetchDoctorsBySymptoms(Request $request)
    {
        $totalData =  DoctorsBySymptoms::where('is_deleted', 0)->count();
        $rows = DoctorsBySymptoms::where('is_deleted', 0)->orderBy('id', 'DESC')->get();
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
            $result = DoctorsBySymptoms::where('is_deleted', 0)->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
        } else {
            $search = $request->input('search.value');
            $result =  DoctorsBySymptoms::where('is_deleted', 0)
                ->Where('problem', 'LIKE', "%{$search}%")
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
            $totalFiltered = DoctorsBySymptoms::where('is_deleted', 0)
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

    function addDoctorsBySymptoms(Request $request)
    {
        $request->validate([
            'problem' => 'required|string|max:255',
            'image' => 'required|image|mimes:jpeg,jpg,png,gif,webp,bmp|max:5120',
            'speciality' => 'required',
            'priority' => 'required',
            'info' => 'required',
        ]);

        $item = new DoctorsBySymptoms();
        $item->problem = $request->problem;
        $item->image = GlobalFunction::saveFileAndGivePath($request->image);
        $item->speciality = $request->speciality;
        $item->priority = $request->priority;
        $item->info = $request->info;
        $item->save();

        return GlobalFunction::sendSimpleResponse(true, 'added successfully');
    }

    function editDoctorsBySymptoms(Request $request)
    {   
        $request->validate([
            'id' => 'required',
            'problem' => 'required|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,jpg,png,gif,webp,bmp|max:5120',
            'speciality' => 'required',
            'priority' => 'required',
            'info' => 'required',
        ]);

        $item = DoctorsBySymptoms::find($request->id);
        $item->problem = $request->problem;
        if ($request->hasFile('image')) {
            $item->image = GlobalFunction::saveFileAndGivePath($request->image);
        }
        $item->speciality = $request->speciality;
        $item->priority = $request->priority;
        $item->info = $request->info;
        $item->save();
        return GlobalFunction::sendSimpleResponse(true, 'problem edited successfully');
    }

    function deleteDoctorsBySymptoms($id)
    {
        $health_problem = DoctorsBySymptoms::find($id);
        $health_problem->is_deleted = 1;
        $health_problem->save();

        return GlobalFunction::sendSimpleResponse(true, 'health problem deleted successfully');
    }
}
