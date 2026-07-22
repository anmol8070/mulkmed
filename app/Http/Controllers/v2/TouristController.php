<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\TravelFlowBanner;
use App\Models\TouristList;
use App\Models\Doctors;
use App\Models\DoctorCategories;
use App\Models\Constants;
use App\Models\TouristAppointments;
use App\Models\GlobalFunction;
use App\Models\GlobalSettings;
use App\Models\TouristJitsiMeeting;
use App\Models\TouristIsabelReport;
use App\Models\TouristDoctorReviews;
use App\Models\DoctorEarningHistory;
use App\Models\PlatformEarningHistory;
use App\Models\PlatformData;
use App\Models\TouristPrescription;
use App\Models\TouristAIVital;
use App\Models\TouristAIVitalScanMisa;
use App\Models\TouristAppointmentDocs;
use App\Models\TouristRankedDifferentialDiagnoses;
use App\Models\TouristAppointmentEmrs;
use App\Models\IsabelPredictiveText;
use App\Models\IsabelAgeGroup;
use App\Models\IsabelPregnancies;
use App\Models\IsabelRegions;
use App\Models\IsabelCountries;
use App\Models\IsabelQuestion;
use App\Models\IsabelAnswer;
use App\Models\TouristIsabelChatCount;
use App\Helpers\EmailHelpers;
use App\Helpers\Helpers;
use App\Support\IsabelI18n;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use DB;
use PDF;

class TouristController extends Controller
{
    public function getTouristBanners()
    {
        $banners = TravelFlowBanner::select('id', 'tourist_partner_banner')
                    ->where('banner_type','login_page')
                    ->first();

        return response()->json([
            'status' => true,
            'data' => $banners
        ]);
    }

    public function touristLogin(Request $request)
    {
        //  $tourist = TouristList::where('first_name',$request->first_name)->where('contact_number',$request->contact_number)->where('country_code', $request->country_code)
                        
          // Accept both:
        // - New request shape: `full_name`
        // - Old request shape: `first_name` (+ optional `last_name`)
        $fullName = trim((string) ($request->full_name ?? ''));
        if ($fullName === '') {
            $firstName = trim((string) ($request->first_name ?? ''));
            $lastName  = trim((string) ($request->last_name ?? ''));
            $fullName  = trim($firstName . ' ' . $lastName);
        }

        // Final fallback: some clients may send only `name`
        if ($fullName === '') {
            $fullName = trim((string) ($request->name ?? ''));
        }

        // DB may contain either:
        // - old format: whole `full_name` stored in `first_name`
        // - new format: `first_name` = first token, `last_name` = remaining tokens
        // So we search by both possibilities.
        $fullNameNormalized = preg_replace('/[\x{00A0}\x{2007}\x{202F}]+/u', ' ', $fullName);
        $fullNameNormalized = preg_replace('/\s+/u', ' ', trim($fullNameNormalized));
        $firstNameKey = explode(' ', $fullNameNormalized, 2)[0] ?? $fullNameNormalized;

        // Contact can be stored either as:
        // - separate: country_code + contact_number
        // - combined: (country_code + contact_number) inside `contact_number`
        $requestCountryCode = ltrim((string) ($request->country_code ?? ''), '+');
        $requestContactNumber = trim((string) ($request->contact_number ?? ''));

       // Keep digits only to avoid mismatches from spaces/formatting.
        $requestContactNumberDigits = preg_replace('/\D+/', '', $requestContactNumber);
        $contactWithCountry = ($requestCountryCode !== '')
            ? $requestCountryCode . $requestContactNumberDigits
            : $requestContactNumberDigits;

        // Pick the *active* tourist record (for agent_type = 1)
        // where today's date is between fly_in and fly_out,
        // to avoid older/expired duplicates blocking login.
        $today = Carbon::today();

        $tourist = TouristList::where(function ($q) use ($fullNameNormalized, $firstNameKey) {
                            $q->where('first_name', $fullNameNormalized)
                              ->orWhere('first_name', $firstNameKey);
                        })
                        ->where(function ($q) use ($requestContactNumberDigits, $contactWithCountry) {
                            // Match either plain or combined phone representation.
                            $q->where('contact_number', $requestContactNumberDigits)
                              ->orWhere('contact_number', $contactWithCountry);
                        })
                        ->where(function ($q) use ($today) {
                            $q->where(function ($qq) use ($today) {
                                $qq->where('agent_type', 1)
                                   ->whereDate('fly_in', '<=', $today)
                                   ->whereDate('fly_out', '>=', $today);
                            })
                            // For other agent types (2,3,...) keep previous behaviour:
                            // they will be validated later using their own dates.
                            ->orWhereIn('agent_type', [2, 3]);
                        })
                        ->orderBy('id', 'desc')
                        ->first();
        if(isset($tourist))
        {
            if($tourist->agent_type == 1)
            {
                // Allow login for the whole calendar days from fly_in to fly_out (inclusive)
                $checkInDate  = Carbon::parse($tourist->fly_in)->toDateString();
                $checkOutDate = Carbon::parse($tourist->fly_out)->toDateString();
                $todayDate    = Carbon::today()->toDateString();

                if ($todayDate >= $checkInDate && $todayDate <= $checkOutDate)
                {
                    $tourist->status = 1;
                    $tourist->save();
                    $tourist->expiry_date = $tourist->fly_out;
                    return response()->json([
                        'status' => true,
                        'data' => $tourist
                    ]);
                }
                else{
                    return response()->json([
                                'status' => false,
                                'message' => "tourist not found"
                            ]);
                }
            }
            elseif($tourist->agent_type == 2)
            {
                $checkIn  = Carbon::parse($tourist->check_in_time);
                $checkOut = Carbon::parse($tourist->check_out_time);
                $now      = Carbon::now();
                if ($now->between($checkIn, $checkOut))
                {
                    $tourist->status = 1;
                    $tourist->save();
                    $tourist->expiry_date = $tourist->check_out_time;
                    return response()->json([
                        'status' => true,
                        'data' => $tourist
                    ]);
                }
                else{
                    return response()->json([
                                'status' => false,
                                'message' => "tourist not found"
                            ]);
                }
            }elseif($tourist->agent_type == 3)
            {
                $checkIn  = Carbon::parse($tourist->start_date);
                // If validity is 30 days, it should be expired after 90 days from issue date.
                // If validity is 60 days, it should expire after 120 days from issue date
                 $visa_expiry_days = $tourist->visa_expiry_days;
                if($tourist->visa_expiry_days == 30){
                    $visa_expiry_days = 90;
                }

                if($tourist->visa_expiry_days == 60){
                    $visa_expiry_days = 120;
                }

                // $checkOut = $checkIn->copy()->addDays($tourist->visa_expiry_days);
                $checkOut = $checkIn->copy()->addDays($visa_expiry_days);
                $now      = Carbon::now();
                if ($now->between($checkIn, $checkOut))
                {
                    $tourist->status = 1;
                    $tourist->save();
                    $tourist->expiry_date = $checkOut;
                    $tourist->visa_expiry_days = (int)$tourist->visa_expiry_days;
                    return response()->json([
                        'status' => true,
                        'data' => $tourist
                    ]);
                }
                else{
                    return response()->json([
                                'status' => false,
                                'message' => "tourist not found"
                            ]);
                }
            }
            else{
                return response()->json([
                            'status' => false,
                            'message' => "tourist not found"
                        ]);
            }
        }else{
            return response()->json([
                        'status' => false,
                        'message' => "tourist not found"
                    ]);
        }
        
    }


