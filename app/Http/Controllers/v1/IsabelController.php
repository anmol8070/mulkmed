<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Mail;
use App\Models\IsabelQuestion;
use App\Models\IsabelAnswer;
use App\Models\IsabelUserChatCount;
use App\Models\IsabelReport;
use App\Models\DoctorCategories;
use App\Models\Users;
use App\Models\IsabelPredictiveText;
use App\Models\IsabelAgeGroup;
use App\Models\IsabelPregnancies;
use App\Models\IsabelRegions;
use App\Models\IsabelCountries;
use App\Models\AIVitalScanMisa;
use App\Models\Constants;
use App\Models\RankedDifferentialDiagnoses;
use PDF;
use Illuminate\Support\Facades\Lang;
use App\Support\IsabelI18n;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\UploadedFile;
use App\Models\GlobalFunction;
use App\Mail\AiVitalMesaReportMail;
use App\Mail\ArAiVitalMesaReportMail;
use ArPHP\I18N\Arabic;

class IsabelController extends Controller
{
    public function getIsabelOptions()
    {
        $authorizationKey = env('ISABEL_AUTHORIZATION_KEY');

        $supported = ['en','ar','fr','hi','ur'];
        $lang = request('lang', 'en');
        if (!in_array($lang, $supported, true)) {
            $lang = 'en';
        }

        // 1) load custom translations
        $translationsPath = resource_path("lang/IsabelOptionsTranslations/{$lang}.php");
        $translations = file_exists($translationsPath) ? include $translationsPath : [];

        // 2) force sandbox language to english (see Isabel docs)
        $apiLang = 'english';

        $call = function (string $url, array $path) use ($authorizationKey, $apiLang) {
            $resp = Http::retry(3, 250)
                ->acceptJson()
                ->withHeaders(['authorization' => $authorizationKey])
                ->get($url, ['language' => $apiLang, 'web_service' => 'json']);

            if (!$resp->ok()) return [];
            $data = $resp->json();
            foreach ($path as $p) {
                if (!is_array($data) || !isset($data[$p])) return [];
                $data = $data[$p];
            }
            return $data;
        };

        // === fetch raw (English) data from sandbox ===
        $age_groups  = $call('https://apiscsandbox.isabelhealthcare.com/v2/age_groups',  ['age_groups','age_group']);
        $regions     = $call('https://apiscsandbox.isabelhealthcare.com/v2/regions',     ['travel_history','region']);
        $countries   = $call('https://apiscsandbox.isabelhealthcare.com/v2/countries',   ['countries','country']);
        $pregnancies = $call('https://apiscsandbox.isabelhealthcare.com/v2/pregnancies', ['pregnancies','pregnancy']);

        // === overlay translations while preserving shape ===

        // A) age_groups -> translate `name` by id
        if (!empty($age_groups) && isset($translations['age_groups'])) {
            $map = $translations['age_groups']; // e.g. ['1' => 'नवजात शिशु', ...]
            foreach ($age_groups as &$g) {
                $id = (string)($g['agegroup_id'] ?? '');
                if (isset($map[$id])) {
                    $g['name'] = $map[$id];
                }
            }
            unset($g);
        }

        // B) pregnancies -> you already map; keep your logic and enhance with translations
        $pregnancyMap = ['0' => 'unknown', '1' => 'not_pregnant', '2' => 'pregnant'];
        if (!empty($pregnancies) && isset($translations['pregnancies'])) {
            foreach ($pregnancies as &$preg) {
                $id = (string)$preg['pregnancy_id'];
                if (isset($pregnancyMap[$id])) {
                    $key = $pregnancyMap[$id];
                    $preg['pregnancy_name'] = $translations['pregnancies'][$key] ?? $preg['pregnancy_name'];
                }
            }
            unset($preg);
        }

        // C) regions / countries -> optional: translate only the display `region_name`/`country_name` if you add maps
        if (!empty($regions) && isset($translations['regions'])) {
            foreach ($regions as &$r) {
                $id = (string)$r['region_id'];
                if (isset($translations['regions'][$id])) {
                    $r['region_name'] = $translations['regions'][$id];
                }
            }
            unset($r);
        }

        if (!empty($countries) && isset($translations['countries'])) {
            foreach ($countries as &$c) {
                $id = (string)$c['country_id'];
                if (isset($translations['countries'][$id])) {
                    $c['country_name'] = $translations['countries'][$id];
                }
            }
            unset($c);
        }

        // static fields from your custom translation files
        $sex = [
            ['sex_id' => 'f', 'sex_name' => $translations['sex']['female'] ?? 'Female'],
            ['sex_id' => 'm', 'sex_name' => $translations['sex']['male']   ?? 'Male'],
        ];

        return response()->json([
            'status'       => true,
            'locale'       => $lang,
            'age_groups'   => $age_groups,   // <= same shape, `name` translated
            'regions'      => $regions,
            'countries'    => $countries,
            'pregnancies'  => $pregnancies,
            'sex'          => $sex,
        ], 200, [], JSON_UNESCAPED_UNICODE); // ensure Arabic/Urdu/Hindi render correctly
    }

