<?php

namespace App\Http\Controllers\v2;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Mail;
use App\Models\IsabelQuestion;
use App\Models\IsabelUserChatCount;
use App\Models\DoctorCategories;
use Illuminate\Support\Facades\Lang;
use App\Support\IsabelI18n;

class IsabelController extends Controller
{
public function getIsabelInfoOptions()
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

    function submit_answers(Request $request)
    {

        $rules = [
            'user_id' => 'required',
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
                        ])->get('https://apiscsandbox.isabelhealthcare.com/v2/ranked_differential_diagnoses', [
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
                            'language'    => 'en',
                            'web_service' => 'json',
                        ]);

        if ($ranked_differential_diagnoses->ok()) {
            $ranked_differential_diagnoses = $ranked_differential_diagnoses->json();
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
                            'language'    => 'en',
                            'web_service' => 'json',
                        ]);
            if ($response->ok()) {

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

                $gp = DoctorCategories::select('id')->where('title', 'General Physician')->where('is_deleted', 0)->first();

                return response()->json([
                    'status' => true,
                    'data'   => $response->json(),
                    'gp_id' => $gp->id ?? null,
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
