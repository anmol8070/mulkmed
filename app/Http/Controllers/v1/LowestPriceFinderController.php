<?php

namespace App\Http\Controllers\v1;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\LowestPriceFinderSectionSequence;
use App\Models\TopHospitals;
use App\Models\Hospitals;
use App\Models\Country;
use App\Models\TopProcedures;
use App\Models\MulkmedChoiceOfDoctors;
use App\Models\WhySecondOpinionMatters;
use App\Models\TrustedHealthcarePartners;
use App\Models\DashboardBanners;
use App\Models\HospitalProcedurePrice;
use App\Models\UnlockMoreBenefitsCard;
use App\Models\HospitalProcedures;
use Illuminate\Support\Facades\Validator;
use App\Helpers\CurrencyHelper;
use App\Models\City;
use DB;
  
class LowestPriceFinderController extends Controller
{

// function dashboard()
//     {  
//         $lang = request()->header('lang', 'en');

//         $columnsection_name = match ($lang) {
//                         'ar' => 'ar_section_name',
//                         'fr' => 'fr_section_name',
//                         'hi' => 'hi_section_name',
//                         'ur' => 'ur_section_name',
//                         default => 'section_name',
//                     };
//         $lowest_price_finder_sections = LowestPriceFinderSectionSequence::select('id',DB::raw("`$columnsection_name` as `section_name`"),'section_type as section_type')->where('is_deleted', 0)->orderBy('position', 'ASC')->where('status',1)->get();
//         $sectionSequence = [];
//         foreach ($lowest_price_finder_sections as $key => $sequence) {
//             if($sequence->section_type == 'top_hospitals')
//             {
//                 $section = [];
//                 $columnname = match ($lang) {
//                         'ar' => 'ar_name',
//                         'fr' => 'fr_name',
//                         'hi' => 'hi_name',
//                         'ur' => 'ur_name',
//                         default => 'name',
//                     };
//                 $section = TopHospitals::select('*',DB::raw("`$columnname` as `name`"))->where('is_deleted', 0)->orderBy('priority')->get();
//                 if($section != [])
//                 {
//                     $sequence->section_data = $section;
//                     array_push($sectionSequence,$sequence);
//                 }
//             }

//             if($sequence->section_type == 'lowest_price_finder')
//             {
//                 $section = [];
//                 $columnname = match ($lang) {
//                         'ar' => 'ar_name',
//                         'fr' => 'fr_name',
//                         'hi' => 'hi_name',
//                         'ur' => 'ur_name',
//                         default => 'name',
//                     };
//                 $section = [];
//                 if($section != [])
//                 {
//                     $sequence->section_data = $section;
//                     array_push($sectionSequence,$sequence);
//                 }
//             }

//             // if($sequence->section_type == 'filters')
//             // {   
//             //     $section = [];
//             //     $section['hospital_procedures'] = HospitalProcedures::where('is_deleted', 0)->orderBy('id', 'DESC')->get();
//             //     $section['specialities'] = DoctorCategories::where('is_deleted', 0)->orderBy('id', 'DESC')->get();
//             //     $section['countries'] = Hospitals::select('country')->where('is_deleted', 0)->whereNotNull('country')->distinct()->orderBy('country')->pluck('country');
//             //     $section['hospitals'] = Hospitals::where('is_deleted', 0)->orderBy('id', 'DESC')->get();
//             //     $sequence->section_data = $section;
//             //     array_push($sectionSequence,$sequence);
//             // }
//             if($sequence->section_type == 'dont_have_mulk_hnh_card')
//             {
//                 $section = [];
//                 $section = DashboardBanners::where('name', "Don't Have Mulk HnH Card?")
//                                 ->where('is_deleted', 0)
//                                 ->get();
//                 if($section != [])
//                 {
//                     $sequence->section_data = $section;
//                     array_push($sectionSequence,$sequence);
//                 }
//             }
//             if($sequence->section_type == 'why_second_opinion_matters')
//             {
//                 $section = [];
//                 $section = WhySecondOpinionMatters::where('is_deleted', 0)->get();
//                 if($section != [])
//                 {
//                     $sequence->section_data = $section;
//                     array_push($sectionSequence,$sequence);
//                 }
//             }

//             if($sequence->section_type == 'explore_our_trusted_healthcare_providers')
//             {
//                 $section = [];
//                 $columnname = match ($lang) {
//                         'ar' => 'ar_name',
//                         'fr' => 'fr_name',
//                         'hi' => 'hi_name',
//                         'ur' => 'ur_name',
//                         default => 'name',
//                     };
//                 $section = TrustedHealthcarePartners::select('*',DB::raw("`$columnname` as `name`"))->where('is_deleted', 0)->get();
//                 if($section != [])
//                 {
//                     $sequence->section_data = $section;
//                     array_push($sectionSequence,$sequence);
//                 }
//             }

//             if($sequence->section_type == 'browse_by_category')
//             {
//                 $section = [];
//                 $columnname = match ($lang) {
//                         'ar' => 'ar_name',
//                         'fr' => 'fr_name',
//                         'hi' => 'hi_name',
//                         'ur' => 'ur_name',
//                         default => 'name',
//                     };
//                 $section['categories'] = HospitalCategories::select('*',DB::raw("`$columnname` as `name`"))->where('is_deleted', 0)->get();
//                 $columncountry = match ($lang) {
//                         'ar' => 'ar_country',
//                         'fr' => 'fr_country',
//                         'hi' => 'hi_country',
//                         'ur' => 'ur_country',
//                         default => 'country',
//                     };
//                 $section['countries'] = Hospitals::where('is_deleted', 0)->whereNotNull($columncountry)->where('country','United Arab Emirates')->distinct('country')->orderBy('country')->pluck($columncountry);

//                 if($section != [])
//                 {
//                     $sequence->section_data = $section;
//                     array_push($sectionSequence,$sequence);
//                 }
//             }

//             if($sequence->section_type == 'top_procedures')
//             {
//                 $section = [];
//                 $columnname = match ($lang) {
//                         'ar' => 'ar_name',
//                         'fr' => 'fr_name',
//                         'hi' => 'hi_name',
//                         'ur' => 'ur_name',
//                         default => 'name',
//                     };

//                 $columndescription = match ($lang) {
//                         'ar' => 'ar_description',
//                         'fr' => 'fr_description',
//                         'hi' => 'hi_description',
//                         'ur' => 'ur_description',
//                         default => 'description',
//                     };
//                 $section = TopProcedures::select('id','hospital_id','image',DB::raw("`$columnname` as `name`"),DB::raw("`$columndescription` as `description`"))
//                             ->where('is_deleted', 0)->get();
//                 if($section != [])
//                 {
//                     $sequence->section_data = $section;
//                     array_push($sectionSequence,$sequence);
//                 }
//             }

//             if($sequence->section_type == 'mulkmed_choice_of_doctors')
//             {
//                 $section = [];

//                 $columndescription = match ($lang) {
//                         'ar' => 'ar_description',
//                         'fr' => 'fr_description',
//                         'hi' => 'hi_description',
//                         'ur' => 'ur_description',
//                         default => 'description',
//                     };
//                 $section = MulkmedChoiceOfDoctors::select('*',DB::raw("`$columndescription` as `description`"))->where('is_deleted', 0)->get();
//                 $columndesignation = match ($lang) {
//                         'ar' => 'ar_designation',
//                         'fr' => 'fr_designation',
//                         'hi' => 'hi_designation',
//                         'ur' => 'ur_designation',
//                         default => 'designation',
//                     };
//                 $mulkmed_choice_of_doctors = MulkmedChoiceOfDoctors::where('is_deleted', 0)->pluck('doctor_id')->toArray();
//                 $section = Doctors::select('id as doctor_id','name','image',DB::raw("`$columndesignation` as `designation`"))->whereIn('id',$mulkmed_choice_of_doctors)->get();
//                 if($section != [])
//                 {
//                     $sequence->section_data = $section;
//                     array_push($sectionSequence,$sequence);
//                 }
//             }

            
//         }
        
//         return response()->json([
//             'status' => true, 
//             'sectionSequence' => $sectionSequence
//         ]);
//     }