    public function storeIsabelInfoOptions()
    {
        $authorizationKey = env('ISABEL_AUTHORIZATION_KEY');

        $supported = ['en','ar','fr'];
        $lang = request('lang', 'en');
        if (!in_array($lang, $supported, true)) {
            $lang = 'en';
        }

        // 1) load custom translations
        $translationsPath = resource_path("lang/IsabelOptionsTranslations/{$lang}.php");
        $translations = file_exists($translationsPath) ? include $translationsPath : [];

        // 2) force sandbox language to english (see Isabel docs)
        $apiLang = $lang;

        $call = function (string $url, array $path) use ($authorizationKey, $apiLang) {
            $resp = Http::retry(3, 250)
                ->acceptJson()
                ->withHeaders(['authorization' => $authorizationKey])
                ->get($url, ['language' => $apiLang, 'web_service' => 'json']);

            if (!$resp->ok()) return [];
            $data = $resp->json();
            foreach ($path as $p) {
                if (!is_array($data) || !isset($data[$p])) return [];
                $data = $data[$p];
            }
            return $data;
        };

        // === fetch raw (English) data from sandbox ===
        $age_groups  = $call('https://apiscsandbox.isabelhealthcare.com/v3/age_groups',  ['age_groups','age_group']);
        $regions     = $call('https://apiscsandbox.isabelhealthcare.com/v3/regions',     ['travel_history','region']);
        $countries   = $call('https://apiscsandbox.isabelhealthcare.com/v3/countries',   ['countries','country']);
        $pregnancies = $call('https://apiscsandbox.isabelhealthcare.com/v3/pregnancies', ['pregnancies','pregnancy']);

        // DB::table('isabel_age_groups')->truncate();
        // foreach ($age_groups as $g) {
            // $age_group                  = new IsabelAgeGroup();
            // $age_group->agegroup_id     = $g['agegroup_id'];
            // $age_group->ordinal         = $g['ordinal'];
            // $age_group->name            = $g['name'];
            // $age_group->yr_from         = $g['yr_from'];
            // $age_group->yr_to           = $g['yr_to'];
            // $age_group->branch          = $g['branch'];
            // $age_group->can_conceive    = $g['can_conceive'];
            // $age_group->save();       
        // }  
        
        if($apiLang == "en")
        {
            DB::table('isabel_age_groups')->updateOrInsert(
                ['id' => 1], 
                ['english' => json_encode($age_groups)]
            );
        }   
        if($apiLang == "ar")
        {
            DB::table('isabel_age_groups')->updateOrInsert(
                ['id' => 1], 
                ['arabic' => json_encode($age_groups)]
            );
        }        
        if($apiLang == "fr")
        {
            DB::table('isabel_age_groups')->updateOrInsert(
                ['id' => 1], 
                ['french' => json_encode($age_groups)]
            );
        } 

        // DB::table('isabel_pregnancies')->truncate();
        // foreach ($pregnancies as $preg) {
        //     $pregnancies = new IsabelPregnancies();
        //     $pregnancies->pregnancy_id = $preg['pregnancy_id'];
        //     $pregnancies->pregnancy_name = $preg['pregnancy_name'];
        //     $pregnancies->save();
        // }

        if($apiLang == "en")
        {
            DB::table('isabel_pregnancies')->updateOrInsert(
                ['id' => 1], 
                ['english' => json_encode($pregnancies)]
            );
        }   
        if($apiLang == "ar")
        {
            DB::table('isabel_pregnancies')->updateOrInsert(
                ['id' => 1], 
                ['arabic' => json_encode($pregnancies)]
            );
        }        
        if($apiLang == "fr")
        {
            DB::table('isabel_pregnancies')->updateOrInsert(
                ['id' => 1], 
                ['french' => json_encode($pregnancies)]
            );
        }  

        // DB::table('isabel_regions')->truncate();
        // foreach ($regions as $region) {
        //     $reg = new IsabelRegions();
        //     $reg->region_id = $region['region_id'];
        //     $reg->region_name = $region['region_name'];
        //     $reg->save();
        // }

        if($apiLang == "en")
        {
            DB::table('isabel_regions')->updateOrInsert(
                ['id' => 1], 
                ['english' => json_encode($regions)]
            );
        }   
        if($apiLang == "ar")
        {
            DB::table('isabel_regions')->updateOrInsert(
                ['id' => 1], 
                ['arabic' => json_encode($regions)]
            );
        }        
        if($apiLang == "fr")
        {
            DB::table('isabel_regions')->updateOrInsert(
                ['id' => 1], 
                ['french' => json_encode($regions)]
            );
        } 

        // DB::table('isabel_countries')->truncate();
        // foreach ($countries as $country) {
        //     $count = new IsabelCountries();
        //     $count->country_id = $country['country_id'];
        //     $count->country_name = $country['country_name'];
        //     $count->abbreviation = $country['abbreviation'];
        //     $count->region_id = $country['region_id'];
        //     $count->save();
        // }

        if($apiLang == "en")
        {
            DB::table('isabel_countries')->updateOrInsert(
                ['id' => 1], 
                ['english' => json_encode($countries)]
            );
        }   
        if($apiLang == "ar")
        {
            DB::table('isabel_countries')->updateOrInsert(
                ['id' => 1], 
                ['arabic' => json_encode($countries)]
            );
        }        
        if($apiLang == "fr")
        {
            DB::table('isabel_countries')->updateOrInsert(
                ['id' => 1], 
                ['french' => json_encode($countries)]
            );
        } 

        return response()->json([
            'status'       => true,
            'message'       => "added successfully"
        ], 200, []); // ensure Arabic/Urdu/Hindi render correctly
    }

