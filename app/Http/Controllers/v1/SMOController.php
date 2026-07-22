<?php

namespace App\Http\Controllers\v1;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\SmoSectionSequence;
use App\Models\TopHospitals;
use App\Models\TopProcedures;
use App\Models\MulkmedChoiceOfDoctors;
use App\Models\WhySecondOpinionMatters;
use App\Models\TrustedHealthcarePartners;
use App\Models\UnlockMoreBenefitsCard;
use App\Models\HospitalProcedures;
use App\Models\HospitalProcedurePrice;
use App\Models\QueryProcedures;
use App\Models\HospitalCategories;
use App\Models\DoctorCategories;
use App\Models\HospitalImages;
use App\Models\Hospitals;
use App\Models\Doctors;
use App\Models\SubmitYourQuery;
use App\Models\SMOQueries;
use App\Models\SMOEnquiries;
use App\Models\GlobalFunction;
use App\Models\SMOQueryDocs;
use App\Models\SMODocuments;
use App\Models\Users;
use App\Helpers\Helpers;
use Illuminate\Support\Facades\Validator;
use DB;
use Stichoza\GoogleTranslate\GoogleTranslate;

class SMOController extends Controller
{
    function dashboard()
    {
        $lang = request()->header('lang', 'en');

        $columnsection_name = match ($lang) {
                        'ar' => 'ar_section_name',
                        'fr' => 'fr_section_name',
                        'hi' => 'hi_section_name',
                        'ur' => 'ur_section_name',
                        default => 'section_name',
                    };
        $smo_sections = SmoSectionSequence::select('id',DB::raw("`$columnsection_name` as `section_name`"),'section_type as section_type')->where('is_deleted', 0)->orderBy('position', 'ASC')->where('status',1)->get();
        $sectionSequence = [];
        foreach ($smo_sections as $key => $sequence) {
            if($sequence->section_type == 'top_hospitals')
            {
                $section = [];
                $columnname = match ($lang) {
                        'ar' => 'ar_name',
                        'fr' => 'fr_name',
                        'hi' => 'hi_name',
                        'ur' => 'ur_name',
                        default => 'name',
                    };
                $section = TopHospitals::select('*',DB::raw("`$columnname` as `name`"))->where('is_deleted', 0)->orderBy('priority')->get();
                if($section != [])
                {
                    $sequence->section_data = $section;
                    array_push($sectionSequence,$sequence);
                }
            }
            if($sequence->section_type == 'filters')
            {
                $section = [];
                $section['hospital_procedures'] = HospitalProcedures::where('is_deleted', 0)->orderBy('id', 'DESC')->get();
                $section['specialities'] = DoctorCategories::where('is_deleted', 0)->orderBy('id', 'DESC')->get();
                $section['countries'] = Hospitals::select('country')->where('is_deleted', 0)->whereNotNull('country')->distinct()->orderBy('country')->pluck('country');
                $section['hospitals'] = Hospitals::where('is_deleted', 0)->orderBy('id', 'DESC')->get();
                $sequence->section_data = $section;
                array_push($sectionSequence,$sequence);
            }
            if($sequence->section_type == 'submit_your_query')
            {
                $section = [];
                $section = SubmitYourQuery::where('is_deleted', 0)->get();
                if($section != [])
                {
                    $sequence->section_data = $section;
                    array_push($sectionSequence,$sequence);
                }
            }
            if($sequence->section_type == 'why_second_opinion_matters')
            {
                $section = [];
                $section = WhySecondOpinionMatters::where('is_deleted', 0)->get();
                if($section != [])
                {
                    $sequence->section_data = $section;
                    array_push($sectionSequence,$sequence);
                }
            }
            if($sequence->section_type == 'explore_our_trusted_healthcare_providers')
            {
                $section = [];
                $columnname = match ($lang) {
                        'ar' => 'ar_name',
                        'fr' => 'fr_name',
                        'hi' => 'hi_name',
                        'ur' => 'ur_name',
                        default => 'name',
                    };
                $section = TrustedHealthcarePartners::select('*',DB::raw("`$columnname` as `name`"))->where('is_deleted', 0)->get();
                if($section != [])
                {
                    $sequence->section_data = $section;
                    array_push($sectionSequence,$sequence);
                }
            }

            if($sequence->section_type == 'browse_by_category')
            {
                $section = [];
                $columnname = match ($lang) {
                        'ar' => 'ar_name',
                        'fr' => 'fr_name',
                        'hi' => 'hi_name',
                        'ur' => 'ur_name',
                        default => 'name',
                    };
                $section['categories'] = HospitalCategories::select('*',DB::raw("`$columnname` as `name`"))->where('is_deleted', 0)->get();
                $columncountry = match ($lang) {
                        'ar' => 'ar_country',
                        'fr' => 'fr_country',
                        'hi' => 'hi_country',
                        'ur' => 'ur_country',
                        default => 'country',
                    };
                $section['countries'] = Hospitals::where('is_deleted', 0)->whereNotNull($columncountry)->where('country','United Arab Emirates')->distinct($columncountry)->orderBy($columncountry)->pluck($columncountry);

                $domain = request()->getHost();
                if($domain == "india.mulkmed.com")
                {
                    $section['countries'] = Hospitals::where('is_deleted', 0)->whereNotNull($columncountry)->where('country','India')->distinct($columncountry)->orderBy($columncountry)->pluck($columncountry);
                }

                if($section != [])
                {
                    $sequence->section_data = $section;
                    array_push($sectionSequence,$sequence);
                }
            }

            if($sequence->section_type == 'top_procedures')
            {
                $section = [];
                $columnname = match ($lang) {
                        'ar' => 'ar_name',
                        'fr' => 'fr_name',
                        'hi' => 'hi_name',
                        'ur' => 'ur_name',
                        default => 'name',
                    };

                $columndescription = match ($lang) {
                        'ar' => 'ar_description',
                        'fr' => 'fr_description',
                        'hi' => 'hi_description',
                        'ur' => 'ur_description',
                        default => 'description',
                    };
                $section = TopProcedures::select('id','image',DB::raw("`$columnname` as `name`"),DB::raw("`$columndescription` as `description`"))
                            ->where('is_deleted', 0)->get();
                if($section != [])
                {
                    $sequence->section_data = $section;
                    array_push($sectionSequence,$sequence);
                }
            }
            if($sequence->section_type == 'mulkmed_choice_of_doctors')
            {
                $section = [];

                $columndescription = match ($lang) {
                        'ar' => 'ar_description',
                        'fr' => 'fr_description',
                        'hi' => 'hi_description',
                        'ur' => 'ur_description',
                        default => 'description',
                    };
                $section = MulkmedChoiceOfDoctors::select('*',DB::raw("`$columndescription` as `description`"))->where('is_deleted', 0)->get();
                if($section != [])
                {
                    $sequence->section_data = $section;
                    array_push($sectionSequence,$sequence);
                }
            }
        }
        
        return response()->json([
            'status' => true, 
            'sectionSequence' => $sectionSequence
        ]);
    }