    function getProceduresAndPrice(Request $request)
    {
        $procedures = HospitalProcedures::where('is_deleted', 0)->get();
        $dontHaveCard = DashboardBanners::where('name', "Don't Have Mulk HnH Card?")
                                ->where('is_deleted', 0)
                                ->get();

        return response()->json([
            'status' => true,
            'data' => [
                'procedures' => $procedures,
                'currencyCodes' => CurrencyHelper::currencies(),
                'dont_have_mulk_hnh_card' => $dontHaveCard
            ]
        ]);
    }

    function lowestPriceFinder(Request $request){

        $rules = [
            'procedure_id' => 'required',
        ];

         $lang = request()->header('lang', 'en');

        $columnsection_name_hpp = match ($lang) {
                        'ar' => 'ar_name',
                        'fr' => 'fr_name',
                        'hi' => 'hi_name',
                        'ur' => 'ur_name',
                        default => 'name',
                    };

        $columnsection_name_country = match ($lang) {
                        'ar' => 'name_ar',
                        'fr' => 'name_fr',
                        'hi' => 'name_hi',
                        'ur' => 'name_ur',
                        default => 'name',
                    };

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }

         $procedureId = (int) $request->procedure_id;

        $currency = request('currency', 'AED');

        $records = HospitalProcedurePrice::where('procedure_id', $procedureId)
            ->whereNotNull('price')
            ->where('price', '>', 0)
            ->orderBy('price', 'asc')
            ->with('hospital:id,' . $columnsection_name_hpp)
            ->take(3)
            ->get()
            ->values(); // reindex: 0,1,2