    public function getIsabelInfoOptions(Request $request)
    {
        
        $rules = [
            'user_id' => 'required',
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
        // $age_groups  = IsabelAgeGroup::select('agegroup_id','ordinal','name','yr_from','yr_to','branch','can_conceive')->get();
        // $regions     = IsabelRegions::select('region_id','region_name')->get();
        // $countries   = IsabelCountries::select('country_id','country_name','abbreviation','region_id')->get();
        // $pregnancies = isabelPregnancies::select('pregnancy_id','pregnancy_name')->get();

        // === overlay translations while preserving shape ===

        // A) age_groups -> translate `name` by id
        // if (!empty($age_groups) && isset($translations['age_groups'])) {
        //     $map = $translations['age_groups']; // e.g. ['1' => 'नवजात शिशु', ...]
        //     foreach ($age_groups as &$g) {
        //         $id = (string)($g['agegroup_id'] ?? '');
        //         if (isset($map[$id])) {
        //             $g['name'] = $map[$id];
        //         }
        //     }
        //     unset($g);
        // }

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

        // Normalize English pregnancy labels to consistent capitalization.
        if (!empty($pregnancies)) {
            foreach ($pregnancies as &$pregnancy) {
                if ($lang === 'en') {
                    if (is_object($pregnancy) && isset($pregnancy->pregnancy_id) && (string) $pregnancy->pregnancy_id === '0') {
                        $pregnancy->pregnancy_name = "Don't Know";
                    } elseif (is_array($pregnancy) && isset($pregnancy['pregnancy_id']) && (string) $pregnancy['pregnancy_id'] === '0') {
                        $pregnancy['pregnancy_name'] = "Don't Know";
                    } elseif (is_object($pregnancy) && isset($pregnancy->pregnancy_name)) {
                        $pregnancy->pregnancy_name = ucwords(strtolower((string) $pregnancy->pregnancy_name));
                    } elseif (is_array($pregnancy) && isset($pregnancy['pregnancy_name'])) {
                        $pregnancy['pregnancy_name'] = ucwords(strtolower((string) $pregnancy['pregnancy_name']));
                    }
                }
            }
            unset($pregnancy);
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

        // $mesa_ai_vital_report = AIVitalScanMisa::where('user_id',$request->user_id)->latest()->first();
        // $filtered = [];
        // if(isset($mesa_ai_vital_report))
        // {
        //     $reports = json_decode($mesa_ai_vital_report->report);

        //     $terms = [];

        //     if (isset($reports->heartRate)) {
        //         $hr = (float) $reports->heartRate;
        //         if ($hr < 60) {
        //             array_push($terms, "bradycardia");
        //         } elseif ($hr > 100) {
        //             array_push($terms, "tachycardia");
        //         }
        //     }

        //     if (isset($reports->temp)) {
        //         if ($reports->temp < 35) {
        //             array_push($terms, "hypothermia");
        //         } elseif ($reports->temp >= 39) {
        //             array_push($terms, "high fever");
        //         } elseif ($reports->temp >= 37.6) {
        //             array_push($terms, "fever");
        //         }
        //     }

        //     // Respiratory Rate (rr → respiratoryRate)
        //     if (isset($reports->respiratoryRate)) {
        //         $rr = (int) $reports->respiratoryRate;
        //         if ($rr < 12) {
        //             array_push($terms, "bradypnea");
        //         } elseif ($rr > 20) {
        //             array_push($terms, "tachypnea");
        //         }
        //     }

        //     // Blood Pressure (format: "124/78")
        //     if (!empty($reports->bloodPressure) && strpos($reports->bloodPressure, '/') !== false) {
        //         [$systolic, $diastolic] = explode('/', $reports->bloodPressure);
        //         $systolic = (int) $systolic;
        //         $diastolic = (int) $diastolic;

        //         if ($systolic < 90 || $diastolic < 60) {
        //             array_push($terms, "hypotension");
        //         } elseif ($systolic >= 140 || $diastolic >= 90) {
        //             array_push($terms, "hypertension");
        //         }
        //     }

        //     // BMI (Body Mass Index)
        //     if (!empty($reports->bmi)) {
        //         $bmi = (float) $reports->bmi;
        //         if ($bmi < 18.5) {
        //             array_push($terms, "underweight");
        //         } elseif ($bmi >= 25 && $bmi < 30) {
        //             array_push($terms, "overweight");
        //         } elseif ($bmi >= 30) {
        //             array_push($terms, "obesity");
        //         } else {
        //             array_push($terms, "normal BMI");
        //         }
        //     }

        //     // Oxygen Saturation (if available in future)
        //     if (isset($reports->spo2)) {
        //         $spo2 = (float) $reports->spo2;
        //         if ($spo2 < 90) {
        //             array_push($terms, "severe hypoxemia");
        //         } elseif ($spo2 < 95) {
        //             array_push($terms, "mild hypoxemia");
        //             $terms[] = "mild hypoxemia";
        //         }
        //     }

        //     $search = $request->search;

        //     $filtered = array_filter($terms, function($term) use ($search) {
        //         return stripos($term, $search) !== false; // case-insensitive LIKE
        //     });
        //     $filtered = array_values($filtered);
        // }
        
        $mesa_ai_vital_report = AIVitalScanMisa::where('user_id', $request->user_id)->where('payment_type', Constants::CCAvenueAIVitalScanPaymentType)->latest()->first();

        if (!$mesa_ai_vital_report) {
            $mesa_ai_vital_report = AIVitalScanMisa::where('user_id', $request->user_id)->latest()->first();
        }

        $reports = json_decode($mesa_ai_vital_report->report);
       
        // get terms from the report (reusable helper; defined below)
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
                        'pregnant' => $this->appPregnancyProvided($request)
                            ? $this->mapAppPregnancyToIsabel($this->resolveAppPregnancyInput($request))
                            : $request->input('pregnant', $request->input('pregnancy')),
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

    //new function
    public function isabelTriageReport($id, Request $request)
    {
        $triage_report = IsabelReport::find($id);
        $request->merge(['user_id' => $triage_report->user_id]);

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

            $user = Users::find($triage_report->user_id);
            $patient_name = $user->fullname;
            $date_of_birth = $user->dob;
            $contact_details = $user->phone_number;

            $data = [
                'report' => $triage_report,
                'country_name' => $country_name,
                'region_name' => $region_name,
                'age' => $age,
                'patient_name' => $patient_name,
                'date_of_birth' => $date_of_birth,
                'contact_details' => $contact_details,
                'user' => $user,
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
            $ranked_differential_diagnoses = RankedDifferentialDiagnoses::where('isabel_report_id', $id)->latest()->first();
            $data['ranked_differential_diagnoses'] = json_decode($ranked_differential_diagnoses?->diagnoses, true);

            $filename = 'triage_report.pdf';

            if ($lang == 'ar') {
                $reportHtml = view('pages.triage_report_ar', $data)->render();
                return view('pages.triage_report_ar', $data);

                $arabic = new Arabic();
                $p = $arabic->arIdentify($reportHtml);

                for ($i = count($p) - 1; $i >= 0; $i -= 2) {
                    $utf8ar = $arabic->utf8Glyphs(substr($reportHtml, $p[$i-1], $p[$i] - $p[$i-1]));
                    $reportHtml = substr_replace($reportHtml, $utf8ar, $p[$i-1], $p[$i] - $p[$i-1]);
                }

                $pdf = PDF::loadHTML($reportHtml);
            } elseif ($lang == 'fr') {
                $pdf = PDF::loadView('pages.triage_report_fr', $data);
            } else {
                $pdf = PDF::loadView('pages.triage_report', $data);
            }

            return response($pdf->output())
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'attachment; filename=' . $filename)
                ->header('Cache-Control', 'private, max-age=0, must-revalidate');
        } else {
            return response()->json(['status' => false, 'message' => 'No AI vital report found for this user.']);
        }
    }
    // function isabelTriageReport($id,Request $request)
    // {
    //     $triage_report = IsabelReport::find($id);
    //     $request->merge(['user_id' => $triage_report->user_id]);
    //     if($triage_report->lang != null){
    //         $request->merge(['lang' => $triage_report->lang]);
    //     }else{
    //         $request->merge(['lang' => 'en']);
    //     }
    //     // return $request;
    //     $response = $this->getIsabelInfoOptions($request);
    //     $data = $response->getData(true); // now it's an array
    //     if($data['status'] == true)
    //     {
    //         $country_name = null;
    //         $region_name = null;
    //         $region_name = null;
            
    //         $countries = collect($data['countries']);
    //         $country = $countries->firstWhere('country_id', $triage_report->country);

    //         if ($country) {
    //             $country_name = $country['country_name']; // e.g. India
    //         }

    //         $regions = collect($data['regions']);
    //         $region = $regions->firstWhere('region_id', $triage_report->region);

    //         if ($region) {
    //             $region_name = $region['region_name']; // e.g. India
    //         }

    //         $age_groups = collect($data['age_groups']);
    //         $age_group = $age_groups->firstWhere('agegroup_id', $triage_report->age);

    //         if ($age_group) {
    //             $age = $age_group['name'] . ' ' . $age_group['yr_from'] .'-'. $age_group['yr_to']; // e.g. India
    //         }

    //         $user = Users::find($triage_report->user_id);
    //         $patient_name = $user->fullname;
    //         $date_of_birth = $user->dob;
    //         $contact_details = $user->phone_number; 

    //         $data = [];
    //         $data['report'] = $triage_report;
    //         $data['country_name'] = $country_name;
    //         $data['region_name'] = $region_name;
    //         $data['age'] = $age;
    //         $data['patient_name'] = $patient_name;
    //         $data['date_of_birth'] = $date_of_birth;
    //         $data['contact_details'] = $contact_details;
    //         $data['user'] = $user;
    //         $data['scan_date'] = $triage_report->created_at;

    //         $translationsPath = resource_path("lang/IsabelQuestionAnswerTranslation/{$request->lang}.php");
    //         $translations = file_exists($translationsPath) ? include $translationsPath : [];

    //         $questions = IsabelQuestion::all();
    //         if(!empty($questions) && isset($translations['questions']))
    //         {
    //             $map = $translations['questions'];
    //             foreach ($questions as &$question) {
    //                 $map_id = (string)($question['id'] ?? '');
    //                 if (isset($map[$map_id])) {
    //                     $question->question = $map[$map_id];
    //                 }
    //             }
    //         }
    //         foreach ($questions as $key => $question) {
    //             if($question->id == 1)
    //             {   
    //                 $answer = IsabelAnswer::where('isabel_question_id',$question->id)->where('option_number',$triage_report->q1)->first();

    //                 if(isset($answer) && isset($translations['answers']))
    //                 {
    //                     $map = $translations['answers'];
    //                     $map_id = (string)($question['id'] ?? '');
    //                     if (isset($map[$map_id])) {
    //                         $question->answer = $map[$map_id][$triage_report->q1];
    //                     }
    //                 }                
    //             }
    //             if($question->id == 2)
    //             {
    //                 $answer = IsabelAnswer::where('isabel_question_id',$question->id)->where('option_number',$triage_report->q2)->first();
    //                 if(isset($answer) && isset($translations['answers']))
    //                 {
    //                     $map = $translations['answers'];
    //                     $map_id = (string)($question['id'] ?? '');
    //                     if (isset($map[$map_id])) {
    //                         $question->answer = $map[$map_id][$triage_report->q2];
    //                     }
    //                 }  
    //             }
    //             if($question->id == 3)
    //             {
    //                 $answer = IsabelAnswer::where('isabel_question_id',$question->id)->where('option_number',$triage_report->q3)->first();
    //                 if(isset($answer) && isset($translations['answers']))
    //                 {
    //                     $map = $translations['answers'];
    //                     $map_id = (string)($question['id'] ?? '');
    //                     if (isset($map[$map_id])) {
    //                         $question->answer = $map[$map_id][$triage_report->q3];
    //                     }
    //                 }  
    //             }
    //             if($question->id == 4)
    //             {
    //                 $answer = IsabelAnswer::where('isabel_question_id',$question->id)->where('option_number',$triage_report->q4)->first();
    //                 if(isset($answer) && isset($translations['answers']))
    //                 {
    //                     $map = $translations['answers'];
    //                     $map_id = (string)($question['id'] ?? '');
    //                     if (isset($map[$map_id])) {
    //                         $question->answer = $map[$map_id][$triage_report->q4];
    //                     }
    //                 }  
    //             }
    //             if($question->id == 5)
    //             {
    //                 $answer = IsabelAnswer::where('isabel_question_id',$question->id)->where('option_number',$triage_report->q5)->first();
    //                 if(isset($answer) && isset($translations['answers']))
    //                 {
    //                     $map = $translations['answers'];
    //                     $map_id = (string)($question['id'] ?? '');
    //                     if (isset($map[$map_id])) {
    //                         $question->answer = $map[$map_id][$triage_report->q5];
    //                     }
    //                 }  
    //             }
    //             if($question->id == 6)
    //             {
    //                 $answer = IsabelAnswer::where('isabel_question_id',$question->id)->where('option_number',$triage_report->q6)->first();
    //                 if(isset($answer) && isset($translations['answers']))
    //                 {
    //                     $map = $translations['answers'];
    //                     $map_id = (string)($question['id'] ?? '');
    //                     if (isset($map[$map_id])) {
    //                         $question->answer = $map[$map_id][$triage_report->q6];
    //                     }
    //                 }  
    //             }
    //             if($question->id == 7)
    //             {
    //                 $answer = IsabelAnswer::where('isabel_question_id',$question->id)->where('option_number',$triage_report->q7)->first();
    //                 if(isset($answer) && isset($translations['answers']))
    //                 {
    //                     $map = $translations['answers'];
    //                     $map_id = (string)($question['id'] ?? '');
    //                     if (isset($map[$map_id])) {
    //                         $question->answer = $map[$map_id][$triage_report->q7];
    //                     }
    //                 }  
    //             }
    //         }
    //         $data['questions'] = $questions;
    //         $ranked_differential_diagnoses = RankedDifferentialDiagnoses::where('isabel_report_id',$id)->latest()->first();
    //         $data['ranked_differential_diagnoses'] = json_decode($ranked_differential_diagnoses?->diagnoses,true);
    //         // return $data;
    //         // return view('pages.triage_report_ar', $data);  
            
    //         $filename = 'triage_report.pdf';

    //         if($request->lang == 'fr'){
    //             $pdf = PDF::loadView('pages.triage_report_fr', $data);
    //         }
    //         elseif($request->lang == 'ar'){
    //             $pdf = PDF::loadView('pages.triage_report_ar', $data);
                
    //         }else{
    //             $pdf = PDF::loadView('pages.triage_report', $data);
    //         }
    //         return response($pdf->output())
    //             ->header('Content-Type', 'application/pdf')
    //             ->header('Content-Disposition', 'attachment; filename=' . $filename)
    //             ->header('Cache-Control', 'private, max-age=0, must-revalidate'); 
    //     }
    //     else{
    //         return response()->json(['status' => false, 'message' => 'No AI vital report found for this user.']);
    //     }
            
    // }

    function isabelPredictiveText(Request $request)
    {
        $authorizationKey = env('ISABEL_AUTHORIZATION_KEY');
        
        $supported = ['en','ar','fr'];
        $lang = request('lang', 'en');
        if (!in_array($lang, $supported, true)) {
            $lang = 'en';
        }
        $url = "https://apiscsandbox.isabelhealthcare.com/v3/predictive-text";

        $response = Http::retry(3, 250)
                ->acceptJson()
                ->withHeaders(['authorization' => $authorizationKey])
                ->get($url, [
                        'language' => $lang,
                        'web_service' => 'json',
                    ]);

        if ($response->successful()) {
            // DB::table('isabel_predictive_text')->truncate();
            $data = $response->json();
            if($lang == "en")
            {
                DB::table('isabel_predictive_text')->updateOrInsert(
                    ['id' => 1], 
                    ['english' => json_encode($data)]
                );
            }   
            if($lang == "ar")
            {
                DB::table('isabel_predictive_text')->updateOrInsert(
                    ['id' => 1], 
                    ['arabic' => json_encode($data)]
                );
            }        
            if($lang == "fr")
            {
                DB::table('isabel_predictive_text')->updateOrInsert(
                    ['id' => 1], 
                    ['french' => json_encode($data)]
                );
            }
            
            return response()->json([
                'message' => 'predictive text added successfully',
                'status' => $response->status(),
            ], $response->status());
        }

        return response()->json([
            'error' => 'Failed to fetch predictive text',
            'status' => $response->status(),
        ], $response->status());
    }

    function ranked_differential_diagnoses(Request $request)
    {
        $rules = [
            'user_id' => 'required',
            'age' => 'required',
            'sex' => 'required',
            'region' => 'nullable',
            'country' => 'required_without:country_id',
            'country_id' => 'required_without:country',
            'text' => 'required',
            'pregnancy' => 'nullable',
            'pregnant' => 'nullable',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }

        $age = $request->age;
        $sex = $request->sex;
        $countryId = $request->country_id ?? $request->country;
        $region = $this->resolveRegionIdForCountryId($countryId) ?? $request->region;
        $text = $request->text;
        // App contract: 0 = (empty), 1 = Don't know, 2 = Not pregnant, 3 = Pregnant
        $pregnancyProvided = $this->appPregnancyProvided($request);
        $pregnancy = $pregnancyProvided ? $this->resolveAppPregnancyInput($request) : null;

        $authorizationKey = env('ISABEL_AUTHORIZATION_KEY');
        $lang = $this->normalizeIsabelLocale($request->language ?? $request->lang);

        $rankedPayload = [
                            'age' => $age,
                            'specialities' => 28,
                            'dob'=> $age,
                            'sex' => $sex,
                            'querytext' => $text,
            'country_id' => $countryId,
                            'suggest'=> 'Suggest+Differential+Diagnosis',
                            'flag'=> 'sortbyRW_advanced',
                            'searchType'=> 0,
                            'language'    => $lang,
                            'web_service' => 'json',
        ];

        if (!is_null($region) && $region !== '') {
            $rankedPayload['region'] = $region;
        }

        if ($pregnancyProvided) {
            $isabelPregnancy = $this->mapAppPregnancyToIsabel($pregnancy);
            if ($isabelPregnancy !== null) {
                $rankedPayload['pregnant'] = $isabelPregnancy;
            }
        }

        $ranked_differential_diagnoses = Http::withHeaders([
                            'authorization' => $authorizationKey,
                        ])->get('https://apiscsandbox.isabelhealthcare.com/v3/ranked_differential_diagnoses', $rankedPayload);

        if ($ranked_differential_diagnoses->ok()) {
            $ranked_differential_diagnoses = $ranked_differential_diagnoses->json();
            if(isset($ranked_differential_diagnoses['diagnoses_checklist']['no_result'])){
                return response()->json([
                    'error'      => $ranked_differential_diagnoses['diagnoses_checklist']['no_result']['information'],
                    'status'     => false,
                ]);
            }

            $this->applyIsabelRankedDiagnosesQueryResultDetails(
                $ranked_differential_diagnoses,
                $pregnancyProvided ? ($pregnancy ?? '0') : '0',
                $countryId,
                $lang,
                $pregnancyProvided
            );

            $diagnoses = $ranked_differential_diagnoses['diagnoses_checklist']['diagnoses'];
            $differential_diagnoses = new RankedDifferentialDiagnoses();
            $differential_diagnoses->user_id = $request->user_id;
            $differential_diagnoses->diagnoses = json_encode($ranked_differential_diagnoses['diagnoses_checklist']['diagnoses']);
            $differential_diagnoses->save();
            return response()->json([
                    'ranked_differential_diagnoses' => $ranked_differential_diagnoses,
                    'status'     => true,
                ]);
        }

        return response()->json([
            'status' => false,
            'message' => 'Failed to fetch ranked differential diagnoses',
            'isabel_status_code' => $ranked_differential_diagnoses->status(),
            'isabel_response' => $ranked_differential_diagnoses->body(),
        ], $ranked_differential_diagnoses->status() ?: 500);
    }

    // function getPredictiveText(Request $request)
    // {
    //     $rules = [
    //         'user_id' => 'required',
    //     ];

    //     $validator = Validator::make($request->all(), $rules);
    //     if ($validator->fails()) {
    //         $messages = $validator->errors()->all();
    //         $msg = $messages[0];
    //         return response()->json(['status' => false, 'message' => $msg]);
    //     }

    //     $mesa_ai_vital_report = AIVitalScanMisa::where('user_id',$request->user_id)->latest()->first();
    //     $reports = json_decode($mesa_ai_vital_report->report);

    //     $terms = [];

    //     if (isset($reports->heartRate)) {
    //         $hr = (float) $reports->heartRate;
    //         if ($hr < 60) {
    //             array_push($terms, "bradycardia");
    //         } elseif ($hr > 100) {
    //             array_push($terms, "tachycardia");
    //         }
    //     }

    //     if (isset($reports->temp)) {
    //         if ($reports->temp < 35) {
    //             array_push($terms, "hypothermia");
    //         } elseif ($reports->temp >= 39) {
    //             array_push($terms, "high fever");
    //         } elseif ($reports->temp >= 37.6) {
    //             array_push($terms, "fever");
    //         }
    //     }

    //     // Respiratory Rate (rr → respiratoryRate)
    //     if (isset($reports->respiratoryRate)) {
    //         $rr = (int) $reports->respiratoryRate;
    //         if ($rr < 12) {
    //             array_push($terms, "bradypnea");
    //         } elseif ($rr > 20) {
    //             array_push($terms, "tachypnea");
    //         }
    //     }

    //     // Blood Pressure (format: "124/78")
    //     if (!empty($reports->bloodPressure) && strpos($reports->bloodPressure, '/') !== false) {
    //         [$systolic, $diastolic] = explode('/', $reports->bloodPressure);
    //         $systolic = (int) $systolic;
    //         $diastolic = (int) $diastolic;

    //         if ($systolic < 90 || $diastolic < 60) {
    //             array_push($terms, "hypotension");
    //         } elseif ($systolic >= 140 || $diastolic >= 90) {
    //             array_push($terms, "hypertension");
    //         }
    //     }

    //     // BMI (Body Mass Index)
    //     if (!empty($reports->bmi)) {
    //         $bmi = (float) $reports->bmi;
    //         if ($bmi < 18.5) {
    //             array_push($terms, "underweight");
    //         } elseif ($bmi >= 25 && $bmi < 30) {
    //             array_push($terms, "overweight");
    //         } elseif ($bmi >= 30) {
    //             array_push($terms, "obesity");
    //         } else {
    //             array_push($terms, "normal BMI");
    //         }
    //     }

    //     // Oxygen Saturation (if available in future)
    //     if (isset($reports->spo2)) {
    //         $spo2 = (float) $reports->spo2;
    //         if ($spo2 < 90) {
    //             array_push($terms, "severe hypoxemia");
    //         } elseif ($spo2 < 95) {
    //             array_push($terms, "mild hypoxemia");
    //             $terms[] = "mild hypoxemia";
    //         }
    //     }

    //     $search = $request->search;

    //     $filtered = array_filter($terms, function($term) use ($search) {
    //         return stripos($term, $search) !== false; // case-insensitive LIKE
    //     });

    //     $filtered = array_values($filtered); 
    //     $symptoms = [];
    //     foreach ($filtered as $term) {
    //         $symptoms[] = ["text" => $term];
    //     }

    //     if($request->has('search'))
    //     {
    //         $predictive_text = isabelPredictiveText::select('text')->where('text', 'like', '%' . $request->search . '%')->get();
    //     }else{
    //         $predictive_text = isabelPredictiveText::select('text')->get();
    //     }

    //     foreach ($predictive_text as $value) {
    //         $symptoms[] = $value;
    //     }

    //     // return $symptoms;
    //     return response()->json([
    //                 'status' => true,
    //                 'predictive_text'   => $symptoms,
    //                 'ai_vital_symptoms' => $filtered
    //             ]);
    // }
    public function getPredictiveText(Request $request)
    {
        $rules = ['user_id' => 'required'];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json(['status' => false, 'message' => $validator->errors()->first()]);
        }

        $supported = ['en','ar','fr'];
        $lang = request('lang', 'en');
        if (!in_array($lang, $supported, true)) {
            $lang = 'en';
        }

        $mesa_ai_vital_report = AIVitalScanMisa::where('user_id', $request->user_id)->where('payment_type', Constants::CCAvenueAIVitalScanPaymentType)->latest()->first();

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

// private function extractAIVitalTerms($reports): array
// {
//     $terms = [];

//     // ---------- helpers ----------
//     $isNumeric = fn($v) => is_numeric($v) || (is_string($v) && preg_match('/^-?\d+(\.\d+)?$/', $v));
//     $toFloat = fn($v) => $isNumeric($v) ? (float)$v : null;

//     // CAPITALIZE helper: make each word start with uppercase
//     $capitalize = fn($s) => ucwords(strtolower(trim((string)$s)));

//     $prettyKey = function ($keyPath) use ($capitalize) {
//         $parts = explode('.', $keyPath);
//         $k = end($parts);
//         $k = preg_replace('/([a-z0-9])([A-Z])/', '$1 $2', $k);
//         $k = str_replace(['_', '-'], ' ', $k);
//         return $capitalize($k); // <-- now returns "Heart Rate" instead of "heart rate"
//     };

//     $riskBucket = function (?float $p) {
//         if ($p === null) return null;
//         if ($p <= 0.05) return 'very low';
//         if ($p <= 0.1)  return 'low';
//         if ($p <= 0.2)  return 'moderate';
//         if ($p <= 0.4)  return 'high';
//         return 'very high';
//     };

//     $relativeBuckets = function ($value, $min, $max) {
//         if ($value === null) return null;
//         if ($min === $max) return 'moderate';
//         $pct = ($value - $min) / ($max - $min);
//         if ($pct <= 0.33) return 'low';
//         if ($pct <= 0.66) return 'moderate';
//         return 'high';
//     };

//     $domainBucket = function ($key, $value) use ($toFloat, $riskBucket) {
//         if ($value === null) return null;
//         $v = $toFloat($value);
//         if ($v === null) return null;
//         $k = strtolower($key);

//         if ($k === 'heartrate') {
//             if ($v < 60) return 'low';
//             if ($v > 100) return 'high';
//             return 'normal';
//         }

//         if ($k === 'respiratoryrate') {
//             if ($v < 12) return 'low';
//             if ($v > 20) return 'high';
//             return 'normal';
//         }

//         if (stripos($k, 'sdnn') !== false) {
//             if ($v < 20) return 'low';
//             if ($v <= 50) return 'moderate';
//             return 'high';
//         }

//         if (stripos($k, 'rmssd') !== false) {
//             if ($v < 20) return 'low';
//             if ($v <= 50) return 'moderate';
//             return 'high';
//         }

//         if (stripos($k, 'stress') !== false) {
//             if ($v < 3) return 'low';
//             if ($v <= 7) return 'moderate';
//             return 'high';
//         }

//         if (stripos($k, 'bmi') !== false) {
//             if ($v < 18.5) return 'low';
//             if ($v < 25) return 'normal';
//             return 'high';
//         }

//         if (stripos($k, 'bodyfat') !== false) {
//             if ($v < 18) return 'low';
//             if ($v < 25) return 'moderate';
//             return 'high';
//         }

//         if (stripos($k, 'waisttoheight') !== false) {
//             if ($v < 0.4) return 'low';
//             if ($v < 0.5) return 'moderate';
//             return 'high';
//         }

//         if (stripos($k, 'cardiac') !== false) {
//             if ($v < 100) return 'low';
//             if ($v <= 200) return 'moderate';
//             return 'high';
//         }

//         if ($v >= 0 && $v <= 1) {
//             return $riskBucket($v);
//         }

//         return null;
//     };

//     // collect numeric range
//     $numericCandidates = [];
//     $walkCollect = function ($obj, $prefix = '') use (&$walkCollect, &$numericCandidates, $isNumeric) {
//         $arr = is_object($obj) ? get_object_vars($obj) : (array)$obj;
//         foreach ($arr as $k => $v) {
//             if ($v === null) continue;
//             $fullKey = $prefix === '' ? $k : "{$prefix}.{$k}";
//             if (is_object($v) || is_array($v)) {
//                 $walkCollect($v, $fullKey);
//             } elseif ($isNumeric($v)) {
//                 $numericCandidates[] = ['key' => $fullKey, 'value' => (float)$v];
//             }
//         }
//     };
//     $walkCollect($reports);

//     $minVal = $maxVal = null;
//     if (!empty($numericCandidates)) {
//         $values = array_column($numericCandidates, 'value');
//         $minVal = min($values);
//         $maxVal = max($values);
//     }

//     // interpret
//     $walkInterpret = function ($obj, $prefix = '') use (
//         &$walkInterpret, &$terms, $isNumeric, $toFloat, $domainBucket, $riskBucket,
//         $relativeBuckets, $minVal, $maxVal, $prettyKey, $capitalize
//     ) {
//         $arr = is_object($obj) ? get_object_vars($obj) : (array)$obj;

//         foreach ($arr as $k => $v) {
//             if ($v === null) continue;
//             $fullKey = $prefix === '' ? $k : "{$prefix}.{$k}";
//             $label = $prettyKey($fullKey);

//             // blood pressure
//             if ($k === 'bloodPressure' && is_string($v) && strpos($v, '/') !== false) {
//                 [$sysRaw, $diaRaw] = explode('/', $v) + [null, null];
//                 $sys = is_numeric($sysRaw) ? (int)$sysRaw : null;
//                 $dia = is_numeric($diaRaw) ? (int)$diaRaw : null;
//                 if ($sys !== null && $dia !== null) {
//                     if ($sys < 90 || $dia < 60) $terms[] = $capitalize("low blood pressure");
//                     elseif ($sys > 140 || $dia > 90) $terms[] = $capitalize("high blood pressure");
//                     // ❌ skip normal blood pressure
//                 }
//                 continue;
//             }

//             if (is_object($v) || is_array($v)) {
//                 $walkInterpret($v, $fullKey);
//                 continue;
//             }

//             if (is_string($v) && preg_match('/^(low|moderate|high|very low|very high|normal|overweight|obese)$/i', trim($v))) {
//                 $status = strtolower(trim($v));
//                 if ($status !== 'normal') { // ✅ only abnormal
//                     $terms[] = "{$label} " . $capitalize($status);
//                 }
//                 continue;
//             }

//             if ($isNumeric($v)) {
//                 $vFloat = $toFloat($v);
//                 $domain = $domainBucket($k, $vFloat);
//                 if ($domain !== null && $domain !== 'normal') { // ✅ skip normal
//                     $terms[] = "{$label} " . $capitalize($domain);
//                     continue;
//                 }

//                 if (stripos($k, 'risk') !== false && $vFloat >= 0 && $vFloat <= 1) {
//                     $risk = $riskBucket($vFloat);
//                     if ($risk !== 'very low' && $risk !== 'low') { // ✅ keep only moderate or above
//                         $terms[] = "{$label} " . $capitalize($risk);
//                     }
//                     continue;
//                 }

//                 if (stripos($k, 'score') !== false) {
//                     if ($vFloat > 0) { // ✅ only moderate or high
//                         if ($vFloat <= 5) $terms[] = "{$label} Moderate";
//                         else $terms[] = "{$label} High";
//                     }
//                     continue;
//                 }

//                 if ($minVal !== null && $maxVal !== null) {
//                     $bucket = $relativeBuckets($vFloat, $minVal, $maxVal);
//                     if ($bucket !== 'moderate') { // ✅ avoid neutral values
//                         $terms[] = "{$label} " . $capitalize($bucket);
//                     }
//                 }
//             }
//         }
//     };

//     $walkInterpret($reports);

//     return array_values(array_unique(array_filter(array_map('trim', $terms))));
// }

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

    private function resolveRegionIdForCountryId($countryId): ?string
    {
        if ($countryId === null || $countryId === '') {
            return null;
        }

        // `isabel_countries` stores the full countries list as JSON in language columns.
        $row = IsabelCountries::select(['english', 'arabic', 'french'])->first();
        if (!$row) {
            return null;
        }

        $json = $row->english ?: ($row->arabic ?: $row->french);
        if (!$json) {
            return null;
        }

        $countries = json_decode($json, true);
        if (!is_array($countries)) {
            return null;
        }

        $needle = (string) $countryId;
        foreach ($countries as $c) {
            if (!is_array($c)) continue;
            if (!isset($c['country_id'])) continue;
            if ((string) $c['country_id'] !== $needle) continue;

            $regionId = $c['region_id'] ?? null;
            return ($regionId === null || $regionId === '') ? null : (string) $regionId;
        }

        return null;
    }

    private function normalizeIsabelLocale(?string $languageOrLang): string
    {
        $supported = ['en', 'ar', 'fr'];
        $lang = strtolower((string) ($languageOrLang ?? 'en'));
        if (!in_array($lang, $supported, true)) {
            return 'en';
        }

        return $lang;
        }

    private function resolveCountryNameById($countryId, string $lang = 'en'): ?string
    {
        if ($countryId === null || $countryId === '') {
            return null;
        }

        $lang = $this->normalizeIsabelLocale($lang);
        $column = match ($lang) {
            'fr' => 'french',
            'ar' => 'arabic',
            default => 'english',
        };

        $row = IsabelCountries::select(['english', 'arabic', 'french'])->first();
        if (!$row) {
            return null;
        }

        $json = $row->{$column} ?: ($row->english ?: ($row->arabic ?: $row->french));
        if (!$json) {
            return null;
        }

        $countries = json_decode($json, true);
        if (!is_array($countries)) {
            return null;
        }

        $needle = (string) $countryId;
        foreach ($countries as $c) {
            if (!is_array($c) || !isset($c['country_id'])) continue;
            if ((string) $c['country_id'] !== $needle) continue;
            return isset($c['country_name']) ? (string) $c['country_name'] : null;
        }

        return null;
    }

    private function appPregnancyProvided(Request $request): bool
    {
        return $request->exists('pregnant') || $request->exists('pregnancy');
    }

    private function resolveAppPregnancyInput(Request $request): string
    {
        return $this->normalizeAppPregnancy(
            $request->input('pregnant', $request->input('pregnancy'))
        );
    }

    private function normalizeAppPregnancy(mixed $pregnancy): string
    {
        $pregnancy = (string) $pregnancy;

        return in_array($pregnancy, ['0', '1', '2', '3'], true) ? $pregnancy : '0';
    }

    private function mapAppPregnancyToIsabel(string $pregnancy): ?string
    {
        return match ($pregnancy) {
            '1' => '0',
            '2' => '1',
            '3' => '2',
            default => null,
        };
    }

    private function getPregnancyLabel(string $pregnancy, string $lang = 'en'): string
    {
        if ($pregnancy === '0') {
            return '';
        }

        $lang = $this->normalizeIsabelLocale($lang);
        $translationsPath = resource_path("lang/IsabelOptionsTranslations/{$lang}.php");
        $translations = file_exists($translationsPath) ? include $translationsPath : [];
        $pregnancies = $translations['pregnancies'] ?? [];

        // App contract keys: 1 = Don't know, 2 = Not pregnant, 3 = Pregnant
        if (isset($pregnancies[$pregnancy])) {
            return (string) $pregnancies[$pregnancy];
        }

        $isabelId = $this->mapAppPregnancyToIsabel($pregnancy);
        if ($isabelId !== null && isset($pregnancies[$isabelId])) {
            return (string) $pregnancies[$isabelId];
        }

        $enKeyByIsabelId = ['0' => 'unknown', '1' => 'not_pregnant', '2' => 'pregnant'];
        $enKey = $isabelId !== null ? ($enKeyByIsabelId[$isabelId] ?? null) : null;
        if ($enKey && isset($pregnancies[$enKey])) {
            return (string) $pregnancies[$enKey];
        }

        return match ($pregnancy) {
            '1' => "Don't know",
            '2' => 'Not pregnant',
            '3' => 'Pregnant',
            default => '',
        };
    }

    private function applyIsabelRankedDiagnosesQueryResultDetails(
        array &$rankedDiagnoses,
        string $pregnancy,
        $countryId,
        string $lang = 'en',
        bool $pregnancyProvided = true
    ): void {
        if (!isset($rankedDiagnoses['diagnoses_checklist']['query_result_details'])) {
            return;
        }

        $rankedDiagnoses['diagnoses_checklist']['query_result_details']['pregnant'] =
            $pregnancyProvided ? $this->getPregnancyLabel($pregnancy, $lang) : '';
        unset($rankedDiagnoses['diagnoses_checklist']['query_result_details']['pregnancy']);
        $rankedDiagnoses['diagnoses_checklist']['query_result_details']['country'] =
            $this->resolveCountryNameById($countryId, $lang) ?? (string) $countryId;
    }

    function submit_answers(Request $request)
    {

        $rules = [
            'user_id' => 'required',
            'age' => 'required',
            'sex' => 'required',
            'region' => 'nullable',
            'country' => 'required_without:country_id',
            'country_id' => 'required_without:country',
            'text' => 'required',
            'pregnancy' => 'required_without:pregnant|nullable',
            'pregnant' => 'required_without:pregnancy|nullable',
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
        $countryId = $request->country_id ?? $request->country;
        $region = $this->resolveRegionIdForCountryId($countryId) ?? $request->region;
        $text = $request->text;
        // App contract: 0 = (empty), 1 = Don't know, 2 = Not pregnant, 3 = Pregnant
        $pregnancy = $this->resolveAppPregnancyInput($request);
        $Q1 = $request->Q1;
        $Q2 = $request->Q2;
        $Q3 = $request->Q3;
        $Q4 = $request->Q4;
        $Q5 = $request->Q5;
        $Q6 = $request->Q6;
        $Q7 = $request->Q7;

        $authorizationKey = env('ISABEL_AUTHORIZATION_KEY');

        $rankedPayload = [
                            'age' => $age,
                            'specialities' => 28,
                            'dob'=> $age,
                            'sex' => $sex,
                            'querytext' => $text,
            'country_id' => $countryId,
                            'suggest'=> 'Suggest+Differential+Diagnosis',
                            'flag'=> 'sortbyRW_advanced',
                            'searchType'=> 0,
                            'language'    => $lang,
                            'web_service' => 'json',
        ];

        if (!is_null($region) && $region !== '') {
            $rankedPayload['region'] = $region;
        }

        $isabelPregnancy = $this->mapAppPregnancyToIsabel($pregnancy);
        if ($isabelPregnancy !== null) {
            $rankedPayload['pregnant'] = $isabelPregnancy;
        }

        $ranked_differential_diagnoses = Http::withHeaders([
                            'authorization' => $authorizationKey,
                        ])->get('https://apiscsandbox.isabelhealthcare.com/v3/ranked_differential_diagnoses', $rankedPayload);

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

                $isabel_report = new IsabelReport();
                $isabel_report->user_id = $request->user_id;
                $isabel_report->age = $request->age;
                $isabel_report->sex = $request->sex;
                $isabel_report->region = $region;
                $isabel_report->country = $countryId;
                $isabel_report->text = $request->text;
                $isabel_report->pregnancy = $pregnancy;
                $isabel_report->Q1 = $request->Q1;
                $isabel_report->Q2 = $request->Q2;
                $isabel_report->Q3 = $request->Q3;
                $isabel_report->Q4 = $request->Q4;
                $isabel_report->Q5 = $request->Q5;
                $isabel_report->Q6 = $request->Q6;
                $isabel_report->Q7 = $request->Q7;
                $isabel_report->report_from = $request->report_from;
                $isabel_report->lang = $lang;
                $isabel_report->order_id = isset($request->order_id) ? $request->order_id : null;
                $mesa_ai_vital_report = AIVitalScanMisa::where('user_id', $request->user_id)->where('order_id', $request->order_id)->first();

                if(isset($mesa_ai_vital_report))
                {

                    $data = [];
                    $user = Users::where('id',$mesa_ai_vital_report->user_id)->first();
                    $data['user'] = $user; 
                    $data['scan_date'] = $mesa_ai_vital_report->created_at; 
                    $data['report'] = json_decode($mesa_ai_vital_report->report); 
                    // return $data;
                    $filename = "aiVitalMIDAS_Report.pdf";
                    // return view('pages.vitalScanReport', $data);
                    $pdf = PDF::loadView('pages.vitalScanReport', $data)
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

                if(isset($mesa_ai_vital_report))
                {
                    $isabel_report->payment_type = $mesa_ai_vital_report->payment_type;
                }else{
                    $isabel_report->payment_type = null;
                }
                $isabel_report->score = data_get($response->json(), 'where_to_now.triage_score', 0);
                $isabel_report->save();


                $triage_report = IsabelReport::find($isabel_report->id);
                $request->merge(['user_id' => $triage_report->user_id]);
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

                    $user = Users::find($triage_report->user_id);
                    $patient_name = $user->fullname;
                    $date_of_birth = $user->dob;
                    $contact_details = $user->phone_number; 

                    $data = [];
                    $data['report'] = $triage_report;
                    $data['country_name'] = $country_name;
                    $data['region_name'] = $region_name;
                    $data['age'] = $age;
                    $data['patient_name'] = $patient_name;
                    $data['date_of_birth'] = $date_of_birth;
                    $data['contact_details'] = $contact_details;
                    $data['user'] = $user;
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
                    $ranked_differential_diagnoses = RankedDifferentialDiagnoses::where('user_id',$request->user_id)->latest()->first();
                    $data['ranked_differential_diagnoses'] = json_decode($ranked_differential_diagnoses?->diagnoses,true);                    

                    if($request->lang == 'fr'){
                        $pdf = PDF::loadView('pages.triage_report_fr', $data);
                        $filename = 'MIDASReport.pdf';

                        $tempPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $filename;

                        file_put_contents($tempPath, $pdf->output());

                        $uploadedFile = new UploadedFile(
                            $tempPath,     
                            $filename, 
                            'application/pdf',
                            null,
                            true
                        );
                        
                        Mail::to($user->email)->send(new AiVitalMesaReportMail($user, $uploadedFile));
                    }
                    elseif($request->lang == 'ar'){
                        // $pdf = PDF::loadView('pages.triage_report_ar', $data);
                        $report_link = url("/api/v1/user/report/{$isabel_report->id}");
                        Mail::to($user->email)->send(new ArAiVitalMesaReportMail($user, $report_link));

                    }else{
                        $pdf = PDF::loadView('pages.triage_report', $data);
                        $filename = 'MIDASReport.pdf';

                        $tempPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $filename;

                        file_put_contents($tempPath, $pdf->output());

                        $uploadedFile = new UploadedFile(
                            $tempPath,     
                            $filename, 
                            'application/pdf',
                            null,
                            true
                        );
                        
                        Mail::to($user->email)->send(new AiVitalMesaReportMail($user, $uploadedFile));
                    }

                    

                }


                $differential_diagnoses = RankedDifferentialDiagnoses::where('user_id',$request->user_id)->latest()->first();
                if(isset($differential_diagnoses))
                {
                    $differential_diagnoses->isabel_report_id = $isabel_report->id;
                    $differential_diagnoses->save();
                }

                $get_chat_count = IsabelUserChatCount::where('user_id',$request->user_id)->first();
                if(isset($get_chat_count))
                {
                    $count = $get_chat_count->count_of_misa_chat;
                    $get_chat_count->count_of_misa_chat = $count + 1;
                    $get_chat_count->updated_at = now();
                    $get_chat_count->save();
                }
                else{
                    $chat_count = new IsabelUserChatCount();
                    $chat_count->user_id = $request->user_id;
                    $chat_count->count_of_misa_chat = 1;
                    $chat_count->created_at = now();
                    $chat_count->updated_at = now();
                    $chat_count->save();
                }

                $gp = DoctorCategories::select('id')->where('title', 'General Practice (GP)')->where('is_deleted', 0)->first();

                return response()->json([
                    'status' => true,
                    'data'   => $response->json(),
                    'gp_id' => $gp->id ?? null,
                    'isabel_report_id' => $isabel_report->id,
                    'isabel_report_link' => $request->lang == 'ar' ? route('isabelTriageReportArabic', $isabel_report->id) : route('isabelTriageReport', $isabel_report->id)
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
}
