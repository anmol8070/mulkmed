<?php

namespace App\Http\Controllers;

use App\Models\OrderMedicineCategories;

use Illuminate\Http\Request;

use App\Models\GlobalFunction;
use App\Models\GlobalSettings;

include base_path("./app/Class/AgoraDynamicKey/RtcTokenBuilder.php");

class OrderMedicineController extends Controller
{
    function orderMedicineCategories()
    {
        return view('order_medicine.orderMedicineCategories');
    }  

    function fetchOrderMedicineCategoriesList(Request $request)
    {
        $totalData = OrderMedicineCategories::where('is_deleted', 0)->count();
        $rows = OrderMedicineCategories::where('is_deleted', 0)->orderBy('id', 'DESC')->get();
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
            $result = OrderMedicineCategories::where('is_deleted', 0)->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
        } else {
            $search = $request->input('search.value');
            $result =  OrderMedicineCategories::where('is_deleted', 0)
                ->Where('title', 'LIKE', "%{$search}%")
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
            $totalFiltered = OrderMedicineCategories::where('is_deleted', 0)
                ->Where('title', 'LIKE', "%{$search}%")
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

            $edit = '<a data-icon="' . $imgUrl . '" data-title="' . $item->title . '"  data-info="' . $item->info . '" href="" class="mr-2 btn btn-primary text-white edit" rel=' . $item->id . ' >' . __("Edit") . '</a>';
            $delete = '<a href="" class="mr-2 btn btn-danger text-white delete" rel=' . $item->id . ' >' . __("Delete") . '</a>';
            $action =  $edit . $delete;


            $data[] = array(
                $img,
                $item->title,
                $item->info,
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

     function addOrderMedicineCat(Request $request)
    {
        $cat = new OrderMedicineCategories();
        $cat->title = $request->title;
        $cat->info = $request->info;
        $cat->image = GlobalFunction::saveFileAndGivePath($request->image);
        $cat->save();

        return GlobalFunction::sendSimpleResponse(true, 'cat added successfully');
    }

        function editOrderMedicineCat(Request $request)
    {
        $item = OrderMedicineCategories::find($request->id);
        $item->title = $request->title;
        $item->info = $request->info;
        if ($request->has('image')) {
            $item->image = GlobalFunction::saveFileAndGivePath($request->image);
        }

        $item->save();
        return GlobalFunction::sendSimpleResponse(true, 'Cat edited successfully');
    }

        function deleteOrderMedicineCat($id)
    {
        $cat = OrderMedicineCategories::find($id);
        $cat->is_deleted = 1;
        $cat->save();

        return GlobalFunction::sendSimpleResponse(true, 'cat deleted successfully');
    }
}
