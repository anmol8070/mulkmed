<?php

namespace App\Http\Controllers\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\UploadedFile;
use App\Models\AgencyType;
use App\Models\Users;
use App\Models\TravelFlowBanner;
use App\Models\TouristList;
use App\Models\GlobalFunction;
use App\Helpers\Crypto;
use Carbon\Carbon;
use PDF;
use DB;


class TravelerController extends Controller
{
    public function getServiceTypes(Request $request)
    {
        $requestedLang = $request->get('lang') ?? $request->get('language') ?? $request->header('Accept-Language', 'en');
        $lang = strtolower((string) $requestedLang);
        if (str_contains($lang, ',')) {
            $lang = explode(',', $lang)[0];
        }
        if (str_contains($lang, '-')) {
            $lang = explode('-', $lang)[0];
        }

        if (in_array($lang, ['en', 'ar', 'fr', 'hi', 'ur'], true)) {
            app()->setLocale($lang);
        } else {
            app()->setLocale('en');
        }

        $agency_types = AgencyType::where('id','!=',3) ->orderByRaw("FIELD(name,'Hotel','Travel')") ->get();
        $Banner = TravelFlowBanner::select('id','tourist_partner_banner as image')
            ->where('banner_type','travel_guide')
            ->get()
            ->map(function ($item) {
                if (!empty($item->image)) {
                    $item->image = GlobalFunction::createMediaUrl($item->image);
                }
                return $item;
            });
        $data = [
        'heading' => __('Mulk AI Health Shield For Your Travel & Stay'),
        'info'    => __('Purchase Mulk Travel Coverage Certificate Before Departure/ Hotel Check In'),
        'points'  => [
                        __('Instant Online Dr Consultation 24/7: Immediate access to medical professionals anytime.'),
                        __('AI Driven Wellness Health Check: Comprehensive screening covering 30+ health parameters.'),
                        __('AI Dr Midas Support 24/7: Constant AI-powered medical guidance and support.')
                    ]

        ];

        return response()->json([
            'success' => true,
            'service_types' => $agency_types,
            'banner'     => $Banner,
            'data'          => $data,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    public function initiateTravelerPayment_old(Request $request)
    {
        // try {
            $rules = [
                'user_id' => 'required',
                'first_name' => 'required',
                'contact_number' => 'required',
                'service_type' => 'required',
            ];

            $validator = Validator::make($request->all(), $rules);
            if ($validator->fails()) {
                $messages = $validator->errors()->all();
                $msg = $messages[0];
                return response()->json(['status' => false, 'message' => $msg]);
            }                                   

            $user = Users::find($request->user_id);
            if ($user == null) {
                return response()->json(['status' => false, 'message' => "User doesn't exists!"]);
            }

            $order_id = 'ccavenue_' . uniqid();

            $tourist                   = new TouristList();
            $tourist->agent_id         = 0;
            $tourist->agent_type       = $request->service_type;
            $tourist->first_name       = $request->first_name;
            $tourist->contact_number   = $request->contact_number;
            $tourist->check_in_time    = $request->check_in_time ?? null;
            $tourist->check_out_time   = $request->check_out_time ?? null;
            $tourist->fly_in           = $request->fly_in ?? null;
            $tourist->fly_out          = $request->fly_out ?? null;
            $tourist->passport_number  = $request->passport_number ?? null;
            $tourist->visa_expiry_days = $request->visa_expiry_days ?? null;
            $tourist->self_registered  = 1;
            $tourist->order_id         = $order_id;
            $tourist->payment_status   = 0;
            $tourist->payment_amount   = 1;

            if($request->service_type == 2)
            {
                $checkIn                            = Carbon::parse($request->check_in_time);
                $checkOut                           = Carbon::parse($request->check_out_time);
                // $numberOfDays                       = $checkIn->diffInDays($checkOut) + 1;

                // Night-based entitlements:
                // check-in today + check-out tomorrow => 1 night (not 2 days)
                $numberOfDays                       = max(1, $checkIn->diffInDays($checkOut));
                $numberOfDays                       = max(1,$checkIn->copy()->startOfDay()->diffInDays($checkOut->copy()->startOfDay()) );
                $tourist->number_of_midas           = $numberOfDays;
                $tourist->number_of_ai_health_check = $numberOfDays;
                $tourist->number_of_consultation    = $numberOfDays;
            }

            if($request->service_type == 1)
            {
                $checkIn                            = Carbon::parse($request->fly_in);
                $checkOut                           = Carbon::parse($request->fly_out);
                // $numberOfDays                       = $checkIn->diffInDays($checkOut) + 1;

                // Night-based entitlements:
                // check-in today + check-out tomorrow => 1 night (not 2 days)
                $numberOfDays                       = max(1, $checkIn->diffInDays($checkOut));
                $tourist->number_of_midas           = $numberOfDays;
                $tourist->number_of_ai_health_check = $numberOfDays;
                $tourist->number_of_consultation    = $numberOfDays;
            }

            if($request->service_type == 3)
            {
                $tourist->number_of_midas           = $request->visa_expiry_days;
                $tourist->number_of_ai_health_check = $request->visa_expiry_days;
                $tourist->number_of_consultation    = $request->visa_expiry_days;
            }

            $tourist->save();

            $amount = 1;

            $baseUrl = request()->getSchemeAndHttpHost();

            // $redirectUrl = $baseUrl . '/v2/api/v1/user/traveler-payment-response';
            // $cancelUrl   = $baseUrl . '/v2/api/v1/user/traveler-payment-cancel';

            $redirectUrl = $baseUrl . '/api/v1/user/traveler-payment-response';
            $cancelUrl   = $baseUrl . '/api/v1/user/traveler-payment-cancel';

            $data = [
                "billing_name" => $request->user_name,
                "billing_email" => $user->email ?? '',
                "billing_address" => $request->address ?? '',
                "merchant_id" => env('CCAVENUE_MERCHANT_ID'),
                "order_id" => $order_id,
                "currency" => "AED",
                "amount" => $amount,
                "redirect_url" => $redirectUrl,
                "cancel_url" => $cancelUrl,
                "language" => "EN",
            ];

            // return $data;

            $merchant_data = "";
            foreach ($data as $key => $value) {
                $merchant_data .= $key . '=' . $value . '&';
            }
            

            $encrypted_data = Crypto::encrypt($merchant_data, env('CCAVENUE_WORKING_KEY'));

            $payment_url = env('CCAVENUE_BASE_URL') . "=$encrypted_data&access_code=" . env('CCAVENUE_ACCESS_CODE');
            // $payment_url = $baseUrl . '/v2/api/v1/user/paymentSuccess?order_id=' . $order_id;
            $payment_url = $baseUrl . '/api/v1/user/paymentSuccess?order_id=' . $order_id;
            return response()->json([
                'status' => true,
                'payment_url' => $payment_url,
                'tourist' => $tourist,
                // 'final_doctor_charge' => $final_doctor_charge
            ]);

            
        // }

        // catch (\Throwable $e) {
        //     return ['status' => false, 'message' => $e->getMessage()];
        // }
        
    }

    public function initiateTravelerPayment(Request $request)
    {
        // return $request->all();
        $rules = [
            'user_id' => 'required|exists:users,id',
            'tourist_list' => 'required|array|min:1',
            // 'tourist_list.*.first_name' => 'required',

            // Support both:
            // - New request shape: tourist_list.*.full_name
            // - Old request shape: tourist_list.*.first_name (+ optional last_name)
            'tourist_list.*.full_name' => 'nullable|string',
            'tourist_list.*.first_name' => 'nullable|string',
            'tourist_list.*.last_name' => 'nullable|string',

            'tourist_list.*.mobile.country_code' => 'required',
            'tourist_list.*.mobile.contact_number' => 'required',
            'tourist_list.*.service_type' => 'required',
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validator->errors()->first()
            ]);
        }

        $user = Users::find($request->user_id);

        $order_id = 'ccavenue_' . uniqid();
        $totalAmount = 0;

        // foreach ($request->tourist_list as $touristData) {
        foreach ($request->tourist_list as $touristIndex => $touristData) {

            $tourist = new TouristList();
            $tourist->agent_id = 0;
            $tourist->agent_type = $touristData['service_type'];
            // $tourist->first_name = $touristData['first_name'];
             
            $fullName = trim((string) ($touristData['full_name'] ?? ''));
            if ($fullName === '') {
                // If full_name isn't provided, build it from first_name + last_name.
                $firstName = trim((string) ($touristData['first_name'] ?? ''));
                $lastName  = trim((string) ($touristData['last_name'] ?? ''));
                $fullName  = trim($firstName . ' ' . $lastName);
            }

            if ($fullName === '') {
                return response()->json([
                    'status' => false,
                    'message' => "full_name is required for tourist_list item " . ($touristIndex + 1),
                ]);
            }

            // This project stores the traveller name in the `first_name` column.
            // Split `full_name` by the first space:
            // - `first_name` = first token
            // - `last_name`  = remaining tokens (unless `last_name` key is provided)
            // Normalize whitespace (handles NBSP/non-breaking spaces) before splitting.
            $fullNameNormalized = preg_replace('/[\x{00A0}\x{2007}\x{202F}]+/u', ' ', $fullName);
            $fullNameNormalized = preg_replace('/\s+/u', ' ', trim($fullNameNormalized));

            $firstLast = explode(' ', $fullNameNormalized, 2);
            $tourist->first_name = $firstLast[0] ?? $fullNameNormalized;
            $derivedLastName = $firstLast[1] ?? '';

            $providedLastName = trim((string) ($touristData['last_name'] ?? ''));
            $tourist->last_name = ($providedLastName !== '') ? $providedLastName : $derivedLastName;

            $tourist->contact_number =  $touristData['mobile']['contact_number'];
            $tourist->country_code =    $touristData['mobile']['country_code'];

            // // `tourist_list` table in this project does not have `country_code`,
            // // so store full phone (country_code + contact_number) in `contact_number`.
            // $contactNumber = $touristData['mobile']['contact_number'];
            // $countryCode   = ltrim((string) ($touristData['mobile']['country_code'] ?? ''), '+');
            // $tourist->contact_number = trim($countryCode . $contactNumber);

            $tourist->self_registered = 1;
            $tourist->order_id = $order_id;
            $tourist->payment_status = 0;

            $numberOfDays = 1;

            // ✅ Service Type Logic
            if ($touristData['service_type'] == 2) {

                $checkIn = Carbon::parse($touristData['check_in_time']);
                $checkOut = Carbon::parse($touristData['check_out_time']);

                $tourist->check_in_time = $touristData['check_in_time'];
                $tourist->check_out_time = $touristData['check_out_time'];

                // $numberOfDays = $checkIn->diffInDays($checkOut) + 1;

                 // Night-based 
                // check-in today + check-out tomorrow => 1 night
                $numberOfDays = max( 1, $checkIn->copy()->startOfDay()->diffInDays($checkOut->copy()->startOfDay()) );

                $amountPerDay = 15;
            }

            if ($touristData['service_type'] == 1) {

                $checkIn = Carbon::parse($touristData['fly_in']);
                $checkOut = Carbon::parse($touristData['fly_out']);

                $tourist->fly_in = $touristData['fly_in'];
                $tourist->fly_out = $touristData['fly_out'];

                // $numberOfDays = $checkIn->diffInDays($checkOut) + 1;
                $numberOfDays =  1; // change logic 0n 24-03-2026
                $amountPerDay = 10;
            }

            if ($touristData['service_type'] == 3) {

                $numberOfDays = $touristData['visa_expiry_days'];
                $tourist->visa_expiry_days = $numberOfDays;
            }

            $tourist->number_of_midas = $numberOfDays;
            $tourist->number_of_ai_health_check = $numberOfDays;
            $tourist->number_of_consultation = $numberOfDays;

            // ✅ Per Day Amount (Example: 1 AED per day)
            // $amountPerDay = 1;
            $touristAmount = $numberOfDays * $amountPerDay;

            $tourist->payment_amount = $touristAmount;

            $totalAmount += $touristAmount;

            $tourist->save();
        }

        // ✅ Payment Setup
        $baseUrl = request()->getSchemeAndHttpHost();
            $redirectUrl = $baseUrl . '/api/v1/user/traveler-payment-response';
            $cancelUrl   = $baseUrl . '/api/v1/user/traveler-payment-cancel';

            $data = [
                "billing_name" => $request->user_name,
                "billing_email" => $user->email ?? '',
                "billing_address" => $request->address ?? '',
                "merchant_id" => env('CCAVENUE_MERCHANT_ID'),
                "order_id" => $order_id,
                "currency" => "AED",
                "amount" => $totalAmount,
                "redirect_url" => $redirectUrl,
                "cancel_url" => $cancelUrl,
                "language" => "EN",
            ];

            // return $data;

            $merchant_data = "";
            foreach ($data as $key => $value) {
                $merchant_data .= $key . '=' . $value . '&';
            }
            

            $encrypted_data = Crypto::encrypt($merchant_data, env('CCAVENUE_WORKING_KEY'));

            $payment_url = env('CCAVENUE_BASE_URL') . "=$encrypted_data&access_code=" . env('CCAVENUE_ACCESS_CODE');
       

            return response()->json([
                'status' => true,
                'payment_url' => $payment_url,
                'order_id' => $order_id,
                'total_amount' => $totalAmount
            ]);

 
    }

    public function paymentSuccess(Request $request)
    {
        $tourist = TouristList::where('order_id', $request['order_id'])->first();
        $tourist->payment_status = 1;
        $tourist->save();

        return response()->json([
                    'status' => "Success",
                    'data'   => [
                        'order_id'     => $request['order_id'],
                        'amount'       => 1,
                        'currency'     => "AED",
                        'tourist' => $tourist
                    ]
                ]);
    }

    public function paymentResponse(Request $request)
    {
        $workingKey = env('CCAVENUE_WORKING_KEY');
        $encResponse = $request->input('encResp');

        $meeting_link = '';

        if(!$encResponse)
        {
            return response('No enResp', 400);
        }

        // Decrypt CCAvenue response
        $rcvdString = Crypto::decrypt($encResponse, $workingKey);
        parse_str($rcvdString, $responseData);

        // status check
        $status = $responseData['order_status'] ?? 'Unknown';
        $order_id = $responseData['order_id'] ?? null;

        $tourist = TouristList::where('order_id', $responseData['order_id'])->first();
        if($tourist){
            if($status == 'Success'){
                $tourist->payment_status = 1;
                $tourist->save();
            }

            else if($status == 'Failure'){
                $tourist->payment_status = 2;
                $tourist->save();
            }
        }

        return response()->json([
                    'status' => $status,
                    'data'   => [
                        'order_id'     => $order_id,
                        'amount'       => $responseData['amount'],
                        'currency'     => $responseData['currency'],  
                        'payment_mode' => $responseData['payment_mode'],
                        'tourist' => $tourist
                    ]
                ]);
    }

    function ccavenue_payment_cancel(Request $request){

        try{
            $workingKey = env('CCAVENUE_WORKING_KEY');
            $encResponse = $request->input('encResp');

            if(!$encResponse)
            {
                return response('No enResp', 400);
            }

            $rcvdString = Crypto::decrypt($encResponse, $workingKey);
            parse_str($rcvdString, $responseData);

            $status = $responseData['order_status'] ?? 'Unknown';
            $order_id = $responseData['order_id'] ?? null;

            $tourist = TouristList::where('order_id', $responseData['order_id'])->first();
            if($tourist){
                if($status == 'Aborted'){
                    $tourist->payment_status = 3;
                    $tourist->save();
                }
            }

            return response()->json([
                'status' => $status,
                'data'   => [
                    'order_id'     => $order_id,
                    'amount'       => $responseData['amount'],
                    'currency'     => $responseData['currency'],
                    'payment_mode' => $responseData['payment_mode'],
                ]
            ]);
        }

        catch (\Throwable $e) {
            return ['status' => false, 'message' => $e->getMessage()];
        }
    }


// public function downloadCertificate(Request $request)
// {
//     try {

//         $data = [
//             'title' => 'Mulk Medical Travel Coverage Certificate',
//             'status' => 'Activation Successful'
//         ];

//         $pdf = PDF::loadView('certificate.medical-certificate', $data)
//             ->setPaper('A4', 'portrait');

//         return $pdf->download('mulk_medical_certificate.pdf');

//     } 
//     catch (\Throwable $e) {

//         return response()->json([
//             'status' => false,
//             'message' => $e->getMessage()
//         ]);

//     }
// }

  public function downloadCertificate(Request $request)
{
    try {

        $filePath = base_path('resources/views/certificate/Certificate.pdf');

        if (!file_exists($filePath)) {
            return response()->json([
                'status' => false,
                'message' => 'Certificate file not found'
            ], 404);
        }

        return response()->download($filePath, 'Certificate.pdf', [
            'Content-Type' => 'application/pdf'
        ]);

    } catch (\Throwable $e) {

        return response()->json([
            'status' => false,
            'message' => $e->getMessage()
        ], 500);
    }
}

}