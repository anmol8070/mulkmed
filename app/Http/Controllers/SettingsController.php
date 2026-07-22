<?php

namespace App\Http\Controllers;

use App\Classes\AgoraDynamicKey\RtcTokenBuilder;
use App\Models\AddedPatients;
use App\Models\Admin;
use App\Models\AppointmentReminders;
use App\Models\Appointments;
use App\Models\Constants;
use App\Models\Coupons;
use App\Models\DoctorAppointmentSlots;
use App\Models\DoctorAwards;
use App\Models\DoctorBankAccount;
use App\Models\DoctorCategories;
use App\Models\DoctorCatSuggestions;
use App\Models\DoctorEarningHistory;
use App\Models\DoctorExperience;
use App\Models\DoctorExpertise;
use App\Models\DoctorHolidays;
use App\Models\DoctorNotifications;
use App\Models\DoctorPayoutHistory;
use App\Models\DoctorReviews;
use App\Models\Doctors;
use App\Models\DoctorServiceLocations;
use App\Models\DoctorServices;
use App\Models\DoctorWalletStatements;
use App\Models\EmrSearchMaster;
use App\Models\FaqCats;
use App\Models\Faqs;
use App\Models\GlobalFunction;
use App\Models\GlobalSettings;
use App\Models\PlatformEarningHistory;
use App\Models\ScheduledReminders;
use App\Models\Taxes;
use App\Models\UserNotification;
use App\Models\UserWalletRechargeLogs;
use App\Models\UserWalletStatements;
use App\Models\UserWithdrawRequest;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Google\Client;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Collection;
use Rap2hpoutre\FastExcel\FastExcel;
use Stichoza\GoogleTranslate\GoogleTranslate;


include base_path("./app/Class/AgoraDynamicKey/RtcTokenBuilder.php");

class SettingsController extends Controller
{

    function sendScheduledReminders_and_expirePlan_Cron(){

        $currentTimestamp = Carbon::now()->toDateTimeString();

        $scheduledReminders = ScheduledReminders::where('scheduled_at','<=', $currentTimestamp)->get();

       foreach($scheduledReminders as $scheduled){
        if($scheduled->status == 1){
            $title = "You have appointment after : ". $scheduled->after_time_string;
            $message = 'Please be available on time!';

            $notifyData = [
                'type'=> Constants::notifyAppointment.'',
                'id'=>$scheduled->appointment_id.''
            ];

            GlobalFunction::sendPushToUser($title, $message, $scheduled->user, $notifyData);
        }
        $scheduled->delete();
       }

        $timezone = config('app.timezone', 'UTC');
        $now =Carbon::now($timezone);

        // Only run if it's exactly 00:05 in the set timezone
        if ($now->format('H:i') === '00:05') {
            Artisan::call('userPlans:expire');
            Artisan::call('appointments:markCompletedMissed');
        }

        

    //    return GlobalFunction::sendDataResponse(true,'cron run', $scheduledReminders);
    }

    function fetchAllRemindersList(Request $request)
    {
        $totalData =  AppointmentReminders::count();
        $rows = AppointmentReminders::orderBy('id', 'DESC')->get();

        $result = $rows;

        $columns = array(
            0 => 'id',
            1 => 'fullname',
            2 => 'identity',
            3 => 'username',
        );

        $limit = $request->input('length');
        $start = $request->input('start');
        $order = $columns[$request->input('order.0.column')];
        $dir = $request->input('order.0.dir');

        $totalFiltered = $totalData;
        if (empty($request->input('search.value'))) {
            $result = AppointmentReminders::offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
        } else {
            $search = $request->input('search.value');
            $result =  AppointmentReminders::where(function ($query) use ($search) {
                $query->Where('day', 'LIKE', "%{$search}%")
                    ->orWhere('hr', 'LIKE', "%{$search}%")
                    ->orWhere('min', 'LIKE', "%{$search}%");
            })->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
            $totalFiltered = AppointmentReminders::where(function ($query) use ($search) {
                $query->Where('day', 'LIKE', "%{$search}%")
                    ->orWhere('hr', 'LIKE', "%{$search}%")
                    ->orWhere('min', 'LIKE', "%{$search}%");
            })->count();
        }
        $data = array();
        foreach ($result as $item) {


            $edit = '<a data-taxtitle="' . $item->tax_title . '" data-type="' . $item->type . '" href="" data-value="' . $item->value . '"  class="mr-2 btn btn-primary text-white edit" rel=' . $item->id . ' >' . __("Edit") . '</a>';

            $delete = '<a href="" class="mr-2 btn btn-danger text-white delete" rel=' . $item->id . ' >' . __("Delete") . '</a>';
            $action = $delete;

            $data[] = array(
                $item->day . ' Day',
                $item->hr .  ' Hours',
                $item->min . ' Minutes',
                $action,
            );
        }
        $json_data = array(
            "draw"            => intval($request->input('draw')),
            "recordsTotal"    => intval($totalData),
            "recordsFiltered" => $totalFiltered,
            "data"            => $data
        );
        echo json_encode($json_data);
        exit();
    }

    function addReminderItem(Request $request){
        $item = new AppointmentReminders();
        $item->day = $request->day;
        $item->hr = $request->hr;
        $item->min= $request->min;
        $item->save();
        return GlobalFunction::sendSimpleResponse(true, 'Item saved successfully');
    }
    function deleteRemindersItem($id)
    {
        $item = AppointmentReminders::find($id);
        $item->delete();
        return GlobalFunction::sendSimpleResponse(true, 'item deleted successfully!');
    }

    function onOffChatBot($value){
        $settings = GlobalSettings::first();
        $settings->enable_chatbot = $value;
        $settings->save();
        return response()->json(['status' => true, 'message' => 'value changes successfully']);
    }

    function pushNotificationToSingleUser(Request $request){
        $client = new Client();
        $client->setAuthConfig('googleCredentials.json');
        $client->addScope('https://www.googleapis.com/auth/firebase.messaging');
        $client->fetchAccessTokenWithAssertion();
        $accessToken = $client->getAccessToken();
        $accessToken = $accessToken['access_token'];

        // Log::info($accessToken);
        $contents = File::get(base_path('googleCredentials.json'));
        $json = json_decode(json: $contents, associative: true);

        $url = 'https://fcm.googleapis.com/v1/projects/'.$json['project_id'].'/messages:send';
        // $notificationArray = array('title' => $title, 'body' => $message);

        // $device_token = $user->device_token;

        $fields = $request->json()->all();

        // $fields = array(
        //     'message'=> [
        //         'token'=> $device_token,
        //         'notification' => $notificationArray,
        //     ]
        // );

        $headers = array(
            'Content-Type:application/json',
            'Authorization:Bearer ' . $accessToken
        );
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
        // print_r(json_encode($fields));
        $result = curl_exec($ch);
        Log::debug($result);

        if ($result === FALSE) {
            die('FCM Send Error: ' . curl_error($ch));
        }
        curl_close($ch);

        // return $response;
        return response()->json(['result'=> $result, 'fields'=> $fields]);
    }

    function cleanDatabase()
    {
        Appointments::doesntHave('doctor')->doesntHave('user', 'or')->delete();
        PlatformEarningHistory::doesntHave('doctor')->doesntHave('appointment', 'or')->delete();

        AddedPatients::doesntHave('user')->delete();
        UserWithdrawRequest::doesntHave('user')->delete();
        UserWalletStatements::doesntHave('user')->delete();

        DoctorPayoutHistory::doesntHave('doctor')->delete();
        DoctorReviews::doesntHave('doctor')->doesntHave('user', 'or')->delete();
        DoctorAppointmentSlots::doesntHave('doctor')->delete();
        DoctorAwards::doesntHave('doctor')->delete();
        DoctorBankAccount::doesntHave('doctor')->delete();
        DoctorEarningHistory::doesntHave('appointment')->delete();
        DoctorExperience::doesntHave('doctor')->delete();
        DoctorExpertise::doesntHave('doctor')->delete();
        DoctorHolidays::doesntHave('doctor')->delete();
        DoctorServices::doesntHave('doctor')->delete();
        DoctorServiceLocations::doesntHave('doctor')->delete();
        DoctorWalletStatements::doesntHave('doctor')->delete();
    }

    function generateAgoraToken(Request $request)
    {
        $rules = [
            'channelName' => 'required'
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }

        $appID = env('AGORA_APP_ID');
        $appCertificate = env('AGORA_APP_CERT');
        $channelName = $request->channelName;

        $role = RtcTokenBuilder::RolePublisher;
        $expireTimeInSeconds = 7200;
        $currentTimestamp = now()->getTimestamp();
        $privilegeExpiredTs = $currentTimestamp + $expireTimeInSeconds;

        $token = RtcTokenBuilder::buildTokenWithUid($appID, $appCertificate, $channelName, 0, $role, $privilegeExpiredTs);

        return json_encode(['status' => true, 'message' => "generated successfully", 'token' => $token]);
    }

