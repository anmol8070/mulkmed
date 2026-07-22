<?php

namespace App\Http\Controllers;

use App\Models\HnHCards;
use App\Models\HnHDiscountManagement;
use App\Models\HnHPointSetting;
use App\Models\GlobalFunction;
use App\Models\GlobalSettings;
use App\Models\SeniorCards;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SeniorCardController extends Controller
{
    function HnHDiscountManagement(Request $request)
    {
        return view('hnh.HnHDiscountManagement');
    }

    function seniorCards()
    {
        return view('senior_card.seniorCard');
    }  

    function fetchSeniorCards(Request $request)
    {
        $totalData =  SeniorCards::where('is_deleted', 0)->count();
        $rows = SeniorCards::where('is_deleted', 0)->orderBy('id', 'DESC')->get();
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
            $result = SeniorCards::where('is_deleted', 0)->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
        } else {
            $search = $request->input('search.value');
            $result =  SeniorCards::where('is_deleted', 0)
                ->where(function ($query) use ($search) {
                    $query->where('user_name', 'LIKE', "%{$search}%")
                          ->orWhere('card_number', 'LIKE', "%{$search}%")
                          ->orWhere('email', 'LIKE', "%{$search}%")
                          ->orWhere('phone_number', 'LIKE', "%{$search}%");
                })
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
            $totalFiltered = SeniorCards::where('is_deleted', 0)
                ->where(function ($query) use ($search) {
                    $query->where('user_name', 'LIKE', "%{$search}%")
                          ->orWhere('card_number', 'LIKE', "%{$search}%")
                          ->orWhere('email', 'LIKE', "%{$search}%")
                          ->orWhere('phone_number', 'LIKE', "%{$search}%");
                })
                ->count();
        }
        // $currency_symbol = Settings::first();
        $data = array();
        foreach ($result as $item) {

           

            $view = '<a href="#" 
                class="mr-2 btn btn-primary text-white edit" 
                data-card_number="' . $item->card_number . '" 
                data-user_name="' . $item->user_name . '" 
                data-phone_number="' . $item->phone_number . '" 
                data-email="' . $item->email . '" 
                data-date_of_birth="' . $item->date_of_birth . '" 
                data-gender="' . $item->gender . '" 
                data-address="' . $item->address . '" 
                data-points="' . $item->points . '" 
                data-emirates_id="' . $item->emirates_id . '" 
                data-payment_status="' . $item->payment_status . '" 
                data-payment_amount="' . $item->payment_amount . '" 
                rel="' . (int)$item->id . '">' . __("View") . '</a>';

            $action = $view;

            $data[] = array(
                $item->card_number,
                $item->user_name,
                $item->email,  
                $item->points,
                $item->phone_number,
                $item->payment_status == 0 ? 'Pending' :($item->payment_status == 1 ? "Paid" : "Failed"),
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
}