    function getHospitalsAndDoctors(Request $request)
    {
        $hostAndConversionRate = Helpers::conversionRate();
        $conversionRate = (float) $hostAndConversionRate['conversionRate'];
        $hospital_query = Hospitals::where('is_deleted', 0);
        if($request->has('country'))
        {
            $hospital_query = $hospital_query->where('country',$request->country);
        }
        if($request->has('hospital'))
        {
            $hospital_query = $hospital_query->where('id',$request->hospital);
        }
        // if($request->has('speciality'))
        // {
        //     $doctor_with_speciality = Doctors::where('category_id',$request->speciality)->where('status',1)->pluck('clinic_name');
        //     $hospital_query = $hospital_query->whereIn('name',$doctor_with_speciality);
        // }
        if($request->has('procedure'))
        {
            $procedureId = (string) $request->procedure;
            $hospital_query = $hospital_query->whereRaw("JSON_CONTAINS(procedure_ids, '\"$procedureId\"')");
        }

        // $hospitals      = $hospital_query->orderBy('id', 'DESC')->get();
        $hospitalNames  = $hospital_query->pluck('name')->toArray();
        // only speciality
        if($request->has('speciality') && (!$request->has('hospital')))
        {
            $doctors = Doctors::select('doctors.*',DB::raw("ROUND(consultation_fee * {$conversionRate}) as consultation_fee"))
                            ->where('category_id',$request->speciality)->where('status',1)->orderByRaw('FIELD(id, 88) DESC')
                            ->orderBy('id', 'DESC') // then others normally
                            ->get();
            // $hospital_query = $hospital_query->whereIn('name',$doctor_with_speciality);
        }
        // only hospital
        else if($request->has('hospital') && (!$request->has('speciality'))){
            $doctors = Doctors::select('doctors.*',DB::raw("ROUND(consultation_fee * {$conversionRate}) as consultation_fee"))
                            ->whereIn('clinic_name', $hospitalNames)->where('status',1)->orderByRaw('FIELD(id, 88) DESC')
                            ->orderBy('id', 'DESC') // then others normally
                            ->get();
        }
        // both hospital and speciality
        else if($request->has('hospital') && ($request->has('speciality'))){
            $doctors        = Doctors::select('doctors.*',DB::raw("ROUND(consultation_fee * {$conversionRate}) as consultation_fee"))
                            ->whereIn('clinic_name', $hospitalNames)
                            ->where('category_id',$request->speciality)
                            ->where('status', 1)
                            ->orderByRaw('FIELD(id, 88) DESC') // doctor with ID 88 first
                            ->orderBy('id', 'DESC') // then others normally
                            ->get();
        }
        // none
        else{
              $doctors  = Doctors::select('doctors.*',DB::raw("ROUND(consultation_fee * {$conversionRate}) as consultation_fee"))
                            ->whereIn('clinic_name', $hospitalNames)
                            ->where('status', 1)
                            ->orderByRaw('FIELD(id, 88) DESC') // doctor with ID 88 first
                            ->orderBy('id', 'DESC') // then others normally
                            ->get();
        }

        $lang = request()->header('lang', 'en');
        $columnname = match ($lang) {
                        'ar' => 'ar_name',
                        'fr' => 'fr_name',
                        'hi' => 'hi_name',
                        'ur' => 'ur_name',
                        default => 'name',
                    };
        $columncountry = match ($lang) {
                            'ar' => 'ar_country',
                            'fr' => 'fr_country',
                            'hi' => 'hi_country',
                            'ur' => 'ur_country',
                            default => 'country',
                        };

        $columnaddress = match ($lang) {
                            'ar' => 'ar_address',
                            'fr' => 'fr_address',
                            'hi' => 'hi_address',
                            'ur' => 'ur_address',
                            default => 'address',
                        };

        $columnclinic_timing = match ($lang) {
                            'ar' => 'ar_clinic_timing',
                            'fr' => 'fr_clinic_timing',
                            'hi' => 'hi_clinic_timing',
                            'ur' => 'ur_clinic_timing',
                            default => 'clinic_timing',
                        };
        $columnservices_offered = match ($lang) {
                            'ar' => 'ar_services_offered',
                            'fr' => 'fr_services_offered',
                            'hi' => 'hi_services_offered',
                            'ur' => 'ur_services_offered',
                            default => 'services_offered',
                        };

        $columnexclusive_mulkmed_benefits = match ($lang) {
                            'ar' => 'ar_exclusive_mulkmed_benefits',
                            'fr' => 'fr_exclusive_mulkmed_benefits',
                            'hi' => 'hi_exclusive_mulkmed_benefits',
                            'ur' => 'ur_exclusive_mulkmed_benefits',
                            default => 'exclusive_mulkmed_benefits',
                        };
        
        $hospitals      = $hospital_query->select('id',DB::raw("`$columnname` as `name`"),DB::raw("`$columncountry` as `country`"),
                            DB::raw("`$columnaddress` as `address`"),DB::raw("`$columnservices_offered` as `services_offered`"),
                            DB::raw("`$columnexclusive_mulkmed_benefits` as `exclusive_mulkmed_benefits`"),'image as image',
                            'image as image','rating as rating','rating_count as rating_count','longitude as longitude',
                            'latitude as latitude','website as website','contact_number as contact_number',DB::raw("`$columnclinic_timing` as `clinic_timing`"),
                            'procedure_ids as procedure_ids')->orderBy('id', 'DESC')->get();
        $unlockMoreBenefitsCard = UnlockMoreBenefitsCard::where('is_deleted', 0)->orderBy('id', 'DESC')->get();
        
        return response()->json([
            'hospitals' => $hospitals,
            'doctors' => $doctors,
            'unlockMoreBenefitsCard' => $unlockMoreBenefitsCard
        ]);

    }

