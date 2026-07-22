<?php

namespace App\Http\Controllers;

use App\Models\Agencies;
use App\Models\AgencyType;
use App\Models\AgencySubscriptionPlans;
use App\Models\RiderAllocation;
use App\Models\AgencyRidersUsage;
use App\Models\TransactionHistory;
use Illuminate\Support\Facades\Validator;
use App\Helpers\Crypto;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class PartnerController extends Controller
{
    public function showLogin()
    {
        return view('partnerportal.login');
    }

    public function login(Request $request)
    {
        $rules = [
            'email'    => 'required|email',
            'password' => 'required'
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }
        
        $agency = Agencies::where('email',$request->email)->where('password',$request->password)->where('is_deleted',0)->first();
        if ($agency) {
            $agency_type = AgencyType::find($agency->agency_type);
            session([
                'partner_logged_in' => true,
                'partner_type'      => $agency_type->name,
                'type_id'           => $agency_type->id,
                'agency_id'         => $agency->id,
            ]);
            return redirect()->route('partner.dashboard');
        }

        return back()
            ->withErrors(['email' => 'Invalid email or password'])
            ->withInput();
    }

    public function logout()
    {
        session()->flush();
        return redirect()->route('partner.login');
    }

    public function dashboard()
    {
        $agency_id      = session('agency_id');
        $agency_type_id = session('type_id');

        $subscribed_riders  = 0;
        $remaining_riders   = 0;
        $allocated_riders   = 0;

        $partner_info = Agencies::where('id',$agency_id)->first();
        $has_subscription_plan = 0;
        // return $agency_id;
        $subscription_plan  = AgencySubscriptionPlans::select('rider_allocations.*','agency_subscription_plans.id as subscription_plan_id','agency_subscription_plans.remaining_riders',
                                    'agency_subscription_plans.outbound_remaining_riders','agency_subscription_plans.inbound_remaining_riders')
                                // ->whereMonth('agency_subscription_plans.expiry_date', Carbon::now()->month)
                                // ->whereYear('agency_subscription_plans.expiry_date', Carbon::now()->year)
                                ->where('agency_subscription_plans.expiry_date', '>', now())
                                ->where('agency_subscription_plans.agency_id',$agency_id)
                                ->join('rider_allocations','rider_allocations.id','agency_subscription_plans.subscription_id')
                                ->first();

        if(isset($subscription_plan))
        {
            $has_subscription_plan = 1;
            if($subscription_plan->payment_type == "Prepaid")
            {
                $subscribed_riders  = intval($subscription_plan->inbound_rider_number) + intval($subscription_plan->outbound_rider_number);
                $remaining_riders   = intval($subscription_plan->inbound_remaining_riders) + intval($subscription_plan->outbound_remaining_riders);
                $allocated_riders   = $subscribed_riders - $remaining_riders;
                $inbound_allocated_riders   = intval($subscription_plan->inbound_rider_number) - intval($subscription_plan->inbound_remaining_riders);
                $outbound_allocated_riders  = intval($subscription_plan->outbound_rider_number) - intval($subscription_plan->outbound_remaining_riders);

                // new point added
                $inbound_remaining_riders   =  intval($subscription_plan->inbound_remaining_riders);
                $outbound_remaining_riders  =  intval($subscription_plan->outbound_remaining_riders);

                $outbound_rider_number      =  intval($subscription_plan->outbound_rider_number);
                $inbound_rider_number       =  intval($subscription_plan->inbound_rider_number);

                return view('partnerportal.dashboard', compact('partner_info','has_subscription_plan','subscription_plan',
                                            'subscribed_riders','allocated_riders','remaining_riders',
                                            'inbound_allocated_riders','outbound_allocated_riders','inbound_remaining_riders','outbound_remaining_riders'
                                            ,'outbound_rider_number','inbound_rider_number'));
            }

            elseif($subscription_plan->payment_type == "Postpaid")
            {
                $usage = AgencyRidersUsage::where('plan_id', $subscription_plan->subscription_plan_id)
                        ->selectRaw('
                            SUM(inbound_riders)  as total_inbound_riders,
                            SUM(outbound_riders) as total_outbound_riders
                        ')
                        ->first();

                $inbound_used_rider         = intval($usage->total_inbound_riders);
                $outbound_used_rider        = intval($usage->total_outbound_riders);
                $total_used_rider           = $inbound_used_rider + $outbound_used_rider;
                $inbound_price_per_rider    = intval($subscription_plan->inbound_amount);
                $outbound_price_per_rider   = intval($subscription_plan->outbound_amount);
                $total_amount               = ($inbound_used_rider * $inbound_price_per_rider) + ($outbound_used_rider * $outbound_price_per_rider);


                // new key 

                $inbound_rider_total_amount = ($inbound_used_rider * $inbound_price_per_rider);
                $outbound_rider_total_amount = ($outbound_used_rider * $outbound_price_per_rider);
                // return $subscription_plan;
                return view('partnerportal.dashboard', compact('partner_info','has_subscription_plan','subscription_plan',
                                            'inbound_used_rider','outbound_used_rider','total_used_rider',
                                            'inbound_price_per_rider','outbound_price_per_rider','total_amount'
                                            ,'inbound_rider_total_amount','outbound_rider_total_amount'));
            }
        }

        
        // $has_subscription_plan  = AgencySubscriptionPlans::select('rider_allocations.*','agency_subscription_plans.remaining_riders',
        //                             'agency_subscription_plans.outbound_remaining_riders','agency_subscription_plans.inbound_remaining_riders')
        //                         ->where('agency_subscription_plans.expiry_date', '>', now())
        //                         ->where('agency_subscription_plans.agency_id',$agency_id)
        //                         ->join('rider_allocations','rider_allocations.id','agency_subscription_plans.subscription_id')
        //                         ->first();
        // if($has_subscription_plan){
        //     $has_subscription_plan = 1;
        // }
        return view('partnerportal.dashboard', compact('partner_info','has_subscription_plan'));        
    }

    public function subscription()
    {
        $agency_id      = session('agency_id');
        $agency_type_id = session('type_id');
        $current_plan   = AgencySubscriptionPlans::select('product_plan.name as subscription_name',
                                'product_plan.description as description','agency_subscription_plans.remaining_riders',
                                'agency_subscription_plans.expiry_date','rider_allocations.id','agency_subscription_plans.id as plan_id')
                            // ->whereMonth('agency_subscription_plans.expiry_date', Carbon::now()->month)
                            // ->whereYear('agency_subscription_plans.expiry_date', Carbon::now()->year)
                            ->where('agency_subscription_plans.expiry_date', '>', now())
                            ->where('agency_subscription_plans.agency_id',$agency_id)
                            ->join('rider_allocations','rider_allocations.id','agency_subscription_plans.subscription_id')
                            ->join('product_plan','product_plan.id','rider_allocations.product_plan_id')
                            ->first();
             
        $nextMonth      = Carbon::now()->copy()->addMonthNoOverflow();

        // return $nextMonth->month;
        $upcoming_plan  = RiderAllocation::with('product_plan')->where('agency_id',$agency_id)
                            ->whereMonth('expiry_date', $nextMonth->month)
                            ->whereYear('expiry_date', $nextMonth->year)
                            ->first();
        return view('partnerportal.subscription', compact('current_plan','upcoming_plan'));
    }

    public function getSubscriptionInfo(Request $request)
    {
        $subscription   = AgencySubscriptionPlans::select(
                                        'agency_subscription_plans.id','product_plan.name','product_plan.description','rider_allocations.product_plan_id',
                                        'rider_allocations.agency_type','rider_allocations.agency_id','rider_allocations.payment_type',
                                        'rider_allocations.expiry_date','rider_allocations.inbound','agency_subscription_plans.inbound_remaining_riders',
                                        'rider_allocations.inbound_amount','rider_allocations.outbound','agency_subscription_plans.outbound_remaining_riders',
                                        'rider_allocations.outbound_amount','agency_subscription_plans.created_at')
                                    ->where('agency_subscription_plans.id',$request->plan_id)
                                    ->join('rider_allocations','rider_allocations.id','agency_subscription_plans.subscription_id')
                                    ->join('product_plan','product_plan.id','rider_allocations.product_plan_id')
                                    ->first();

        $inbound_amount = abs($subscription->inbound_remaining_riders) * ($subscription->inbound_amount);
        $outbound_amount = abs($subscription->outbound_remaining_riders) * ($subscription->outbound_amount);
        $total_amount = $inbound_amount + $outbound_amount;
        $total_riders = abs($subscription->inbound_remaining_riders) + abs($subscription->outbound_remaining_riders);
        $subscription->total_riders = $total_riders;
        $subscription->amount = $total_amount;
        return response()->json(['status' => true, 'subscription' => $subscription]);
    }

    public function getSubscriptionHistory()
    {
        $agency_id              = session('agency_id');
        $subscription_history   = AgencySubscriptionPlans::select(
                                        'agency_subscription_plans.id','product_plan.name','rider_allocations.product_plan_id',
                                        'rider_allocations.agency_type','rider_allocations.agency_id','rider_allocations.payment_type',
                                        'rider_allocations.expiry_date','rider_allocations.inbound','rider_allocations.inbound_rider_number',
                                        'rider_allocations.inbound_amount','rider_allocations.outbound','rider_allocations.outbound_rider_number',
                                        'rider_allocations.outbound_amount','rider_allocations.amount','agency_subscription_plans.created_at')
                                    ->where('agency_subscription_plans.agency_id',$agency_id)
                                    ->join('rider_allocations','rider_allocations.id','agency_subscription_plans.subscription_id')
                                    ->join('product_plan','product_plan.id','rider_allocations.product_plan_id')
                                    ->paginate(10);

        return response()->json(['status' => true, 'subscription_history' => $subscription_history]);
    }

    public function downloadSubscriptionInvoice(Request $request)
    {
        // return $request->id;
       
        $subscription   = AgencySubscriptionPlans::select(
                                        'agency_subscription_plans.id','agency_subscription_plans.payment_status','product_plan.name','rider_allocations.product_plan_id',
                                        'rider_allocations.agency_type','rider_allocations.agency_id','rider_allocations.payment_type',
                                        'rider_allocations.expiry_date','rider_allocations.inbound','rider_allocations.inbound_rider_number',
                                        'rider_allocations.inbound_amount','rider_allocations.outbound','rider_allocations.outbound_rider_number',
                                        'rider_allocations.outbound_amount','rider_allocations.amount','agency_subscription_plans.created_at')
                                    ->where('agency_subscription_plans.id',$request->id)
                                    ->join('rider_allocations','rider_allocations.id','agency_subscription_plans.subscription_id')
                                    ->join('product_plan','product_plan.id','rider_allocations.product_plan_id')
                                    // ->orderBy('rider_allocations.id','desc')
                                    ->first();

        $createdAt = Carbon::parse($subscription->created_at);

        if($subscription->payment_type == "Prepaid"){
            $data  = [[
                        'Date'                      => $createdAt->format('Y-m-d'),
                        'Time'                      => $createdAt->format('H:i:s'),
                        'Payment Type'              => $subscription->payment_type,
                        'Amount Paid'               => $subscription->amount,
                        'Total Number of Riders'    => intval($subscription->inbound_rider_number) + intval($subscription->outbound_rider_number),
                        'Expiry Date'               => Carbon::parse($subscription->expiry_date)->format('Y-m-d'),
                        'Inbound Amount Per Rider'  => $subscription->inbound_amount,
                        'Inbound Riders'            => $subscription->inbound_rider_number,
                        'Outbound Amount Per Rider' => $subscription->outbound_amount,
                        'Outbound Riders'           => $subscription->outbound_rider_number,
                        'Service Type'              => ($subscription->inbound == 1 ? "inbound" : " ") . "," . ($subscription->outbound == 1 ? "outbound" : "")
                    ]];
        }
        else{

            $usage = AgencyRidersUsage::where('plan_id', $request->id)
                            ->selectRaw('
                                SUM(inbound_riders)  as total_inbound_riders,
                                SUM(outbound_riders) as total_outbound_riders
                            ')
                            ->first();

            $inbound_used_rider         = intval($usage->total_inbound_riders);
            $outbound_used_rider        = intval($usage->total_outbound_riders);
            $total_used_rider           = $inbound_used_rider + $outbound_used_rider;
            $inbound_price_per_rider    = intval($subscription->inbound_amount);
            $outbound_price_per_rider   = intval($subscription->outbound_amount);
            $total_amount               = ($inbound_used_rider * $inbound_price_per_rider) + ($outbound_used_rider * $outbound_price_per_rider);
            $data  = [[
                    'Date'                          => $createdAt->format('Y-m-d'),
                    'Time'                          => $createdAt->format('H:i:s'),
                    'Payment Type'                  => $subscription->payment_type,
                    'Amount Paid'                   => $total_amount,
                    'Number of Rider Used'          => $total_used_rider,
                    'Inbound Amount Per Rider'      => $subscription->inbound_amount,
                    'Number of Inbound Riders Used' => $inbound_used_rider,
                    'Outbound Amount Per Rider'     => $subscription->outbound_amount,
                    'Number of Outbound Riders Used'=> $outbound_used_rider,
                    'Service Type'                  => ($subscription->inbound == 1 ? "inbound" : " ") . "," . ($subscription->outbound == 1 ? "outbound" : ""),
                    'Payment Status'                => $subscription->payment_status == 1 ? "Paid" : "Pending",
                ]];
        }

        return Excel::download(new class($data) implements FromArray, WithHeadings {

            private $data;

            public function __construct($data)
            {
                $this->data = $data;
            }

            public function array(): array
            {
                // Only values (rows)
                return array_map('array_values', $this->data);
            }

            public function headings(): array
            {
                // Column titles (keys)
                return array_keys($this->data[0]);
            }

        }, 'invoice.xlsx');
    }

    public function addAgencySubscriptionPlan(Request $request)
    {
        $rules = [
            'subscription_id' => 'required',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }

        $agency_id          = session('agency_id');

        $subscriptionPlan   = RiderAllocation::where('id',$request->subscription_id)
                                ->where('expiry_date', '>', now())
                                ->where('is_deleted', 0)
                                ->first();
        
        if($subscriptionPlan) 
        {
            if($subscriptionPlan->payment_type == "Postpaid")
            {
                $agency_subscription_plan   = new AgencySubscriptionPlans();
                $agency_subscription_plan->subscription_id = $request->subscription_id;
                $agency_subscription_plan->payment_type = $subscriptionPlan->payment_type;
                $agency_subscription_plan->agency_id = $agency_id;
                $agency_subscription_plan->expiry_date = $subscriptionPlan->expiry_date;
                $agency_subscription_plan->remaining_riders = $subscriptionPlan->number_of_rider_plan;
                $agency_subscription_plan->payment_status = 0;
                $agency_subscription_plan->save();
            } 
            else{
                $order_id = 'ccavenue_' . uniqid();
                $agency_subscription_plan   = new AgencySubscriptionPlans();
                $agency_subscription_plan->subscription_id = $request->subscription_id;
                $agency_subscription_plan->payment_type = $subscriptionPlan->payment_type;
                $agency_subscription_plan->agency_id = $agency_id;
                $agency_subscription_plan->expiry_date = $subscriptionPlan->expiry_date;
                $agency_subscription_plan->remaining_riders = $subscriptionPlan->number_of_rider_plan;
                $agency_subscription_plan->inbound_remaining_riders = $subscriptionPlan->inbound_rider_number;
                $agency_subscription_plan->outbound_remaining_riders = $subscriptionPlan->outbound_rider_number;
                $agency_subscription_plan->order_id = $order_id;
                $agency_subscription_plan->amount = $subscriptionPlan->amount;
                $agency_subscription_plan->payment_status = 0;
                // return $agency_subscription_plan;
                $agency_subscription_plan->save();

                $agent = Agencies::find($agency_id);

                $amount = $subscriptionPlan->amount;

                $baseUrl = request()->getSchemeAndHttpHost();

                $redirectUrl = $baseUrl . '/v2/plan-payment-response';
                $cancelUrl   = $baseUrl . '/v2/plan-payment-cancel';

                $data = [
                    "billing_name" => $agent->name,
                    "billing_email" => $agent->email ?? '',
                    "billing_address" => $agent->address ?? '',
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
                // $payment_url = $baseUrl . '/v2/paymentSuccess?order_id=' . $order_id; old code 

                //return redirect()->to($payment_url); //old code
                // return response()->json([
                //     'status' => true,
                //     'payment_url' => $payment_url,
                // ]);

                return response()->json([
                        'status' => true,
                        'encRequest'  => $encrypted_data,
                        'access_code'=>  env('CCAVENUE_ACCESS_CODE'),
                         'payment_url' => $payment_url,
                    ]);

                // new code 
                        //    $encrypted_data = Crypto::encrypt(
                        //         $merchant_data,
                        //         env('CCAVENUE_WORKING_KEY')
                        //     );
                        // // ✅ Blade view return
                        // return response()->view('payment.ccavenue-redirect', [
                        //     'encRequest' => $encrypted_data,
                        //     'accessCode' => env('CCAVENUE_ACCESS_CODE'),
                        //      'payment_url' => $payment_url,
                        // ]);
            }           
        }    
        $nextMonth      = Carbon::now()->addMonth();

        $upcoming_plan  = RiderAllocation::where('agency_id',$agency_id)
                            ->whereMonth('expiry_date', $nextMonth->month)
                            ->whereYear('expiry_date', $nextMonth->year)
                            ->first();
        
        return view('partnerportal.subscription', compact('subscriptionPlan','upcoming_plan'));
    }

    public function paymentInitiateForPostpaid(Request $request)
    {
        $rules = [
            'id' => 'required',
        ];
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }

        $agency_subscription_plan   = AgencySubscriptionPlans::find($request->id);
        $order_id = 'ccavenue_' . uniqid();
        $agency_subscription_plan->payment_status = 0;
        $agency_subscription_plan->order_id = $order_id;
        $agency_subscription_plan->save();

        $agent = Agencies::find($agency_subscription_plan->agency_id);

        $amount = $agency_subscription_plan->amount;
        $baseUrl = request()->getSchemeAndHttpHost();

        $redirectUrl = $baseUrl . '/v2/plan-payment-response';
        $cancelUrl   = $baseUrl . '/v2/plan-payment-cancel';

        $data = [
            "billing_name" => $agent->name,
            "billing_email" => $agent->email ?? '',
            "billing_address" => $agent->address ?? '',
            "merchant_id" => env('CCAVENUE_MERCHANT_ID'),
            "order_id" => $order_id,
            "currency" => "AED",
            "amount" => $amount,
            "redirect_url" => $redirectUrl,
            "cancel_url" => $cancelUrl,
            "language" => "EN",
        ];

        $merchant_data = "";
        foreach ($data as $key => $value) {
            $merchant_data .= $key . '=' . $value . '&';
        }        

        $encrypted_data = Crypto::encrypt($merchant_data, env('CCAVENUE_WORKING_KEY'));

        $payment_url = env('CCAVENUE_BASE_URL') . "=$encrypted_data&access_code=" . env('CCAVENUE_ACCESS_CODE');
        // $payment_url = $baseUrl . '/v2/paymentSuccess?order_id=' . $order_id;

            return response()->json([
                        'status' => true,
                        'encRequest'  => $encrypted_data,
                        'access_code'=>  env('CCAVENUE_ACCESS_CODE'),
                         'payment_url' => $payment_url,
                    ]);    
        // old code
        // return redirect()->to($payment_url);
    }

    public function paymentSuccess(Request $request)
    {
        $agency_subscription_plan = AgencySubscriptionPlans::where('order_id', $request['order_id'])->first();
        $agency_subscription_plan->payment_status = 1;
        $agency_subscription_plan->save();
        // return $AgencySubscriptionPlans;
        $transaction = new TransactionHistory();
        $transaction->agency_id = $agency_subscription_plan->agency_id;
        $transaction->plan_id = $agency_subscription_plan->id;
        $transaction->amount = $agency_subscription_plan->amount;
        $transaction->created_at = now();
        $transaction->updated_at = now();
        $transaction->save();
        $baseUrl = request()->getSchemeAndHttpHost();
        return response()->json([
                    'status' => "Success",
                    'data'   => [
                        'order_id'     => $request['order_id'],
                        'amount'       => $agency_subscription_plan->amount,
                        'currency'     => "AED",
                        'agency_subscription_plan' => $agency_subscription_plan
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

        // return $responseData;
        // status check
        $status = $responseData['order_status'] ?? 'Unknown';
        $order_id = $responseData['order_id'] ?? null;

        $agency_subscription_plan = AgencySubscriptionPlans::where('order_id', $responseData['order_id'])->first();
        if($agency_subscription_plan){
            if($status == 'Success'){
                $agency_subscription_plan->payment_status = 1;
                $agency_subscription_plan->save();

                $transaction = new TransactionHistory();
                $transaction->agency_id = $agency_subscription_plan->agency_id;
                $transaction->plan_id = $agency_subscription_plan->id;
                $transaction->amount = $agency_subscription_plan->amount;
                $transaction->created_at = now();
                $transaction->updated_at = now();
                $transaction->save();
            }

            else if($status == 'Failure'){
                $agency_subscription_plan->payment_status = 2;
                $agency_subscription_plan->save();
            }
        }

        $baseUrl = request()->getSchemeAndHttpHost();
        // return response()->json([
        //             'status' => $status,
        //             'data'   => [
        //                 'order_id'     => $order_id,
        //                 'amount'       => $responseData['amount'],
        //                 'currency'     => $responseData['currency'],  
        //                 'payment_mode' => $responseData['payment_mode'],
        //                 'agency_subscription_plan' => $agency_subscription_plan
        //             ]
        //         ]);

        return redirect()->route('partner.subscription')
            ->with([
                'status' => $status,
                'order_id' => $order_id,
                'amount' => $responseData['amount'],
                'currency' => $responseData['currency'],
                'payment_mode' => $responseData['payment_mode'],
                'agency_subscription_plan' => $agency_subscription_plan
            ]);

    }

    function payment_cancel(Request $request)
    {
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

            $agency_subscription_plan = AgencySubscriptionPlans::where('order_id', $responseData['order_id'])->first();
            if($agency_subscription_plan){
                if($status == 'Aborted'){
                    $agency_subscription_plan->payment_status = 3;
                    $agency_subscription_plan->save();
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

    public function getTransactionHistory(Request $request)
    {
        $agency_id          = session('agency_id');
        $transaction_history = TransactionHistory::select('agencies.name','agency_type.name as agency_type','agency_subscription_plans.payment_type',
                                        'transaction_history.amount','transaction_history.created_at','agency_subscription_plans.id as plan_id',
                                        'rider_allocations.inbound','rider_allocations.outbound','agency_subscription_plans.subscription_id')
                                ->join('agency_subscription_plans','agency_subscription_plans.id','transaction_history.plan_id')
                                ->join('rider_allocations','rider_allocations.id','agency_subscription_plans.subscription_id')
                                ->join('agencies','agencies.id','transaction_history.agency_id')
                                ->join('agency_type','agency_type.id','agencies.agency_type')
                                ->where('transaction_history.agency_id',$agency_id)->paginate(10);;
        foreach ($transaction_history as $key => $transaction) {
            if($transaction->payment_type == "Prepaid")
            {
                $rider = RiderAllocation::where('id',$transaction->subscription_id)->first();
                $transaction->inbound_number_of_rider = $rider->inbound_rider_number;
                $transaction->outbound_number_of_rider = $rider->outbound_rider_number;
            }
            else{
                $usage = AgencyRidersUsage::where('plan_id', $transaction->plan_id)
                        ->selectRaw('
                            SUM(inbound_riders)  as total_inbound_riders,
                            SUM(outbound_riders) as total_outbound_riders
                        ')
                        ->first();

                $transaction->inbound_number_of_rider = $usage->total_inbound_riders;
                $transaction->outbound_number_of_rider = $usage->total_outbound_riders;
            }
        }
        return response()->json([
                'status' => true,
                'transaction_history'   => $transaction_history
            ]);
    }

    public function dropdown()
    {
        $agencies = Agencies::select('id', 'name as agency_name')
            ->where('is_deleted', 0)   // sirf active agencies
            ->orderBy('name', 'asc')
            ->get();

        return response()->json([
            'status' => true,
            'data'   => $agencies
        ]);
    }

    //downloadSubscriptionInvoice BY Admin
    public function downloadSubscriptionInvoiceByAdmin(Request $request)
    {
        $subscription = AgencySubscriptionPlans::select(
                'agency_subscription_plans.id',
                'agency_subscription_plans.payment_status',
                'agency_subscription_plans.created_at',
                'product_plan.name',
                'rider_allocations.product_plan_id',
                'rider_allocations.agency_type',
                'rider_allocations.agency_id',
                'rider_allocations.payment_type',
                'rider_allocations.expiry_date',
                'rider_allocations.inbound',
                'rider_allocations.inbound_rider_number',
                'rider_allocations.inbound_amount',
                'rider_allocations.outbound',
                'rider_allocations.outbound_rider_number',
                'rider_allocations.outbound_amount',
                'rider_allocations.amount',
                'agencies.name as agencies_name',
                'agency_type.name as agency_type',
            )
            ->join('rider_allocations', 'rider_allocations.id', '=', 'agency_subscription_plans.subscription_id')
            ->join('product_plan', 'product_plan.id', '=', 'rider_allocations.product_plan_id')
            ->join('agencies', 'agencies.id', '=', 'rider_allocations.agency_id')
            ->join('agency_type', 'agency_type.id', '=', 'rider_allocations.agency_type')
            ->where('agency_subscription_plans.id', $request->id)
            ->firstOrFail();

        $createdAt   = Carbon::parse($subscription->created_at);
        $serviceType = trim(
            ($subscription->inbound ? 'inbound' : '') .
            ($subscription->inbound && $subscription->outbound ? ', ' : '') .
            ($subscription->outbound ? 'outbound' : '')
        );

        if ($subscription->payment_type === 'Prepaid') {

            $data[] = [
                'Date'                      => $createdAt->format('Y-m-d'),
                'Time'                      => $createdAt->format('H:i:s'),
                'Agencies Name'             => $subscription->agencies_name,
                'Agency Type'             => $subscription->agency_type,
                'Payment Type'              => $subscription->payment_type,
                'Amount Paid'               => $subscription->amount,
                'Total Number of Riders'    => (int) $subscription->inbound_rider_number + (int) $subscription->outbound_rider_number,
                'Expiry Date'               => Carbon::parse($subscription->expiry_date)->format('Y-m-d'),
                'Inbound Amount Per Rider'  => $subscription->inbound_amount,
                'Inbound Riders'            => $subscription->inbound_rider_number,
                'Outbound Amount Per Rider' => $subscription->outbound_amount,
                'Outbound Riders'           => $subscription->outbound_rider_number,
                'Service Type'              => $serviceType,
            ];

        } else {

            $usage = AgencyRidersUsage::where('plan_id', $request->id)
                ->selectRaw('
                    COALESCE(SUM(inbound_riders),0)  as total_inbound_riders,
                    COALESCE(SUM(outbound_riders),0) as total_outbound_riders
                ')
                ->first();

            $inboundUsed   = (int) $usage->total_inbound_riders;
            $outboundUsed  = (int) $usage->total_outbound_riders;
            $totalUsed     = $inboundUsed + $outboundUsed;

            $totalAmount = ($inboundUsed * (int) $subscription->inbound_amount)
                        + ($outboundUsed * (int) $subscription->outbound_amount);

            $data[] = [
                'Date'                           => $createdAt->format('Y-m-d'),
                'Time'                           => $createdAt->format('H:i:s'),
                'Agencies Name'                  => $subscription->agencies_name,
                'Agency Type'                    => $subscription->agency_type,
                'Payment Type'                   => $subscription->payment_type,
                'Amount Paid'                    => $totalAmount,
                'Number of Rider Used'           => $totalUsed,
                'Inbound Amount Per Rider'       => $subscription->inbound_amount,
                'Number of Inbound Riders Used'  => $inboundUsed,
                'Outbound Amount Per Rider'      => $subscription->outbound_amount,
                'Number of Outbound Riders Used' => $outboundUsed,
                'Service Type'                   => $serviceType,
                'Payment Status'                 => $subscription->payment_status ? 'Paid' : 'Pending',
            ];
        }

        // return $data;

        $filename = 'invoice_' 
          . strtolower($subscription->agencies_name) . '_'  
          . strtolower($subscription->agency_type) . '_'  
          . strtolower($subscription->payment_type) . '_'
          . $subscription->id . '_'
          . $createdAt->format('Ymd_His') 
          . '.xlsx';

        return Excel::download(
            new class($data) implements FromArray, WithHeadings {

                public function __construct(private array $data) {}

                public function array(): array
                {
                    return array_map('array_values', $this->data);
                }

                public function headings(): array
                {
                    return array_keys($this->data[0]);
                }
            },
             $filename
        );
    }

}
