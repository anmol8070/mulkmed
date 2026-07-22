<?php

namespace App\Http\Controllers\v1;
use App\Models\BestOfferCart;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\GlobalFunction;
use App\Models\BestOfferPlans;
use App\Models\BestOfferPlanOrders;

use DB;
  
class BestOffersController extends Controller
{

   public function details(Request $request, $id)
    {
        $lang = $request->header('lang', 'en'); // default en

        $plan = BestOfferPlans::where('id', $id)->first();  

        if (!$plan) {  
            return response()->json([
                'status' => false,
                'message' => 'Offer not found',
                'data' => null
            ], 200); 
        }

        $supportedLangs = ['ar', 'fr', 'hi', 'ur'];

        if (in_array($lang, $supportedLangs)) {
            $plan->price_description = $plan->{$lang . '_price_description'};
            $plan->benefits          = $plan->{$lang . '_benefits'};
            $plan->description       = $plan->{$lang . '_description'};
            $plan->title             = $plan->{$lang . '_title'};
        }

        return response()->json([
            'status' => true,
            'data' => $plan
        ], 200);
    }

    function addToCart(Request $request){

        $cart = BestOfferCart::where("offer_id", $request->offer_id)->where('user_id', $request->user_id)->first();
        if($cart){
            $cart->quantity = $request->quantity;
            $cart->save();
        }

        else{
  
            $plan = BestOfferPlans::where('id', $request->offer_id)->first();

            if (!$plan) {
                return response()->json([
                    'status' => false,
                    'message' => 'Offer not found',
                    'data' => null
                ], 200); 
            }

            $cart = new BestOfferCart;
            $cart->user_id = $request->user_id;
            $cart->offer_id = $request->offer_id;
            $cart->image = $plan->detail_image;
            $cart->quantity = $request->quantity;
            $cart->price = $plan->price;
  
            $cart->offer_name = $plan->title;
            $cart->ar_offer_name = $plan->ar_title;
            $cart->fr_offer_name = $plan->fr_title;
            $cart->hi_offer_name = $plan->hi_title;
            $cart->ur_offer_name = $plan->ur_title;

            $cart->price_description = $plan->price_description;
            $cart->ar_price_description = $plan->ar_price_description;
            $cart->fr_price_description = $plan->fr_price_description;
            $cart->hi_price_description = $plan->hi_price_description;
            $cart->ur_price_description = $plan->ur_price_description;

            $cart->description = $plan->description;
            $cart->ar_description = $plan->ar_description;
            $cart->fr_description = $plan->fr_description;
            $cart->hi_description = $plan->hi_description;
            $cart->ur_description = $plan->ur_description;
  
            $cart->benefits = $plan->benefits;
            $cart->ar_benefits = $plan->ar_benefits;
            $cart->fr_benefits = $plan->fr_benefits;
            $cart->hi_benefits = $plan->hi_benefits;
            $cart->ur_benefits = $plan->ur_benefits;
            $cart->status = 1;
            $cart->save();
        }
        
         return response()->json([
            'status' => true,
            'message' => "Successfully Added to Cart"
        ], 200);
    } 

    function removeFromCart(Request $request){

        $cart = BestOfferCart::where("offer_id", $request->offer_id)->where('user_id', $request->user_id)->first();
        if($cart){
            $cart->delete();

             return response()->json([
            'status' => true,
            'message' => "Successfully Removed from Cart"
            ], 200);
        }

           return response()->json([
            'status' => false,
            'message' => "Cart Not Found"
            ], 200);
    } 

    function getCartData(Request $request){

        $cart = BestOfferCart::where('user_id', $request->user_id)
        ->where('status', 1)    
        ->get();    

        $lang = $request->header('lang', 'en');  

        $supportedLangs = ['ar', 'fr', 'hi', 'ur'];

        if ($cart->isNotEmpty()) {
            $cart = $cart->map(function ($item) use ($lang, $supportedLangs) {
                if (in_array($lang, $supportedLangs)) {
                    $item->price_description = $item->{$lang . '_price_description'};
                    $item->benefits          = $item->{$lang . '_benefits'};
                    $item->description       = $item->{$lang . '_description'};
                    $item->offer_name             = $item->{$lang . '_offer_name'};
                }
                return $item;  
            });

            return response()->json([
                'status' => true,
                'data' => $cart
            ]);     
        }


        return response()->json([  
            'status' => true,
            'message' => 'Cart not found',
            'data' => []
        ], 200); 
        
  
    }

    function myPackages(Request $request){
        $orders = BestOfferPlanOrders::where('user_id', $request->user_id)->where('status', 1)->get();

        $lang = $request->header('lang', 'en');

        $supportedLangs = ['ar', 'fr', 'hi', 'ur'];

        if ($orders->isNotEmpty()) {
            $orders = $orders->map(function ($item) use ($lang, $supportedLangs) {
                if (in_array($lang, $supportedLangs)) {
                    $item->price_description = $item->{$lang . '_price_description'};
                    $item->benefits          = $item->{$lang . '_benefits'};
                    $item->description       = $item->{$lang . '_description'};
                    $item->offer_name             = $item->{$lang . '_offer_name'};
                }
                return $item;  
            });
        }

        return response()->json([
            'status' => true,
            'data' => $orders
        ], 200); 
    }

    function myPackageDetails(Request $request){
        
        $order = BestOfferPlanOrders::where('id', $request->package_id)->where('status', 1)->first();

        $lang = $request->header('lang', 'en');

        $supportedLangs = ['ar', 'fr', 'hi', 'ur'];

        if (in_array($lang, $supportedLangs)) {
            $order->price_description = $order->{$lang . '_price_description'};
            $order->benefits          = $order->{$lang . '_benefits'};
            $order->description       = $order->{$lang . '_description'};
            $order->offer_name             = $order->{$lang . '_offer_name'};
        }

        return response()->json([
            'status'=>true,
            'data'=>$order
        ]);
    }

    
}