    function getCategoryDatails($id, Request $request)
    {
        $lang = request()->header('lang', 'en');
        $columnname = match ($lang) {
                        'ar' => 'ar_name',
                        'fr' => 'fr_name',
                        'hi' => 'hi_name',
                        'ur' => 'ur_name',
                        default => 'name',
                    };
        $trustedHealthcarePartners = TrustedHealthcarePartners::select('*',DB::raw("`$columnname` as `name`"))->where('is_deleted', 0)->get();

        $columnname = match ($lang) {
                        'ar' => 'ar_name',
                        'fr' => 'fr_name',
                        'hi' => 'hi_name',
                        'ur' => 'ur_name',
                        default => 'name',
                    };
        $columncountry = match ($lang) {
                            'ar' => 'ar_country',
                            'fr' => 'fr_country',
                            'hi' => 'hi_country',
                            'ur' => 'ur_country',
                            default => 'country',
                        };

        $columnaddress = match ($lang) {
                            'ar' => 'ar_address',
                            'fr' => 'fr_address',
                            'hi' => 'hi_address',
                            'ur' => 'ur_address',
                            default => 'address',
                        };

        $columnclinic_timing = match ($lang) {
                            'ar' => 'ar_clinic_timing',
                            'fr' => 'fr_clinic_timing',
                            'hi' => 'hi_clinic_timing',
                            'ur' => 'ur_clinic_timing',
                            default => 'clinic_timing',
                        };

        $columnservices_offered = match ($lang) {
                            'ar' => 'ar_services_offered',
                            'fr' => 'fr_services_offered',
                            'hi' => 'hi_services_offered',
                            'ur' => 'ur_services_offered',
                            default => 'services_offered',
                        };

        $columnexclusive_mulkmed_benefits = match ($lang) {
                            'ar' => 'ar_exclusive_mulkmed_benefits',
                            'fr' => 'fr_exclusive_mulkmed_benefits',
                            'hi' => 'hi_exclusive_mulkmed_benefits',
                            'ur' => 'ur_exclusive_mulkmed_benefits',
                            default => 'exclusive_mulkmed_benefits',
                        };
        $hospitals = Hospitals::select('id',DB::raw("`$columnname` as `name`"),DB::raw("`$columncountry` as `country`"),
                            DB::raw("`$columnaddress` as `address`"),DB::raw("`$columnservices_offered` as `services_offered`"),
                            DB::raw("`$columnexclusive_mulkmed_benefits` as `exclusive_mulkmed_benefits`"),'image as image',
                            'image as image','rating as rating','rating_count as rating_count','longitude as longitude',
                            'latitude as latitude','website as website','contact_number as contact_number',DB::raw("`$columnclinic_timing` as `clinic_timing`"),
                            'procedure_ids as procedure_ids')
                            ->where('is_deleted', 0)->whereRaw("JSON_CONTAINS(category, '\"$id\"')")->get();

        if($request->has('country') && $request->country != null)
        {
            $hospitals = Hospitals::select('id',DB::raw("`$columnname` as `name`"),DB::raw("`$columncountry` as `country`"),
                            DB::raw("`$columnaddress` as `address`"),DB::raw("`$columnservices_offered` as `services_offered`"),
                            DB::raw("`$columnexclusive_mulkmed_benefits` as `exclusive_mulkmed_benefits`"),'image as image',
                            'image as image','rating as rating','rating_count as rating_count','longitude as longitude',
                            'latitude as latitude','website as website','contact_number as contact_number',DB::raw("`$columnclinic_timing` as `clinic_timing`"),
                            'procedure_ids as procedure_ids')
                            ->where('is_deleted', 0)->where($columncountry,$request->country)->whereRaw("JSON_CONTAINS(category, '\"$id\"')")->get();
        }
        
        $smo_sections = SmoSectionSequence::where('section_type', 'why_second_opinion_matters')->where('status',1)->first();
        $section = [];

        if(isset($smo_sections))
        {
            $section = WhySecondOpinionMatters::where('is_deleted', 0)->get();
        }

        return response()->json([
            'trustedHealthcarePartners' => $trustedHealthcarePartners,
            'hospitals' => $hospitals,
            'why_second_opinion_matters' => $section
        ]);
    }