    public function updateTouristFCM(Request $request)
    {
        try {

            $validated = $request->validate([
                'id'           => 'required|integer|exists:tourist_list,id',
                'device_token' => 'required|string'
            ]);

            $updated = TouristList::where('id', $validated['id'])
                            ->update([
                                'device_token' => $validated['device_token']
                            ]);

            if (!$updated) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Failed to update FCM token'
                ], 400);
            }

            return response()->json([
                'status'  => true,
                'message' => 'Tourist FCM token updated successfully'
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {

            return response()->json([
                'status'  => false,
                'message' => $e->errors()
            ], );

        } catch (\Exception $e) {


            return response()->json([
                'status'  => false,
                'message' => $e->getMessage() //'Something went wrong'
            ], );
        }
    }

    public function homePage(Request $request)
    {
        $doctors = [];
        $sectionSequence = [];
        $hostAndConversionRate = Helpers::conversionRate();
        $conversionRate = (float) $hostAndConversionRate['conversionRate'];
        $sequence = [];

        if($request->has('tourist_id'))
        {
            $tourist = TouristList::where('id',$request->tourist_id)->first();
        }

        $hospital_doctors_is_disabled = 0;
        $dr_midas_is_disabled = 0;
        $wellness_health_check_is_disabled = 0;
        if(isset($tourist))
        {
            if($tourist->number_of_consultation == 0)
            {
                $hospital_doctors_is_disabled = 1;
            }
            if($tourist->number_of_midas == 0)
            {
                $dr_midas_is_disabled = 1;
            }
            if($tourist->number_of_ai_health_check == 0)
            {
                $wellness_health_check_is_disabled = 1;
            }
        }

        if($request->has('search'))
        {
            $lang = request()->header('lang', 'en');

            $columndesignation = match ($lang) {
                'ar' => 'ar_designation',
                'fr' => 'fr_designation',
                'hi' => 'hi_designation',
                'ur' => 'ur_designation',
                default => 'designation',
            };

            $columnlanguages_spoken = match ($lang) {
                'ar' => 'ar_languages_spoken',
                'fr' => 'fr_languages_spoken',
                'hi' => 'hi_languages_spoken',
                'ur' => 'ur_languages_spoken',
                default => 'languages_spoken',
            };

            $section = Doctors::select('*', DB::raw("$columndesignation as designation"), DB::raw("$columnlanguages_spoken as languages_spoken"),
                            DB::raw("ROUND(consultation_fee * {$conversionRate}) as consultation_fee"))
                        ->with('expertise')
                        ->where('status', Constants::statusDoctorApproved)
                        ->where('on_vacation', Constants::doctorNotOnVacation)
                        ->where("name", 'like', '%' . $request->search . '%')
                        // ->where('is_mulkmed', 1)
                        ->where('travel_visible', 1)
                        ->orderBy('is_online', 'DESC')
                        ->get();
            $cats = DoctorCategories::where('is_deleted', 0)->where("title", 'like', '%' . $request->search . '%')->get();
        }else{
            $lang = request()->header('lang', 'en');

            $columndesignation = match ($lang) {
                'ar' => 'ar_designation',
                'fr' => 'fr_designation',
                'hi' => 'hi_designation',
                'ur' => 'ur_designation',
                default => 'designation',
            };

            $columnlanguages_spoken = match ($lang) {
                'ar' => 'ar_languages_spoken',
                'fr' => 'fr_languages_spoken',
                'hi' => 'hi_languages_spoken',
                'ur' => 'ur_languages_spoken',
                default => 'languages_spoken',
            };
            
            $section = Doctors::select('*', DB::raw("$columndesignation as designation"), DB::raw("$columnlanguages_spoken as languages_spoken"),
                            DB::raw("ROUND(consultation_fee * {$conversionRate}) as consultation_fee"))
                        ->with('expertise')
                        ->where('status', Constants::statusDoctorApproved)
                        ->where('on_vacation', Constants::doctorNotOnVacation)
                        // ->where('is_mulkmed', 1)
                        ->where('travel_visible', 1)
                        ->orderBy('is_online', 'DESC')
                        ->get();
        }
        if ($section->isNotEmpty()) {
            $sectionSequence[] = [
                'section_name' => 'Mulk Med Virtual Hospital Doctors',
                'section_type' => 'mulk_med_virtual_hospital_doctors',
                'section_data' => $section,
                'is_disabled' => $hospital_doctors_is_disabled,
            ];
        }

        if ($request->has('tourist_id')) {
            $appointments = TouristAppointments::where('tourist_id', $request->tourist_id)
                ->where('status', Constants::orderAccepted)
                ->orderBy('date', 'asc')
                ->orderBy('time', 'asc')
                ->get();

            if ($appointments->count()) {
                $appointmentsWithJitsi = [];

                foreach ($appointments as $appointment) {
                    $jitsi_meeting = TouristJitsiMeeting::where('appointment_id', $appointment->id)->first();
                    if ($jitsi_meeting) {
                        $appointment->jitsi_link = url("/api/v1/tourist/join_tourist_jitsi_meeting?tourist_id={$appointment->tourist_id}&room={$jitsi_meeting->room}");;
                        $appointment->image = asset('storage/uploads/dashboard_appointment_banner.png');
                        $appointmentsWithJitsi[] = $appointment;
                    }

                    $vital_scan = TouristAIVital::where('tourist_id',$request->tourist_id)->where('appointment_id',$appointment->id)->get();
                    $isVitalScanDone = 0;

                    if(count($vital_scan)){
                        $isVitalScanDone = 1;
                    }

                    if($wellness_health_check_is_disabled == 1){
                        $isVitalScanDone = 1;
                    }
                    $appointment->is_vital_scan_done = $isVitalScanDone;
                }

                if (count($appointmentsWithJitsi) > 0) {
                    $appointmentBanner = new \stdClass();
                    $appointmentBanner->section_name = "appointment_banner";
                    $appointmentBanner->section_type = "appointment_banner";
                    $appointmentBanner->section_data = $appointmentsWithJitsi;

                    $sectionSequence[] = [
                        'section_name' => 'appointment_banner',
                        'section_type' => 'appointment_banner',
                        'section_data' => $appointmentsWithJitsi,
                    ];
                }
            }

        }

        
        
        $mulk_ai_dr_midas = TravelFlowBanner::select('id', 'tourist_partner_banner')
            ->where('banner_type', 'mulk_ai_dr')
            ->first();
        
        $sectionSequence[] = [
            'section_name' => 'Mulk AI Dr. MIDAS',
            'section_type' => 'mulk_ai_dr_midas',
            'section_data' => $mulk_ai_dr_midas,
            'is_disabled' => $dr_midas_is_disabled,
        ];
        
        $mulk_ai_wellness_health_check = TravelFlowBanner::select('id', 'tourist_partner_banner')
            ->where('banner_type', 'mulk_ai_wellness')
            ->first();
        
        $sectionSequence[] = [
            'section_name' => 'Mulk AI Wellness Health Check',
            'section_type' => 'mulk_ai_wellness_health_check',
            'section_data' => $mulk_ai_wellness_health_check,
            'is_disabled' => $wellness_health_check_is_disabled,
        ];

        return response()->json([
            'status' => true,
            'message' => 'data fetched successfully!',
            'sectionSequence' => $sectionSequence,
            // 'doctors' => $doctors,
            // 'mulk_ai_dr_midas' => $mulk_ai_dr_midas,
            // 'mulk_ai_wellness_health_check' => $mulk_ai_wellness_health_check,
        ]);
    }

    function fetchDoctorDetails(Request $request)
    {
        $rules = [
            'doctor_id' => 'required',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }

        $hostAndConversionRate = Helpers::conversionRate();
        $conversionRate = (float) $hostAndConversionRate['conversionRate'];
        $doctor = Doctors::select('doctors.*',DB::raw("ROUND(consultation_fee * {$conversionRate}) as consultation_fee"))
                    ->with([
                        'services',
                        'experience',
                        'expertise',
                        'serviceLocations',
                        'awards',
                        'slots',
                        'holidays',
                        'bankAccount',
                    ])->where('id', $request->doctor_id)->first();
        $lang = $request->header('lang', 'en');
        if ($lang == 'ar') {
            $doctor->languages_spoken = $doctor->ar_languages_spoken;
            $doctor->designation = $doctor->ar_designation;
        }
        if ($lang == 'ur') {
            $doctor->languages_spoken = $doctor->ur_languages_spoken;
            $doctor->designation = $doctor->ur_designation;
        }
        if ($lang == 'fr') {
            $doctor->languages_spoken = $doctor->fr_languages_spoken;
            $doctor->designation = $doctor->fr_designation;
        }
        if ($lang == 'hi') {
            $doctor->languages_spoken = $doctor->hi_languages_spoken;
            $doctor->designation = $doctor->hi_designation;
        }
        if ($doctor == null) {
            return GlobalFunction::sendSimpleResponse(false, 'Doctor does not exists!');
        }

        // return $result =  DoctorReviews::with(['user'])
        //     ->Where('doctor_id', $request->doctor_id)
        //     ->whereHas('user')
        //     ->whereHas('doctor')
        //     ->orderBy('id', 'DESC')
        //     ->offset($request->start)
        //     ->limit($request->count)
        //     ->get();
        $result = [];

        $lang = $request->header('lang', 'en');
        if ($lang == "hi") {
            $firstRecord_hi = [
                "comment" => "मुल्क HnH कार्ड से मेरी MRI पर 50% बचत हुई। छूट तुरंत मिल गई और बहुत फर्क पड़ा!",
                "rating" => 5,
                "user" => [
                        "fullname" => "अब्दुल",
                        "gender"=> 1,
                        "profile_image" => "uploads/1430453.png"
                    ]
            ];

            $secondRecord_hi = [
                "comment" => "टेलीहेल्थ परामर्श बहुत सुविधाजनक था। मैंने घर बैठे विशेषज्ञ से बात की और मेरे सभी सवालों के जवाब मिले।",
                "rating" => 4,
                "user" => [
                        "fullname" => "विजिजा",
                        "gender"=> 0,
                        "profile_image" => "uploads/avatar.png"
                    ]
            ];

            $thirdRecord_hi = [
                "comment" => "सीटी स्कैन के लिए HnH कार्ड का उपयोग किया। बचत तुरंत मिल गई और प्रक्रिया बहुत आसान थी। शानदार सेवा।",
                "rating" => 4.5,
                "user" => [
                        "fullname" => "फयाज़",
                        "gender" => 1,
                        "profile_image" => "uploads/1430453.png"
                    ]
            ];

            $forthRecord_hi = [
                "comment" => "टेलीहेल्थ सेवा एक बहुत बड़ी मदद है। यह जानकर सुकून है कि मैं घर से ही प्रोफेशनल मेडिकल सलाह ले सकता हूँ।",
                "rating" => 5,
                "user" => [
                        "fullname" => "प्राशुम",
                        "gender" => 1,
                        "profile_image" => "uploads/1430453.png"
                    ]
            ];

            $fifthRecord_hi = [
                "comment" => "फॉलो-अप के लिए टेलीहेल्थ फीचर बढ़िया है। मैंने घर बैठे डॉक्टर से बात की और अपनी दवा फिर से लिखवाई। जोरदार सिफारिश करता हूँ।",
                "rating" => 4,
                "user" => [
                        "fullname" => "मैरी",
                        "gender" => 0,
                        "profile_image" => "uploads/avatar.png"
                    ]
            ];

            $sixRecord_hi = [
                "comment" => "HnH कार्ड से अल्ट्रासाउंड में मुझे पूरे 50% की छूट मिली। यह शानदार सेवा है जो मेडिकल खर्चों में सचमुच मदद करती है।",
                "rating" => 4.5,
                "user" => [
                        "fullname" => "आदम",
                        "gender" => 1,
                        "profile_image" => "uploads/1430453.png"
                    ]
            ];

            $seventhRecord_hi = [
                "comment" => "मेरी टेलीहेल्थ कंसल्टेशन बेहतरीन थी। डॉक्टर ने ध्यान से सुना और मुझे साफ योजना दी। व्यक्तिगत मिलन से कहीं आसान।",
                "rating" => 4,
                "user" => [
                        "fullname" => "सकीना",
                        "gender" => 0,
                        "profile_image" => "uploads/avatar.png"
                    ]
            ];


            array_push($result, $firstRecord_hi);
            array_push($result, $secondRecord_hi);
            array_push($result, $thirdRecord_hi);
            array_push($result, $forthRecord_hi);
            array_push($result, $fifthRecord_hi);
            array_push($result, $sixRecord_hi);
            array_push($result, $seventhRecord_hi);
        } else if ($lang == "fr") {
            $firstRecord_fr = [
                "comment" => "J’ai économisé 50% sur mon IRM avec la carte Mulk HnH. La remise était instantanée et cela a vraiment fait la différence !",
                "rating" => 5,
                "user" => [
                    "fullname" => "Abdul",
                    "gender"=> 1,
                    "profile_image" => "uploads/1430453.png"
                ]
            ];

            $secondRecord_fr = [
                "comment" => "La consultation en télémédecine était très pratique. J'ai parlé à un spécialiste depuis chez moi et toutes mes questions ont reçu une réponse.",
                "rating" => 4,
                "user" => [
                    "fullname" => "Visija",
                    "gender"=> 0,
                    "profile_image" => "uploads/avatar.png"
                ]
            ];

            $thirdRecord_fr = [
                "comment" => "J'ai utilisé la carte HnH pour mon scanner. Les économies étaient immédiates et le processus très simple. Excellent service.",
                "rating" => 4.5,
                "user" => [
                    "fullname" => "Fayaz",
                    "gender"=> 1,
                    "profile_image" => "uploads/1430453.png"
                ]
            ];

            $forthRecord_fr = [
                "comment" => "Le service de télémédecine est un énorme avantage. C’est rassurant de savoir que je peux obtenir des conseils médicaux professionnels depuis chez moi.",
                "rating" => 5,
                "user" => [
                    "fullname" => "Prashum",
                    "gender"=> 1,
                    "profile_image" => "uploads/1430453.png"
                ]
            ];

            $fifthRecord_fr = [
                "comment" => "La fonction télémédecine est parfaite pour les suivis. J’ai parlé à un médecin, confortablement depuis mon domicile, et j’ai renouvelé mon ordonnance. Je recommande vivement.",
                "rating" => 4,
                "user" => [
                    "fullname" => "Mary",
                    "gender"=> 0,
                    "profile_image" => "uploads/avatar.png"
                ]
            ];

            $sixRecord_fr = [
                "comment" => "J’ai reçu 50% de réduction sur mon échographie avec la carte HnH. C’est un service fantastique qui aide vraiment à couvrir les frais médicaux.",
                "rating" => 4.5,
                "user" => [
                    "fullname" => "Adam",
                    "gender"=> 1,
                    "profile_image" => "uploads/1430453.png"
                ]
            ];

            $seventhRecord_fr = [
                "comment" => "Ma consultation en télémédecine était excellente. Le médecin m’a écouté attentivement et m’a donné un plan clair. Beaucoup plus facile qu’une visite en personne.",
                "rating" => 4,
                "user" => [
                    "fullname" => "Sakina",
                    "gender"=> 0,
                    "profile_image" => "uploads/avatar.png"
                ]
            ];


            array_push($result, $firstRecord_fr);
            array_push($result, $secondRecord_fr);
            array_push($result, $thirdRecord_fr);
            array_push($result, $forthRecord_fr);
            array_push($result, $fifthRecord_fr);
            array_push($result, $sixRecord_fr);
            array_push($result, $seventhRecord_fr);
        } else if ($lang == "ur") {
            $firstRecord_ur = [
                "comment" => "ملک HnH کارڈ سے میری MRI پر 50٪ کی بچت ہوئی۔ رعایت فوری تھی اور بہت فائدہ مند ثابت ہوئی!",
                "rating" => 5,
                "user" => [
                    "fullname" => "عبدال",
                    "gender"=> 1,
                    "profile_image" => "uploads/1430453.png"
                ]
            ];

            $secondRecord_ur = [
                "comment" => "ٹیلی ہیلتھ مشاورت بہت سہل تھی۔ میں نے گھر سے ماہر سے بات کی اور اپنے تمام سوالات کے جواب پائے۔",
                "rating" => 4,
                "user" => [
                    "fullname" => "ویسجا",
                    "gender"=> 0,
                    "profile_image" => "uploads/avatar.png"
                ]
            ];

            $thirdRecord_ur = [
                "comment" => "سی ٹی اسکین کے لئے HnH کارڈ استعمال کیا۔ بچت فوری ملی اور عمل بہت آسان تھا۔ بہترین سروس۔",
                "rating" => 4.5,
                "user" => [
                    "fullname" => "فیاض",
                    "gender"=> 1,
                    "profile_image" => "uploads/1430453.png"
                ]
            ];

            $forthRecord_ur = [
                "comment" => "ٹیلی ہیلتھ سروس ایک بڑا فائدہ ہے۔ اطمینان بخش ہے کہ میں گھر سے ہی پروفیشنل طبی مشورہ لے سکتا ہوں۔",
                "rating" => 5,
                "user" => [
                    "fullname" => "پراشوم",
                    "gender"=> 1,
                    "profile_image" => "uploads/1430453.png"
                ]
            ];

            $fifthRecord_ur = [
                "comment" => "فالو اپ کے لئے ٹیلی ہیلتھ فیچر بہترین ہے۔ میں نے گھر بیٹھے ڈاکٹر سے بات کی اور نسخہ دوبارہ حاصل کیا۔ بہت سفارش کرتا ہوں۔",
                "rating" => 4,
                "user" => [
                    "fullname" => "میری",
                    "gender"=> 0,
                    "profile_image" => "uploads/avatar.png"
                ]
            ];

            $sixRecord_ur = [
                "comment" => "کارڈ سے الٹراساؤنڈ پر پورے 50٪ رعایت ملی۔ یہ شاندار سروس ہے جو طبی اخراجات میں واقعی مدد کرتی ہے۔",
                "rating" => 4.5,
                "user" => [
                    "fullname" => "آدم",
                    "gender"=> 1,
                    "profile_image" => "uploads/1430453.png"
                ]
            ];

            $seventhRecord_ur = [
                "comment" => "میری ٹیلی ہیلتھ مشاورت شاندار تھی۔ ڈاکٹر نے غور سے سنا اور واضح منصوبہ دیا۔ ذاتی ملاقات سے کہیں آسان۔",
                "rating" => 4,
                "user" => [
                    "fullname" => "سکینہ",
                    "gender"=> 0,
                    "profile_image" => "uploads/avatar.png"
                ]
            ];

            array_push($result, $firstRecord_ur);
            array_push($result, $secondRecord_ur);
            array_push($result, $thirdRecord_ur);
            array_push($result, $forthRecord_ur);
            array_push($result, $fifthRecord_ur);
            array_push($result, $sixRecord_ur);
            array_push($result, $seventhRecord_ur);
        } else if ($lang == "ar") {
            $firstRecord_ar = [
                "comment" => "وفرت 50٪ على فحص الرنين المغناطيسي ببطاقة ملك HnH. الخصم كان فوري وأحدث فرقاً كبيراً!",
                "rating" => 5,
                "user" => [
                    "fullname" => "عبدول",
                    "gender"=> 1,
                    "profile_image" => "uploads/1430453.png"
                ]
            ];



            $secondRecord_ar = [
                "comment" => "كان الاستشارة الصحية عن بُعد مريح جداً. تحدثت مع أخصائي من المنزل وتمت الإجابة على كل أسئلتي.",
                "rating" => 4,
                "user" => [
                    "fullname" => "فيزيجا",
                    "gender"=> 0,
                    "profile_image" => "uploads/avatar.png"
                ]
            ];



            $thirdRecord_ar = [
                "comment" => "استخدمت بطاقة HnH لفحص الأشعة المقطعية. التوفير كان فورياً وكانت العملية سهلة جداً. خدمة رائعة.",
                "rating" => 4.5,
                "user" => [
                    "fullname" => "فياض",
                    "gender"=> 1,
                    "profile_image" => "uploads/1430453.png"
                ]
            ];



            $forthRecord_ar = [
                "comment" => "الخدمة الصحية عن بُعد ميزة كبيرة. يبعث على الطمأنينة أنني أستطيع الحصول على نصيحة طبية احترافية من المنزل.",
                "rating" => 5,
                "user" => [
                    "fullname" => "براشوم",
                    "gender"=> 1,
                    "profile_image" => "uploads/1430453.png"
                ]
            ];



            $fifthRecord_ar = [
                "comment" => "ميزة الصحة عن بعد ممتازة للمتابعة. تحدثت مع طبيب وأنا في منزلي وتم تجديد وصفيتي الطبية. أوصي بها بشدة.",
                "rating" => 4,
                "user" => [
                    "fullname" => "ماري",
                    "gender"=> 0,
                    "profile_image" => "uploads/avatar.png"
                ]
            ];



            $sixRecord_ar = [
                "comment" => "حصلت على خصم 50٪ كامل في فحص الموجات فوق الصوتية ببطاقة HnH. إنها خدمة رائعة وتساعد حقاً في تكاليف الطب.",
                "rating" => 4.5,
                "user" => [
                    "fullname" => "آدم",
                    "gender"=> 1,
                    "profile_image" => "uploads/1430453.png"
                ]
            ];



            $seventhRecord_ar = [
                "comment" => "كانت استشارتي الصحية عن بعد ممتازة. استمع الطبيب بعناية وأعطاني خطة واضحة. أسهل بكثير من زيارة شخصية.",
                "rating" => 4,
                "user" => [
                    "fullname" => "سكينة",
                    "gender"=> 0,
                    "profile_image" => "uploads/avatar.png"
                ]
            ];



            array_push($result, $firstRecord_ar);
            array_push($result, $secondRecord_ar);
            array_push($result, $thirdRecord_ar);
            array_push($result, $forthRecord_ar);
            array_push($result, $fifthRecord_ar);
            array_push($result, $sixRecord_ar);
            array_push($result, $seventhRecord_ar);
        } else {

            $firstRecord = [
                "comment" => "Saved 50% on my MRI with the Mulk HnH Card. The discount was instant and made a huge difference!",
                "rating" => 5,
                "user" => [
                    "fullname" => "Abdul",
                    "gender"=> 1,
                    "profile_image" => "uploads/1430453.png"
                ]
            ];

            $secondRecord = [
                "comment" => "The telehealth consultation was so convenient. I spoke with a specialist from home and got all my questions answered.",
                "rating" => 4,
                "user" => [
                    "fullname" => "Visija",
                    "gender"=> 0,
                    "profile_image" => "uploads/avatar.png"
                ]
            ];

            $thirdRecord = [
                "comment" => "Used the HnH Card for a CT scan. The savings were immediate, and the process was so simple. Great service",
                "rating" => 4.5,
                "user" => [
                    "fullname" => "Fayaz",
                    "gender"=> 1,
                    "profile_image" => "uploads/1430453.png"
                ]
            ];
            $forthRecord = [
                "comment" => "The telehealth service is a huge plus. Its comforting to know I can get professional medical advice from home.",
                "rating" => 5,
                "user" => [
                    "fullname" => "Prashum",
                    "gender"=> 1,
                    "profile_image" => "uploads/1430453.png"
                ]
            ];

            $fifthRecord = [
                "comment" => "The telehealth feature is perfect for follow-ups. I spoke to a doctor from the comfort of my home. Got my prescription refilled.Highly recommend it.",
                "rating" => 4,
                "user" => [
                    "fullname" => "Mary",
                    "gender"=> 0,
                    "profile_image" => "uploads/avatar.png"
                ]
            ];
            $sixRecord = [
                "comment" => "Got a full 50% off my ultrasound with the HnH Card. Its a fantastic service that really helps with medical costs.",
                "rating" => 4.5,
                "user" => [
                    "fullname" => "Adam",
                    "gender"=> 1,
                    "profile_image" => "uploads/1430453.png"
                ]
            ];

            $seventhRecord = [
                "comment" => "My telehealth consultation was excellent. The doctor listened carefully and gave me a clear plan. So much easier than an in-person visit",
                "rating" => 4,
                "user" => [
                    "fullname" => "Sakina",
                    "gender"=> 0,
                    "profile_image" => "uploads/avatar.png"
                ]
            ];

            array_push($result, $firstRecord);
            array_push($result, $secondRecord);
            array_push($result, $thirdRecord);
            array_push($result, $forthRecord);
            array_push($result, $fifthRecord);
            array_push($result, $sixRecord);
            array_push($result, $seventhRecord);
        }

        $doctor->reviews = $result;

        return response()->json(['status' => true, 'message' => 'data fetched successfully', 'data' => $doctor]);
        // return GlobalFunction::sendDataResponse(true, 'data fetched successfully', $doctor, $doctor_with_same_category);
    }

    function addAppointment(Request $request)
    {
        $rules = [
            'tourist_id' => 'required',
            'doctor_id' => 'required',
            'problem' => 'required',
            'date' => 'required',
            'time' => 'required',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }

        $doctor = Doctors::find($request->doctor_id);
        if ($doctor == null) {
            return response()->json(['status' => false, 'message' => "Doctor doesn't exists!"]);
        }
        if ($doctor->on_vacation == 1) {
            return response()->json(['status' => false, 'message' => "this doctor is on vacation!"]);
        }
        if ($doctor->status != Constants::statusDoctorApproved) {
            return response()->json(['status' => false, 'message' => "this doctor is not active!"]);
        }

        $tourist = TouristList::find($request->tourist_id);
        if ($tourist == null) {
            return response()->json(['status' => false, 'message' => "tourist doesn't exists!"]);
        }

        $appointment = new TouristAppointments();

        $appointment->appointment_number = GlobalFunction::generateAppointmentNumber();
        $appointment->tourist_id        = $request->tourist_id;
        $appointment->doctor_id         = $request->doctor_id;
        $appointment->status            = 1;
        $appointment->date              = $request->date;
        $appointment->time              = $request->time;
        $appointment->problem           = GlobalFunction::cleanString($request->problem);
        // return $appointment;
        $appointment->save();
        if ($request->has('documents')) {
            foreach ($request->documents as $document) {
                $docs = new TouristAppointmentDocs();
                $docs->appointment_id = $appointment->id;
                $docs->image = GlobalFunction::saveFileAndGivePath($document);
                $docs->save();
            }
        }

        $appId  = env('JITSI_APP_ID');
        $secret = env('JITSI_SECRET');
        $domain = env('JITSI_DOMAIN', 'meet.jit.si'); 
        $room = 'appointment-' . Str::random(10);
        // $jitsiBaseUrl = env('JITSI_URL');
        // $jitsiJwt = env('JWT_TOKEN_JITSI_MEETING');
        // $link = $jitsiBaseUrl . '?roomId=' . $room . '&jwt=' . $jitsiJwt;
        $date = $appointment->date;
        $time = $appointment->time;
        $formattedTime = substr($time, 0, 2) . ':' . substr($time, 2, 2);
        $startDateTime = Carbon::createFromFormat('Y-m-d H:i', $date . ' ' . $formattedTime);
        
        $endDateTime = $startDateTime->copy()->addHour();
        $endTimestamp   = $endDateTime->copy()->setTimezone('UTC')->timestamp;
        $appointmentdate = Carbon::parse($appointment->date)->format('d-m-Y');
        $appointmentTime = Carbon::createFromFormat('Hi', $appointment->time)->format('g:i A');

        $meeting_link_tourist = GlobalFunction::GenerateTouristJitsiMeetingLink($appointment, $room, $endTimestamp);
        $meeting_link_doctor = GlobalFunction::GenerateTouristDoctorJitsiMeetingLink($appointment, $room, $endTimestamp);

        $tourist_link = GlobalFunction::CreateTouristLink($appointment, $room, $endTimestamp);
        $doctor_link = GlobalFunction::CreateTouristDoctorLink($appointment, $room, $endTimestamp);

        $tourist_link_mail = GlobalFunction::CreateTouristLinkMail($appointment, $room, $endTimestamp);
        $doctor_link_mail = GlobalFunction::CreateTouristDoctorLinkMail($appointment, $room, $endTimestamp);

        $meeting = new TouristJitsiMeeting;
        $meeting->room = $room;
        $meeting->tourist_link = $meeting_link_tourist;
        $meeting->doctor_link = $meeting_link_doctor;
        $meeting->appointment_id = $appointment->id;
        $meeting->tourist_id = $appointment->tourist_id;
        $meeting->doctor_id = $appointment->doctor_id;
        $meeting->start_time = $startDateTime;
        $meeting->end_time = $endDateTime;
        $meeting->save();

        // $path = "meetings/join/".$room;
        // $meeting_link = url($path);
        // $meeting_link_patient =  $link;
        // $meeting_link_doctor =  $link;

        $tourist = TouristList::find($appointment->tourist_id);
        $doctor = Doctors::find($appointment->doctor_id);

        // \Mail::to($tourist->email)->send(new \App\Mail\JitsiMeetingLinkTourist($appointment, $doctor, $tourist, $tourist_link_mail));

        $cleanCode = ltrim($tourist->country_code, '+');
        // Build SMS message (plain text)
        $message = "Dear {$tourist->first_name},

                Your appointment has been successfully booked with {$doctor->name} ({$doctor->designation}).

                Appointment Details:
                Doctor: {$doctor->name}
                Specialty: {$doctor->designation}
                Date & Time: {$appointmentdate}, {$appointmentTime}
                Link: {$tourist_link_mail}

                Regards,
                Team Mulk Med";          
                // $result = EmailHelpers::sendSms($cleanCode . $tourist->contact_number, $message);

                                $countryCode = ltrim($doctor->country_code, '+');

                                $host = request()->getHost();
                                // if ($host === 'india.mulkmed.com') {
                //                          $message = "Dear {$doctor->name},
                // You have a new appointment booked on {$appointmentdate} at {$appointmentTime}.
                // Kindly log in to the Mulk Med app to view details.

                // Regards,
                // Team Mulk Med";           
                //                     $result = EmailHelpers::sendSms($countryCode . $doctor->mobile_number, $message);
                    $message = "Dear {$doctor->name},
You have a new appointment booked on {$appointmentdate} at {$appointmentTime}.
Kindly log in to the Mulk Med app to view details.
Regards,
Team Mulk Med";            
                    $result = EmailHelpers::sendSms($countryCode . $doctor->mobile_number, $message);                
                                // }

                                $message = "Dear Team,
                Patient {$tourist->first_name} ({$tourist->contact_number}) booked an appointment with {$doctor->name} on {$appointmentdate} at {$appointmentTime}.";            
                // $result = EmailHelpers::sendSms(971522463433, $message);
                // $result1 = EmailHelpers::sendSms(971569337544, $message);
                // $result2 = EmailHelpers::sendSms(00971569337544, $message);
                
                \Mail::to($doctor->identity)->send(new \App\Mail\JitsiMeetingLinkTouristDoctor($appointment, $doctor, $tourist, $doctor_link_mail));
                // return $doctor;
                if($doctor->email_2 != null)
                {
                    \Mail::to($doctor->email_2)->send(new \App\Mail\JitsiMeetingLinkTouristDoctor($appointment, $doctor, $tourist, $doctor_link_mail));
                }
                if($doctor->email_3 != null)
                {
                    \Mail::to($doctor->email_3)->send(new \App\Mail\JitsiMeetingLinkTouristDoctor($appointment, $doctor, $tourist, $doctor_link_mail));
                }
                if($doctor->email_4 != null)
                {
                    \Mail::to($doctor->email_4)->send(new \App\Mail\JitsiMeetingLinkTouristDoctor($appointment, $doctor, $tourist, $doctor_link_mail));
                }
                if($doctor->email_5 != null)
                {
                    \Mail::to($doctor->email_5)->send(new \App\Mail\JitsiMeetingLinkTouristDoctor($appointment, $doctor, $tourist, $doctor_link_mail));
                }

                // Send Push to user
                $title = "Appointment :" . $appointment->appointment_number;
                $message = "Appointment has been placed successfully!";
                $notifyData = [
                    'type'=> Constants::notifyAppointment.'',
                    'id'=> $appointment->id.''
                ];
                // GlobalFunction::sendPushToUser($title, $message, $user, $notifyData);

                // Send push to doctor
                $title = "New Appointment Request Received";
                $message = "Review the details and accept.";
                $notifyData = [
                    'type'=> Constants::notifyAppointment.'',
                    'id'=> $appointment->id.''
                ];

                $appointment = TouristAppointments::where('id', $appointment->id)->with(['tourist', 'doctor', 'documents'])->first();

                $tourist = TouristList::find($appointment->tourist_id);
                
                if ($tourist && $tourist->number_of_consultation > 0) {
                    $tourist->decrement('number_of_consultation', 1);
                }

        return GlobalFunction::sendDataResponse(true, 'Appointment placed successfully', $appointment);
    }

    function join_tourist_meeting_mail(Request $request)
    {
        $rules = [
            'room' => 'required',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }

        $jitsi_meeting = TouristJitsiMeeting::where('room',$request->room)->first();
        if($jitsi_meeting){
            $appointment = TouristAppointments::where('id',$jitsi_meeting->appointment_id)->where('status',2)->first();
            if($appointment)
            {
                $message = "Your appointment is completed successfully";
                return view('pages.jitsi_meeting_message', compact('message'));
            }
            $current_date_time = Carbon::now()->format('Y-m-d H:i:s');

            if($jitsi_meeting->start_time > $current_date_time)
            {
                $message = "Your will start at {$jitsi_meeting->start_time}";
                return view('pages.jitsi_meeting_message', compact('message'));
            }
            if($request->has('doctor_id'))
            {
                $doctor_jitsi_meeting = TouristJitsiMeeting::where('room',$request->room)->where('doctor_id',$request->doctor_id)->first();
                if($doctor_jitsi_meeting)
                {
                    $doctor_jitsi_meeting->doctor_joined = 1;
                    $doctor_jitsi_meeting->save();
                    // return response()->json(['status' => true, 'link' => $jitsi_meeting->doctor_link]);
                    return redirect($jitsi_meeting->doctor_link);

                }
            }
            if($request->has('tourist_id'))
            {
                if($jitsi_meeting->doctor_joined == 1)
                {
                    // return response()->json(['status' => true, 'link' => $jitsi_meeting->patient_link]);
                    return redirect($jitsi_meeting->tourist_link);
                }
                else{
                    $message = 'Kindly hold on, your doctor will be with you shortly.';
                    return view('pages.jitsi_meeting_message', compact('message'));
                }
            }
        }
    }

    function join_tourist_jitsi_meeting(Request $request)
    {
        $rules = [
            'room' => 'required',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }

        $jitsi_meeting = TouristJitsiMeeting::where('room',$request->room)->first();
        if($jitsi_meeting){
            $current_date_time = Carbon::now()->format('Y-m-d H:i:s');

            if($jitsi_meeting->start_time > $current_date_time)
            {
                return response()->json(['status' => false, 'message' => "Your consultation will start at {$jitsi_meeting->start_time}"]);
            }
            if($request->has('doctor_id'))
            {
                $doctor_jitsi_meeting = TouristJitsiMeeting::where('room',$request->room)->where('doctor_id',$request->doctor_id)->first();
                if($doctor_jitsi_meeting)
                {
                    $doctor_jitsi_meeting->doctor_joined = 1;
                    $doctor_jitsi_meeting->save();

                    $fullUrl = $doctor_jitsi_meeting->doctor_link;
                    $parsedUrl = parse_url($fullUrl);
                    parse_str($parsedUrl['query'], $query);
                    return response()->json(['status' => true, 'link' => $jitsi_meeting->doctor_link, 'room_id' => $request->room ,'base_url' => $parsedUrl['scheme'].'://'.$parsedUrl['host'],
            'room_id'  => $query['roomId'] ?? null,
            'token'    => $query['jwt'] ?? null]); 
                }
            }
            if($request->has('tourist_id'))
            {
                
                if($jitsi_meeting->doctor_joined == 1)
                {
                     $fullUrl = $jitsi_meeting->tourist_link;
                     $parsedUrl = parse_url($fullUrl);
                     parse_str($parsedUrl['query'], $query);    
                    return response()->json(['status' => true, 'link' => $jitsi_meeting->tourist_link, 'room_id' => $request->room,'base_url' => $parsedUrl['scheme'].'://'.$parsedUrl['host'],
            'room_id'  => $query['roomId'] ?? null,
            'token'    => $query['jwt'] ?? null]);
                }
                else{
                    return response()->json(['status' => true, 'message' => 'Kindly hold on, your doctor will be with you shortly.']);
                }
            }
        }
    }

    function join_tourist_jitsi_meeting_v2(Request $request)
    {
        $rules = [
            'room' => 'required',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }

        $jitsi_meeting = TouristJitsiMeeting::where('room',$request->room)->first();
        if($jitsi_meeting){
            $current_date_time = Carbon::now()->format('Y-m-d H:i:s');

            if($jitsi_meeting->start_time > $current_date_time)
            {
                return response()->json(['status' => false, 'message' => "Your consultation will start at {$jitsi_meeting->start_time}"]);
            }
            if($request->has('doctor_id'))
            {
                $doctor_jitsi_meeting = TouristJitsiMeeting::where('room',$request->room)->where('doctor_id',$request->doctor_id)->first();
                if($doctor_jitsi_meeting)
                {
                    $doctor_jitsi_meeting->doctor_joined = 1;
                    $doctor_jitsi_meeting->save();

                    $fullUrl = $doctor_jitsi_meeting->doctor_link;
                    $parsedUrl = parse_url($fullUrl);
                    parse_str($parsedUrl['query'], $query);
                    return response()->json(['status' => true, 'link' => $jitsi_meeting->doctor_link, 'room_id' => $request->room ,'base_url' => $parsedUrl['scheme'].'://'.$parsedUrl['host'],
            'room_id'  => $query['roomId'] ?? null,
            'token'    => $query['jwt'] ?? null]); 
                }
            }
            if($request->has('tourist_id'))
            {
                
                // if($jitsi_meeting->doctor_joined != 1)
                // {
                     $fullUrl = $jitsi_meeting->tourist_link;
                     $parsedUrl = parse_url($fullUrl);
                     parse_str($parsedUrl['query'], $query);    
                    return response()->json(['status' => true, 'link' => $jitsi_meeting->tourist_link, 'room_id' => $request->room,'base_url' => $parsedUrl['scheme'].'://'.$parsedUrl['host'],
                                            'room_id'  => $query['roomId'] ?? null,
                                            'token'    => $query['jwt'] ?? null]);
                // }
                // else{
                //     return response()->json(['status' => true, 'message' => 'Kindly hold on, your doctor will be with you shortly.']);
                // }
            }
        }
    }

    function fetchMyAppointments(Request $request)
    {
        $rules = [
            'tourist_id' => 'required',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }

        $tourist = TouristList::find($request->tourist_id);
        if ($tourist == null) {
            return response()->json(['status' => false, 'message' => "tourist doesn't exists!"]);
        }

        $wellness_health_check_is_disabled = 0;
        if(isset($tourist))
        {
            if($tourist->number_of_ai_health_check == 0)
            {
                $wellness_health_check_is_disabled = 1;
            }
        }

        $result = TouristAppointments::with(['tourist', 'doctor', 'documents', 'prescription', 'rating', 'appointmentMeeting','emrdocuments'])
                    ->Where('tourist_id', $request->tourist_id)
                    ->orderBy('id', 'DESC')
                    ->get();

        foreach ($result as $appointment) {
            $appointment->previous_appointments = TouristAppointments::with(['tourist', 'doctor', 'documents', 'prescription', 'rating', 'appointmentMeeting','emrdocuments'])
                            ->Where('tourist_id', $request->tourist_id)
                            ->Where('doctor_id', $appointment->doctor_id)
                            ->WhereNotIn('id', [$appointment->id])
                            ->WhereIn('status', [Constants::orderCompleted, Constants::orderCancelled, Constants::orderDeclined])
                            ->get();

            $jitsiMeeting = DB::table('tourist_jitsi_meetings')
                    ->where('appointment_id', $appointment->id)
                    ->first();

            $vital_scan = TouristAIVital::where('tourist_id',$request->tourist_id)->where('appointment_id',$appointment->id)->get();
            $isVitalScanDone = 0;

            if(count($vital_scan)){
                $isVitalScanDone = 1;
            }
            if($wellness_health_check_is_disabled == 1){
                $isVitalScanDone = 1;
            }

            $appointment->is_vital_scan_done = $isVitalScanDone;

            $appointment->jitsi_link = url("/api/v1/tourist/join_tourist_jitsi_meeting?tourist_id={$appointment->tourist_id}&room={$jitsiMeeting->room}");
            $appointment->ai_vital_link = null;
            $ai_vital = TouristAIVital::where('appointment_id',$appointment->id)->whereNotNull('pdf_file')->orderBy('id','desc')->first();
            if($ai_vital)
            {
                $appointment->ai_vital_link = $ai_vital->pdf_file;
            }

            $appointment->is_feedback_submitted = TouristDoctorReviews::where('appointment_id', $appointment->id)
                                                    ->where('doctor_id', $appointment->doctor_id)
                                                    ->where('tourist_id', $request->tourist_id)
                                                    ->exists();
        }

        return GlobalFunction::sendDataResponse(true, 'data fetched successfully', $result);
    }

    function fetchAcceptedAppointsByDate(Request $request)
    {
        $rules = [
            'date' => 'required',
            'doctor_id' => 'required',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }
        $doctor = Doctors::where('id', $request->doctor_id)->first();
        if ($doctor == null) {
            return GlobalFunction::sendSimpleResponse(false, 'Doctor does not exists!');
        }

        $result = TouristAppointments::with(['tourist', 'doctor', 'documents', 'prescription', 'rating', 'appointmentMeeting','emrdocuments'])
            ->Where('doctor_id', $request->doctor_id)
            ->Where('date', $request->date)
            ->where('status', Constants::orderAccepted)
            ->get();

        foreach ($result as $appointment) {
            $appointment->previous_appointments =
                TouristAppointments::with(['tourist', 'doctor', 'documents', 'prescription', 'rating', 'appointmentMeeting','emrdocuments'])
                ->Where('doctor_id', $request->doctor_id)
                ->WhereNotIn('id', [$appointment->id])
                ->WhereIn('status', [Constants::orderCompleted, Constants::orderCancelled, Constants::orderDeclined])
                ->where('status', Constants::orderPlacedPending)
                ->get();

            $jitsiMeeting = DB::table('tourist_jitsi_meetings')
                                ->where('appointment_id', $appointment->id)
                                ->first();

            $appointment->jitsi_link = $jitsiMeeting?->doctor_link;
            $appointment->ai_vital_link = null;
            $ai_vital = TouristAIVital::where('tourist_id',$appointment->tourist_id)->Where('appointment_id', $appointment->id)->orderBy('id','desc')->first();
            if($ai_vital)
            {
                $appointment->ai_vital_link = $ai_vital->pdf_file;
            }
        }

        return GlobalFunction::sendDataResponse(true, 'data fetched successfully', $result);
    }

    function fetchAppointmentDetails(Request $request)
    {
        $rules = [
            'appointment_id' => 'required',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }
        $result = TouristAppointments::where('id', $request->appointment_id)
            ->with(['tourist', 'doctor', 'documents', 'prescription', 'rating', 'appointmentMeeting'])
            ->first();
        if ($result == null) {
            return GlobalFunction::sendSimpleResponse(false, 'Appointment does not exists!');
        }

        $result->previous_appointments =
            TouristAppointments::with(['tourist', 'doctor', 'documents', 'prescription', 'rating', 'appointmentMeeting','emrdocuments'])
            ->Where('doctor_id', $result->doctor_id)
            ->Where('tourist_id', $result->tourist_id)
            ->WhereNotIn('id', [$result->id])
            ->WhereIn('status', [Constants::orderCompleted, Constants::orderCancelled, Constants::orderDeclined])
            ->get();

        $jitsiMeeting = DB::table('tourist_jitsi_meetings')
                ->where('appointment_id', $result->id)
                ->first();

        // $result->jitsi_link = $jitsiMeeting?->doctor_link;
        $result->jitsi_link = url("/api/v1/tourist/join_tourist_jitsi_meeting?doctor_id={$jitsiMeeting->doctor_id}&room={$jitsiMeeting->room}");

        $result->ai_vital_link = null;
        $ai_vital = TouristAIVital::where('tourist_id',$result->tourist_id)->Where('appointment_id', $result->id)->orderBy('id','desc')->first();
        if($ai_vital)
        {
            $result->ai_vital_link = $ai_vital->pdf_file;
        }

        return GlobalFunction::sendDataResponse(true, 'Data fetched successfully', $result);
    }

    function addAppointmentDocs(Request $request)
    {
        try {
          $rules = [
                'appointment_id' => 'required',  
                'documents'      => 'required|array',
                'documents.*'    => 'file|mimes:jpg,jpeg,png,pdf|max:5120',
            ];

            $messages = [
                'documents.*.max'   => 'Each document must not be larger than 5 MB.',
                'documents.*.mimes' => 'Only JPG, JPEG, PNG, and PDF files are allowed.',
                'documents.required'=> 'Please upload at least one document.',
            ];

            $validator = Validator::make($request->all(), $rules, $messages);

            if ($validator->fails()) {
                $messages = $validator->errors()->all();
                $msg = $messages[0];
                return response()->json([
                    'status'  => false,
                    'message' => $msg,
                ]);
            }
            if ($request->has('documents')) {
                foreach ($request->documents as $document) {
                    $docs = new TouristAppointmentDocs();
                    $docs->appointment_id = $request->appointment_id;
                    $docs->image = GlobalFunction::saveFileAndGivePath($document);
                    $docs->save();
                }
            }
            return response()->json(['status' => true, 'message' => 'Documents Saved successfully']);        
        
        } catch (\Throwable $th) {
            return ['status' => false, 'message' => $th->getMessage()];
        }
    }

    function deleteAppointmentDocs(Request $request)
    {
        try {
            $rules = [
                'document_id' => 'required',
            ];
            $validator = Validator::make($request->all(), $rules);
            if ($validator->fails()) {
                $messages = $validator->errors()->all();
                $msg = $messages[0];
                return response()->json(['status' => false, 'message' => $msg]);
            }
            
            $appointment_docs = TouristAppointmentDocs::find($request->document_id);

            if($appointment_docs){
                $appointment_docs->is_deleted = 1;
                $appointment_docs->save();
            }

            return response()->json(['status' => true, 'message' => 'Documents Deleted successfully']);        
        
        } catch (\Throwable $th) {
            Log::error('INR→AED conversion error', [
                'error' => $th->getMessage(),
                'trace' => $th->getTraceAsString()
            ]);

            return ['status' => false, 'message' => $th->getMessage()];
        }
    }

    function completeAppointment(Request $request)
    {
        $rules = [
            'doctor_id' => 'required',
            'appointment_id' => 'required',
            'diagnosed_with' => 'required',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }
        $doctor = Doctors::where('id', $request->doctor_id)->first();
        if ($doctor == null) {
            return GlobalFunction::sendSimpleResponse(false, 'Doctor does not exists!');
        }
        $appointment = TouristAppointments::where('id', $request->appointment_id)->first();
        if ($appointment == null) {
            return GlobalFunction::sendSimpleResponse(false, 'Appointment does not exists!');
        }
        if ($appointment->doctor_id != $request->doctor_id) {
            return response()->json(['status' => false, 'message' => "Appointment is not owned by this doctor!"]);
        }
        // if ($appointment->completion_otp != $request->completion_otp) {
        //     return response()->json(['status' => false, 'message' => "Completion OTP is incorrect!"]);
        // }
        if ($appointment->status == Constants::orderAccepted) {
            $appointment->status = Constants::orderCompleted;
            $appointment->diagnosed_with = $request->diagnosed_with;
            $appointment->save();

            // Commission calculation
            $earning = $doctor->consultation_fee;
            $settings = GlobalSettings::first();
            $commissionAmount = ($settings->comission / 100) * $earning;

            // Adding Earning statement
            $earningSummary = "Earning from Tourist appointment: " . $appointment->appointment_number;
            GlobalFunction::addDoctorStatementEntry($doctor->id, $appointment->appointment_number, $earning, Constants::credit, Constants::doctorWalletEarning, $earningSummary);

            // Adding Commission deduct statement
            $commissionSummary = "Commission of Tourist appointment: " . $appointment->appointment_number . " : (" . $settings->comission . "%)";
            GlobalFunction::addDoctorStatementEntry($doctor->id, $appointment->appointment_number, $commissionAmount, Constants::debit, Constants::doctorWalletCommission, $commissionSummary);

            // Adding earning to doctor wallet + count increase + lifetime earning increase
            $earningAfterCommission = $earning - $commissionAmount;
            $doctor->wallet = $doctor->wallet + $earningAfterCommission;
            $doctor->total_patients_cured = $doctor->total_patients_cured + 1;
            $doctor->lifetime_earnings = $doctor->lifetime_earnings + $earningAfterCommission;
            $doctor->save();

            // Adding Earning Logs Of Doctor
            $doctorEarningHistory = new DoctorEarningHistory();
            $doctorEarningHistory->doctor_id = $doctor->id;
            $doctorEarningHistory->appointment_id = $appointment->id;
            $doctorEarningHistory->earning_number = GlobalFunction::generateDoctorEarningHistoryNumber();
            $doctorEarningHistory->amount = $earningAfterCommission;
            $doctorEarningHistory->save();

            // Adding Earning Logs of Platform
            $platformEarningHistory = new PlatformEarningHistory();
            $platformEarningHistory->earning_number = GlobalFunction::generatePlatformEarningHistoryNumber();
            $platformEarningHistory->amount = $commissionAmount;
            $platformEarningHistory->commission_percentage = $settings->comission;
            $platformEarningHistory->appointment_id = $appointment->id;
            $platformEarningHistory->doctor_id = $doctor->id;
            $platformEarningHistory->save();

            // Increasing total platform earning data
            $platformData = PlatformData::first();
            $platformData->lifetime_earnings = $platformData->lifetime_earnings + $commissionAmount;
            $platformData->save();

            // Send Push to user
            $title = "Appointment :" . $appointment->appointment_number;
            $message = "Appointment has been completed by doctor!";
            $notifyData = [
                'type'=> Constants::notifyAppointment.'',
                'id'=> $appointment->id.''
            ];
            GlobalFunction::sendPushToUser($title, $message, $appointment->user, $notifyData);

            return GlobalFunction::sendSimpleResponse(true, 'Appointment completed successfully');
        } else {
            return response()->json(['status' => false, 'message' => "This booking can't be completed!"]);
        }
    }

    function addPrescription(Request $request)
    {
        $rules = [
            'appointment_id' => 'required',
            'tourist_id' => 'required',
            'medicine' => 'required',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }
        $appointment = TouristAppointments::where('id', $request->appointment_id)
            ->with(['tourist', 'doctor', 'documents'])
            ->first();
        if ($appointment == null) {
            return GlobalFunction::sendSimpleResponse(false, 'Appointment does not exists!');
        }
        $tourist = TouristList::where('id', $request->tourist_id)->first();
        if ($tourist == null) {
            return GlobalFunction::sendSimpleResponse(false, 'User does not exists!');
        }
        if ($appointment->tourist_id != $tourist->id) {
            return GlobalFunction::sendSimpleResponse(false, 'Appointment not owned by this tourist!');
        }
        $prescription = TouristPrescription::where('tourist_id', $tourist->id)->where('appointment_id', $appointment->id)->first();
        if ($prescription != null) {
            return GlobalFunction::sendSimpleResponse(false, 'This appointment has prescription already!');
        }

        $prescription = new TouristPrescription();
        $prescription->tourist_id = $tourist->id;
        $prescription->appointment_id = $appointment->id;
        $prescription->medicine = $request->medicine;
        $prescription->diagnosis = $request->diagnosis;
        $prescription->erx_no = $request->has('erx') ? $request->erx : null;
        $prescription->save();

        return GlobalFunction::sendSimpleResponse(true, 'Prescription added successfully');
    }

    function editPrescription(Request $request)
    {
        $rules = [
            'prescription_id' => 'required',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }
        $prescription = TouristPrescription::where('id', $request->prescription_id)
            ->first();
        if ($prescription == null) {
            return GlobalFunction::sendSimpleResponse(false, 'Prescription does not exists!');
        }
        $prescription->medicine = $request->medicine;
        $prescription->diagnosis = $request->diagnosis;
        $prescription->erx_no =$request->has('erx') ? $request->erx : null;
        $prescription->save();

        return GlobalFunction::sendSimpleResponse(true, 'Prescription edited successfully');
    }

    function downloadPrescriptions(Request $request)
    {
        $rules = [
            'tourist_id' => 'required',
            'prescription_id' => 'required',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }

        $tourist = TouristList::find($request->tourist_id);
        if ($tourist == null) {
            return response()->json(['status' => false, 'message' => "tourist doesn't exists!"]);
        }

        $items = TouristPrescription::with(['tourist', 'appointment', 'appointment.doctor'])
            ->where('tourist_id', $tourist->id)
            ->where('id', $request->prescription_id)
            ->first();

        $data = [];
        $data['tourist'] = $tourist;  
        $data['prescription'] = $items; 
        $filename = "prescription.pdf";
        // return $data;
        // return view('pages.tourist_prescription', $data);

        $pdf = PDF::loadView('pages.tourist_prescription',$data)
        ->setPaper('a4', 'portrait')
        ->setOptions([
            'dpi' => 150,
            'isRemoteEnabled' => true,
            'isHtml5ParserEnabled' => true,
        ]);
        return $pdf->download($filename);
        return GlobalFunction::sendDataResponse(true, 'data fetched successfully', $items);
    }

    function addAppointmentEmrs(Request $request)
    {
        try {
            $rules = [
                'appointment_id' => 'required',
                'documents' => 'required|array',
                'documents.*' => 'file|mimes:jpg,jpeg,png,pdf|max:5120',
            ];

            $messages = [
                'documents.*.max' => 'Each document must not be larger than 5 MB.',
                'documents.*.mimes' => 'Only JPG, JPEG, PNG, and PDF files are allowed.',
                'documents.required' => 'Please upload at least one document.',
            ];

            $validator = Validator::make($request->all(), $rules, $messages);

            if ($validator->fails()) {
                $messages = $validator->errors()->all();
                $msg = $messages[0];
                return response()->json([
                    'status' => false,
                    'message' => $msg,
                ]);
            }

            if ($request->hasFile('documents')) {
                foreach ($request->file('documents') as $document) {
                    $docs = new TouristAppointmentEmrs();
                    $docs->appointment_id = $request->appointment_id;
                    $docs->image = GlobalFunction::saveFileAndGivePath($document);
                    $docs->save();
                }
            }
            return response()->json(['status' => true, 'message' => 'Documents Saved successfully']);

        } catch (\Throwable $th) {

            return ['status' => false, 'message' => $th->getMessage()];
        }
    }

    function submitDoctorReviews(Request $request)
    {
        $rules = [
            'appointment_id' => 'required',
            'rating' => 'required',
            'comment' => 'required',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }
        $appointment = TouristAppointments::find($request->appointment_id);
        if ($appointment == null) {
            return GlobalFunction::sendSimpleResponse(false, 'Appointment does not exists!');
        }

        $review = new TouristDoctorReviews();
        $review->tourist_id = $appointment->tourist_id;
        $review->doctor_id = $appointment->doctor_id;
        $review->appointment_id = $request->appointment_id;
        $review->rating = $request->rating;
        $review->comment = $request->comment;
        $review->created_at = now();
        $review->save();

        return response()->json(['status' => true, 'message' => 'Review submitted successfully', 'data' => $review]);

    }

    function fetchAppointmentHistory(Request $request)
    {
        $rules = [
            'start' => 'required',
            'count' => 'required',
            'doctor_id' => 'required',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }
        $doctor = Doctors::where('id', $request->doctor_id)->first();
        if ($doctor == null) {
            return GlobalFunction::sendSimpleResponse(false, 'Doctor does not exists!');
        }
        
        $result = TouristAppointments::with(['tourist', 'doctor', 'documents', 'prescription', 'rating', 'appointmentMeeting','emrdocuments'])
            ->Where('doctor_id', $request->doctor_id)
             ->Where('tourist_appointments.status','!=',5)
            ->offset($request->start)
            ->limit($request->count)
            ->orderBy('id', 'DESC')
            ->select('tourist_appointments.*') // ⚠ important
            ->get()
            ->map(function ($item) {
                    $item->is_Tourist = true;   // 👈 key add
                    return $item;
            });

        foreach ($result as $appointment) {
            $appointment->previous_appointments =
                TouristAppointments::with(['tourist', 'doctor', 'documents', 'prescription', 'rating', 'appointmentMeeting','emrdocuments'])
                ->Where('doctor_id', $request->doctor_id)
                ->Where('tourist_id', $appointment->tourist_id)
                ->WhereNotIn('id', [$appointment->id])
                ->Where('tourist_appointments.status','!=',5)
                ->WhereIn('status', [Constants::orderCompleted, Constants::orderCancelled, Constants::orderDeclined])
                ->select('tourist_appointments.*') // ⚠ important
                ->get()
                ->map(function ($item) {
                    $item->is_Tourist = true;   // 👈 key add
                    return $item;
                });
        }

         $missingAppointments = TouristAppointments::with(['tourist', 'doctor', 'documents', 'prescription', 'rating', 'appointmentMeeting','emrdocuments'])
            ->join('consult_requests','consult_requests.appointment_id','tourist_appointments.id')
            ->Where('tourist_appointments.status',5)
            ->Where('consult_requests.doctor_id',$request->doctor_id)
            ->offset($request->start)
            ->limit($request->count)
            ->orderBy('tourist_appointments.id', 'DESC')
            ->select('tourist_appointments.*','consult_requests.id as consult_requests_id') // ⚠ important
            ->get()
            ->map(function ($item) {
                $item->is_Tourist = true;   // 👈 key add
                return $item;
            });

        // return GlobalFunction::sendDataResponse(true, 'data fetched successfully', $result ,$missingAppointments);

         return response()->json([
                'status'  => true,
                'message' => 'Data fetched successfully',
                'data' => [
                    'appointments'        => $result,
                    'missing_appointments'=> $missingAppointments
                ]
            ]);
    }

    function rescheduleAppointment(Request $request)
    {
        $rules = [
            'appointment_id' => 'required',
            'tourist_id' => 'required',
            'date' => 'required',
            'time' => 'required',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }

        $tourist = TouristList::find($request->tourist_id);
        if ($tourist == null) {
            return response()->json(['status' => false, 'message' => "Tourist doesn't exists!"]);
        }

        $appointment = TouristAppointments::where('id', $request->appointment_id)
            ->with(['tourist', 'doctor', 'documents', 'prescription', 'rating', 'appointmentMeeting','emrdocuments'])
            ->first();
        if ($appointment == null) {
            return GlobalFunction::sendSimpleResponse(false, 'Appointment does not exists!');
        }
        if ($appointment->tourist_id != $request->tourist_id) {
            return response()->json(['status' => false, 'message' => "This appointment doesn't belong to this tourist"]);
        }

        $appointment->date = $request->date;
        $appointment->time = $request->time;
        $appointment->status = 1;
        $appointment->updated_at = now();
        $appointment->save();


        $room = 'appointment-' . Str::random(10);
        $jitsiBaseUrl = env('JITSI_URL');
        $jitsiJwt = env('JWT_TOKEN_JITSI_MEETING');
        $link = $jitsiBaseUrl . '?roomId=' . $room . '&jwt=' . $jitsiJwt;
        $date = $appointment->date;
        $time = $appointment->time;
        $formattedTime = substr($time, 0, 2) . ':' . substr($time, 2, 2);
        $startDateTime = Carbon::createFromFormat('Y-m-d H:i', $date . ' ' . $formattedTime);
        $endDateTime = $startDateTime->copy()->addHour();
        $appointmentdate = Carbon::createFromFormat('Y-m-d', $date)->format('d-m-Y');
        $appointmentTime = Carbon::createFromFormat('H:i', $formattedTime)->format('h:i A');

        $endDateTime = $startDateTime->copy()->addHour();
        $endTimestamp   = $endDateTime->copy()->setTimezone('UTC')->timestamp;

        $meeting_link_tourist = GlobalFunction::GenerateTouristJitsiMeetingLink($appointment, $room, $endTimestamp);
        $meeting_link_doctor = GlobalFunction::GenerateTouristDoctorJitsiMeetingLink($appointment, $room, $endTimestamp);
        // $meeting_link_patient = GlobalFunction::GeneratePatientJitsiMeetingLink($appointment, $room);
        // $meeting_link_doctor = GlobalFunction::GenerateDoctorJitsiMeetingLink($appointment, $room);
                
        $meeting = TouristJitsiMeeting::where('appointment_id',$request->appointment_id)->where('tourist_id',$request->tourist_id)->latest()->first();
        $meeting->room = $room;
        $meeting->tourist_link = $meeting_link_tourist;
        $meeting->doctor_link = $meeting_link_doctor;
        $meeting->start_time = $startDateTime;
        $meeting->end_time = $endDateTime;
        $meeting->save();
        // Delete user scheduled reminders

        $appointment->jitsiMeetingLink = $meeting_link_tourist;
        // GlobalFunction::deleteAppointmentScheduledReminders($appointment);

        // Send Push to user
        $title = "Appointment :" . $appointment->appointment_number;
        $message = "Appointment has been rescheduled successfully!";
        $notifyData = [
            'type'=> Constants::notifyAppointment.'',
            'id'=>$appointment->id.''
        ];
        // GlobalFunction::sendPushToUser($title, $message, $user, $notifyData);

        return GlobalFunction::sendDataResponse(true, 'Appointment rescheduled successfully!', $appointment);
    }

    function AIVitals(Request $request)
    {
        $rules = [
            'tourist_id' => 'required',
            'appointment_id' => 'required',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }

        $ai_vitals = new TouristAIVital();
        $ai_vitals->tourist_id = $request->tourist_id;
        $ai_vitals->appointment_id = $request->appointment_id;
        $ai_vitals->report = $request->report;
        $ai_vitals->scan_date = $request->date;
        
        // if($request->has('pdf_file'))
        // {
        //     $ai_vitals->pdf_file = GlobalFunction::saveFileAndGivePath($request->pdf_file);
        // }

        $data = [];
        $tourist = TouristList::where('id',$ai_vitals->tourist_id)->first();
        $data['tourist'] = $tourist; 
        $data['scan_date'] = $request->date; 
        if (is_string($ai_vitals->report)) {
            $data['report'] = json_decode($ai_vitals->report, true);
        } elseif (is_array($ai_vitals->report)) {
            $data['report'] = $ai_vitals->report;
        }
        if ($tourist && $tourist->number_of_ai_health_check > 0) {
            $tourist->decrement('number_of_ai_health_check', 1);
        }
        // return $data;
        $filename = "aiVitalMIDAS_Report.pdf";
        // return view('pages.TouristVitalScanReport', $data);
        $pdf = PDF::loadView('pages.TouristVitalScanReport', $data)
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'dpi' => 150,
                'isRemoteEnabled' => true,
                'isHtml5ParserEnabled' => true,
            ]);

        $filename = 'vitalScan.pdf';

        // create temp file path
        $tempPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $filename;

        // write PDF bytes to temp file
        file_put_contents($tempPath, $pdf->output());
        $uploadedFile = new UploadedFile(
            $tempPath,      // full path to temp file
            $filename,      // original filename
            'application/pdf', // mime type
            null,           // size (null lets PHP handle it)
            true            // $test = true (important)
        );
        // Now call your helper exactly as before
        $saveResult = GlobalFunction::saveFileAndGivePath($uploadedFile);

        $ai_vitals->pdf_file = is_array($saveResult)
            ? ($saveResult['path'] ?? null)
            : $saveResult;

        $ai_vitals->report = is_array($ai_vitals->report)
            ? json_encode($ai_vitals->report)
            : $ai_vitals->report;

        $ai_vitals->save();
        // Mail::to($user->email)->send(new AiVitalReportMail($user, $uploadedFile));
        // Log::info('AI Vitals Response'. $request);
        $baseUrl = url('/');
        $pdf_url = $baseUrl . '/api/v1/tourist/vitalReportPdf?tourist_id=' . $ai_vitals->tourist_id .'&report_id=' . $ai_vitals->id;

        return response()->json([
            'status' => true, 
            'pdf_url' => $pdf_url,
            'message' => "Data saved successfully",
        ]);
    }

    function initiatePaymentAIVitalScan(Request $request)
    {
        //  try {
            $rules = [
                'tourist_id' => 'required',
                'report' => 'required',
            ];

            $validator = Validator::make($request->all(), $rules);
            if ($validator->fails()) {
                $messages = $validator->errors()->all();
                $msg = $messages[0];
                return response()->json(['status' => false, 'message' => $msg]);
            }                                   

            $tourist = TouristList::find($request->tourist_id);
            if ($tourist == null) {
                return response()->json(['status' => false, 'message' => "tourist doesn't exists!"]);
            }

            $ai_vital_misa = new TouristAIVitalScanMisa();
            $ai_vital_misa->tourist_id = $request->tourist_id;
            $ai_vital_misa->report_from = $request->report_from;
            if (is_string($request->report)) {
                // Type-1: JSON string
                $ai_vital_misa->report =$request->report;
            }else {
               $ai_vital_misa->report = json_encode($request->report);
            }
            
            $ai_vital_misa->scan_date = $request->date;
            // $ai_vital_misa->save();

            $data['tourist'] = $tourist; 
            $data['scan_date'] = $request->date; 
            
            if (is_string($ai_vital_misa->report)) {
                $data['report'] = json_decode($ai_vital_misa->report, true);
            } elseif (is_array($ai_vital_misa->report)) {
                $data['report'] = $ai_vital_misa->report;
            }

            if ($tourist && $tourist->number_of_ai_health_check > 0) {
                $tourist->decrement('number_of_ai_health_check', 1);
            }

            $filename = "aiVitalMIDAS_Report.pdf";
            // return $data;
            // return view('pages.TouristVitalScanReport', $data);

            $pdf = PDF::loadView('pages.TouristVitalScanReport', $data)
                ->setPaper('a4', 'portrait')
                ->setOptions([
                    'dpi' => 150,
                    'isRemoteEnabled' => true,
                    'isHtml5ParserEnabled' => true,
                ]);

            $filename = 'vitalScan.pdf';

            $tempPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $filename;

            file_put_contents($tempPath, $pdf->output());

            $uploadedFile = new UploadedFile(
                $tempPath,
                $filename,
                'application/pdf',
                null,
                true
            );

            // Now call your helper exactly as before
            $saveResult = GlobalFunction::saveFileAndGivePath($uploadedFile);
            $ai_vital_misa->pdf_file = $saveResult;
            $ai_vital_misa->save();

            // return $ai_vital_misa;

            $pdf_url = route('touristAiVitalMesaReportPdf') . '?' . http_build_query([
                'tourist_id'   => $ai_vital_misa->tourist_id,
                'report_id' => $ai_vital_misa->id,
            ]);


            // Log::info('AI Vitals Response'. $request);
            if($ai_vital_misa->report_from == "ai_vital"){
                return response()->json([
                    'status' => true,
                    'category_id' => 36,
                    'pdf_url' => $pdf_url,
                    'message' => "Data saved successfully",
                ]);
            }
            return response()->json([
                'status' => true, 
                'pdf_url' => $pdf_url, 
                'message' => "Data saved successfully",
            ]);
        // }

        // catch (\Throwable $e) {

        //     return ['status' => false, 'message' => $e->getMessage()];
        // }
    }

    function TouristAiVitalMesaReportPdf(Request $request)
    {
        $rules = [
            'tourist_id' => 'required',
            'report_id' => 'required',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }

        $ai_vital_report = TouristAIVitalScanMisa::where('tourist_id',$request->tourist_id)->where('id',$request->report_id)->first();
        $data = [];
        $tourist = TouristList::where('id',$request->tourist_id)->first();
        $data['tourist'] = $tourist; 
        $data['scan_date'] = $ai_vital_report->scan_date; 
        $data['report'] = json_decode($ai_vital_report->report); 
        // return $data;
        $filename = "aiVitalMIDAS_Report.pdf";
        // return view('pages.vitalScanReport', $data);
        $pdf = PDF::loadView('pages.TouristVitalScanReport',$data)
        ->setPaper('a4', 'portrait')
        ->setOptions([
            'dpi' => 150,
            'isRemoteEnabled' => true,
            'isHtml5ParserEnabled' => true,
        ]);
        return $pdf->download($filename);
    }

    function vitalReportPdf(Request $request)
    {     
        $rules = [
            'tourist_id' => 'required',
            'report_id' => 'required',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }

        $ai_vital_report = TouristAIVital::where('tourist_id',$request->tourist_id)->where('id',$request->report_id)->first();
        $data = [];
        $tourist = TouristList::where('id',$request->tourist_id)->first();
        $data['tourist'] = $tourist; 
        $data['scan_date'] = $ai_vital_report->scan_date; 
        // if (is_string($ai_vital_report->report)) {
        //     $data['report'] = json_decode($ai_vital_report->report, true);
        // } elseif (is_array($ai_vital_report->report)) {
        //     $data['report'] = $ai_vital_report->report;
        // }
          $data['report'] = !empty($ai_vital_report->report) ? json_decode($ai_vital_report->report) : '';
        // return $data;
        $filename = "vitalScanReport.pdf";
        // return view('pages.TouristVitalScanReport', $data);
        $pdf = PDF::loadView('pages.TouristVitalScanReport',$data)
        ->setPaper('a4', 'portrait')
        ->setOptions([
            'dpi' => 150,
            'isRemoteEnabled' => true,
            'isHtml5ParserEnabled' => true,
        ]);
        return $pdf->download($filename);
    }

    function mesaBeforeChat(Request $request)
    {
         try {
            $rules = [
                'tourist_id' => 'required',
            ];

            $validator = Validator::make($request->all(), $rules);
            if ($validator->fails()) {
                $messages = $validator->errors()->all();
                $msg = $messages[0];
                return response()->json(['status' => false, 'message' => $msg]);
            }                                   

            $tourist = TouristList::find($request->tourist_id);
            if ($tourist == null) {
                return response()->json(['status' => false, 'message' => "tourist doesn't exists!"]);
            }

            $ai_vital_misa = new TouristAIVitalScanMisa();
            $ai_vital_misa->tourist_id = $request->tourist_id;
            $ai_vital_misa->report_from = $request->report_from;
            $ai_vital_misa->scan_date = $request->date;
            $ai_vital_misa->save();

            return response()->json([
                'status' => true,
                'ai vital misa' => $ai_vital_misa
            ]);
        }

        catch (\Throwable $e) {
            return ['status' => false, 'message' => $e->getMessage()];
        }
    }

    public function getIsabelInfoOptions(Request $request)
    {        
        $rules = [
            'tourist_id' => 'required',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }

        $supported = ['en','ar','fr'];
        $lang = request('lang', 'en');
        if (!in_array($lang, $supported, true)) {
            $lang = 'en';
        }
        $translationsPath = resource_path("lang/IsabelOptionsTranslations/{$lang}.php");
        $translations = file_exists($translationsPath) ? include $translationsPath : [];

        if($lang == 'en')
        {
            $age_groups = IsabelAgeGroup::select('english')->first();
            $age_groups = json_decode($age_groups->english);
        }

        if($lang == 'fr')
        {
            $age_groups = IsabelAgeGroup::select('french')->first();
            $age_groups =  json_decode($age_groups->french);
        }

        if($lang == 'ar')
        {
            $age_groups = IsabelAgeGroup::select('arabic')->first();
            $age_groups =  json_decode($age_groups->arabic);
        }

        if($lang == 'en')
        {
            $pregnancies = isabelPregnancies::select('english')->first();
            $pregnancies = json_decode($pregnancies->english);
        }

        if($lang == 'fr')
        {
            $pregnancies = isabelPregnancies::select('french')->first();
            $pregnancies =  json_decode($pregnancies->french);
        }

        if($lang == 'ar')
        {
            $pregnancies = isabelPregnancies::select('arabic')->first();
            $pregnancies =  json_decode($pregnancies->arabic);
        }

        if($lang == 'en')
        {
            $regions = IsabelRegions::select('english')->first();
            $regions = json_decode($regions->english);
        }

        if($lang == 'fr')
        {
            $regions = IsabelRegions::select('french')->first();
            $regions =  json_decode($regions->french);
        }

        if($lang == 'ar')
        {
            $regions = IsabelRegions::select('arabic')->first();
            $regions =  json_decode($regions->arabic);
        }

        if($lang == 'en')
        {
            $countries = IsabelCountries::select('english')->first();
            $countries = json_decode($countries->english);
        }

        if($lang == 'fr')
        {
            $countries = IsabelCountries::select('french')->first();
            $countries =  json_decode($countries->french);
        }

        if($lang == 'ar')
        {
            $countries = IsabelCountries::select('arabic')->first();
            $countries =  json_decode($countries->arabic);
        }

        $sex = [
            ['sex_id' => 'f', 'sex_name' => $translations['sex']['female'] ?? 'Female'],
            ['sex_id' => 'm', 'sex_name' => $translations['sex']['male']   ?? 'Male'],
        ];
        
        $mesa_ai_vital_report = TouristAIVitalScanMisa::where('tourist_id', $request->tourist_id)->latest()->first();

        $reports = [];
        if(isset($mesa_ai_vital_report)){
            $reports = json_decode($mesa_ai_vital_report->report);
        }
        
        $terms = $this->extractAIVitalTerms($reports); // returns array of strings, unique

        return response()->json([
            'status'       => true,
            'locale'       => $lang,
            'age_groups'   => $age_groups,   // <= same shape, `name` translated
            'regions'      => $regions,
            'countries'    => $countries,
            'pregnancies'  => $pregnancies,
            'sex'          => $sex,
            'symptoms'     => $terms,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function isabelQuestionsAnswers(Request $request)
    {
        $supported = ['en','ar','fr','hi','ur'];
        // $lang = request()->header('lang', 'en');
        $lang = $request->query('lang', 'en');
        if (!in_array($lang, $supported, true)) $lang = 'en';

        $rows = IsabelQuestion::with(['answer' => fn($q) => $q->orderBy('option_number')])
            ->orderBy('id')
            ->get()
            ->map(function ($q) use ($lang) {
                // start with the original row so we keep *all* fields
                $row = $q->toArray();

                // override only the translatable field
                $row['question'] = IsabelI18n::get("questions.$q->id", $lang, 'en') ?: $q->question;

                // map answers, preserving original fields and overriding only 'answer'
                $row['answer'] = $q->answer->map(function ($a) use ($lang) {
                    $ans = $a->toArray();
                    $ans['answer'] = IsabelI18n::get("answers.{$a->isabel_question_id}.{$a->option_number}", $lang, 'en') ?: $a->answer;
                    return $ans;
                })->values()->toArray();

                return $row;
            })->values();

        return response()->json($rows);
    }

    public function getPredictiveText(Request $request)
    {
        $rules = ['tourist_id' => 'required'];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()]);
        }

        $supported = ['en','ar','fr'];
        $lang = request('lang', 'en');
        if (!in_array($lang, $supported, true)) {
            $lang = 'en';
        }

        $mesa_ai_vital_report = TouristAIVitalScanMisa::where('tourist_id', $request->tourist_id)->where('report_from', 'ai_vital')->latest()->first();

        $ai_symptoms_payload = [];
        $filtered = [];
        if ($mesa_ai_vital_report) {
            $reports = json_decode($mesa_ai_vital_report->report);
            $terms = $this->extractAIVitalTerms($reports);

            $search = $request->search ?? null;
            if ($search) {
                $filtered = array_values(array_filter($terms, fn($t) => stripos($t, $search) !== false));
            } else {
                $filtered = $terms;
            }

            $ai_symptoms_payload = array_map(fn($t) => ['text' => $t], $filtered);
        }

        if($lang == 'en'){
            if ($request->has('search')) {
                $search = $request->search ?? null;
                $predictive_text_rows = isabelPredictiveText::select('english')->first();
                $predictive_text_rows = json_decode($predictive_text_rows->english)->predictive_text;
                $predictive_text_rows = array_values(array_filter($predictive_text_rows, fn($t) => stripos($t, $search) !== false));

            } else {
                $predictive_text_rows = isabelPredictiveText::select('english')->first();
                $predictive_text_rows = json_decode($predictive_text_rows->english)->predictive_text;
            }
        }
        if($lang == 'ar'){
            if ($request->has('search')) {
                $search = $request->search ?? null;
                $predictive_text_rows = isabelPredictiveText::select('arabic')->first();
                $predictive_text_rows = json_decode($predictive_text_rows->arabic)->predictive_text;
                $predictive_text_rows = array_values(array_filter($predictive_text_rows, fn($t) => stripos($t, $search) !== false));

            } else {
                $predictive_text_rows = isabelPredictiveText::select('arabic')->first();
                $predictive_text_rows = json_decode($predictive_text_rows->arabic)->predictive_text;
            }
        }
        if($lang == 'fr'){
            if ($request->has('search')) {
                $search = $request->search ?? null;
                $predictive_text_rows = isabelPredictiveText::select('french')->first();
                $predictive_text_rows = json_decode($predictive_text_rows->french)->predictive_text;
                $predictive_text_rows = array_values(array_filter($predictive_text_rows, fn($t) => stripos($t, $search) !== false));

            } else {
                $predictive_text_rows = isabelPredictiveText::select('french')->first();
                $predictive_text_rows = json_decode($predictive_text_rows->french)->predictive_text;
            }
        }

        $predictive_text = array_map(fn($t) => ['text' => $t], $predictive_text_rows);

        $merged = array_merge($ai_symptoms_payload, $predictive_text);
        $unique = [];
        foreach ($merged as $item) {
            $key = mb_strtolower($item['text']);
            if (!isset($unique[$key])) $unique[$key] = $item;
        }
        $predictive_text_final = array_values($unique);

        return response()->json([
            'status' => true,
            'predictive_text'   => $predictive_text_final,
            'ai_vital_symptoms' => $filtered
        ]);
    }

    function ranked_differential_diagnoses(Request $request)
    {
        $rules = [
            'tourist_id' => 'required',
            'age' => 'required',
            'sex' => 'required',
            'region' => 'required',
            'country' => 'required',
            'text' => 'required',
            'pregnancy' => 'required'
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }

        $age = $request->age;
        $sex = $request->sex;
        $region = $request->region;
        $country = $request->country;
        $text = $request->text;
        $pregnancy = $request->pregnancy;

        $authorizationKey = env('ISABEL_AUTHORIZATION_KEY');

        $ranked_differential_diagnoses = Http::withHeaders([
                            'authorization' => $authorizationKey,
                        ])->get('https://apiscsandbox.isabelhealthcare.com/v3/ranked_differential_diagnoses', [
                            'age' => $age,
                            'specialities' => 28,
                            'dob'=> $age,
                            'sex' => $sex,
                            'region' => $region,
                            'querytext' => $text,
                            'pregnant' => $pregnancy,
                            'country' => $country,
                            'suggest'=> 'Suggest+Differential+Diagnosis',
                            'flag'=> 'sortbyRW_advanced',
                            'searchType'=> 0,
                            'language'    => $request->language,
                            'web_service' => 'json',
                        ]);

        if ($ranked_differential_diagnoses->ok()) {
            $ranked_differential_diagnoses = $ranked_differential_diagnoses->json();
            if(isset($ranked_differential_diagnoses['diagnoses_checklist']['no_result'])){
                return response()->json([
                    'error'      => $ranked_differential_diagnoses['diagnoses_checklist']['no_result']['information'],
                    'status'     => false,
                ]);
            }

            $diagnoses = $ranked_differential_diagnoses['diagnoses_checklist']['diagnoses'];
            $differential_diagnoses = new TouristRankedDifferentialDiagnoses();
            $differential_diagnoses->tourist_id = $request->tourist_id;
            $differential_diagnoses->diagnoses = json_encode($ranked_differential_diagnoses['diagnoses_checklist']['diagnoses']);
            $differential_diagnoses->save();
            return response()->json([
                    'ranked_differential_diagnoses' => $ranked_differential_diagnoses,
                    'status'     => true,
                ]);
        }
    }

    function knowledge_window_urls(Request $request)
    {
        $authorizationKey = env('ISABEL_AUTHORIZATION_KEY');
        
        $url = "https://apiscsandbox.isabelhealthcare.com/v3/knowledge_window_urls";

        $response = Http::retry(3, 250)
                ->acceptJson()
                ->withHeaders(['authorization' => $authorizationKey])
                ->get($url, [
                        'language' => $request->language,
                        'web_service' => 'json',
                        'category_id' => $request->category_id,
                        'category_type' => $request->category_type,
                        'diagnoses_name' => $request->diagnoses_name,
                        'diagnoses_sub' => $request->diagnoses_sub,
                        'age_id' => $request->age_id,
                        'sex' => $request->sex,
                        'pregnant' => $request->pregnancy,
                        'region' => $request->region,
                        'text' => $request->text,
                        'specialty_id'=> $request->specialty_id,
                        'audit_id' => $request->audit_id
                    ]);
        if ($response->successful()) {
            $data = $response->json();
            return response()->json([
                'response' => $data,
                'status' => $response->status(),
            ], $response->status());
        }else{
            return response()->json([
                'error' => 'Failed to fetch predictive text',
                'status' => $response->status(),
            ], $response->status());
        }

    }

    private function extractAIVitalTerms($reports): array
    {
        // Normalize into array if object passed
        if (is_object($reports)) {
            $reports = json_decode(json_encode($reports), true);
        } elseif (!is_array($reports)) {
            return [];
        }

        // --- Thresholds (tweak these to match clinical rules or vendor docs) ---
        $THRESH = [
            'pulse_brady' => 60,    // < => Bradycardia
            'pulse_tachy' => 100,   // > => Tachycardia (user called yachycardio)
            // Blood pressure thresholds (systolic / diastolic)
            'bp_systolic_low'  => 90,
            'bp_diastolic_low' => 60,
            'bp_systolic_high' => 140,
            'bp_diastolic_high'=> 90,
            // Respiratory rate (breaths/min)
            'rr_low'  => 12,  // < => Bradypnea (slow)
            'rr_high' => 20,  // > => Tachypnea (fast)
            // HRV (SDNN) thresholds (example values; tune as needed)
            'hrv_low'  => 50,   // < => Low HRV
            'hrv_high' => 100,  // > => High HRV
            // Stress index (example scale; tune to your provider)
            'stress_low'  => 3.0,
            'stress_high' => 7.0,
            // Cardiac workload (example threshold)
            'cardiac_low'  => 50,
            'cardiac_high' => 100,
            // Parasympathetic activity (example; tune)
            'parasymp_low'  => 20,
            'parasymp_high' => 60,
            // BMI categories (WHO)
            'bmi_under' => 18.5,
            'bmi_over'  => 25.0,
        ];

        $tags = [];

        // --- Pulse / Heart Rate ---
        $hr = $reports['heartRate'] ?? $reports['heartbeats'] ?? null;
        if (is_numeric($hr)) {
            $hr = floatval($hr);
            if ($hr < $THRESH['pulse_brady']) {
                $tags[] = 'Bradycardia';
            } elseif ($hr > $THRESH['pulse_tachy']) {
                // user wrote "yachycardio" — using clinical term Tachycardia
                $tags[] = 'Tachycardia';
            }
        }

        // --- Blood Pressure (expects "systolic/diastolic" string) ---
        $bp = $reports['bloodPressure'] ?? null;
        if (is_string($bp) && strpos($bp, '/') !== false) {
            [$systolic, $diastolic] = array_map('trim', explode('/', $bp, 2));
            if (is_numeric($systolic) && is_numeric($diastolic)) {
                $s = floatval($systolic);
                $d = floatval($diastolic);
                if ($s < $THRESH['bp_systolic_low'] || $d < $THRESH['bp_diastolic_low']) {
                    $tags[] = 'Hypotension';
                } elseif ($s >= $THRESH['bp_systolic_high'] || $d >= $THRESH['bp_diastolic_high']) {
                    $tags[] = 'Hypertension';
                }
            }
        }

        // --- Respiratory Rate ---
        $rr = $reports['respiratoryRate'] ?? null;
        if (is_numeric($rr)) {
            $rr = floatval($rr);
            if ($rr < $THRESH['rr_low']) {
                $tags[] = 'Bradypnea';   // slow breathing
            } elseif ($rr > $THRESH['rr_high']) {
                $tags[] = 'Tachypnea';   // fast breathing
            }
        }

        // --- HRV (using SDNN field if available) ---
        // Try a few common keys
        $hrv = $reports['hrvSdnnMs'] ?? $reports['hrv_sdnn_ms'] ?? null;
        if (is_string($hrv)) {
            // numeric strings may be present
            $hrv = is_numeric($hrv) ? floatval($hrv) : null;
        }
        if (is_numeric($hrv)) {
            if ($hrv < $THRESH['hrv_low']) {
                $tags[] = 'Low HRV';
            } elseif ($hrv > $THRESH['hrv_high']) {
                $tags[] = 'High HRV';
            }
        }

        // --- Stress level / index ---
        $stress = $reports['stressLevel'] ?? $reports['stress_index'] ?? null;
        if (is_string($stress)) {
            $stress = is_numeric($stress) ? floatval($stress) : null;
        }
        if (is_numeric($stress)) {
            if ($stress < $THRESH['stress_low']) {
                $tags[] = 'Low stress';
            } elseif ($stress > $THRESH['stress_high']) {
                $tags[] = 'High Stress';
            }
        }

        // --- Cardiac workload ---
        $cw = $reports['cardiacWorkload'] ?? null;
        if (is_string($cw)) {
            $cw = is_numeric($cw) ? floatval($cw) : null;
        }
        if (is_numeric($cw)) {
            if ($cw < $THRESH['cardiac_low']) {
                $tags[] = 'Low Cardiac Workload';
            } elseif ($cw > $THRESH['cardiac_high']) {
                $tags[] = 'High Cardiac Workload';
            }
        }

        // --- Parasympathetic Activity ---
        $pa = $reports['parasympatheticActivity'] ?? null;
        if (is_string($pa)) {
            $pa = is_numeric($pa) ? floatval($pa) : null;
        }
        if (is_numeric($pa)) {
            if ($pa < $THRESH['parasymp_low']) {
                $tags[] = 'Dysautonomia';
            } elseif ($pa > $THRESH['parasymp_high']) {
                $tags[] = 'Vagal Hypertonia';
            }
        }

        // --- BMI ---
        $bmi = $reports['bmi'] ?? null;
        if (is_string($bmi)) {
            $bmi = is_numeric($bmi) ? floatval($bmi) : null;
        }
        if (is_numeric($bmi)) {
            if ($bmi < $THRESH['bmi_under']) {
                $tags[] = 'Underweight';
            } elseif ($bmi >= $THRESH['bmi_over']) {
                $tags[] = 'Overweight';
            }
        }

        // Make unique and reindex
        $tags = array_values(array_unique($tags));

        return $tags;
    }

    function submit_answers(Request $request)
    {
        $rules = [
            'tourist_id' => 'required',
            'age' => 'required',
            'sex' => 'required',
            'region' => 'required',
            'country' => 'required',
            'text' => 'required',
            'pregnancy' => 'required',
            'Q1' => 'required',
            'Q2' => 'required',
            'Q3' => 'required',
            'Q4' => 'required',
            'Q5' => 'required',
            'Q6' => 'required',
            'Q7' => 'required',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }

        $supported = ['en','ar','fr'];
        $lang = request('lang', 'en');
        if (!in_array($lang, $supported, true)) {
            $lang = 'en';
        }

        $age = $request->age;
        $sex = $request->sex;
        $region = $request->region;
        $country = $request->country;
        $text = $request->text;
        $pregnancy = $request->pregnancy;
        $Q1 = $request->Q1;
        $Q2 = $request->Q2;
        $Q3 = $request->Q3;
        $Q4 = $request->Q4;
        $Q5 = $request->Q5;
        $Q6 = $request->Q6;
        $Q7 = $request->Q7;

        $authorizationKey = env('ISABEL_AUTHORIZATION_KEY');

        $ranked_differential_diagnoses = Http::withHeaders([
                            'authorization' => $authorizationKey,
                        ])->get('https://apiscsandbox.isabelhealthcare.com/v3/ranked_differential_diagnoses', [
                            'age' => $age,
                            'specialities' => 28,
                            'dob'=> $age,
                            'sex' => $sex,
                            'region' => $region,
                            'querytext' => $text,
                            'pregnant' => $pregnancy,
                            'country' => $country,
                            'suggest'=> 'Suggest+Differential+Diagnosis',
                            'flag'=> 'sortbyRW_advanced',
                            'searchType'=> 0,
                            'language'    => $lang,
                            'web_service' => 'json',
                        ]);

        if ($ranked_differential_diagnoses->ok()) {
            $ranked_differential_diagnoses = $ranked_differential_diagnoses->json();
            if(isset($ranked_differential_diagnoses['diagnoses_checklist']['no_result'])){
                return response()->json([
                    'error'      => $ranked_differential_diagnoses['diagnoses_checklist']['no_result']['information'],
                    'status'     => false,
                ]);
            }
            $triage_url = $ranked_differential_diagnoses['diagnoses_checklist']['triage_api_url'];
            $parsedUrl = parse_url($triage_url);
            $baseUrl = $parsedUrl['scheme'] . "://" . $parsedUrl['host'] . $parsedUrl['path'];
            parse_str($parsedUrl['query'], $queryParams);
            $dx        = $queryParams['dx'] ?? null;
            $age       = $queryParams['age'] ?? null;
            $sex       = $queryParams['sex'] ?? null;
            $region    = $queryParams['region'] ?? null;
            $text      = $queryParams['text'] ?? null;
            $pregnancy = $queryParams['pregnant'] ?? $queryParams['pregnancy'] ?? null;

            $response = Http::withHeaders([
                            'authorization' => $authorizationKey,
                        ])->get($baseUrl, [
                            'dx'  => $dx,
                            'age' => $age,
                            'sex' => $sex,
                            'region' => $region,
                            'text' => $text,
                            'pregnant' => $pregnancy,
                            'Q1' => $Q1,
                            'Q2' => $Q2,
                            'Q3' => $Q3,
                            'Q4' => $Q4,
                            'Q5' => $Q5,
                            'Q6' => $Q6,
                            'Q7' => $Q7,
                            'language'    => $lang,
                            'web_service' => 'json',
                        ]);
            if ($response->ok()) {

                $isabel_report = new TouristIsabelReport();
                $isabel_report->tourist_id = $request->tourist_id;
                $isabel_report->age = $request->age;
                $isabel_report->sex = $request->sex;
                $isabel_report->region = $request->region;
                $isabel_report->country = $request->country;
                $isabel_report->text = $request->text;
                $isabel_report->pregnancy = $request->pregnancy;
                $isabel_report->Q1 = $request->Q1;
                $isabel_report->Q2 = $request->Q2;
                $isabel_report->Q3 = $request->Q3;
                $isabel_report->Q4 = $request->Q4;
                $isabel_report->Q5 = $request->Q5;
                $isabel_report->Q6 = $request->Q6;
                $isabel_report->Q7 = $request->Q7;
                $isabel_report->report_from = $request->report_from;
                $isabel_report->lang = $lang;
                $mesa_ai_vital_report = TouristAIVitalScanMisa::where('tourist_id', $request->tourist_id)->latest()->first();

                if(isset($mesa_ai_vital_report))
                {
                    $data = [];
                    $tourist = TouristList::where('id',$mesa_ai_vital_report->tourist_id)->first();
                    $data['tourist'] = $tourist; 
                    $data['scan_date'] = $mesa_ai_vital_report->created_at; 
                    $data['report'] = json_decode($mesa_ai_vital_report->report); 
                    // return $data;
                    $filename = "aiVitalMIDAS_Report.pdf";
                    // return view('pages.vitalScanReport', $data);
                    $pdf = PDF::loadView('pages.TouristVitalScanReport', $data)
                    ->setPaper('a4', 'portrait')
                    ->setOptions([
                        'dpi' => 150,
                        'isRemoteEnabled' => true,
                        'isHtml5ParserEnabled' => true,
                    ]);

                    $filename = 'MIDASVitalScan.pdf';

                    // create temp file path
                    $tempPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $filename;

                    // write PDF bytes to temp file
                    file_put_contents($tempPath, $pdf->output());

                    // Create an UploadedFile instance (set $test = true so it bypasses is_uploaded_file checks)
                    $uploadedFile = new UploadedFile(
                        $tempPath,      // full path to temp file
                        $filename,      // original filename
                        'application/pdf', // mime type
                        null,           // size (null lets PHP handle it)
                        true            // $test = true (important)
                    );

                    // Now call your helper exactly as before
                    $saveResult = GlobalFunction::saveFileAndGivePath($uploadedFile);

                    $mesa_ai_vital_report->pdf_file = $saveResult;
                    
                    $mesa_ai_vital_report->save();
                }

                $isabel_report->score = data_get($response->json(), 'where_to_now.triage_score', 0);
                $isabel_report->save();


                $triage_report = TouristIsabelReport::find($isabel_report->id);
                $request->merge(['tourist_id' => $triage_report->tourist_id]);
                if($triage_report->lang != null){
                    $request->merge(['lang' => $triage_report->lang]);
                }else{
                    $request->merge(['lang' => 'en']);
                }
                // return $request;
                $isabel_response = $this->getIsabelInfoOptions($request);
                $data = $isabel_response->getData(true); // now it's an array
            
                if($data['status'] == true)
                {
                    $country_name = null;
                    $region_name = null;
                    $region_name = null;
                    
                    $countries = collect($data['countries']);
                    $country = $countries->firstWhere('country_id', $triage_report->country);

                    if ($country) {
                        $country_name = $country['country_name']; // e.g. India
                    }

                    $regions = collect($data['regions']);
                    $region = $regions->firstWhere('region_id', $triage_report->region);

                    if ($region) {
                        $region_name = $region['region_name']; // e.g. India
                    }

                    $age_groups = collect($data['age_groups']);
                    $age_group = $age_groups->firstWhere('agegroup_id', $triage_report->age);

                    if ($age_group) {
                        $age = $age_group['name'] . ' ' . $age_group['yr_from'] .'-'. $age_group['yr_to']; // e.g. India
                    }

                    $tourist = TouristList::where('id',$triage_report->tourist_id)->first();
                    // $user = Users::find($triage_report->user_id);
                    $patient_name = $tourist->first_name . ' ' . $tourist->last_name;
                    // $date_of_birth = $user->dob;
                    $contact_details = $tourist->contact_number; 

                    $data = [];
                    $data['report'] = $triage_report;
                    $data['country_name'] = $country_name;
                    $data['region_name'] = $region_name;
                    $data['age'] = $age;
                    $data['patient_name'] = $patient_name;
                    // $data['date_of_birth'] = $date_of_birth;
                    $data['contact_details'] = $contact_details;
                    $data['tourist'] = $tourist;
                    $data['scan_date'] = $triage_report->created_at;

                    $translationsPath = resource_path("lang/IsabelQuestionAnswerTranslation/{$request->lang}.php");
                    $translations = file_exists($translationsPath) ? include $translationsPath : [];

                    $questions = IsabelQuestion::all();
                    if(!empty($questions) && isset($translations['questions']))
                    {
                        $map = $translations['questions'];
                        foreach ($questions as &$question) {
                            $map_id = (string)($question['id'] ?? '');
                            if (isset($map[$map_id])) {
                                $question->question = $map[$map_id];
                            }
                        }
                    }
                    foreach ($questions as $key => $question) {
                        if($question->id == 1)
                        {   
                            $answer = IsabelAnswer::where('isabel_question_id',$question->id)->where('option_number',$triage_report->q1)->first();

                            if(isset($answer) && isset($translations['answers']))
                            {
                                $map = $translations['answers'];
                                $map_id = (string)($question['id'] ?? '');
                                if (isset($map[$map_id])) {
                                    $question->answer = $map[$map_id][$triage_report->q1];
                                }
                            }                
                        }
                        if($question->id == 2)
                        {
                            $answer = IsabelAnswer::where('isabel_question_id',$question->id)->where('option_number',$triage_report->q2)->first();
                            if(isset($answer) && isset($translations['answers']))
                            {
                                $map = $translations['answers'];
                                $map_id = (string)($question['id'] ?? '');
                                if (isset($map[$map_id])) {
                                    $question->answer = $map[$map_id][$triage_report->q2];
                                }
                            }  
                        }
                        if($question->id == 3)
                        {
                            $answer = IsabelAnswer::where('isabel_question_id',$question->id)->where('option_number',$triage_report->q3)->first();
                            if(isset($answer) && isset($translations['answers']))
                            {
                                $map = $translations['answers'];
                                $map_id = (string)($question['id'] ?? '');
                                if (isset($map[$map_id])) {
                                    $question->answer = $map[$map_id][$triage_report->q3];
                                }
                            }  
                        }
                        if($question->id == 4)
                        {
                            $answer = IsabelAnswer::where('isabel_question_id',$question->id)->where('option_number',$triage_report->q4)->first();
                            if(isset($answer) && isset($translations['answers']))
                            {
                                $map = $translations['answers'];
                                $map_id = (string)($question['id'] ?? '');
                                if (isset($map[$map_id])) {
                                    $question->answer = $map[$map_id][$triage_report->q4];
                                }
                            }  
                        }
                        if($question->id == 5)
                        {
                            $answer = IsabelAnswer::where('isabel_question_id',$question->id)->where('option_number',$triage_report->q5)->first();
                            if(isset($answer) && isset($translations['answers']))
                            {
                                $map = $translations['answers'];
                                $map_id = (string)($question['id'] ?? '');
                                if (isset($map[$map_id])) {
                                    $question->answer = $map[$map_id][$triage_report->q5];
                                }
                            }  
                        }
                        if($question->id == 6)
                        {
                            $answer = IsabelAnswer::where('isabel_question_id',$question->id)->where('option_number',$triage_report->q6)->first();
                            if(isset($answer) && isset($translations['answers']))
                            {
                                $map = $translations['answers'];
                                $map_id = (string)($question['id'] ?? '');
                                if (isset($map[$map_id])) {
                                    $question->answer = $map[$map_id][$triage_report->q6];
                                }
                            }  
                        }
                        if($question->id == 7)
                        {
                            $answer = IsabelAnswer::where('isabel_question_id',$question->id)->where('option_number',$triage_report->q7)->first();
                            if(isset($answer) && isset($translations['answers']))
                            {
                                $map = $translations['answers'];
                                $map_id = (string)($question['id'] ?? '');
                                if (isset($map[$map_id])) {
                                    $question->answer = $map[$map_id][$triage_report->q7];
                                }
                            }  
                        }
                    }
                    $data['questions'] = $questions;
                    $ranked_differential_diagnoses = TouristRankedDifferentialDiagnoses::where('tourist_id',$request->tourist_id)->latest()->first();
                    $data['ranked_differential_diagnoses'] = json_decode($ranked_differential_diagnoses?->diagnoses,true);                    

                    // if($request->lang == 'fr'){
                    //     $pdf = PDF::loadView('pages.triage_report_fr', $data);
                    //     $filename = 'MIDASReport.pdf';

                    //     $tempPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $filename;

                    //     file_put_contents($tempPath, $pdf->output());

                    //     $uploadedFile = new UploadedFile(
                    //         $tempPath,     
                    //         $filename, 
                    //         'application/pdf',
                    //         null,
                    //         true
                    //     );
                        
                    //     Mail::to($user->email)->send(new AiVitalMesaReportMail($user, $uploadedFile));
                    // }
                    // elseif($request->lang == 'ar'){
                    //     // $pdf = PDF::loadView('pages.triage_report_ar', $data);
                    //     $report_link = url("/api/v1/user/report/{$isabel_report->id}");
                    //     Mail::to($user->email)->send(new ArAiVitalMesaReportMail($user, $report_link));

                    // }else{
                    //     $pdf = PDF::loadView('pages.triage_report', $data);
                    //     $filename = 'MIDASReport.pdf';

                    //     $tempPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $filename;

                    //     file_put_contents($tempPath, $pdf->output());

                    //     $uploadedFile = new UploadedFile(
                    //         $tempPath,     
                    //         $filename, 
                    //         'application/pdf',
                    //         null,
                    //         true
                    //     );
                        
                    //     Mail::to($user->email)->send(new AiVitalMesaReportMail($user, $uploadedFile));
                    // }                   

                }

                $differential_diagnoses = TouristRankedDifferentialDiagnoses::where('tourist_id',$request->tourist_id)->latest()->first();
                if(isset($differential_diagnoses))
                {
                    $differential_diagnoses->isabel_report_id = $isabel_report->id;
                    $differential_diagnoses->save();
                }

                $get_chat_count = TouristIsabelChatCount::where('tourist_id',$request->tourist_id)->first();
                if(isset($get_chat_count))
                {
                    $count = $get_chat_count->count_of_misa_chat;
                    $get_chat_count->count_of_misa_chat = $count + 1;
                    $get_chat_count->updated_at = now();
                    $get_chat_count->save();
                }
                else{
                    $chat_count = new TouristIsabelChatCount();
                    $chat_count->tourist_id = $request->tourist_id;
                    $chat_count->count_of_misa_chat = 1;
                    $chat_count->created_at = now();
                    $chat_count->updated_at = now();
                    $chat_count->save();
                }

                $tourist = TouristList::where('id',$request->tourist_id)->first();
                if ($tourist && $tourist->number_of_midas > 0) {
                    $tourist->decrement('number_of_midas', 1);
                }

                $gp = DoctorCategories::select('id')->where('title', 'General Practice (GP)')->where('is_deleted', 0)->first();

                return response()->json([
                    'status' => true,
                    'data'   => $response->json(),
                    'gp_id' => $gp->id ?? null,
                    'isabel_report_id' => $isabel_report->id,
                    'isabel_report_link' => $request->lang == 'ar' ? route('isabelTouristTriageReportArabic', $isabel_report->id) : route('isabelTouristTriageReport', $isabel_report->id)
                ]);
            }
            return response()->json([
                'error'      => 'Failed to fetch response',
                'status'     => false,
                'body'       => $response->body(),
            ], $response->status());
        }      

        
        return response()->json([
            'error'      => 'Failed to fetch response',
            'status'     => false,
            'body'       => $ranked_differential_diagnoses->body(),
        ], $ranked_differential_diagnoses->status());
    }

    public function isabelTriageReport($id, Request $request)
    {
        $triage_report = TouristIsabelReport::find($id);
        $request->merge(['tourist_id' => $triage_report->tourist_id]);

        $lang = $triage_report->lang ?? 'en';
        $request->merge(['lang' => $lang]);

        $response = $this->getIsabelInfoOptions($request);
        $data = $response->getData(true);

        if ($data['status'] == true) {
            $country_name = null;
            $region_name = null;
            $age = null;

            $countries = collect($data['countries']);
            $country = $countries->firstWhere('country_id', $triage_report->country);
            if ($country) {
                $country_name = $country['country_name'];
            }

            $regions = collect($data['regions']);
            $region = $regions->firstWhere('region_id', $triage_report->region);
            if ($region) {
                $region_name = $region['region_name'];
            }

            $age_groups = collect($data['age_groups']);
            $age_group = $age_groups->firstWhere('agegroup_id', $triage_report->age);
            if ($age_group) {
                $age = $age_group['name'] . ' ' . $age_group['yr_from'] . '-' . $age_group['yr_to'];
            }

            $tourist = TouristList::where('id',$triage_report->tourist_id)->first();
            $patient_name = $tourist->first_name . ' ' . $tourist->last_name;
            // $date_of_birth = $user->dob;
            $contact_details = $tourist->contact_number;

            $data = [
                'report' => $triage_report,
                'country_name' => $country_name,
                'region_name' => $region_name,
                'age' => $age,
                'patient_name' => $patient_name,
                'date_of_birth' => $date_of_birth ?? null,
                'contact_details' => $contact_details,
                'tourist' => $tourist,
                'scan_date' => $triage_report->created_at,
            ];

            $translationsPath = resource_path("lang/IsabelQuestionAnswerTranslation/{$lang}.php");
            $translations = file_exists($translationsPath) ? include $translationsPath : [];

            $questions = IsabelQuestion::all();
            if (!empty($questions) && isset($translations['questions'])) {
                $map = $translations['questions'];
                foreach ($questions as &$question) {
                    $map_id = (string)($question['id'] ?? '');
                    if (isset($map[$map_id])) {
                        $question->question = $map[$map_id];
                    }
                }
            }

            foreach ($questions as $key => $question) {
                $q_field = 'q' . $question->id;
                if (isset($triage_report->$q_field) && isset($translations['answers'])) {
                    $map = $translations['answers'];
                    $map_id = (string)($question['id'] ?? '');
                    if (isset($map[$map_id])) {
                        $question->answer = $map[$map_id][$triage_report->$q_field];
                    }
                }
            }

            $data['questions'] = $questions;
            $ranked_differential_diagnoses = TouristRankedDifferentialDiagnoses::where('isabel_report_id', $id)->latest()->first();
            $data['ranked_differential_diagnoses'] = json_decode($ranked_differential_diagnoses?->diagnoses, true);

            $filename = 'triage_report.pdf';
            // return view('pages.tourist_triage_report', $data);
            if ($lang == 'ar') {
                $reportHtml = view('pages.triage_report_ar', $data)->render();
                return view('pages.tourist_triage_report_ar', $data);

                $arabic = new Arabic();
                $p = $arabic->arIdentify($reportHtml);

                for ($i = count($p) - 1; $i >= 0; $i -= 2) {
                    $utf8ar = $arabic->utf8Glyphs(substr($reportHtml, $p[$i-1], $p[$i] - $p[$i-1]));
                    $reportHtml = substr_replace($reportHtml, $utf8ar, $p[$i-1], $p[$i] - $p[$i-1]);
                }

                $pdf = PDF::loadHTML($reportHtml);
            } elseif ($lang == 'fr') {
                $pdf = PDF::loadView('pages.tourist_triage_report_fr', $data);
            } else {
                $pdf = PDF::loadView('pages.tourist_triage_report', $data);
            }

            return response($pdf->output())
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'attachment; filename=' . $filename)
                ->header('Cache-Control', 'private, max-age=0, must-revalidate');
        } else {
            return response()->json(['status' => false, 'message' => 'No AI vital report found for this user.']);
        }
    }

    function markMissedAppointmentFromSheduler(Request $request)
    {
        $rules = [
            // 'doctor_id' => 'required',
            'appointment_id' => 'required',
            // 'completion_otp' => 'required',
            'diagnosed_with' => 'required',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }
        $doctor = null;
        if($request->has('doctor_id')){
            $doctor = Doctors::where('id', $request->doctor_id)->first();
        }
        // if ($doctor == null) {
        //     return GlobalFunction::sendSimpleResponse(false, 'Doctor does not exists!');
        // }
        $appointment = TouristAppointments::where('id', $request->appointment_id)->first();
        if ($appointment == null) {
            return GlobalFunction::sendSimpleResponse(false, 'Appointment does not exists!');
        }
        if ($appointment->doctor_id != $request->doctor_id) {
            return response()->json(['status' => false, 'message' => "Appointment is not owned by this doctor!"]);
        }
        // if ($appointment->completion_otp != $request->completion_otp) {
        //     return response()->json(['status' => false, 'message' => "Completion OTP is incorrect!"]);
        // }
        if ($appointment->status == Constants::orderAccepted) {
            $appointment->status = Constants::orderMissed;
            $appointment->diagnosed_with = $request->diagnosed_with;
            $appointment->save();    

            $earning = $doctor->consultation_fee;
            $settings = GlobalSettings::first();
            $commissionAmount = ($settings->comission / 100) * $earning;

            // Adding Earning statement
            $earningSummary = "Earning from Tourist appointment: " . $appointment->appointment_number;
            GlobalFunction::addDoctorStatementEntry($doctor->id, $appointment->appointment_number, $earning, Constants::credit, Constants::doctorWalletEarning, $earningSummary);

            // Adding Commission deduct statement
            $commissionSummary = "Commission of Tourist appointment: " . $appointment->appointment_number . " : (" . $settings->comission . "%)";
            GlobalFunction::addDoctorStatementEntry($doctor->id, $appointment->appointment_number, $commissionAmount, Constants::debit, Constants::doctorWalletCommission, $commissionSummary);

            // Adding earning to doctor wallet + count increase + lifetime earning increase
            $earningAfterCommission = $earning - $commissionAmount;
            $doctor->wallet = $doctor->wallet + $earningAfterCommission;
            $doctor->total_patients_cured = $doctor->total_patients_cured + 1;
            $doctor->lifetime_earnings = $doctor->lifetime_earnings + $earningAfterCommission;
            $doctor->save();

            // Adding Earning Logs Of Doctor
            $doctorEarningHistory = new DoctorEarningHistory();
            $doctorEarningHistory->doctor_id = $doctor->id;
            $doctorEarningHistory->appointment_id = $appointment->id;
            $doctorEarningHistory->earning_number = GlobalFunction::generateDoctorEarningHistoryNumber();
            $doctorEarningHistory->amount = $earningAfterCommission;
            $doctorEarningHistory->save();

            // Adding Earning Logs of Platform
            $platformEarningHistory = new PlatformEarningHistory();
            $platformEarningHistory->earning_number = GlobalFunction::generatePlatformEarningHistoryNumber();
            $platformEarningHistory->amount = $commissionAmount;
            $platformEarningHistory->commission_percentage = $settings->comission;
            $platformEarningHistory->appointment_id = $appointment->id;
            $platformEarningHistory->doctor_id = $doctor->id;
            $platformEarningHistory->save();

            // Increasing total platform earning data
            $platformData = PlatformData::first();
            $platformData->lifetime_earnings = $platformData->lifetime_earnings + $commissionAmount;
            $platformData->save();

            return GlobalFunction::sendSimpleResponse(true, 'Appointment completed successfully');
        } else {
            return response()->json(['status' => false, 'message' => "This booking can't be completed!"]);
        }
    }
}
