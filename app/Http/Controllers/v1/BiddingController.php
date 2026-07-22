<?php

namespace App\Http\Controllers\v1;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\BiddingServices;
use App\Models\BiddingSubmitBanner;
use App\Models\BiddingSubmissions;
use App\Models\BiddingSubmissionDocs;
use App\Models\GlobalFunction;
use Illuminate\Support\Facades\Validator;
use App\Models\Users;
use DB;

class BiddingController extends Controller
{

    function getServices(Request $request)
    {
        
        $lang = request()->header('lang', 'en');

        $columnservice = match ($lang) {
                        'ar' => 'service_ar',
                        'fr' => 'service_fr',
                        'hi' => 'service_hi',
                        'ur' => 'service_ur',
                        default => 'service',
                    };
        $search = $request->input('search');
        $query = BiddingServices::select($columnservice)
                    ->where('is_deleted', 0)  
                    ->whereNotNull($columnservice)  
                    ->distinct();
 
        if ($search) {
            $query->where($columnservice, 'like', "%{$search}%");
        }

        $limit = $request->get('limit', 2);
        $offset = $request->get('offset', 0);
        $services = $query->select('id',DB::raw("`$columnservice` as `name`"))->where('is_deleted', 0)->orderBy($columnservice)->paginate($limit, ['*'], 'page', $offset);
        $bidSubmtBanner = BiddingSubmitBanner::select('image')->where('is_deleted', 0)->first();
        return response()->json(['status' => true, 'services' => $services, 'banner' => $bidSubmtBanner]);
    }

    function submitBid(Request $request){
 
        $rules = [
            'country' => 'required',
            'city' => 'required',
            'date' => 'required',
            'comments' => 'required',
            'budget' => 'required|numeric|min:1',
            'documents' => 'sometimes|array',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        } 

        $service = $request->service_id ? BiddingServices::find($request->service_id) : null;  
  
        $bidding_query = new BiddingSubmissions();
        $bidding_query->service_id = $service->id ?? null;
        $bidding_query->user_id = $request->user_id ?? 0;
        $bidding_query->service = $service->service ?? null;
        $bidding_query->service_ar = $service->service_ar ?? null;
        $bidding_query->service_fr = $service->service_fr ?? null;
        $bidding_query->service_hi = $service->service_hi ?? null;
        $bidding_query->service_ur = $service->service_ur ?? null;

        $bidding_query->budget = $request->budget;
        $bidding_query->city = $request->city;
        $bidding_query->country = $request->country;
        $bidding_query->date = $request->date;
        $bidding_query->comments = $request->comments;
        $bidding_query->other_service = $request->other_text;
        $bidding_query->save();

        $attachments = [];
        if ($request->has('documents')) {
            foreach ($request->documents as $document) {
                $docs = new BiddingSubmissionDocs();
                $docs->bidding_submission_id = $bidding_query->id;
                $docs->document = GlobalFunction::saveFileAndGivePath($document);
                $docs->save();
                $attachments[] = GlobalFunction::createMediaUrl($docs->document);
            }
        }

        //  $user = Users::find($request->user_id);

        //     \Mail::to('yash.k@reapmind.com')->send(
        //                 new \App\Mail\BidSubmitMail(
        //                     $user->fullname,
        //                     $user->phone_number,
        //                     $user->email,
        //                     $attachments
        //                 )
        //             );
        
        return response()->json(['status' => true, 'message' => "Your Bid Is Submitted Successfully"]);
    }

    
}