    function getHospitalDatails($id,Request $request)
    {
        $lang = request()->header('lang', 'en');

        $columnname = match ($lang) {
                        'ar' => 'ar_name',
                        'fr' => 'fr_name',
                        'hi' => 'hi_name',
                        'ur' => 'ur_name',
                        default => 'name',
                    };

        $columncountry = match ($lang) {
                            'ar' => 'ar_country',
                            'fr' => 'fr_country',
                            'hi' => 'hi_country',
                            'ur' => 'ur_country',
                            default => 'country',
                        };

        $columnaddress = match ($lang) {
                            'ar' => 'ar_address',
                            'fr' => 'fr_address',
                            'hi' => 'hi_address',
                            'ur' => 'ur_address',
                            default => 'address',
                        };

        $columnclinic_timing = match ($lang) {
                            'ar' => 'ar_clinic_timing',
                            'fr' => 'fr_clinic_timing',
                            'hi' => 'hi_clinic_timing',
                            'ur' => 'ur_clinic_timing',
                            default => 'clinic_timing',
                        };

        $columnservices_offered = match ($lang) {
                            'ar' => 'ar_services_offered',
                            'fr' => 'fr_services_offered',
                            'hi' => 'hi_services_offered',
                            'ur' => 'ur_services_offered',
                            default => 'services_offered',
                        };

        $columnexclusive_mulkmed_benefits = match ($lang) {
                            'ar' => 'ar_exclusive_mulkmed_benefits',
                            'fr' => 'fr_exclusive_mulkmed_benefits',
                            'hi' => 'hi_exclusive_mulkmed_benefits',
                            'ur' => 'ur_exclusive_mulkmed_benefits',
                            default => 'exclusive_mulkmed_benefits',
                        };
        $data = Hospitals::select('id','name as hospital_name',DB::raw("`$columnname` as `name`"),DB::raw("`$columncountry` as `country`"),
                            DB::raw("`$columnaddress` as `address`"),DB::raw("`$columnservices_offered` as `services_offered`"),
                            DB::raw("`$columnexclusive_mulkmed_benefits` as `exclusive_mulkmed_benefits`"),'image as image',
                            'image as image','rating as rating','rating_count as rating_count','longitude as longitude',
                            'latitude as latitude','website as website','contact_number as contact_number',DB::raw("`$columnclinic_timing` as `clinic_timing`"),
                            'procedure_ids as procedure_ids')
                            ->where('id',$id)->where('is_deleted', 0)->first();

        // $services_offered = array_map('trim', explode(',', $data->services_offered));
        // $exclusive_mulkmed_benefits = array_map('trim', explode(',', $data->exclusive_mulkmed_benefits));

        $services_offered = preg_split('/[,،]/u', $data->services_offered);
        $services_offered = array_map('trim', $services_offered);

        $exclusive_mulkmed_benefits = preg_split('/[,،]/u', $data->exclusive_mulkmed_benefits);
        $exclusive_mulkmed_benefits = array_map('trim', $exclusive_mulkmed_benefits);

        $procedureIds = json_decode($data->procedure_ids, true);
        $columnprocedure = match ($lang) {
                        'ar' => 'ar_procedure',
                        'fr' => 'fr_procedure',
                        'hi' => 'hi_procedure',
                        'ur' => 'ur_procedure',
                        default => 'procedure',
                    };

        $procedures = [];                    
        if($procedureIds){
            $procedures = HospitalProcedures::whereIn('id', $procedureIds)->pluck($columnprocedure);
        }
        $photos = HospitalImages::where('hospital_id', $data->id)->where('is_deleted', 0)->pluck('image');

        $hostAndConversionRate = Helpers::conversionRate();
        $conversionRate = (float) $hostAndConversionRate['conversionRate'];

        $doctors = Doctors::select('doctors.*',DB::raw("ROUND(consultation_fee * {$conversionRate}) as consultation_fee"))
                    ->where('clinic_name', $data->hospital_name)->get();

        $hospital = []; 
        $hospital['id']                         = $data->id;
        $hospital['name']                       = $data->name;
        $hospital['image']                      = $data->image;
        $hospital['rating']                     = $data->rating;
        $hospital['rating_count']               = $data->rating_count;
        $hospital['country']                    = $data->country;
        $hospital['address']                    = $data->address;
        $hospital['longitude']                  = $data->longitude;
        $hospital['latitude']                   = $data->latitude;
        $hospital['website']                    = $data->website;
        $hospital['contact_number']             = $data->contact_number;
        $hospital['clinic_timing']              = $data->clinic_timing;
        $hospital['services_offered']           = $services_offered;
        $hospital['exclusive_mulkmed_benefits'] = $exclusive_mulkmed_benefits;
        $hospital['procedures']                 = $procedures;
        $hospital['photos']                     = $photos;
        $hospital['doctors']                    = $doctors;

        return response()->json(['status' => true, 'hospital' => $hospital]);
    }

