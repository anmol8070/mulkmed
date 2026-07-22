<?php

namespace App\Http\Controllers;

use App\Models\Pages;
use App\Models\PrivacyTermsLocalization;
use App\Models\MidasDescription;
use App\Models\HealthcheckDescription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PagesController extends Controller
{
    //

    function privacypolicy(Request $request){
        $data = Pages::first();
       return $data->privacy;
    }
    function termsOfUse(Request $request){
        $data = Pages::first();
       return $data->termsofuse;
    }

    function viewTerms(Request $request){
        if($request->lang){
             $data = PrivacyTermsLocalization::where('language', $request->lang)->first();
             return view('pages.viewTerms',['data'=> $data->termsofuse]);
        }
        $data = Pages::first();
        return view('pages.viewTerms',['data'=> $data->termsofuse]);
    }
    function updatePrivacy(Request $request){
         if($request->lang && ($request->lang != 'en')){
             $data = PrivacyTermsLocalization::where('language', $request->lang)->first();
             $data->privacy = $request->content;
              $data->save();
              return  json_encode(['status'=>true,'message'=>"update successful"]);
        }
        $data = Pages::first();
        $data->privacy= $request->content;
        $data->save();

        return  json_encode(['status'=>true,'message'=>"update successful"]);
    }
    function updateTerms(Request $request){
         if($request->lang && ($request->lang != 'en')){
             $data = PrivacyTermsLocalization::where('language', $request->lang)->first();
             $data->termsofuse = $request->content;
              $data->save();
              return  json_encode(['status'=>true,'message'=>"update successful"]);
        }
        $data = Pages::first();
        $data->termsofuse= $request->content;
        $data->save();

        return  json_encode(['status'=>true,'message'=>"update successful"]);
    }
    function viewPrivacy(Request $request){
        if($request->lang){
             $data = PrivacyTermsLocalization::where('language', $request->lang)->first();
             return view('pages.viewTerms',['data'=> $data->privacy]);
        }
        $data = Pages::first();
        return view('pages.viewPrivacy',['data'=> $data->privacy]);
    }

    function viewMidasDescription(Request $request){
        if($request->lang && ($request->lang != 'en')){
                $data = MidasDescription::where('language', $request->lang)->first();
                return view('pages.midasDescription',['data'=> $data->description]);
        }
        $data = MidasDescription::where('language', 'en')->first();
        return view('pages.midasDescription',['data'=> $data->description]);
    }        

     function updateMidasDescription(Request $request){
         if($request->lang && ($request->lang != 'en')){
             $data = MidasDescription::where('language', $request->lang)->first();
             $data->description = $request->content;
              $data->save();
              return  json_encode(['status'=>true,'message'=>"update successful"]);
        }
        $data = MidasDescription::where('language', 'en')->first();
        $data->description= $request->content;
        $data->save();

        return  json_encode(['status'=>true,'message'=>"update successful"]);
    }

    function appViewMidasDescription(Request $request){
     
        $data = MidasDescription::where('language', $request->lang ?? 'en')->first();
        return view('pages.appViewMidasDescription',['data'=> $data->description]);
    }




function viewHealthcheckDescription(Request $request){
        if($request->lang && ($request->lang != 'en')){
                $data = HealthcheckDescription::where('language', $request->lang)->first();
                return view('pages.healthcheckDescription',['data'=> $data->description]);
        }
        $data = HealthcheckDescription::where('language', 'en')->first();
        return view('pages.healthcheckDescription',['data'=> $data->description]);
    }

     function updateHealthcheckDescription(Request $request){
         if($request->lang && ($request->lang != 'en')){
             $data = HealthcheckDescription::where('language', $request->lang)->first();
             $data->description = $request->content;
             $data->save();
             return  json_encode(['status'=>true,'message'=>"update successful"]);
            }
    
        $data = HealthcheckDescription::where('language', 'en')->first();
        $data->description = $request->content;
        $data->save();

        return  json_encode(['status'=>true,'message'=>"update successful"]);
    }

    function appViewHealthcheckDescription(Request $request){
    

     
        $data = HealthcheckDescription::where('language', $request->lang ?? 'en')->first();
        return view('pages.appViewHealthcheckDescription',['data'=> $data->description]);
    }






 function appviewTerms(Request $request){
        $lang = $request->input('lang'); 

        if($lang && $lang != 'en'){
            $data = PrivacyTermsLocalization::where('language', $lang)->first();
            return view('pages.appViewTerms',['data'=> $data->termsofuse, 'lang' => $lang]);
        }
        $data = Pages::first();
        return view('pages.appViewTerms',['data'=> $data->termsofuse, 'lang' => $lang]);
    }

    function appPrivacyView(Request $request){
         $lang = $request->input('lang'); 

        if($lang && $lang != 'en'){
            $data = PrivacyTermsLocalization::where('language', $lang)->first();
            return view('pages.appViewPrivacy',['data'=> $data->privacy, 'lang' => $lang]);
        }
        $data = Pages::first();
        return view('pages.appViewPrivacy',['data'=> $data->privacy, 'lang' => $lang]);
    }

    function viewHelpCenter(Request $request){
        $data = Pages::first();
        return view('pages.viewHelpCenter',['data'=> $data->help_center]);
    }

    function updateHelpCenter(Request $request){
        $data = Pages::first();
        $data->help_center= $request->content;
        $data->save();
        return  json_encode(['status'=>true,'message'=>"update successful"]);
    }
    
    function HelpCenter(Request $request){
        $data = Pages::first();
        return $data->help_center;
    }

    function appHelpCenterView(Request $request){
        $lang = $request->input('lang'); 

        if($lang && $lang != 'en'){
            $data = PrivacyTermsLocalization::where('language', $lang)->first();
            return view('pages.appHelpCenter',['data'=> $data->help_center, 'lang' => $lang]);
        }
        $data = Pages::first();
        // return $data->help_center;
        return view('pages.appHelpCenter',['data'=> $data->help_center, 'lang' => $lang]);
    }
}
