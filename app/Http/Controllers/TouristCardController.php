<?php

namespace App\Http\Controllers;

use App\Models\HnHCards;
use App\Models\HnHDiscountManagement;
use App\Models\HnHPointSetting;
use App\Models\GlobalFunction;
use App\Models\GlobalSettings;
use App\Models\TouristCards;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TouristCardController extends Controller
{
    function HnHDiscountManagement(Request $request)
    {
        return view('hnh.HnHDiscountManagement');
    }

    function touristCards()
    {
        return view('tourist_card.touristCard');
    }  

    function fetchTouristCards(Request $request)
    {
        $totalData =  TouristCards::where('is_deleted', 0)->count();
        $rows = TouristCards::where('is_deleted', 0)->orderBy('id', 'DESC')->get();
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
            $result = TouristCards::where('is_deleted', 0)->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
        } else {
            $search = $request->input('search.value');
            $result =  TouristCards::where('is_deleted', 0)
                ->Where('name', 'LIKE', "%{$search}%")
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
            $totalFiltered = TouristCards::where('is_deleted', 0)
                ->Where('name', 'LIKE', "%{$search}%")
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
                data-passport_number="' . $item->passport_number . '" 
                data-travelling_from_country="' . $item->travelling_from_country . '"
                data-travelling_from_date="' . $item->travelling_from_date . '" 
                data-visit_visa_validity="' . $item->visit_visa_validity . '"
                data-passport_document="' . $item->passport_document . '"
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