    function getProcedures(Request $request)
    {
        $lang = request()->header('lang', 'en');

        $columnprocedure = match ($lang) {
                        'ar' => 'ar_procedure',
                        'fr' => 'fr_procedure',
                        'hi' => 'hi_procedure',
                        'ur' => 'ur_procedure',
                        default => 'procedure',
                    };
        $query = HospitalProcedures::where('is_deleted', 0);

        if ($request->has('search')) {
            $query->where('procedure', 'LIKE', "%{$request->search}%");
        }

        if ($request->has('hospital')) {
            $hospitals = Hospitals::where('id', $request->hospital)
                ->where('is_deleted', 0)
                ->get();

            $allProcedureIds = [];

            foreach ($hospitals as $hospital) {
                $ids = json_decode($hospital->procedure_ids, true);
                if (is_array($ids)) {
                    $allProcedureIds = array_merge($allProcedureIds, $ids);
                }
            }

            $allProcedureIds = array_unique($allProcedureIds);
            $query->whereIn('id', $allProcedureIds);
        }

        if ($request->has('country')) {
            $hospitals = Hospitals::where('country', $request->country)
                ->where('is_deleted', 0)
                ->get();

            $allProcedureIds = [];

            foreach ($hospitals as $hospital) {
                $ids = json_decode($hospital->procedure_ids, true);
                if (is_array($ids)) {
                    $allProcedureIds = array_merge($allProcedureIds, $ids);
                }
            }

            $allProcedureIds = array_unique($allProcedureIds);
            $query->whereIn('id', $allProcedureIds);
        }

        $limit = $request->get('limit', 10);
        $offset = $request->get('offset', 0);

        $total = $query->count();

        $hospital_procedures = $query->select('id', DB::raw("`$columnprocedure` as `procedure`"))
                                ->orderBy($columnprocedure, 'ASC')
                                ->whereNotNull($columnprocedure)
                                ->paginate($limit, ['*'], 'page', $offset);
        return response()->json(['status' => true, 'hospital_procedures' => $hospital_procedures]);
    }