    function uploadFileGivePath(Request $request)
    {
        $rules = [
            'file' => 'required|file',
            'original_name' => 'nullable|string|max:255',
            'file_name' => 'nullable|string|max:255',
            'filename' => 'nullable|string|max:255',
            'name' => 'nullable|string|max:255',
        ];

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->errors()->all();
            $msg = $messages[0];
            return response()->json(['status' => false, 'message' => $msg]);
        }

        $path = GlobalFunction::saveFileAndGivePath($request->file, true);

        return response()->json([
            'status' => true,
            'message' => 'file uploaded successfully',
            'path' => $path
        ]);
    }

    function changeTaxStatus($id, $value)
    {
        $item = Taxes::find($id);
        $item->status = $value;
        $item->save();

        return response()->json(['status' => true, 'message' => 'value changes successfully']);
    }

    function fetchGlobalSettings(Request $request)
    {
        $settings = GlobalSettings::first();
        $taxes = Taxes::where('status', 1)->get();
        $reminders = AppointmentReminders::all();

        $settings->reminders = $reminders;
        $settings->taxes = $taxes;
        $settings->defaultScroll = (bool) $settings->defaultScroll;
        
        $data =  "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";
        $key="Et2Ik2MEEnCcYdTIiJrksAeAVKaAfSEgVSrJLD9q5fspZrq";
            $iv = random_bytes(16); // 16 bytes IV

            $encrypted = openssl_encrypt(
                $data,
                'AES-256-CBC',
                $key,
                OPENSSL_RAW_DATA,
                $iv
            );

            // final format: iv:data
            $open_ai_key = base64_encode($iv) . ':' . base64_encode($encrypted);
            $settings->open_ai_key = $open_ai_key;
            $settings->open_ai_key = $data;
        return GlobalFunction::sendDataResponse(true, 'settings fetched success', $settings);
    }
    function changePassword(Request $request)
    {
        $admin = Admin::where('user_type', 1)->first();
        if ($admin->user_password == $request->old_password) {
            $admin->user_password = $request->new_password;
            $admin->save();
            return response()->json(['status' => true, 'message' => 'Password changed successfully']);
        } else {
            return response()->json(['status' => false, 'message' => 'Incorrect Old password !']);
        }
    }

    function updatePaymentSettings(Request $request)
    {
        $settings = GlobalSettings::first();
        $settings->payment_gateway = $request->payment_gateway;

        $settings->stripe_secret = $request->stripe_secret;
        $settings->stripe_publishable_key = $request->stripe_publishable_key;
        $settings->stripe_currency_code = $request->stripe_currency_code;

        $settings->razorpay_key = $request->razorpay_key;
        $settings->razorpay_currency_code = $request->razorpay_currency_code;

        $settings->paystack_secret_key = $request->paystack_secret_key;
        $settings->paystack_public_key = $request->paystack_public_key;
        $settings->paystack_currency_code = $request->paystack_currency_code;

        $settings->paypal_client_id = $request->paypal_client_id;
        $settings->paypal_secret_key = $request->paypal_secret_key;
        $settings->paypal_currency_code = $request->paypal_currency_code;

        $settings->flutterwave_public_key = $request->flutterwave_public_key;
        $settings->flutterwave_secret_key = $request->flutterwave_secret_key;
        $settings->flutterwave_encryption_key = $request->flutterwave_encryption_key;
        $settings->flutterwave_currency_code = $request->flutterwave_currency_code;

        $settings->sslcommerz_store_id = $request->sslcommerz_store_id;
        $settings->sslcommerz_store_passwd = $request->sslcommerz_store_passwd;
        $settings->sslcommerz_currency_code = $request->sslcommerz_currency_code;

        $settings->save();

        return GlobalFunction::sendSimpleResponse(true, 'value changed successfully');
    }
    function updateGlobalSettings(Request $request)
    {
        $settings = GlobalSettings::first();
        $settings->currency = $request->currency;
        $settings->support_email = $request->support_email;
        $settings->comission = $request->comission;
        $settings->min_amount_payout_doctor = $request->min_amount_payout_doctor;
        $settings->max_order_at_once = $request->max_order_at_once;
        $settings->chatbot_name = $request->chatbot_name;
        $settings->chatgpt_token = $request->chatgpt_token;
        if($request->has('chatbot_thumb')){
            $settings->chatbot_thumb = GlobalFunction::saveFileAndGivePath($request->chatbot_thumb);
        }
        $settings->save();

        return GlobalFunction::sendSimpleResponse(true, 'value changed successfully');
    }
    function settings(Request $request)
    {
        $settings = GlobalSettings::first();
        return view('settings', [
            'data' => $settings
        ]);
    }

    function emrMasterData()
    {
        $rows = EmrSearchMaster::where('is_deleted', 0)->orderBy('id', 'DESC')->get();
        $diagnosisTypes = $rows->where('category', 'diagnosis_type')
            ->pluck('name')
            ->map(fn($item) => trim((string) $item))
            ->filter(fn($item) => $item !== '')
            ->values();

        return view('emrMasterData', [
            'diagnosisTypes' => $diagnosisTypes,
            'diagnosis' => $rows->where('category', 'diagnosis')->values(),
            'labOrders' => $rows->where('category', 'lab_order')->values(),
            'radiologyOrders' => $rows->where('category', 'radiology_order')->values(),
            'drugs' => $rows->where('category', 'drug')->values(),
            'diagnosisTypeRows' => $rows->where('category', 'diagnosis_type')->values(),
        ]);
    }

    function addEmrMasterData(Request $request)
    {
        $request->validate([
            'category' => 'required|in:diagnosis_type,diagnosis,lab_order,radiology_order,drug',
            'name' => 'required|string|max:255',
            'diagnosis_type' => 'nullable|string|max:100',
        ]);

        $category = trim((string) $request->category);
        $name = trim((string) $request->name);
        $diagnosisType = $category === 'diagnosis' ? trim((string) $request->diagnosis_type) : null;

        if ($category === 'diagnosis' && $diagnosisType === '') {
            return redirect()->back()->with('error', 'Diagnosis type is required for diagnosis category.');
        }

        if ($category === 'diagnosis_type') {
            $diagnosisType = null;
        }

        $alreadyExists = EmrSearchMaster::where('is_deleted', 0)
            ->where('category', $category)
            ->where('name', $name)
            ->where('diagnosis_type', $diagnosisType)
            ->exists();

        if ($alreadyExists) {
            return redirect()->back()->with('error', 'This value already exists.');
        }

        EmrSearchMaster::create([
            'category' => $category,
            'diagnosis_type' => $diagnosisType,
            'name' => $name,
            'is_deleted' => 0,
        ]);

        return redirect()->back()->with('success', 'EMR master data added successfully.');
    }

    function deleteEmrMasterData($id)
    {
        $item = EmrSearchMaster::find($id);
        if ($item) {
            $item->is_deleted = 1;
            $item->save();
        }

        return redirect()->back()->with('success', 'EMR master data deleted successfully.');
    }

    function bulkUploadEmrMasterData(Request $request)
    {
        $request->validate([
            'category' => 'required|in:diagnosis_type,diagnosis,lab_order,radiology_order,drug',
            'customer_file' => 'required|file|mimes:csv,txt,xlsx,xls|max:10240',
        ]);

        $category = trim((string) $request->category);
        $file = $request->file('customer_file');
        $extension = strtolower((string) $file->getClientOriginalExtension());

        try {
            $rows = in_array($extension, ['xlsx', 'xls'], true)
                ? (new FastExcel)->import($file)
                : $this->parseCsvRows($file->getRealPath());
        } catch (\Throwable $e) {
            if (stripos($e->getMessage(), 'ZipArchive') !== false) {
                return redirect()->back()->with(
                    'error',
                    'XLSX upload requires PHP Zip extension. Please enable extension=zip in XAMPP PHP and restart Apache.'
                );
            }

            return redirect()->back()->with('error', 'Unable to read uploaded file. Please verify file format and try again.');
        }

        $inserted = 0;
        $duplicates = 0;
        $invalid = 0;

        foreach ($rows as $row) {
            $name = trim((string) ($row['name'] ?? $row['Name'] ?? ''));
            $diagnosisType = trim((string) ($row['diagnosis_type'] ?? $row['Diagnosis Type'] ?? $row['diagnosis type'] ?? ''));

            if ($name === '') {
                $invalid++;
                continue;
            }

            if ($category !== 'diagnosis') {
                $diagnosisType = null;
            } elseif ($diagnosisType === '') {
                $invalid++;
                continue;
            }

            $alreadyExists = EmrSearchMaster::where('is_deleted', 0)
                ->where('category', $category)
                ->where('name', $name)
                ->where('diagnosis_type', $diagnosisType)
                ->exists();

            if ($alreadyExists) {
                $duplicates++;
                continue;
            }

            EmrSearchMaster::create([
                'category' => $category,
                'diagnosis_type' => $diagnosisType,
                'name' => $name,
                'is_deleted' => 0,
            ]);

            $inserted++;
        }

        return redirect()->back()->with(
            'success',
            "Bulk upload completed. Inserted: {$inserted}, Duplicates skipped: {$duplicates}, Invalid rows skipped: {$invalid}."
        );
    }

    function downloadEmrMasterTemplate(Request $request)
    {
        $request->validate([
            'category' => 'required|in:diagnosis_type,diagnosis,lab_order,radiology_order,drug',
        ]);

        $category = trim((string) $request->category);
        $templateFiles = [
            'diagnosis_type' => 'diagnosis_types.xlsx',
            'diagnosis' => 'diagnosis_data.xlsx',
            'lab_order' => 'lap_order.xlsx',
            'radiology_order' => 'radiology_orders.xlsx',
            'drug' => 'drug_list.xlsx',
        ];
        $fileName = $templateFiles[$category];
        $filePath = resource_path('views/emr_excel files/' . $fileName);

        return response()->download($filePath, $fileName, [
                    'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ]);
    }

    function downloadLabOrderDummyExcel()
    {
        $fileName = 'lap_order.xlsx';
        $filePath = resource_path('views/emr_excel files/' . $fileName);
            return response()->download($filePath, $fileName, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);
    }

    private function parseCsvRows(string $filePath): array
    {
        $rows = [];
        if (!is_readable($filePath)) {
            return $rows;
        }

        $file = new \SplFileObject($filePath);
        $file->setFlags(\SplFileObject::READ_CSV | \SplFileObject::SKIP_EMPTY);

        $headers = [];
        foreach ($file as $index => $columns) {
            if (!is_array($columns) || count($columns) === 0) {
                continue;
            }

            if ($index === 0) {
                $headers = array_map(function ($value) {
                    return strtolower(trim((string) $value));
                }, $columns);
                continue;
            }

            $row = [];
            foreach ($headers as $keyIndex => $header) {
                if ($header === '') {
                    continue;
                }
                $row[$header] = trim((string) ($columns[$keyIndex] ?? ''));
            }

            if (!empty(array_filter($row, fn($value) => $value !== ''))) {
                $rows[] = $row;
            }
        }

        return $rows;
    }


    function doctorCategories()
    {
        return view('doctorCategories');
    }

    function editDoctorNotification(Request $request)
    {
        $item = DoctorNotifications::find($request->id);
        $item->title = $request->title;
        $item->description = $request->description;
        $item->save();
        return GlobalFunction::sendSimpleResponse(true, 'Notification edited successfully');
    }
    function editUserNotification(Request $request)
    {
        $item = UserNotification::find($request->id);
        $item->title = $request->title;
        $item->description = $request->description;
        $item->save();
        return GlobalFunction::sendSimpleResponse(true, 'User Notification edited successfully');
    }
    function addDoctorNotification(Request $request)
    {
        $item = new DoctorNotifications();
        $item->title = $request->title;
        $item->description = $request->description;
        $item->save();
        try {
        GlobalFunction::sendPushNotificationToDoctors($item->title, $item->description);
        } catch (\Throwable $e) {
            \Log::error('Doctor push notification failed: ' . $e->getMessage());
        }
        return GlobalFunction::sendSimpleResponse(true, 'Notification added successfully');
    }
    function addUserNotification(Request $request)
    {
        $item = new UserNotification();
        $item->title = $request->title;
        $item->description = $request->description;
        $item->save();

        try {
        GlobalFunction::sendPushNotificationToUsers($item->title, $item->description);
        } catch (\Throwable $e) {
            \Log::error('User push notification failed: ' . $e->getMessage());
        }

        return GlobalFunction::sendSimpleResponse(true, 'User Notification added successfully');
    }
    function notifications()
    {
        return view('notifications');
    }
    function banners()
    {
        return view('banners');
    }
    function deleteDoctorCatSuggestion($id)
    {
        $item = DoctorCatSuggestions::find($id);
        $item->delete();

        return GlobalFunction::sendSimpleResponse(true, 'Item deleted successfully');
    }
    function deleteDoctorNotification($id)
    {
        $item = DoctorNotifications::find($id);
        $item->delete();

        return GlobalFunction::sendSimpleResponse(true, 'Doctor Notification deleted successfully');
    }
    function deleteUserNotification($id)
    {
        $item = UserNotification::find($id);
        $item->delete();

        return GlobalFunction::sendSimpleResponse(true, 'User Notification deleted successfully');
    }
    function deleteDoctorCat($id)
    {
        $cat = DoctorCategories::find($id);
        $cat->is_deleted = 1;
        $cat->save();

        return GlobalFunction::sendSimpleResponse(true, 'cat deleted successfully');
    }
    function addDoctorCat(Request $request)
    {
        $cat = new DoctorCategories();
        $cat->title = $request->title;
        $cat->keywords = $request->keywords;
        $cat->info = $request->info;
        $ta = new GoogleTranslate('ar');
        $cat->ar_title = $ta->translate($request->title);
        $cat->ar_info = $ta->translate($request->info);
        $tf = new GoogleTranslate('fr');
        $cat->fr_title = $tf->translate($request->title);
        $cat->fr_info = $tf->translate($request->info);
        $th = new GoogleTranslate('hi');
        $cat->hi_title = $th->translate($request->title);
        $cat->hi_info = $request->info != null ? $th->translate($request->info) : null;
        $tu = new GoogleTranslate('ur');
        $cat->ur_title = $tu->translate($request->title);
        $cat->ur_info = $request->info != null ? $tu->translate($request->info) : null;
        $cat->image = GlobalFunction::saveFileAndGivePath($request->image);
        $cat->save();

        return GlobalFunction::sendSimpleResponse(true, 'cat added successfully');
    }



    function userWalletRecharge()
    {
        return view('userWalletRecharge');
    }
    function fetchDoctorNotificationList(Request $request)
    {
        $totalData =  DoctorNotifications::count();
        $rows = DoctorNotifications::orderBy('id', 'DESC')->get();

        $result = $rows;

        $columns = array(
            0 => 'id',
            1 => 'fullname',
            2 => 'identity',
            3 => 'username',
        );

        $limit = $request->input('length');
        $start = $request->input('start');
        $order = $columns[$request->input('order.0.column')];
        $dir = $request->input('order.0.dir');

        $totalFiltered = $totalData;
        if (empty($request->input('search.value'))) {
            $result = DoctorNotifications::offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
        } else {
            $search = $request->input('search.value');
            $result =  DoctorNotifications::Where('title', 'LIKE', "%{$search}%")
                ->orWhere('description', 'LIKE', "%{$search}%")
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
            $totalFiltered = DoctorNotifications::Where('id', 'LIKE', "%{$search}%")
                ->orWhere('description', 'LIKE', "%{$search}%")
                ->count();
        }
        $data = array();
        foreach ($result as $item) {
            $title = '<span class="text-dark font-weight-bold font-16">' . $item->title . '</span><br>';
            $desc = '<span>' . $item->description . '</span>';
            $notification = $title . $desc;

            $edit = '<a href="" data-description="' . $item->description . '" data-title="' . $item->title . '" class="mr-2 btn btn-primary text-white edit" rel=' . $item->id . ' >' . __("Edit") . '</a>';
            $delete = '<a href="" class="mr-2 btn btn-danger text-white delete" rel=' . $item->id . ' >' . __("Delete") . '</a>';
            $action =  $edit . $delete;


            $data[] = array(
                $notification,
                $action
            );
        }
        $json_data = array(
            "draw"            => intval($request->input('draw')),
            "recordsTotal"    => intval($totalData),
            "recordsFiltered" => $totalFiltered,
            "data"            => $data
        );
        echo json_encode($json_data);
        exit();
    }
    function fetchUserNotificationList(Request $request)
    {
        $totalData =  UserNotification::count();
        $rows = UserNotification::orderBy('id', 'DESC')->get();

        $result = $rows;

        $columns = array(
            0 => 'id',
            1 => 'fullname',
            2 => 'identity',
            3 => 'username',
        );

        $limit = $request->input('length');
        $start = $request->input('start');
        $order = $columns[$request->input('order.0.column')];
        $dir = $request->input('order.0.dir');

        $totalFiltered = $totalData;
        if (empty($request->input('search.value'))) {
            $result = UserNotification::offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
        } else {
            $search = $request->input('search.value');
            $result =  UserNotification::Where('title', 'LIKE', "%{$search}%")
                ->orWhere('description', 'LIKE', "%{$search}%")
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
            $totalFiltered = UserNotification::Where('id', 'LIKE', "%{$search}%")
                ->orWhere('description', 'LIKE', "%{$search}%")
                ->count();
        }
        $data = array();
        foreach ($result as $item) {
            $title = '<span class="text-dark font-weight-bold font-16">' . $item->title . '</span><br>';
            $desc = '<span>' . $item->description . '</span>';
            $notification = $title . $desc;

            $edit = '<a href="" data-description="' . $item->description . '" data-title="' . $item->title . '" class="mr-2 btn btn-primary text-white edit" rel=' . $item->id . ' >' . __("Edit") . '</a>';
            $delete = '<a href="" class="mr-2 btn btn-danger text-white delete" rel=' . $item->id . ' >' . __("Delete") . '</a>';
            $action =  $edit . $delete;


            $data[] = array(
                $notification,
                $action
            );
        }
        $json_data = array(
            "draw"            => intval($request->input('draw')),
            "recordsTotal"    => intval($totalData),
            "recordsFiltered" => $totalFiltered,
            "data"            => $data
        );
        echo json_encode($json_data);
        exit();
    }

    function fetchDoctorCatSuggestionsList(Request $request)
    {
        $totalData =  DoctorCatSuggestions::count();
        $rows = DoctorCatSuggestions::orderBy('id', 'DESC')->get();
        $settings = GlobalSettings::first();

        $result = $rows;

        $columns = array(
            0 => 'id',
            1 => 'fullname',
            2 => 'identity',
            3 => 'username',
        );

        $limit = $request->input('length');
        $start = $request->input('start');
        $order = $columns[$request->input('order.0.column')];
        $dir = $request->input('order.0.dir');

        $totalFiltered = $totalData;
        if (empty($request->input('search.value'))) {
            $result = DoctorCatSuggestions::offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
        } else {
            $search = $request->input('search.value');
            $result =  DoctorCatSuggestions::Where('title', 'LIKE', "%{$search}%")
                ->orWhere('about', 'LIKE', "%{$search}%")
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
            $totalFiltered = DoctorCatSuggestions::Where('title', 'LIKE', "%{$search}%")
                ->orWhere('about', 'LIKE', "%{$search}%")
                ->count();
        }
        $data = array();
        foreach ($result as $item) {

            $delete = '<a href="" class="mr-2 btn btn-danger text-white delete" rel=' . $item->id . ' >' . __("Delete") . '</a>';
            $action =   $delete;


            $data[] = array(
                $item->title,
                $item->about,
                $action
            );
        }
        $json_data = array(
            "draw"            => intval($request->input('draw')),
            "recordsTotal"    => intval($totalData),
            "recordsFiltered" => $totalFiltered,
            "data"            => $data
        );
        echo json_encode($json_data);
        exit();
    }
    function fetchDoctorCatsList(Request $request)
    {
        $totalData =  DoctorCategories::where('is_deleted', 0)->count();
        $rows = DoctorCategories::where('is_deleted', 0)->orderBy('id', 'DESC')->get();
        $settings = GlobalSettings::first();

        $result = $rows;

        $columns = array(
            0 => 'id',
            1 => 'fullname',
            2 => 'identity',
            3 => 'username',
        );

        $limit = $request->input('length');
        $start = $request->input('start');
        $order = $columns[$request->input('order.0.column')];
        $dir = $request->input('order.0.dir');

        $totalFiltered = $totalData;
        if (empty($request->input('search.value'))) {
            $result = DoctorCategories::where('is_deleted', 0)->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
        } else {
            $search = $request->input('search.value');
            $result =  DoctorCategories::where('is_deleted', 0)
                ->Where('title', 'LIKE', "%{$search}%")
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
            $totalFiltered = DoctorCategories::where('is_deleted', 0)
                ->Where('title', 'LIKE', "%{$search}%")
                ->count();
        }
        $data = array();
        foreach ($result as $item) {


            $imgUrl = "http://placehold.jp/150x150.png";
            if ($item->image == null) {
                $img = '<img src="http://placehold.jp/150x150.png" width="50" height="50">';
            } else {
                $imgUrl = GlobalFunction::createMediaUrl($item->image);
                $img = '<img src="' . $imgUrl . '" width="50" height="50">';
            }

            $edit = '<a data-icon="' . $imgUrl . '" data-title="' . $item->title . '" data-keywords="' . $item->keywords . '"  data-info="' . $item->info . '" href="" class="mr-2 btn btn-primary text-white edit" rel=' . $item->id . ' >' . __("Edit") . '</a>';
            $delete = '<a href="" class="mr-2 btn btn-danger text-white delete" rel=' . $item->id . ' >' . __("Delete") . '</a>';
            $action =  $edit . $delete;


            $data[] = array(
                $item->id,
                $img,
                $item->title,
                $item->keywords,
                $item->info,
                $action
            );
        }
        $json_data = array(
            "draw"            => intval($request->input('draw')),
            "recordsTotal"    => intval($totalData),
            "recordsFiltered" => $totalFiltered,
            "data"            => $data
        );
        echo json_encode($json_data);
        exit();
    }

    function fetchWalletRechargeList(Request $request)
    {
        $totalData =  UserWalletRechargeLogs::count();
        $rows = UserWalletRechargeLogs::orderBy('id', 'DESC')->get();
        $settings = GlobalSettings::first();

        $result = $rows;

        $columns = array(
            0 => 'id',
            1 => 'fullname',
            2 => 'identity',
            3 => 'username',
        );

        $limit = $request->input('length');
        $start = $request->input('start');
        $order = $columns[$request->input('order.0.column')];
        $dir = $request->input('order.0.dir');

        $totalFiltered = $totalData;
        if (empty($request->input('search.value'))) {
            $result = UserWalletRechargeLogs::offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
        } else {
            $search = $request->input('search.value');
            $result =  UserWalletRechargeLogs::Where('amount', 'LIKE', "%{$search}%")
                ->orWhere('transaction_summary', 'LIKE', "%{$search}%")
                ->orWhere('transaction_id', 'LIKE', "%{$search}%")
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
            $totalFiltered = UserWalletRechargeLogs::Where('amount', 'LIKE', "%{$search}%")
                ->orWhere('transaction_summary', 'LIKE', "%{$search}%")
                ->orWhere('transaction_id', 'LIKE', "%{$search}%")
                ->count();
        }
        $data = array();
        foreach ($result as $item) {

            $date = $item->created_at->format('d M, Y');

            $summery = '<span class="summery_scroll"> ' . $item->transaction_summary . '  </span>';
            $rechargDate = '<span style="white-space: nowrap;"> ' . $date . '  </span>';

            $data[] = array(
                $item->user != null ? $item->user->fullname : "",
                $settings->currency . $item->amount,
                GlobalFunction::detectPaymentGateway($item->gateway),
                $item->transaction_id,
                $summery,
                $rechargDate,
            );
        }
        $json_data = array(
            "draw"            => intval($request->input('draw')),
            "recordsTotal"    => intval($totalData),
            "recordsFiltered" => $totalFiltered,
            "data"            => $data
        );
        echo json_encode($json_data);
        exit();
    }
    function fetchBookingAndPaymentList(Request $request)
    {
        $totalData =  Appointments::where('payment_status', '!=', 0)->count();
        $rows = Appointments::with('user', 'user_plan')->where('payment_status', '!=', 0)->orderBy('id', 'DESC')->get();
        $settings = GlobalSettings::first();

        $result = $rows;

        $columns = array(
            0 => 'id',
            1 => 'fullname',
            2 => 'identity',
            3 => 'username',
        );

        $limit = $request->input('length');
        $start = $request->input('start');
        $order = $columns[$request->input('order.0.column')];
        $dir = $request->input('order.0.dir');

        $totalFiltered = $totalData;
        if (empty($request->input('search.value'))) {
            $result = Appointments::with('user', 'user_plan')->where('payment_status', '!=', 0)->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
        } else {
            $search = $request->input('search.value');
            $result =  Appointments::where('payment_status', '!=', 0)->Where('appointment_number', 'LIKE', "%{$search}%")
                ->with('user', 'user_plan')
                ->orWhere('appointment_number', 'LIKE', "%{$search}%")
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
            $totalFiltered = Appointments::with('user', 'user_plan')->where('payment_status', '!=', 0)->Where('appointment_number', 'LIKE', "%{$search}%")
                ->orWhere('appointment_number', 'LIKE', "%{$search}%")
                ->count();
        }
        $data = array();
        foreach ($result as $item) {

            $delete = '<a href="" class="mr-2 btn btn-danger text-white delete" rel=' . $item->id . ' >' . __("Delete") . '</a>';
            $action =  $delete;

            $doctor = '<a href="' . route('viewDoctorProfile', $item->doctor->id) . '"><span class="badge bg-primary text-white">
                        ' . $item->doctor->name . '</span></a>';

            $user = '<a href="' . route('viewUserProfile', $item->user->id) . '"><span class="badge bg-primary text-white">
                        ' . $item->user->fullname . '</span></a>';
            $data[] = array(
                $item->appointment_number,
                $item->order_id,
                $item->speciality_name ?? "",
                $user,
                $settings->currency . $item->payable_amount,    
                GlobalFunction::returnAppointmentPaymentStatus($item->payment_status),
                $item->user_plan_id != 0 
                ? $item->user_plan->created_at?->format('Y-m-d')
                 : $item->created_at->format('Y-m-d'),
                $item->user_plan ? $item->user_plan->plan_name : "-",
                $item->user_plan ? "CCAVENUE" : "-",
                // $item->appointment != null ? $settings->currency . $item->appointment->payable_amount : "",
                // $item->commission_percentage . "%",
                // $item->appointment != null ? $item->appointment->appointment_number : "",
                // $doctor,
                // $item->created_at->format('d M, Y'),
                // $action,
            );
        }
        $json_data = array(
            "draw"            => intval($request->input('draw')),
            "recordsTotal"    => intval($totalData),
            "recordsFiltered" => $totalFiltered,
            "data"            => $data
        );
        echo json_encode($json_data);
        exit();
    }

    function fetchPlatformEarningsList(Request $request)
    {
        $totalData =  PlatformEarningHistory::count();
        $rows = PlatformEarningHistory::orderBy('id', 'DESC')->get();
        $settings = GlobalSettings::first();

        $result = $rows;

        $columns = array(
            0 => 'id',
            1 => 'fullname',
            2 => 'identity',
            3 => 'username',
        );

        $limit = $request->input('length');
        $start = $request->input('start');
        $order = $columns[$request->input('order.0.column')];
        $dir = $request->input('order.0.dir');

        $totalFiltered = $totalData;
        if (empty($request->input('search.value'))) {
            $result = PlatformEarningHistory::offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
        } else {
            $search = $request->input('search.value');
            $result =  PlatformEarningHistory::Where('earning_number', 'LIKE', "%{$search}%")
                ->orWhere('amount', 'LIKE', "%{$search}%")
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
            $totalFiltered = PlatformEarningHistory::Where('earning_number', 'LIKE', "%{$search}%")
                ->orWhere('amount', 'LIKE', "%{$search}%")
                ->count();
        }
        $data = array();
        foreach ($result as $item) {

            $delete = '<a href="" class="mr-2 btn btn-danger text-white delete" rel=' . $item->id . ' >' . __("Delete") . '</a>';
            $action =  $delete;

            $doctor = '<a href="' . route('viewDoctorProfile', $item->doctor->id) . '"><span class="badge bg-primary text-white">
                        ' . $item->doctor->name . '</span></a>';
            $data[] = array(
                $item->earning_number,
                $settings->currency . $item->amount,
                $item->appointment != null ? $settings->currency . $item->appointment->payable_amount : "",
                $item->commission_percentage . "%",
                $item->appointment != null ? $item->appointment->appointment_number : "",
                $doctor,
                $item->created_at->format('d M, Y'),
                $action,
            );
        }
        $json_data = array(
            "draw"            => intval($request->input('draw')),
            "recordsTotal"    => intval($totalData),
            "recordsFiltered" => $totalFiltered,
            "data"            => $data
        );
        echo json_encode($json_data);
        exit();
    }

    function platformEarnings()
    {
        return view('platformEarnings');
    }

    function bookingAndPayment(){
        return view('bookingAndPayment');
    }

    function getFaqCats()
    {
        $cats = FaqCats::all();
        return GlobalFunction::sendDataResponse(true, 'cats fetched successfully!', $cats);
    }
    function editDoctorCat(Request $request)
    {
        $item = DoctorCategories::find($request->id);
        $item->title = $request->title;
        $item->keywords = $request->keywords;
        $item->info = $request->info;
        $ta = new GoogleTranslate('ar');
        $item->ar_title = $ta->translate($request->title);
        $item->ar_info = $ta->translate($request->info);
        $tf = new GoogleTranslate('fr');
        $item->fr_title = $tf->translate($request->title);
        $item->fr_info = $tf->translate($request->info);
        $th = new GoogleTranslate('hi');
        $item->hi_title = $th->translate($request->title);
        $item->hi_info = $request->info != null ? $th->translate($request->info) : null;
        $tu = new GoogleTranslate('ur');
        $item->ur_title = $tu->translate($request->title);
        $item->ur_info = $request->info != null ? $tu->translate($request->info) : null;
        if ($request->has('image')) {
            $item->image = GlobalFunction::saveFileAndGivePath($request->image);
        }

        $item->save();
        return GlobalFunction::sendSimpleResponse(true, 'Cat edited successfully');
    }
    function editFaq(Request $request)
    {
        $faq = Faqs::find($request->id);
        $faq->question = $request->question;
        $faq->answer = $request->answer;
        $faq->category_id = $request->category_id;
        $faq->save();
        return GlobalFunction::sendSimpleResponse(true, 'FAQ edited successfully');
    }

    function addFaq(Request $request)
    {
        $faq = new Faqs();
        $faq->question = $request->question;
        $faq->answer = $request->answer;
        $faq->category_id = $request->category_id;
        $faq->save();
        return GlobalFunction::sendSimpleResponse(true, 'FAQ added successfully');
    }

    function deleteFaq($id)
    {
        $faqCat = Faqs::find($id);
        $faqCat->delete();
        return GlobalFunction::sendSimpleResponse(true, 'Faq deleted successfully');
    }
    function deletePlatformEarningItem($id)
    {
        $item = PlatformEarningHistory::find($id);
        $item->delete();
        return GlobalFunction::sendSimpleResponse(true, 'Earning history deleted successfully');
    }
    function deleteFaqCat($id)
    {
        $faqCat = FaqCats::find($id);
        $faqCat->delete();
        Faqs::where('category_id', $id)->delete();
        return GlobalFunction::sendSimpleResponse(true, 'Category deleted successfully');
    }
    function editFaqCategory(Request $request)
    {
        $faqCat = FaqCats::find($request->id);
        $faqCat->title = $request->title;
        $faqCat->save();
        return GlobalFunction::sendSimpleResponse(true, 'Category edited successfully');
    }
    function addFaqCategory(Request $request)
    {
        $faqCat = new FaqCats();
        $faqCat->title = $request->title;
        $faqCat->save();
        return GlobalFunction::sendSimpleResponse(true, 'Category added successfully');
    }
    function faqs()
    {
        $cats = FaqCats::all();
        return view('faqs', [
            'cats' => $cats
        ]);
    }
    function fetchFaqList(Request $request)
    {
        $totalData =  Faqs::count();
        $rows = Faqs::orderBy('id', 'DESC')->get();

        $result = $rows;

        $columns = array(
            0 => 'id',
            1 => 'fullname',
            2 => 'identity',
            3 => 'username',
        );

        $limit = $request->input('length');
        $start = $request->input('start');
        $order = $columns[$request->input('order.0.column')];
        $dir = $request->input('order.0.dir');

        $totalFiltered = $totalData;
        if (empty($request->input('search.value'))) {
            $result = Faqs::offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
        } else {
            $search = $request->input('search.value');
            $result =  Faqs::Where('question', 'LIKE', "%{$search}%")
                ->orWhere('answer', 'LIKE', "%{$search}%")
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
            $totalFiltered = Faqs::Where('question', 'LIKE', "%{$search}%")
                ->orWhere('answer', 'LIKE', "%{$search}%")
                ->count();
        }
        $data = array();
        foreach ($result as $item) {
            $edit = '<a data-cat="' . $item->category_id . '" data-answer="' . e($item->answer) . '" data-question="' . e($item->question) . '" href="" class="mr-2 btn btn-primary text-white edit" rel=' . $item->id . ' >' . __("Edit") . '</a>';

            $delete = '<a href="" class="mr-2 btn btn-danger text-white delete" rel=' . $item->id . ' >' . __("Delete") . '</a>';
            $action = $edit . $delete;

            $categoryTitle = optional($item->category)->title ?? __('Uncategorized');
            $category = '<span class="badge bg-primary text-white">' . e($categoryTitle) . '</span>';

            $data[] = array(
                $item->question,
                $item->answer,
                $category,
                $action,
            );
        }
        $json_data = array(
            "draw"            => intval($request->input('draw')),
            "recordsTotal"    => intval($totalData),
            "recordsFiltered" => $totalFiltered,
            "data"            => $data
        );
        echo json_encode($json_data);
        exit();
    }
    function fetchFaqCatsList(Request $request)
    {
        $totalData =  FaqCats::count();
        $rows = FaqCats::orderBy('id', 'DESC')->get();

        $result = $rows;

        $columns = array(
            0 => 'id',
            1 => 'fullname',
            2 => 'identity',
            3 => 'username',
        );

        $limit = $request->input('length');
        $start = $request->input('start');
        $order = $columns[$request->input('order.0.column')];
        $dir = $request->input('order.0.dir');

        $totalFiltered = $totalData;
        if (empty($request->input('search.value'))) {
            $result = FaqCats::offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
        } else {
            $search = $request->input('search.value');
            $result =  FaqCats::Where('title', 'LIKE', "%{$search}%")
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
            $totalFiltered = FaqCats::Where('title', 'LIKE', "%{$search}%")
                ->count();
        }
        $data = array();
        foreach ($result as $item) {
            $edit = '<a data-title="' . $item->title . '" href="" class="mr-2 btn btn-primary text-white edit" rel=' . $item->id . ' >' . __("Edit") . '</a>';

            $delete = '<a href="" class="mr-2 btn btn-danger text-white delete" rel=' . $item->id . ' >' . __("Delete") . '</a>';
            $action = $edit . $delete;

            $data[] = array(
                $item->title,
                $action,
            );
        }
        $json_data = array(
            "draw"            => intval($request->input('draw')),
            "recordsTotal"    => intval($totalData),
            "recordsFiltered" => $totalFiltered,
            "data"            => $data
        );
        echo json_encode($json_data);
        exit();
    }
    function deleteReview($id)
    {
        $review = DoctorReviews::find($id);
        $doctor = $review->doctor;
        $review->delete();

        $doctor->rating = $doctor->avgRating();
        $doctor->save();

        return Globalfunction::sendSimpleResponse(true, 'rating deleted successfully !');
    }
    function fetchAllReviewsList(Request $request)
    {
        $totalData =  DoctorReviews::with(['doctor', 'appointment'])->count();
        $rows = DoctorReviews::with(['doctor', 'appointment'])->orderBy('id', 'DESC')->get();

        $result = $rows;

        $columns = array(
            0 => 'id',
            1 => 'fullname',
            2 => 'identity',
            3 => 'username',
        );

        $limit = $request->input('length');
        $start = $request->input('start');
        $order = $columns[$request->input('order.0.column')];
        $dir = $request->input('order.0.dir');

        $totalFiltered = $totalData;
        if (empty($request->input('search.value'))) {
            $result = DoctorReviews::with(['doctor', 'appointment'])

                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
        } else {
            $search = $request->input('search.value');
            $result =  DoctorReviews::with(['doctor', 'appointment'])
                ->whereHas('appointment', function ($q) use ($search) {
                    $q->where('appointment_number', 'LIKE', "%{$search}%");
                })
                ->orWhere('comment', 'LIKE', "%{$search}%")
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
            $totalFiltered = DoctorReviews::with(['doctor', 'appointment'])
                ->whereHas('appointment', function ($q) use ($search) {
                    $q->where('appointment_number', 'LIKE', "%{$search}%");
                })
                ->orWhere('comment', 'LIKE', "%{$search}%")
                ->count();
        }
        $data = array();
        foreach ($result as $item) {
            $delete = '<a href="" class="mr-2 btn btn-danger text-white delete" rel=' . $item->id . ' >' . __("Delete") . '</a>';

            $starDisabled = '<i class="fas fa-star starDisabled"></i>';
            $starActive = '<i class="fas fa-star starActive"></i>';

            $ratingBar = '';
            for ($i = 0; $i < 5; $i++) {
                if ($item->rating > $i) {
                    $ratingBar = $ratingBar . $starActive;
                } else {
                    $ratingBar = $ratingBar . $starDisabled;
                }
            }

            $doctor = "";
            if ($item->doctor != null) {
                $doctor = '<a href="' . route('viewDoctorProfile', $item->doctor->id) . '"><span class="badge bg-primary text-white">' . $item->doctor->name . '</span></a>';
            }

            $action = $delete;
            $data[] = array(
                $ratingBar,
                $item->comment,
                $item->appointment != null ? $item->appointment->appointment_number : '',
                $doctor,
                $item->created_at->format('d M, Y'),
                $action,
            );
        }
        $json_data = array(
            "draw"            => intval($request->input('draw')),
            "recordsTotal"    => intval($totalData),
            "recordsFiltered" => $totalFiltered,
            "data"            => $data
        );
        echo json_encode($json_data);
        exit();
    }
    function reviews()
    {
        return view('reviews');
    }
    function deleteCoupon($id)
    {
        $coupon = Coupons::find($id);
        $coupon->delete();
        return GlobalFunction::sendSimpleResponse(true, 'coupon deleted successfully!');
    }
    function deleteTaxItem($id)
    {
        $item = Taxes::find($id);
        $item->delete();
        return GlobalFunction::sendSimpleResponse(true, 'item deleted successfully!');
    }
    function editCouponItem(Request $request)
    {
        $coupon = Coupons::find($request->id);
        $coupon->coupon = $request->coupon;
        $coupon->max_discount_amount = $request->max_discount_amount;
        $coupon->min_order_amount = $request->min_order_amount;
        $coupon->heading = $request->heading;
        $coupon->description = $request->description;
        $coupon->flat = $request->flat;
        $coupon->save();

        return GlobalFunction::sendSimpleResponse(true, 'coupon edited successfully!');
    }
    function addTaxItem(Request $request)
    {
        $item = new Taxes();
        $item->tax_title = $request->tax_title;
        $item->value = $request->value;
        $item->type = $request->type;
        $item->status = 1;
        $item->save();

        return GlobalFunction::sendSimpleResponse(true, 'item added successfully!');
    }
    function editTaxItem(Request $request)
    {
        $item = Taxes::find($request->id);
        $item->tax_title = $request->tax_title;
        $item->value = $request->value;
        $item->type = $request->type;
        $item->save();

        return GlobalFunction::sendSimpleResponse(true, 'item edited successfully!');
    }
    function addCouponItem(Request $request)
    {
        $coupon = new Coupons();
        $coupon->coupon = $request->coupon;
        $coupon->max_discount_amount = $request->max_discount_amount;
        $coupon->percentage = $request->percentage;
        $coupon->heading = $request->heading;
        $coupon->description = $request->description;
        $coupon->flat = $request->flat;
        $coupon->save();

        return GlobalFunction::sendSimpleResponse(true, 'coupon added successfully!');
    }
    function fetchAllTaxList(Request $request)
    {
        $totalData =  Taxes::count();
        $rows = Taxes::orderBy('id', 'DESC')->get();

        $result = $rows;

        $columns = array(
            0 => 'id',
            1 => 'fullname',
            2 => 'identity',
            3 => 'username',
        );

        $limit = $request->input('length');
        $start = $request->input('start');
        $order = $columns[$request->input('order.0.column')];
        $dir = $request->input('order.0.dir');

        $totalFiltered = $totalData;
        if (empty($request->input('search.value'))) {
            $result = Taxes::offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
        } else {
            $search = $request->input('search.value');
            $result =  Taxes::where(function ($query) use ($search) {
                $query->Where('tax_title', 'LIKE', "%{$search}%")
                    ->orWhere('value', 'LIKE', "%{$search}%");
            })->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
            $totalFiltered = Taxes::where(function ($query) use ($search) {
                $query->Where('tax_title', 'LIKE', "%{$search}%")
                    ->orWhere('value', 'LIKE', "%{$search}%");
            })->count();
        }
        $data = array();
        foreach ($result as $item) {

            $type = '';
            if ($item->type == Constants::taxFixed) {
                $type = '<span class="badge bg-primary text-white">' . __('Fixed') . '</span>';
            }
            if ($item->type == Constants::taxPercent) {
                $type = '<span class="badge bg-primary text-white">' . __('Percent') . '</span>';
            }

            $onOff = "";
            if ($item->status == 1) {
                $onOff = '<label class="switch ">
                                <input rel=' . $item->id . ' type="checkbox" class="onoff" checked>
                                <span class="slider round"></span>
                            </label>';
            } else {
                $onOff = '<label class="switch ">
                                <input rel=' . $item->id . ' type="checkbox" class="onoff">
                                <span class="slider round"></span>
                            </label>';
            }

            $edit = '<a data-taxtitle="' . $item->tax_title . '" data-type="' . $item->type . '" href="" data-value="' . $item->value . '"  class="mr-2 btn btn-primary text-white edit" rel=' . $item->id . ' >' . __("Edit") . '</a>';

            $delete = '<a href="" class="mr-2 btn btn-danger text-white delete" rel=' . $item->id . ' >' . __("Delete") . '</a>';
            $action = $edit  . $delete;

            $data[] = array(
                $item->tax_title,
                $type,
                $item->value,
                $onOff,
                $action,
            );
        }
        $json_data = array(
            "draw"            => intval($request->input('draw')),
            "recordsTotal"    => intval($totalData),
            "recordsFiltered" => $totalFiltered,
            "data"            => $data
        );
        echo json_encode($json_data);
        exit();
    }
    function fetchAllCouponsList(Request $request)
    {
        $totalData =  Coupons::count();
        $rows = Coupons::orderBy('id', 'DESC')->get();
        $settings = GlobalSettings::first();

        $result = $rows;

        $columns = array(
            0 => 'id',
            1 => 'fullname',
            2 => 'identity',
            3 => 'username',
        );

        $limit = $request->input('length');
        $start = $request->input('start');
        $order = $columns[$request->input('order.0.column')];
        $dir = $request->input('order.0.dir');

        $totalFiltered = $totalData;
        if (empty($request->input('search.value'))) {
            $result = Coupons::offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
        } else {
            $search = $request->input('search.value');
            $result =  Coupons::where(function ($query) use ($search) {
                $query->Where('coupon', 'LIKE', "%{$search}%")
                    ->orWhere('heading', 'LIKE', "%{$search}%")
                    ->orWhere('percentage', 'LIKE', "%{$search}%")
                    ->orWhere('max_discount_amount', 'LIKE', "%{$search}%")
                    ->orWhere('description', 'LIKE', "%{$search}%");
            })->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
            $totalFiltered = Coupons::where(function ($query) use ($search) {
                $query->Where('coupon', 'LIKE', "%{$search}%")
                    ->orWhere('heading', 'LIKE', "%{$search}%")
                    ->orWhere('max_discount_amount', 'LIKE', "%{$search}%")
                    ->orWhere('percentage', 'LIKE', "%{$search}%")
                    ->orWhere('description', 'LIKE', "%{$search}%");
            })->count();
        }
        $data = array();
        foreach ($result as $item) {
            $edit = '<a data-description="' . $item->description . '" data-heading="' . $item->heading . '" data-percentage="' . $item->percentage . '" data-flat="' . $item->flat . '" data-maxDiscAmount="' . $item->max_discount_amount . '" data-coupon="' . $item->coupon . '" href="" class="mr-2 btn btn-primary text-white edit" rel=' . $item->id . ' >' . __("Edit") . '</a>';

            $delete = '<a href="" class="mr-2 btn btn-danger text-white delete" rel=' . $item->id . ' >' . __("Delete") . '</a>';
            $action = $edit  . $delete;

            $data[] = array(
                $item->coupon,
                $item->percentage . '%',
                $item->flat,
                $settings->currency . $item->max_discount_amount,
                $item->heading,
                $item->description,
                $action,
            );
        }
        $json_data = array(
            "draw"            => intval($request->input('draw')),
            "recordsTotal"    => intval($totalData),
            "recordsFiltered" => $totalFiltered,
            "data"            => $data
        );
        echo json_encode($json_data);
        exit();
    }

    function coupons()
    {
        $settings = GlobalSettings::first();
        return view('coupons', [
            'settings' => $settings
        ]);
    }

    function index()
    {
        $relativePath = Storage::url('avatars/my-avatar.jpg');
        echo $relativePath;
        $settings = GlobalSettings::first();

        $pendingDoctors = Doctors::where('status', Constants::statusDoctorPending)->where('is_profile_complete', 1)->count();
        $activeDoctors = Doctors::where('status', Constants::statusDoctorApproved)->where('is_profile_complete', 1)->count();
        $bannedDoctors = Doctors::where('status', Constants::statusDoctorBanned)->where('is_profile_complete', 1)->count();

        // Today Bookings
        $todayTotalBookings = Appointments::whereDate('created_at', Carbon::now())->count();
        $todayTotalPendingBookings = Appointments::whereDate('created_at', Carbon::today())->where('status', Constants::orderPlacedPending)->count();
        $todayTotalAcceptedBookings = Appointments::whereDate('created_at', Carbon::today())->where('status', Constants::orderAccepted)->count();
        $todayTotalCompletedBookings = Appointments::whereDate('created_at', Carbon::today())->where('status', Constants::orderCompleted)->count();
        $todayTotalCancelledBookings = Appointments::whereDate('created_at', Carbon::today())->where('status', Constants::orderCancelled)->count();
        $todayTotalDeclinedBookings = Appointments::whereDate('created_at', Carbon::today())->where('status', Constants::orderDeclined)->count();
        $todayTotalMissedBookings = Appointments::whereDate('created_at', Carbon::today())->where('status', Constants::orderMissed)->count();

        // Last 7 days
        $last7date = Carbon::now()->subDays(7);
        $last7daysTotalBookings = Appointments::where('created_at', '>=', $last7date)->count();
        $last7daysTotalPendingBookings = Appointments::where('created_at', '>=', $last7date)->where('status', Constants::orderPlacedPending)->count();
        $last7daysTotalAcceptedBookings = Appointments::where('created_at', '>=', $last7date)->where('status', Constants::orderAccepted)->count();
        $last7daysTotalCompletedBookings = Appointments::where('created_at', '>=', $last7date)->where('status', Constants::orderCompleted)->count();
        $last7daysTotalCancelledBookings = Appointments::where('created_at', '>=', $last7date)->where('status', Constants::orderCancelled)->count();
        $last7daysTotalDeclinedBookings = Appointments::where('created_at', '>=', $last7date)->where('status', Constants::orderDeclined)->count();
        $last7daysTotalMissedBookings = Appointments::where('created_at', '>=', $last7date)->where('status', Constants::orderMissed)->count();

        // last 30 days
        $last30date = Carbon::now()->subDays(30);
        $last30daysTotalBookings = Appointments::where('created_at', '>=', $last30date)->count();
        $last30daysTotalPendingBookings = Appointments::where('created_at', '>=', $last30date)->where('status', Constants::orderPlacedPending)->count();
        $last30daysTotalAcceptedBookings = Appointments::where('created_at', '>=', $last30date)->where('status', Constants::orderAccepted)->count();
        $last30daysTotalCompletedBookings = Appointments::where('created_at', '>=', $last30date)->where('status', Constants::orderCompleted)->count();
        $last30daysTotalCancelledBookings = Appointments::where('created_at', '>=', $last30date)->where('status', Constants::orderCancelled)->count();
        $last30daysTotalDeclinedBookings = Appointments::where('created_at', '>=', $last30date)->where('status', Constants::orderDeclined)->count();
        $last30daysTotalMissedBookings = Appointments::where('created_at', '>=', $last30date)->where('status', Constants::orderMissed)->count();

        // last 90 days
        $last90date = Carbon::now()->subDays(90);
        $last90daysTotalBookings = Appointments::where('created_at', '>=', $last90date)->count();
        $last90daysTotalPendingBookings = Appointments::where('created_at', '>=', $last90date)->where('status', Constants::orderPlacedPending)->count();
        $last90daysTotalAcceptedBookings = Appointments::where('created_at', '>=', $last90date)->where('status', Constants::orderAccepted)->count();
        $last90daysTotalCompletedBookings = Appointments::where('created_at', '>=', $last90date)->where('status', Constants::orderCompleted)->count();
        $last90daysTotalCancelledBookings = Appointments::where('created_at', '>=', $last90date)->where('status', Constants::orderCancelled)->count();
        $last90daysTotalDeclinedBookings = Appointments::where('created_at', '>=', $last90date)->where('status', Constants::orderDeclined)->count();
        $last90daysTotalMissedBookings = Appointments::where('created_at', '>=', $last90date)->where('status', Constants::orderMissed)->count();

        // last 180 days
        $last180date = Carbon::now()->subDays(180);
        $last180daysTotalBookings = Appointments::where('created_at', '>=', $last180date)->count();
        $last180daysTotalPendingBookings = Appointments::where('created_at', '>=', $last180date)->where('status', Constants::orderPlacedPending)->count();
        $last180daysTotalAcceptedBookings = Appointments::where('created_at', '>=', $last180date)->where('status', Constants::orderAccepted)->count();
        $last180daysTotalCompletedBookings = Appointments::where('created_at', '>=', $last180date)->where('status', Constants::orderCompleted)->count();
        $last180daysTotalCancelledBookings = Appointments::where('created_at', '>=', $last180date)->where('status', Constants::orderCancelled)->count();
        $last180daysTotalDeclinedBookings = Appointments::where('created_at', '>=', $last180date)->where('status', Constants::orderDeclined)->count();
        $last180daysTotalMissedBookings = Appointments::where('created_at', '>=', $last180date)->where('status', Constants::orderMissed)->count();

        // All time bookings
        $allTimeTotalBookings = Appointments::count();
        $allTimeTotalPendingBookings = Appointments::where('status', Constants::orderPlacedPending)->count();
        $allTimeTotalAcceptedBookings = Appointments::where('status', Constants::orderAccepted)->count();
        $allTimeTotalCompletedBookings = Appointments::where('status', Constants::orderCompleted)->count();
        $allTimeTotalDeclinedBookings = Appointments::where('status', Constants::orderDeclined)->count();
        $allTimeTotalCancelledBookings = Appointments::where('status', Constants::orderCancelled)->count();
        $allTimeTotalMissedBookings = Appointments::where('status', Constants::orderMissed)->count();

        // Platform Earnings
        $todayEarnings = PlatformEarningHistory::whereDate('created_at', Carbon::now())->sum('amount');
        $last7DaysEarnings = PlatformEarningHistory::where('created_at', '>=', $last7date)->sum('amount');
        $last30DaysEarnings = PlatformEarningHistory::where('created_at', '>=', $last30date)->sum('amount');
        $last90DaysEarnings = PlatformEarningHistory::where('created_at', '>=', $last90date)->sum('amount');
        $last180DaysEarnings = PlatformEarningHistory::where('created_at', '>=', $last180date)->sum('amount');
        $allTimeDaysEarnings = PlatformEarningHistory::sum('amount');

        // Withdrawals
        $pendingDoctorPayouts = DoctorPayoutHistory::where('status', 0)->sum('amount');
        $completedDoctorPayouts = DoctorPayoutHistory::where('status', 1)->sum('amount');
        $pendingUserPayouts = UserWithdrawRequest::where('status', 0)->sum('amount');

        // Recharges
        $todayRecharges = UserWalletRechargeLogs::whereDate('created_at', Carbon::now())->sum('amount');
        $last7DaysRecharges = UserWalletRechargeLogs::where('created_at', '>=', $last7date)->sum('amount');
        $last30DaysRecharges = UserWalletRechargeLogs::where('created_at', '>=', $last30date)->sum('amount');
        $last90DaysRecharges = UserWalletRechargeLogs::where('created_at', '>=', $last90date)->sum('amount');
        $last180DaysRecharges = UserWalletRechargeLogs::where('created_at', '>=', $last180date)->sum('amount');
        $allTimeRecharges = UserWalletRechargeLogs::sum('amount');

        return view('index', [
            'settings' => $settings,

            // Wallet recharges User
            'todayRecharges' => GlobalFunction::roundNumber($todayRecharges),
            'last7DaysRecharges' => GlobalFunction::roundNumber($last7DaysRecharges),
            'last30DaysRecharges' => GlobalFunction::roundNumber($last30DaysRecharges),
            'last90DaysRecharges' => GlobalFunction::roundNumber($last90DaysRecharges),
            'last180DaysRecharges' => GlobalFunction::roundNumber($last180DaysRecharges),
            'allTimeRecharges' => GlobalFunction::roundNumber($allTimeRecharges),

            // Payouts
            'pendingDoctorPayouts' => GlobalFunction::roundNumber($pendingDoctorPayouts),
            'completedDoctorPayouts' => GlobalFunction::roundNumber($completedDoctorPayouts),
            'pendingUserPayouts' => GlobalFunction::roundNumber($pendingUserPayouts),

            // Platform Earnings
            'todayEarnings' => GlobalFunction::roundNumber($todayEarnings),
            'last7DaysEarnings' => GlobalFunction::roundNumber($last7DaysEarnings),
            'last30DaysEarnings' => GlobalFunction::roundNumber($last30DaysEarnings),
            'last90DaysEarnings' => GlobalFunction::roundNumber($last90DaysEarnings),
            'last180DaysEarnings' => GlobalFunction::roundNumber($last180DaysEarnings),
            'allTimeDaysEarnings' => GlobalFunction::roundNumber($allTimeDaysEarnings),

            // Doctors
            'activeDoctors' => $activeDoctors,
            'pendingDoctors' => $pendingDoctors,
            'bannedDoctors' => $bannedDoctors,

            // Today
            'todayTotalBookings' => $todayTotalBookings,
            'todayTotalPendingBookings' => $todayTotalPendingBookings,
            'todayTotalAcceptedBookings' => $todayTotalAcceptedBookings,
            'todayTotalCompletedBookings' => $todayTotalCompletedBookings,
            'todayTotalCancelledBookings' => $todayTotalCancelledBookings,
            'todayTotalDeclinedBookings' => $todayTotalDeclinedBookings,
            'todayTotalMissedBookings' => $todayTotalMissedBookings,
            // Last 7 days
            'last7daysTotalBookings' => $last7daysTotalBookings,
            'last7daysTotalPendingBookings' => $last7daysTotalPendingBookings,
            'last7daysTotalAcceptedBookings' => $last7daysTotalAcceptedBookings,
            'last7daysTotalCompletedBookings' => $last7daysTotalCompletedBookings,
            'last7daysTotalCancelledBookings' => $last7daysTotalCancelledBookings,
            'last7daysTotalDeclinedBookings' => $last7daysTotalDeclinedBookings,
            'last7daysTotalMissedBookings' => $last7daysTotalMissedBookings,
            // Last 30 days
            'last30daysTotalBookings' => $last30daysTotalBookings,
            'last30daysTotalPendingBookings' => $last30daysTotalPendingBookings,
            'last30daysTotalAcceptedBookings' => $last30daysTotalAcceptedBookings,
            'last30daysTotalCompletedBookings' => $last30daysTotalCompletedBookings,
            'last30daysTotalCancelledBookings' => $last30daysTotalCancelledBookings,
            'last30daysTotalDeclinedBookings' => $last30daysTotalDeclinedBookings,
            'last30daysTotalMissedBookings' => $last30daysTotalMissedBookings,
            // Last 90 days
            'last90daysTotalBookings' => $last90daysTotalBookings,
            'last90daysTotalPendingBookings' => $last90daysTotalPendingBookings,
            'last90daysTotalAcceptedBookings' => $last90daysTotalAcceptedBookings,
            'last90daysTotalCompletedBookings' => $last90daysTotalCompletedBookings,
            'last90daysTotalCancelledBookings' => $last90daysTotalCancelledBookings,
            'last90daysTotalDeclinedBookings' => $last90daysTotalDeclinedBookings,
            'last90daysTotalMissedBookings' => $last90daysTotalMissedBookings,
            // Last 180 days
            'last180daysTotalBookings' => $last180daysTotalBookings,
            'last180daysTotalPendingBookings' => $last180daysTotalPendingBookings,
            'last180daysTotalAcceptedBookings' => $last180daysTotalAcceptedBookings,
            'last180daysTotalCompletedBookings' => $last180daysTotalCompletedBookings,
            'last180daysTotalCancelledBookings' => $last180daysTotalCancelledBookings,
            'last180daysTotalDeclinedBookings' => $last180daysTotalDeclinedBookings,
            'last180daysTotalMissedBookings' => $last180daysTotalMissedBookings,

            // All time
            'allTimeTotalBookings' => $allTimeTotalBookings,
            'allTimeTotalPendingBookings' => $allTimeTotalPendingBookings,
            'allTimeTotalAcceptedBookings' => $allTimeTotalAcceptedBookings,
            'allTimeTotalCompletedBookings' => $allTimeTotalCompletedBookings,
            'allTimeTotalCancelledBookings' => $allTimeTotalCancelledBookings,
            'allTimeTotalDeclinedBookings' => $allTimeTotalDeclinedBookings,
            'allTimeTotalMissedBookings' => $allTimeTotalMissedBookings,
        ]);
    }

    function fetchFaqCats(Request $request)
    {
        $faqCats = FaqCats::with('faqs')->get();

        return GlobalFunction::sendDataResponse(true, 'Data fetch successfully', $faqCats);
    }
}