        $bestValueRec    = $records[0] ?? null;
        $mostPopularRec  = $records[1] ?? null;
        $premiumCareRec  = $records[2] ?? null;

        $currency = request('currency', 'AED');

        $bestValuePrice   = $bestValueRec ? CurrencyHelper::convert($bestValueRec->price, $currency) : 0;
        $mostPopularPrice = $mostPopularRec ? CurrencyHelper::convert($mostPopularRec->price, $currency) : 0;
        $premiumCarePrice = $premiumCareRec ? CurrencyHelper::convert($premiumCareRec->price, $currency) : 0;

        $results = [
            'best_value' => $bestValueRec ? [
                'id' => $bestValueRec->id ?? 0,
                'hospital_id' => $bestValueRec->hospital->id ?? $bestValueRec->hospital_id,
                'hospital_name' => $bestValueRec->hospital->$columnsection_name_hpp ?? null,
                'price' => (float) $bestValuePrice
            ] : null,

            'most_popular' => $mostPopularRec ? [
                'id' => $mostPopularRec->id ?? 0,
                'hospital_id' => $mostPopularRec->hospital->id ?? $mostPopularRec->hospital_id,
                'hospital_name' => $mostPopularRec->hospital->$columnsection_name_hpp ?? null,
                'price' => (float) $mostPopularPrice
            ] : null,

            'premium_care' => $premiumCareRec ? [
                'id' => $premiumCareRec->id ?? 0,
                'hospital_id' => $premiumCareRec->hospital->id ?? $premiumCareRec->hospital_id,
                'hospital_name' => $premiumCareRec->hospital->$columnsection_name_hpp ?? null,
                'price' => (float) $premiumCarePrice
            ] : null,

            'mulk_price' => $bestValuePrice ? (float) $bestValuePrice : 0,
            'market_price' => $premiumCareRec ? (float) $premiumCarePrice : 0,
        ];

        $mulkPrice = $bestValuePrice ? (float) $bestValuePrice : 0;
        $marketPrice = $premiumCarePrice ? (float) $premiumCarePrice : 0;

        $savedAmount = 0;
        $savedPercent = 0;
        $savingText = null;

        if ($marketPrice > 0 && $mulkPrice > 0 && $marketPrice > $mulkPrice) {
            $savedAmount = $marketPrice - $mulkPrice;
            $savedPercent = ($savedAmount / $marketPrice) * 100;

            $savingText = "You save " . round($savedPercent) . "% i.e. ".$currency .' ' . number_format($savedAmount, 2) . " with Mulk Healthcare Discount Card.";
        }


        return response()->json([
            'status' => true,
            'results' => $results,
            'saving_text' => $savingText,
            'countries' => Country::select('id', "$columnsection_name_country as name")->get()
        ]);
    }


    public function cities(Request $request)
    {
        $countryId = $request->query('country_id');

        if (!$countryId) {
            return response()->json([
                'status' => false,
                'message' => 'country_id is required'
            ], 422);
        }

        $lang = request()->header('lang', 'en');

        $field = match ($lang) {
            'ar' => 'name_ar',
            'fr' => 'name_fr',
            'hi' => 'name_hi',
            'ur' => 'name_ur',
            default => 'name',
        };

        $cities = City::where('country_id', $countryId)
            ->select('id', "$field as name")
            ->orderBy($field)
            ->get();

        return response()->json([
            'status' => true,
            'data' => $cities
        ]);
    }


    
}