    function getSpeciality(Request $request)
    {
        $hostAndConversionRate = Helpers::conversionRate();
        $conversionRate = (float) $hostAndConversionRate['conversionRate'];
        $lang = request()->header('lang', 'en');

        $columntitle = match ($lang) {
                        'ar' => 'ar_title',
                        'fr' => 'fr_title',
                        'hi' => 'hi_title',
                        'ur' => 'ur_title',
                        default => 'title',
                    };

        $query = DoctorCategories::where('is_deleted', 0);        
        if ($request->has('country')) {
            $hospitalNames = Hospitals::where('country', $request->country)
                            ->where('is_deleted', 0)
                            ->pluck('name')->toArray();

            $doctorsSpeciality = Doctors::select('doctors.*',DB::raw("ROUND(consultation_fee * {$conversionRate}) as consultation_fee"))
                                ->whereIn('clinic_name', $hospitalNames)->where('status', 1)
                                ->orderBy('id', 'DESC')
                                ->pluck('category_id')->toArray();

            $query = $query->whereIn('id',$doctorsSpeciality);
        }
        if ($request->has('hospital')) {
            $hospitalNames = Hospitals::where('id', $request->hospital)
                            ->where('is_deleted', 0)
                            ->pluck('name')->toArray();

            $doctorsSpeciality = Doctors::select('doctors.*',DB::raw("ROUND(consultation_fee * {$conversionRate}) as consultation_fee"))
                                ->whereIn('clinic_name', $hospitalNames)->where('status', 1)
                                ->orderBy('id', 'DESC')
                                ->pluck('category_id')->toArray();

            $doctorsSpeciality = array_unique($doctorsSpeciality);

            $query = $query->whereIn('id',$doctorsSpeciality);
        }        
        if ($request->has('search')) {
            $query->where($columntitle, 'LIKE', "%{$request->search}%");
        }

        $limit = $request->get('limit', 10);
        $offset = $request->get('offset', 0);

        $total = $query->count();

        $specialities = $query->select('id',DB::raw("`$columntitle` as `title`"))->orderBy($columntitle, 'ASC')
                                ->whereNotNull($columntitle)
                                ->paginate($limit, ['*'], 'page', $offset);


        return response()->json(['status' => true, 'specialities' => $specialities]);
    }

    function getCountries(Request $request)
    {
        $lang = request()->header('lang', 'en');

        $columncountry = match ($lang) {
                        'ar' => 'ar_country',
                        'fr' => 'fr_country',
                        'hi' => 'hi_country',
                        'ur' => 'ur_country',
                        default => 'country',
                    };
        $search = $request->input('search');
        $query = Hospitals::select('country')
                    ->where('is_deleted', 0)
                    ->whereNotNull($columncountry)
                    ->distinct();

        if ($search) {
            $query->where($columncountry, 'like', "%{$search}%");
        }

        $limit = $request->get('limit', 2);
        $offset = $request->get('offset', 0);
        $countries = $query->select('country as name',DB::raw("`$columncountry` as `country`"))->orderBy('country')->paginate($limit, ['*'], 'page', $offset);
        return response()->json(['status' => true, 'countries' => $countries]);
    }

    function getHospitals(Request $request)
    {
        $lang = request()->header('lang', 'en');

        $columnname = match ($lang) {
                        'ar' => 'ar_name',
                        'fr' => 'fr_name',
                        'hi' => 'hi_name',
                        'ur' => 'ur_name',
                        default => 'name',
                    };

        $query = Hospitals::whereRaw("JSON_CONTAINS(category, '\"6\"')")->where('is_deleted', 0)->orderBy('name', 'asc');

        if ($request->has('search')) {
            $query->where($columnname, 'LIKE', "%{$request->search}%");
        }
  
        if ($request->has('country')) {
            $query = $query->where('country', $request->country);
        }

        $limit = $request->get('limit', 10);
        $offset = $request->get('offset', 0);

        $total = $query->count();

        $hospitals = $query->select('id',DB::raw("`$columnname` as `name`"))->orderBy('id', 'DESC')
                                ->paginate($limit, ['*'], 'page', $offset);
        return response()->json(['status' => true, 'hospitals' => $hospitals]);
    }

    function getQueryProcedures(Request $request)
    {
        $lang = request()->header('lang', 'en');

        $columnprocedure = match ($lang) {
                        'ar' => 'ar_procedure',
                        'fr' => 'fr_procedure',
                        'hi' => 'hi_procedure',
                        'ur' => 'ur_procedure',
                        default => 'procedure',
                    };

        $query = QueryProcedures::where('is_deleted', 0);
        if ($request->has('search')) {
            $query->where($columnprocedure, 'LIKE', "%{$request->search}%");
        }

        $limit = $request->get('limit', 10);
        $offset = $request->get('offset', 0);

        $total = $query->count();

        $query_procedures = $query->select('id', DB::raw("`$columnprocedure` as `procedure`"))
                                ->orderBy($columnprocedure, 'ASC')
                                ->whereNotNull($columnprocedure)
                                ->paginate($limit, ['*'], 'page', $offset);

        return response()->json(['status' => true, 'query_procedures' => $query_procedures]);
    }

    function submitSMOQuery(Request $request)
    {
        $smo_query = new SMOQueries();
        $smo_query->query_id = $request->query_id;
        $smo_query->full_name = $request->full_name;
        $smo_query->contact_number = $request->contact_number;
        $smo_query->email = $request->email;
        $smo_query->location = $request->location;
        $smo_query->comment = $request->comment;
        $smo_query->save();
        if ($request->has('medical_report')) {
            foreach ($request->medical_report as $document) {
                $docs = new SMOQueryDocs();
                $docs->smo_query_id = $smo_query->id;
                $docs->document = GlobalFunction::saveFileAndGivePath($document);
                $docs->save();
            }
        }
        return response()->json(['status' => true, 'message' => "Your Query Submitted Successfully"]);
    }

    function submitSMOEnquiry(Request $request)
    {
        $rules = [
            'enquire_id' => 'required',
            'currency_type' => 'required',
            'price' => 'required'
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }

        $hpp = HospitalProcedurePrice::where('id', $request->enquire_id)->first();

        $hospital = Hospitals::find($hpp->hospital_id);
        $hospital_procedure = HospitalProcedures::find($hpp->procedure_id);

        $smo_query = new SMOEnquiries();
        $smo_query->query_id = $request->query_id;
        $smo_query->full_name = $request->full_name;
        $smo_query->contact_number = $request->contact_number;
        $smo_query->email = $request->email;
        $smo_query->location = $request->location;
        $smo_query->comment = $request->comment;
        $smo_query->hospital_name = $hospital->name;
        $smo_query->hospital_procedure = $hospital_procedure->procedure;
        $smo_query->currency_type = $request->currency_type;
        $smo_query->enquire_id = $request->enquire_id;
        $smo_query->save();
        if ($request->has('medical_report')) {
            foreach ($request->medical_report as $document) {
                $docs = new SMOQueryDocs();
                $docs->smo_query_id = $smo_query->id;
                $docs->document = GlobalFunction::saveFileAndGivePath($document);
                $docs->save();
            }
        }
        return response()->json(['status' => true, 'message' => "Your Query Submitted Successfully"]);
    }

   public function submitDocuments(Request $request)
    {
        $request->validate([
            'doctor_id' => 'required',
            'patient_id' => 'required',
            'documents'  => 'required|array|min:1',
            
        ]);

        $attachments = [];
        if ($request->has('documents')) {
            foreach ($request->documents as $document) {
                $docs = new SMODocuments();
                $docs->doctor_id = $request->doctor_id;
                $docs->patient_id = $request->patient_id;
                $docs->document = GlobalFunction::saveFileAndGivePath($document);
                $docs->save();
                $attachments[] = GlobalFunction::createMediaUrl($docs->document);
            }

                $user = Users::find($request->patient_id);
                $doctor = Doctors::find($request->doctor_id);

            \Mail::to('info@mulkmed.com')->send(
                        new \App\Mail\AppointmentDocumentsMail(
                            $user->fullname,
                            $user->phone_number,
                            $user->email,
                            $doctor->name,
                            $doctor->clinic_name,
                            $attachments
                        )
                    );
        }

       
        return response()->json([
            'status' => true,
            'message' => 'Your document(s) submitted successfully.'
        ]);
    }
     public function autoTranslateHospitals()
    {
        try {
            $hospitals = Hospitals::where('is_deleted', 0)->get();
 
            $trAr = new GoogleTranslate('ar');
            $trFr = new GoogleTranslate('fr');
            $trHi = new GoogleTranslate('hi');
            $trUr = new GoogleTranslate('ur');
 
            $count = 0;
            foreach ($hospitals as $hospital) {
                $updated = false;
                $name = $hospital->name;
 
                if (empty($name)) continue;
 
                // Arabic
                if (empty($hospital->ar_name) || $hospital->ar_name == $name) {
                    try {
                        $hospital->ar_name = $trAr->translate($name);
                        $updated = true;
                    } catch (\Exception $e) {}
                }
 
                // French
                if (empty($hospital->fr_name) || $hospital->fr_name == $name) {
                    try {
                        $hospital->fr_name = $trFr->translate($name);
                        $updated = true;
                    } catch (\Exception $e) {}
                }
 
                // Hindi
                if (empty($hospital->hi_name) || $hospital->hi_name == $name) {
                    try {
                        $hospital->hi_name = $trHi->translate($name);
                        $updated = true;
                    } catch (\Exception $e) {}
                }
 
                // Urdu
                if (empty($hospital->ur_name) || $hospital->ur_name == $name) {
                    try {
                        $hospital->ur_name = $trUr->translate($name);
                        $updated = true;
                    } catch (\Exception $e) {}
                }
 
                if ($updated) {
                    $hospital->save();
                    $count++;
                }
            }
 
            return response()->json([
                'status' => true,
                'message' => "Successfully updated translations for $count hospitals.",
                'updated_count' => $count
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error during translation: ' . $e->getMessage()
            ]);
        }
    }
}