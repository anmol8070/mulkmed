<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Google\Client;
use Illuminate\Support\Facades\Crypt;
use App\Helpers\Helpers;
use Carbon\Carbon;
use DB;


class GlobalFunction extends Model
{
    use HasFactory;

    public static function deleteAppointmentScheduledReminders($appointment){
        ScheduledReminders::where('appointment_id', $appointment->id)->delete();
    }

    public static function decodeDoctorsMobileNumber($doctor){
        $digits = explode(' ', $doctor->mobile_number)[0];
        $number = $doctor->country_code." ".$digits;
        return $number;
    }

    public static function roundNumber($number)
    {
        return round($number, 2);
    }

    public static function sendPushNotificationToUsers($title, $message)
    {

        $client = new Client();
        $client->setAuthConfig('googleCredentials.json');
        $client->addScope('https://www.googleapis.com/auth/firebase.messaging');
        $client->fetchAccessTokenWithAssertion();
        $accessToken = $client->getAccessToken();
        $accessToken = $accessToken['access_token'];

        $contents = File::get(base_path('googleCredentials.json'));
        $json = json_decode(json: $contents, associative: true);

        $url = 'https://fcm.googleapis.com/v1/projects/'.$json['project_id'].'/messages:send';
        $notificationArray = array('title' => $title, 'body' => $message);

        $fields = array(
            'message'=> [
                'topic'=> 'patient',
                'notification' => $notificationArray,
            ]
        );

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

        if ($result) {
            return json_encode(['status' => true, 'message' => 'Notification sent successfully']);
        } else {
            return json_encode(['status' => false, 'message ' => 'Not sent!']);
        }
    }
    public static function sendPushNotificationToDoctors($title, $message)
    {
        $client = new Client();
        $client->setAuthConfig('googleCredentials.json');
        $client->addScope('https://www.googleapis.com/auth/firebase.messaging');
        $client->fetchAccessTokenWithAssertion();
        $accessToken = $client->getAccessToken();
        $accessToken = $accessToken['access_token'];

        $contents = File::get(base_path('googleCredentials.json'));
        $json = json_decode(json: $contents, associative: true);

        $url = 'https://fcm.googleapis.com/v1/projects/'.$json['project_id'].'/messages:send';
        $notificationArray = array('title' => $title, 'body' => $message);

        $fields = array(
            'message'=> [
                'topic'=> 'doctor',
                'notification' => $notificationArray,
            ]
        );

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

        if ($result) {
            return json_encode(['status' => true, 'message' => 'Notification sent successfully']);
        } else {
            return json_encode(['status' => false, 'message ' => 'Not sent!']);
        }
    }

    public static function returnAppointmentStatus($status)
    {
        $statusPill = "";
        switch ($status) {
            case (Constants::orderPlacedPending):
                $statusPill = '<span class="badge bg-warning text-white">' . __('Pending') . '</span>';
                break;
            case (Constants::orderAccepted):
                $statusPill = '<span class="badge bg-primary text-white">' . __('Accepted') . '</span>';
                break;
            case (Constants::orderCompleted):
                $statusPill = '<span class="badge bg-success text-white">' . __('Completed') . '</span>';
                break;
            case (Constants::orderDeclined):
                $statusPill = '<span class="badge bg-danger text-white">' . __('Declined') . '</span>';
                break;
            case (Constants::orderCancelled):
                $statusPill = '<span class="badge bg-danger text-white">' . __('Cancelled') . '</span>';
                break;
            case (Constants::orderMissed):
                $statusPill = '<span class="badge bg-danger text-white">' . __('Missed') . '</span>';
                break;
        }
        return $statusPill;
    }

    public static function returnAppointmentPaymentStatus($status)
    {
        $statusPill = "";
        switch ($status) {
            case (Constants::appointmentPaymentPendingStatus):
                $statusPill = '<span class="badge bg-warning text-white">' . __('Pending') . '</span>';
                break;
            case (Constants::appointmentPaymentSuccessStatus):
                $statusPill = '<span class="badge bg-success text-white">' . __('Paid') . '</span>';
                break;
            case (Constants::appointmentPaymentFailureStatus):
                $statusPill = '<span class="badge bg-danger text-white">' . __('Failed') . '</span>';
                break;
            case (Constants::appointmentPaymentAbortedStatus):
                $statusPill = '<span class="badge bg-danger text-white">' . __('Aborted') . '</span>';
        }
        return $statusPill;
    }

    public static function sendSimpleResponse($status, $msg)
    {
        return response()->json(['status' => $status, 'message' => $msg]);
    }
    public static function sendDataResponse($status, $msg, $data)
    {
        return response()->json(['status' => $status, 'message' => $msg, 'data' => $data]);
    }

    public static function generateUserFullData($id)
    {
        $user = Users::where('id', $id)
            ->with(['patients'])
            ->first();

        return $user;
    }
    public static function generateDoctorFullData($id)
    {
        $hostAndConversionRate = Helpers::conversionRate();
        $conversionRate = (float) $hostAndConversionRate['conversionRate'];
        $doctor = Doctors::select('doctors.*',DB::raw("ROUND(consultation_fee * {$conversionRate}) as consultation_fee"))
            ->where('id', $id)
            ->with([
                'services',
                'experience',
                'expertise',
                'serviceLocations',
                'awards',
                'slots',
                'holidays',
                'bankAccount',
            ])
            ->first();

        return $doctor;
    }

    public static function sendPushToDoctor($title, $message, $doctor, $data = null)
    {

        // if ($doctor->is_notification == 1) {
        //     $client = new Client();
        //     $client->setAuthConfig('googleCredentials.json');
        //     $client->addScope('https://www.googleapis.com/auth/firebase.messaging');
        //     $client->fetchAccessTokenWithAssertion();
        //     $accessToken = $client->getAccessToken();
        //     $accessToken = $accessToken['access_token'];

        //     $contents = File::get(base_path('googleCredentials.json'));
        //     $json = json_decode(json: $contents, associative: true);

        //     $url = 'https://fcm.googleapis.com/v1/projects/'.$json['project_id'].'/messages:send';
        //     $notificationArray = array('title' => $title, 'body' => $message);

        //     if($doctor->device_type == Constants::deviceIOS){
        //         $fields = array(
        //             'message'=> [
        //                 'token'=> $doctor->device_token,
        //                 'notification' => $notificationArray,
        //                 'data'=> $data,
        //                 "apns"=> [
        //                 "payload"=> [
        //                     "aps"=> [
        //                         "sound"=> "default"
        //                     ],
        //                     "content-available"=> true
        //                     ]
        //                 ],
        //             ]
        //         );
        //     }else{
        //         $data = array_merge($data, $notificationArray);
        //         $fields = array(
        //             'message'=> [
        //                 'token'=> $doctor->device_token,
        //                 'data'=> $data,
        //                 "apns"=> [
        //                 "payload"=> [
        //                     "aps"=> [
        //                         "sound"=> "default"
        //                     ],
        //                     "content-available"=> true
        //                     ]
        //                 ],
        //             ]
        //         );
        //     }
        //     // Log::debug($fields);

        //     $headers = array(
        //         'Content-Type:application/json',
        //         'Authorization:Bearer ' . $accessToken
        //     );
        //     $ch = curl_init();
        //     curl_setopt($ch, CURLOPT_URL, $url);
        //     curl_setopt($ch, CURLOPT_POST, true);
        //     curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        //     curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        //     curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        //     curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        //     curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
        //     // print_r(json_encode($fields));
        //     $result = curl_exec($ch);
        //     Log::debug($result);

        //     if ($result === FALSE) {
        //         die('FCM Send Error: ' . curl_error($ch));
        //     }
        //     curl_close($ch);

        //     if ($result) {
        //         return json_encode(['status' => true, 'message' => 'Notification sent successfully']);
        //     } else {
        //         return json_encode(['status' => false, 'message ' => 'Not sent!']);
        //     }
        // }
    }
    public static function sendPushToUser($title, $message, $user, $data = null)
    {
        // try {
        //     if ($user->is_notification == 1) {
        //     $client = new Client();
        //     $client->setAuthConfig('googleCredentials.json');
        //     $client->addScope('https://www.googleapis.com/auth/firebase.messaging');
        //     $client->fetchAccessTokenWithAssertion();
        //     $accessToken = $client->getAccessToken();
        //     $accessToken = $accessToken['access_token'];

        //     $contents = File::get(base_path('googleCredentials.json'));
        //     $json = json_decode(json: $contents, associative: true);

        //     $url = 'https://fcm.googleapis.com/v1/projects/'.$json['project_id'].'/messages:send';
        //     $notificationArray = array('title' => $title, 'body' => $message);

        //     if($user->device_type == Constants::deviceIOS){
        //         $fields = array(
        //             'message'=> [
        //                 'token'=> $user->device_token,
        //                 'notification' => $notificationArray,
        //                 'data'=> $data,
        //                 "apns"=> [
        //                 "payload"=> [
        //                     "aps"=> [
        //                         "sound"=> "default"
        //                     ],
        //                     "content-available"=> true
        //                     ]
        //                 ],
        //             ]
        //         );
        //     }else{
        //         $data = array_merge($data, $notificationArray);
        //         Log::debug($data);
        //         $fields = array(
        //             'message'=> [
        //                 'token'=> $user->device_token,
        //                 'data'=> $data,
        //                 "apns"=> [
        //                 "payload"=> [
        //                     "aps"=> [
        //                         "sound"=> "default"
        //                     ],
        //                     "content-available"=> true
        //                     ]
        //                 ],
        //             ]
        //         );
        //     }



        //     $headers = array(
        //         'Content-Type:application/json',
        //         'Authorization:Bearer ' . $accessToken
        //     );
        //     $ch = curl_init();
        //     curl_setopt($ch, CURLOPT_URL, $url);
        //     curl_setopt($ch, CURLOPT_POST, true);
        //     curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        //     curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        //     curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        //     curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        //     curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
        //     // print_r(json_encode($fields));
        //     $result = curl_exec($ch);
        //     Log::debug($result);

        //     if ($result === FALSE) {
        //         die('FCM Send Error: ' . curl_error($ch));
        //     }
        //     curl_close($ch);

        //     if ($result) {
        //         return json_encode(['status' => true, 'message' => 'Notification sent successfully']);
        //     } else {
        //         return json_encode(['status' => false, 'message ' => 'Not sent!']);
        //     }
        // }
        // } catch (\Throwable $th) {
        //     //throw $th;
        // }
      
    }

    public static function createMediaUrl($media)
    {
        $url = env('FILES_BASE_URL') . $media;
        return $url;
    }

    public static function uploadFilToS3($request, $key)
    {
        $s3 = Storage::disk('s3');
        $file = $request->file($key);
        $fileName = time() . $file->getClientOriginalName();
        $fileName = str_replace(" ", "_", $fileName);
        $filePath = 'uploads/' . $fileName;
        $result =  $s3->put($filePath, file_get_contents($file), 'public-read');
        return $filePath;
    }

    public static function point2point_distance($lat1, $lon1, $lat2, $lon2, $unit = 'K', $radius)
    {
        $theta = $lon1 - $lon2;
        $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) +  cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
        $dist = acos($dist);
        $dist = rad2deg($dist);
        $miles = $dist * 60 * 1.1515;
        $unit = strtoupper($unit);

        if ($unit == "K") {
            return (($miles * 1.609344) <= $radius);
        } else if ($unit == "N") {
            return ($miles * 0.8684);
        } else {
            return $miles;
        }
    }


    public static function detectPaymentGateway($gateway)
    {
        $name = "";
        switch ($gateway) {
            case (Constants::stripe):
                $name = 'Stripe';
                break;
            case (Constants::addedByAdmin):
                $name = 'Added By Admin';
                break;
            case (Constants::flutterWave):
                $name = 'Flutterwave';
                break;
            case (Constants::razorPay):
                $name = 'Razorpay';
                break;
            case (Constants::payStack):
                $name = 'Paystack';
                break;
            case (Constants::payPal):
                $name = 'PayPal';
                break;
            case (Constants::sslCommerze):
                $name = 'SSLCommerze';
                break;
        }

        return $name;
    }

    public static function cleanString($string)
    {
        return  str_replace(array('<', '>', '{', '}', '[', ']', '`'), '', $string);
    }

    // public static function deleteFile($filename)
    // {
    //     if ($filename != null && file_exists(storage_path('/' . $filename))) {
    //         unlink(storage_path('/' . $filename));
    //     }
    // }

    public static function deleteFile($filename)
    {
        $fullPath = storage_path('/' . $filename);

        if ($filename && file_exists($fullPath)) {
            unlink($fullPath);
        }
    }


    public static function saveFileAndGivePath($file, bool $overwriteExisting = false)
    {
        if ($file != null) {
            $clientOriginalName = method_exists($file, 'getClientOriginalName')
                ? (string) $file->getClientOriginalName()
                : null;
            $clientOriginalExtension = method_exists($file, 'getClientOriginalExtension')
                ? (string) $file->getClientOriginalExtension()
                : null;

            $isGenericClientName = is_string($clientOriginalName)
                && preg_match('/^(uploaded_file|blob)(\.[A-Za-z0-9]+)?$/i', trim($clientOriginalName)) === 1;

            $requestProvidedOriginalName = null;
            $candidateKeys = ['original_name', 'file_name', 'filename', 'name'];
            foreach ($candidateKeys as $candidateKey) {
                $candidateValue = request()->input($candidateKey);
                if (is_string($candidateValue) && trim($candidateValue) !== '') {
                    $requestProvidedOriginalName = trim($candidateValue);
                    break;
                }
            }

            $nameToUse = $clientOriginalName;
            if ($isGenericClientName && is_string($requestProvidedOriginalName) && $requestProvidedOriginalName !== '') {
                $nameToUse = $requestProvidedOriginalName;
            }

            $safeOriginalName = basename(str_replace('\\', '/', (string) $nameToUse));
            $safeOriginalName = preg_replace('/[^A-Za-z0-9._ -]/', '', $safeOriginalName) ?? '';

            if ($safeOriginalName === '' || $safeOriginalName === '.' || $safeOriginalName === '..') {
                $ext = $clientOriginalExtension ?: 'bin';
                $safeOriginalName = 'upload.' . strtolower((string) $ext);
            }

            $nameWithoutExt = pathinfo($safeOriginalName, PATHINFO_FILENAME);
            $extFromSafeName = pathinfo($safeOriginalName, PATHINFO_EXTENSION);
            $finalFileName = $safeOriginalName;
            $targetPath = 'uploads/' . $finalFileName;
            if (Storage::disk('public')->exists($targetPath)) {
                if ($overwriteExisting) {
                    Storage::disk('public')->delete($targetPath);
                } else {
            $counter = 1;
            while (Storage::disk('public')->exists('uploads/' . $finalFileName)) {
                $suffix = '_' . date('Ymd_His') . '_' . $counter;
                $finalFileName = $nameWithoutExt . $suffix . ($extFromSafeName !== '' ? '.' . $extFromSafeName : '');
                $counter++;
            }
                }
            }

            $path = $file->storeAs('uploads', $finalFileName, 'public');

            return $path;
        } else {
            return null;
        }
    }

    public static function saveCardFileAndGivePath($file, $folder = 'uploads/cardUploads/0', $customName = null)
    {
        if ($file === null) {
            return null;
        }

        $extension = $file->getClientOriginalExtension();

        // Keep spaces in file name
        $filename = $customName
            ? $customName . '.' . $extension
            : time() . '.' . $extension;

        // Build path: e.g. "uploads/Mulk Healthcare Discount Card/Mulk Healthcare Discount Card.jpg"
        $path = $folder . '/' . $filename;

        // If same file already exists, delete it so we don't stack duplicates
        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }

        // Store file with the same name in the same folder
        $storedPath = $file->storeAs($folder, $filename, 'public');

        @chmod(storage_path($storedPath), 0777);

        return $storedPath;
    }

    public static function formateTimeString($timeString)
    {
        if ($timeString != null) {
            return substr_replace($timeString, ":", 2, 0);
        }
        return "";
    }

    public static function formatAppointmentTimeForDisplay($timeString)
    {
        return GlobalFunction::formateTimeString($timeString);
    }

    public static function getServerTimezone()
    {
        return env('SERVER_TIMEZONE_VALUE',"+00:00");
    }

    public static function getUtcTimezoneValue()
    {
        return env('UTC_TIMEZONE_VALUE', '+00:00');
    }

    public static function getRequestTimezoneOrServer()
    {
        $headerTimezone = request()->header('X-Timezone');
        $inputTimezone = request()->input('browser_timezone');
        $cookieTimezone = request()->cookie('browser_timezone');

        Log::info('getRequestTimezoneOrServer incoming values', [
            'header_timezone' => $headerTimezone,
            'input_timezone' => $inputTimezone,
            'cookie_timezone' => $cookieTimezone,
        ]);

        $timezoneCandidate = $headerTimezone ?: ($inputTimezone ?: $cookieTimezone);

        if (!empty($timezoneCandidate)) {
            $timezoneCandidate = trim((string) $timezoneCandidate);
            if (in_array($timezoneCandidate, \DateTimeZone::listIdentifiers(), true)) {
                Log::info('getRequestTimezoneOrServer resolved from request', [
                    'timezone' => $timezoneCandidate,
                ]);
                return $timezoneCandidate;
            }

            Log::warning('getRequestTimezoneOrServer invalid timezone fallback', [
                'timezone' => $timezoneCandidate,
            ]);
        }

        Log::info('getRequestTimezoneOrServer fallback server timezone', [
            'server_timezone' => GlobalFunction::getServerTimezone(),
        ]);

        return GlobalFunction::getServerTimezone();
    }

    public static function getBaseTimezoneFromPayload($serverTimezone = null)
    {
        if (!empty($serverTimezone)) {
            return $serverTimezone;
        }

        if (request()->has('is_utc_timezone')) {
            $isUtcTimezone = filter_var(request()->input('is_utc_timezone'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            $resolvedTimezone = ($isUtcTimezone === true) ? GlobalFunction::getUtcTimezoneValue() : GlobalFunction::getServerTimezone();

            Log::info('getBaseTimezoneFromPayload resolved from payload', [
                'is_utc_timezone' => request()->input('is_utc_timezone'),
                'resolved_timezone' => $resolvedTimezone,
            ]);

            return $resolvedTimezone;
        }

        return GlobalFunction::getServerTimezone();
    }

    public static function convertDateTimeToUserTimezone($date, $time, $userTimezone, $outputFormat = 'Y-m-d H:i:s', $serverTimezone = null)
    {
        if (empty($date) || empty($time) || empty($userTimezone)) {
            Log::warning('convertDateTimeToUserTimezone skipped due to empty input', [
                'date' => $date,
                'time' => $time,
                'user_timezone' => $userTimezone,
            ]);
            return null;
        }

        try {
            $baseTimezone = GlobalFunction::getBaseTimezoneFromPayload($serverTimezone);
            $dateTime = Carbon::parse($date . ' ' . $time, $baseTimezone)->setTimezone($userTimezone);
            $convertedValue = $dateTime->format($outputFormat);
            Log::info('convertDateTimeToUserTimezone success', [
                'input_date' => $date,
                'input_time' => $time,
                'base_timezone' => $baseTimezone,
                'user_timezone' => $userTimezone,
                'output_format' => $outputFormat,
                'converted_value' => $convertedValue,
            ]);
            return $convertedValue;
        } catch (\Throwable $th) {
            Log::error('convertDateTimeToUserTimezone failed', [
                'date' => $date,
                'time' => $time,
                'user_timezone' => $userTimezone,
                'error' => $th->getMessage(),
            ]);
            return null;
        }
    }

    public static function convertTimeToUserTimezone($time, $userTimezone, $date = '1970-01-01', $outputFormat = 'H:i:s', $serverTimezone = null)
    {
        if (empty($time) || empty($userTimezone)) {
            Log::warning('convertTimeToUserTimezone skipped due to empty input', [
                'date' => $date,
                'time' => $time,
                'user_timezone' => $userTimezone,
            ]);
            return null;
        }

        try {
            $baseTimezone = GlobalFunction::getBaseTimezoneFromPayload($serverTimezone);
            $dateTime = Carbon::parse($date . ' ' . $time, $baseTimezone)->setTimezone($userTimezone);
            $convertedValue = $dateTime->format($outputFormat);
            Log::info('convertTimeToUserTimezone success', [
                'input_date' => $date,
                'input_time' => $time,
                'base_timezone' => $baseTimezone,
                'user_timezone' => $userTimezone,
                'output_format' => $outputFormat,
                'converted_value' => $convertedValue,
            ]);
            return $convertedValue;
        } catch (\Throwable $th) {
            Log::error('convertTimeToUserTimezone failed', [
                'date' => $date,
                'time' => $time,
                'user_timezone' => $userTimezone,
                'error' => $th->getMessage(),
            ]);
            return null;
        }
    }

    public static function normalizeTimeToHis($time)
    {
        if (empty($time)) {
            Log::warning('normalizeTimeToHis received empty time', ['time' => $time]);
            return null;
        }

        try {
            $timeString = trim((string) $time);

            if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $timeString)) {
                Log::info('normalizeTimeToHis success', ['input_time' => $time, 'normalized_time' => $timeString]);
                return $timeString;
            }

            if (preg_match('/^\d{2}:\d{2}$/', $timeString)) {
                $normalized = $timeString . ':00';
                Log::info('normalizeTimeToHis success', ['input_time' => $time, 'normalized_time' => $normalized]);
                return $normalized;
            }

            $digits = preg_replace('/\D+/', '', $timeString);
            if (strlen($digits) >= 4) {
                $normalized = Carbon::createFromFormat('Hi', substr($digits, 0, 4))->format('H:i:s');
                Log::info('normalizeTimeToHis success', ['input_time' => $time, 'normalized_time' => $normalized]);
                return $normalized;
            }

            $normalized = Carbon::parse($timeString)->format('H:i:s');
            Log::info('normalizeTimeToHis success', ['input_time' => $time, 'normalized_time' => $normalized]);
            return $normalized;
        } catch (\Throwable $th) {
            Log::error('normalizeTimeToHis failed', ['time' => $time, 'error' => $th->getMessage()]);
            return null;
        }
    }
    
    //   public static function jitsiBothParticipantsJoined($meeting): bool
    // {
    //     if ($meeting === null) {
    //         return false;
    //     }

    //     return (int) ($meeting->user_joined ?? 0) === 1
    //         && (int) ($meeting->doctor_joined ?? 0) === 1;
    // }

    // public static function jitsiEitherParticipantMissing($meeting): bool
    // {
    //     if ($meeting === null) {
    //         return true;
    //     }

    //     return (int) ($meeting->user_joined ?? 0) !== 1
    //         || (int) ($meeting->doctor_joined ?? 0) !== 1;
    // }
     /**
     * Normalize appointment/slot time to 4-digit HHmm (e.g. 1030) for DB compare.
     */
    public static function normalizeTimeToHi($time)
    {
        if ($time === null || $time === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', trim((string) $time));
        if ($digits === '') {
            return null;
        }

        if (strlen($digits) >= 6) {
            $digits = substr($digits, 0, 4);
        } elseif (strlen($digits) === 3) {
            $digits = '0' . $digits;
        }

        return str_pad(substr($digits, 0, 4), 4, '0', STR_PAD_LEFT);
    }

    /** @alias normalizeTimeToHi */
    public static function normalizeTimeToStorageHi($time)
    {
        return GlobalFunction::normalizeTimeToHi($time);
    }

    public static function appointmentTimesMatch($timeA, $timeB)
    {
        $normalizedA = GlobalFunction::normalizeTimeToHi($timeA);
        $normalizedB = GlobalFunction::normalizeTimeToHi($timeB);

        return $normalizedA !== null && $normalizedB !== null && $normalizedA === $normalizedB;
    }

    public static function timeHiToMinutes($timeHi)
    {
        $normalized = GlobalFunction::normalizeTimeToHi($timeHi);
        if ($normalized === null) {
            return null;
        }

        return ((int) substr($normalized, 0, 2) * 60) + (int) substr($normalized, 2, 2);
    }

    public static function normalizeDateToYmd($date)
    {
        if ($date === null || $date === '') {
            return null;
        }

        try {
            return Carbon::parse($date)->format('Y-m-d');
        } catch (\Throwable $th) {
            return (string) $date;
        }
    }

    /**
     * Appointments that should block a doctor slot on a given date.
     * Only paid (payment_status=1) and accepted (status=1) bookings block the slot.
     *
     * @return array<string, true> keyed by normalized HHmm
     */
    public static function getActiveBookedSlotTimesForDoctorDate($doctorId, $date)
    {
        $normalizedDate = GlobalFunction::normalizeDateToYmd($date);
        if ($normalizedDate === null) {
            return [];
        }

        $appointments = Appointments::where('doctor_id', $doctorId)
            ->whereDate('date', $normalizedDate)
            ->where('payment_status', Constants::appointmentPaymentSuccessStatus)
            ->where('status', Constants::orderAccepted)
            ->get(['time']);

        $booked = [];
        foreach ($appointments as $appointment) {
            $normalized = GlobalFunction::normalizeTimeToHi($appointment->time);
            if ($normalized !== null) {
                $booked[$normalized] = true;
            }
        }

        return $booked;
    }

    public static function isDoctorSlotTimeBooked($slotTime, array $bookedSlotTimes)
    {
        $normalizedSlotTime = GlobalFunction::normalizeTimeToHi($slotTime);

        return $normalizedSlotTime !== null && isset($bookedSlotTimes[$normalizedSlotTime]);
    }

    public static function isDoctorSlotOverlappingAppointment($slotTime, array $bookedSlotTimes, $durationMinutes = null)
    {
        $durationMinutes = $durationMinutes ?? Constants::meetingDurationInMinutes;
        $slotMinutes = GlobalFunction::timeHiToMinutes($slotTime);
        if ($slotMinutes === null) {
            return false;
        }

        $slotEndMinutes = $slotMinutes + $durationMinutes;

        foreach (array_keys($bookedSlotTimes) as $bookedTime) {
            $bookedStartMinutes = GlobalFunction::timeHiToMinutes($bookedTime);
            if ($bookedStartMinutes === null) {
                continue;
            }

            $bookedEndMinutes = $bookedStartMinutes + $durationMinutes;
            if ($slotMinutes < $bookedEndMinutes && $slotEndMinutes > $bookedStartMinutes) {
                return true;
            }
        }

        return false;
    }

    public static function getSlotAvailabilityTimezone($request = null)
    {
        return GlobalFunction::getRequestTimezoneOrServer();
    }

    public static function isDoctorSlotBooked($doctorId, $date, $time, $timezone = null)
    {
        $bookedSlotTimes = GlobalFunction::getActiveBookedSlotTimesForDoctorDate($doctorId, $date);

        return GlobalFunction::isDoctorSlotOverlappingAppointment($time, $bookedSlotTimes);
    }


    public static function formatTimeForDisplay($time, $outputFormat = 'g:i A')
    {
        $normalizedTime = GlobalFunction::normalizeTimeToHis($time);

        if (empty($normalizedTime)) {
            return (string) $time;
        }

        try {
            return Carbon::createFromFormat('H:i:s', $normalizedTime)->format($outputFormat);
        } catch (\Throwable $th) {
            return (string) $time;
        }
    }

    public static function normalizeCountryCode($countryCode)
    {
        if ($countryCode === null) {
            return null;
        }

        $normalized = trim((string) $countryCode);
        $normalized = ltrim($normalized, '+');
        $normalized = preg_replace('/\D+/', '', $normalized);

        if (empty($normalized)) {
            return null;
        }

        return $normalized;
    }

    public static function getTimezoneByCountryCode($countryCode,$isTimeZoneValue = false)
    {
        $normalizedCode = GlobalFunction::normalizeCountryCode($countryCode);

        $countryTimezoneMap = [
            '91' => $isTimeZoneValue ? '+05:30' : 'Asia/Kolkata',
            '971' => $isTimeZoneValue ? '+04:00' : 'Asia/Dubai',
        ];

        $resolvedTimezone = $countryTimezoneMap[$normalizedCode] ?? GlobalFunction::getServerTimezone();
        Log::info('getTimezoneByCountryCode resolved', [
            'country_code_input' => $countryCode,
            'country_code_normalized' => $normalizedCode,
            'resolved_timezone' => $resolvedTimezone,
            'is_timezone_value' => $isTimeZoneValue,
        ]);
        return $resolvedTimezone;
    }

    /**
     * Resolve the admin platform timezone from the request host.
     *
     * Booking admins use different domains per region:
     *   - india.mulkmed.com → India (Asia/Kolkata)
     *   - pt.mulkmed.com    → UAE   (Asia/Dubai)
     *
     * Any unknown host falls back to UAE (Asia/Dubai) since pt.mulkmed.com is
     * the primary platform.
     */
    public static function getAdminTimezoneByHost($host = null, $isTimeZoneValue = false)
    {
        $hostValue = $host ?? request()->getHost();
        $normalizedHost = strtolower(trim((string) $hostValue));

        $hostTimezoneMap = [
            'indiamulkmed.reapmind.com' => $isTimeZoneValue ? '+05:30' : 'Asia/Kolkata',
            'uaemulkmed.reapmind.com'    => $isTimeZoneValue ? '+04:00' : 'Asia/Dubai',
        ];

        $resolvedTimezone = $hostTimezoneMap[$normalizedHost]
            ?? ($isTimeZoneValue ? '+04:00' : 'Asia/Dubai');

        Log::info('getAdminTimezoneByHost resolved', [
            'host_input' => $hostValue,
            'host_normalized' => $normalizedHost,
            'resolved_timezone' => $resolvedTimezone,
            'is_timezone_value' => $isTimeZoneValue,
        ]);

        return $resolvedTimezone;
    }

    public static function generatePlatformEarningHistoryNumber()
    {
        $token =  rand(100000, 999999);

        $first = Constants::prefixPlatformEarningHistory;
        $first .= GlobalFunction::generateRandomString(3);
        $first .= $token;
        $first .= GlobalFunction::generateRandomString(3);
        $count = PlatformEarningHistory::where('earning_number', $first)->count();

        while ($count >= 1) {

            $token =  rand(100000, 999999);

            $first = GlobalFunction::generateRandomString(3);
            $first .= $token;
            $first .= GlobalFunction::generateRandomString(3);
            $count = PlatformEarningHistory::where('earning_number', $first)->count();
        }

        return $first;
    }
    public static function generateDoctorEarningHistoryNumber()
    {
        $token =  rand(100000, 999999);
        $first = Constants::prefixDoctorEarningHistory;
        $first .= GlobalFunction::generateRandomString(3);
        $first .= $token;
        $first .= GlobalFunction::generateRandomString(3);
        $count = DoctorEarningHistory::where('earning_number', $first)->count();

        while ($count >= 1) {
            $token =  rand(100000, 999999);
            $first = GlobalFunction::generateRandomString(3);
            $first .= $token;
            $first .= GlobalFunction::generateRandomString(3);
            $count = DoctorEarningHistory::where('earning_number', $first)->count();
        }

        return $first;
    }

    public static function generateDoctorNumber()
    {
        $token =  rand(100000, 999999);

        $first = Constants::prefixDoctorNumber;
        $first .= GlobalFunction::generateRandomString(3);
        $first .= $token;
        $first .= GlobalFunction::generateRandomString(3);
        $count = Doctors::where('doctor_number', $first)->count();

        while ($count >= 1) {
            $token =  rand(100000, 999999);
            $first = GlobalFunction::generateRandomString(3);
            $first .= $token;
            $first .= GlobalFunction::generateRandomString(3);
            $count = Doctors::where('doctor_number', $first)->count();
        }

        return $first;
    }

    public static function generateDoctorWithdrawRequestNumber()
    {
        $token =  rand(100000, 999999);
        $first = Constants::prefixDoctorWithDrawRequestNumber;
        $first .= GlobalFunction::generateRandomString(3);
        $first .= $token;
        $first .= GlobalFunction::generateRandomString(3);
        $count = DoctorPayoutHistory::where('request_number', $first)->count();

        while ($count >= 1) {

            $token =  rand(100000, 999999);
            $first = GlobalFunction::generateRandomString(3);
            $first .= $token;
            $first .= GlobalFunction::generateRandomString(3);
            $count = DoctorPayoutHistory::where('request_number', $first)->count();
        }

        return $first;
    }
    public static function generateUserWithdrawRequestNumber()
    {
        $token =  rand(100000, 999999);
        $first = Constants::prefixUserWithDrawRequestNumber;
        $first .= GlobalFunction::generateRandomString(3);
        $first .= $token;
        $first .= GlobalFunction::generateRandomString(3);
        $count = UserWithdrawRequest::where('request_number', $first)->count();

        while ($count >= 1) {

            $token =  rand(100000, 999999);
            $first = GlobalFunction::generateRandomString(3);
            $first .= $token;
            $first .= GlobalFunction::generateRandomString(3);
            $count = UserWithdrawRequest::where('request_number', $first)->count();
        }

        return $first;
    }
    public static function generateAppointmentNumber()
    {
        $token =  rand(100000, 999999);

        $first = Constants::prefixAppointmentNumber;
        $first .= GlobalFunction::generateRandomString(3);
        $first .= $token;
        $first .= GlobalFunction::generateRandomString(3);
        $count = Appointments::where('appointment_number', $first)->count();

        while ($count >= 1) {
            $token =  rand(100000, 999999);
            $first = GlobalFunction::generateRandomString(3);
            $first .= $token;
            $first .= GlobalFunction::generateRandomString(3);
            $count = Appointments::where('appointment_number', $first)->count();
        }

        return $first;
    }

    public static function addDoctorStatementEntry($doctorId, $appointmentNumber, $amount, $crOrDr, $type, $summary)
    {
        $stmt = new DoctorWalletStatements();
        $stmt->transaction_id = GlobalFunction::generateDoctorTransactionId();
        $stmt->doctor_id = $doctorId;
        $stmt->appointment_number = $appointmentNumber;
        $stmt->amount = $amount;
        $stmt->cr_or_dr = $crOrDr;
        $stmt->type = $type;
        $stmt->summary = $summary;
        $stmt->save();
    }
    public static function addUserStatementEntry($userId, $appointmentNumber, $amount, $crOrDr, $type, $summary)
    {
        $stmt = new UserWalletStatements();
        $stmt->transaction_id = GlobalFunction::generateTransactionId();
        $stmt->user_id = $userId;
        $stmt->appointment_number = $appointmentNumber;
        $stmt->amount = $amount;
        $stmt->cr_or_dr = $crOrDr;
        $stmt->type = $type;
        $stmt->summary = $summary;
        $stmt->save();
    }

    public static function generateDoctorTransactionId()
    {
        $token =  rand(100000, 999999);

        $first = Constants::prefixDoctorTransactionId;
        $first .= GlobalFunction::generateRandomString(3);
        $first .= $token;
        $first .= GlobalFunction::generateRandomString(3);
        $count = DoctorWalletStatements::where('transaction_id', $first)->count();

        while ($count >= 1) {

            $token =  rand(100000, 999999);

            $first = GlobalFunction::generateRandomString(3);
            $first .= $token;
            $first .= GlobalFunction::generateRandomString(3);
            $count = DoctorWalletStatements::where('transaction_id', $first)->count();
        }

        return $first;
    }
    public static function generateTransactionId()
    {
        $token =  rand(100000, 999999);
        $first = Constants::prefixUserTransactionId;
        $first .= GlobalFunction::generateRandomString(3);
        $first .= $token;
        $first .= GlobalFunction::generateRandomString(3);
        $count = UserWalletStatements::where('transaction_id', $first)->count();

        while ($count >= 1) {

            $token =  rand(100000, 999999);

            $first = GlobalFunction::generateRandomString(3);
            $first .= $token;
            $first .= GlobalFunction::generateRandomString(3);
            $count = UserWalletStatements::where('transaction_id', $first)->count();
        }

        return $first;
    }


    public static function generateRandomString($length)
    {
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    private static function jitsiWrapperBaseUrl(): string 
    {
        $wrapperUrl = rtrim((string) env('JITSI_URL', 'https://vc.mulkmed.com/wrapper.html'), '/');

        if (str_ends_with($wrapperUrl, '/wrapper.html')) {
            return substr($wrapperUrl, 0, -strlen('wrapper.html'));
        }

        return $wrapperUrl . '/';
    }

    private static function encodeJitsiJwt(array $payload): string
    {
        return JWT::encode($payload, env('JITSI_SECRET'), 'HS256');
    }

    private static function buildJitsiJwtPayload(object $user, bool $isModerator, string $room = '*'): array
    {
        return [
            'aud' => env('JITSI_JWT_AUD', 'jitsi'),
            'iss' => env('JITSI_APP_ID', 'mulk_app_id'),
            'sub' => env('JITSI_DOMAIN', 'vc.mulkmed.com'),
            'room' => $room,
            'moderator' => $isModerator,
            'context' => self::buildJitsiJwtContext($user, $isModerator),
        ];
    }

    private static function buildJitsiJwtContext(object $user, bool $isModerator): object
    {
        $context = ['user' => $user];

        if (filter_var(env('JITSI_RECORDING_ENABLED', true), FILTER_VALIDATE_BOOLEAN)) {
            $context['features'] = (object) [
                'recording' => true,
                'livestreaming' => filter_var(env('JITSI_LIVESTREAMING_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
            ];
        }

        return (object) $context;
    }

    private static function jitsiJwtModeratorForRole(bool $isDoctorOrModeratorRole): bool
    {
        if ($isDoctorOrModeratorRole) {
            return true;
        }

        return filter_var(env('JITSI_PATIENT_JWT_MODERATOR', false), FILTER_VALIDATE_BOOLEAN);
    }
    private static function jitsiBackendDomain(): string
    {
        $domain = trim((string) env('JITSI_BACKEND_DOMAIN', ''));
        if ($domain !== '') {
            return self::normalizeJitsiBackendDomain($domain);
        }

        $appUrl = trim((string) config('app.url', ''));
        if ($appUrl !== '') {
            return self::normalizeJitsiBackendDomain($appUrl);
        }

        return self::normalizeJitsiBackendDomain(request()->getSchemeAndHttpHost());
    }

    private static function normalizeJitsiBackendDomain(string $domain): string
    {
        $domain = trim($domain);
        if ($domain === '') {
            return '';
        }

        if (!preg_match('#^https?://#i', $domain)) {
            $domain = 'https://' . ltrim($domain, '/');
        }

        $parts = parse_url($domain);
        if ($parts !== false && !empty($parts['host'])) {
            return ($parts['scheme'] ?? 'https') . '://' . $parts['host'];
        }

        return (string) preg_replace('#/v2/?$#', '', rtrim($domain, '/'));
    }

    public static function normalizeJitsiWrapperUrl(?string $wrapperUrl): ?string
    {
        if ($wrapperUrl === null || $wrapperUrl === '') {
            return $wrapperUrl;
        }

        // Production links often use a partially-encoded domain param:
        // domain=https%3A%2F%2Fuaemulkmed.reapmind.com/v2
        $wrapperUrl = preg_replace(
            '#(domain=https%3A%2F%2F[^&]+?)(?:%2Fv2|/v2)(?=&|$)#i',
            '$1',
            $wrapperUrl
        );

        if (preg_match('#domain=([^&]+)#', $wrapperUrl, $matches)) {
            $decodedDomain = urldecode($matches[1]);
            $normalizedDomain = self::normalizeJitsiBackendDomain($decodedDomain);
            if ($normalizedDomain !== $decodedDomain) {
                $wrapperUrl = preg_replace(
                    '#domain=[^&]+#',
                    'domain=' . rawurlencode($normalizedDomain),
                    $wrapperUrl,
                    1
                );
            }
        }

        return $wrapperUrl;
    }

    public static function persistNormalizedJitsiMeetingLinks(JitsiMeeting $meeting): JitsiMeeting
    {
        $doctorLink = self::normalizeJitsiWrapperUrl($meeting->doctor_link);
        $patientLink = self::normalizeJitsiWrapperUrl($meeting->patient_link);
        $changed = false;

        if ($doctorLink !== null && $doctorLink !== $meeting->doctor_link) {
            $meeting->doctor_link = $doctorLink;
            $changed = true;
        }
        if ($patientLink !== null && $patientLink !== $meeting->patient_link) {
            $meeting->patient_link = $patientLink;
            $changed = true;
        }

        if ($changed) {
            $meeting->save();
        }

        return $meeting;
    }

    public static function jitsiMeetingJoinResponseFields(?string $wrapperUrl, string $room): array
    {
        $link = self::normalizeJitsiWrapperUrl($wrapperUrl);
        $parsedUrl = parse_url($link ?? '');
        parse_str($parsedUrl['query'] ?? '', $query);

        return [
            'link' => $link,
            'base_url' => ($parsedUrl['scheme'] ?? 'https') . '://' . ($parsedUrl['host'] ?? ''),
            'room_id' => $query['roomId'] ?? $room,
            'token' => $query['jwt'] ?? null,
        ];
    }

    private static function buildJitsiWrapperUrl(string $roomId, string $jwt): string
    {
        $baseUrl = self::jitsiWrapperBaseUrl();
        $backendDomain = urlencode(self::jitsiBackendDomain());
        $hiddenDomain = urlencode((string) env('JITSI_HIDDEN_DOMAIN', 'recorder.vc.mulkmed.com'));
        $moderatorOnly = filter_var(env('JITSI_AUTO_RECORD_MODERATOR_ONLY', true), FILTER_VALIDATE_BOOLEAN)
            ? '1'
            : '0';
        $recordingEnabled = filter_var(env('JITSI_RECORDING_ENABLED', true), FILTER_VALIDATE_BOOLEAN);
        $autoRecord = $recordingEnabled ? '&autoRecord=1' : '';

        return "{$baseUrl}wrapper.html?roomId={$roomId}&jwt={$jwt}&domain={$backendDomain}&hiddenDomain={$hiddenDomain}{$autoRecord}&autoRecordModeratorOnly={$moderatorOnly}";
    }

    private static function jitsiRoomIdPrefix(): string
    {
        return request()->getHost() === 'india.mulkmed.com' ? 'IN' : 'UAE';
    }

    public static function GeneratePatientJitsiMeetingLink($appointment, $room, $endTimestamp){
        $roomId = self::jitsiRoomIdPrefix() . '_' . $appointment->id;
        $isModerator = self::jitsiJwtModeratorForRole(false);
        $payload = self::buildJitsiJwtPayload((object) [
                    'appointment_id' => $appointment->id,
                    'role' => 'patient',
                    'user_id' => $appointment->user_id,
            'moderator' => $isModerator,
        ], $isModerator);

        return self::buildJitsiWrapperUrl($roomId, self::encodeJitsiJwt($payload));
    }

    public static function GenerateDoctorJitsiMeetingLink($jitsiMeeting, $room, $endTimestamp){
        $roomId = self::jitsiRoomIdPrefix() . '_' . $jitsiMeeting->id;
        $payload = self::buildJitsiJwtPayload((object) [
                    'appointment_id' => $jitsiMeeting->id,
                    'role' => 'doctor',
                    'user_id' => $jitsiMeeting->doctor_id,
            'moderator' => true,
        ], true);

        return self::buildJitsiWrapperUrl($roomId, self::encodeJitsiJwt($payload));
    }

    public static function CreatePatientLink($appointment, $room, $endTimestamp){
        return $meetingUrl = url("/api/v1/join_jitsi_meeting?user_id={$appointment->user_id}&room={$room}");
    }

    public static function CreateDoctorLink($jitsiMeeting, $room, $endTimestamp){
        return $meetingUrl = url("/api/v1/join_jitsi_meeting?doctor_id={$jitsiMeeting->doctor_id}&room={$room}");
    }

    public static function CreatePatientLinkMail($appointment, $room, $endTimestamp){
        return $meetingUrl = url("/api/v1/join_meeting_mail?user_id={$appointment->user_id}&room={$room}");
    }

    public static function CreateDoctorLinkMail($jitsiMeeting, $room, $endTimestamp){
        return $meetingUrl = url("/api/v1/join_meeting_mail?doctor_id={$jitsiMeeting->doctor_id}&room={$room}");
    }

    public static function GenerateTouristJitsiMeetingLink($appointment, $room, $endTimestamp){
        $roomId = self::jitsiRoomIdPrefix() . '_tourist_' . $appointment->id;
        $isModerator = self::jitsiJwtModeratorForRole(false);
        $payload = self::buildJitsiJwtPayload((object) [
                    'appointment_id' => $appointment->id,
                    'role' => 'tourist',
                    'tourist_id' => $appointment->tourist_id,
            'moderator' => $isModerator,
        ], $isModerator);

        return self::buildJitsiWrapperUrl($roomId, self::encodeJitsiJwt($payload));
    }

    public static function GenerateTouristDoctorJitsiMeetingLink($jitsiMeeting, $room, $endTimestamp){
        $roomId = self::jitsiRoomIdPrefix() . '_tourist_' . $jitsiMeeting->id;
        $payload = self::buildJitsiJwtPayload((object) [
                    'appointment_id' => $jitsiMeeting->id,
                    'role' => 'doctor',
                    'user_id' => $jitsiMeeting->doctor_id,
            'moderator' => true,
        ], true);

        return self::buildJitsiWrapperUrl($roomId, self::encodeJitsiJwt($payload));
    }

    public static function CreateTouristLink($appointment, $room, $endTimestamp){
        return $meetingUrl = url("/api/v1/tourist/join_tourist_jitsi_meeting?tourist_id={$appointment->tourist_id}&room={$room}");
    }

    public static function CreateTouristLinkV2($appointment, $room, $endTimestamp){
        return $meetingUrl = url("/api/v1/tourist/join_tourist_jitsi_meeting_v2?tourist_id={$appointment->tourist_id}&room={$room}");
    }

    public static function CreateTouristDoctorLink($jitsiMeeting, $room, $endTimestamp){
        return $meetingUrl = url("/api/v1/tourist/join_tourist_jitsi_meeting?doctor_id={$jitsiMeeting->doctor_id}&room={$room}");
    }

    public static function CreateTouristDoctorLinkV2($jitsiMeeting, $room, $endTimestamp){
        return $meetingUrl = url("/api/v1/tourist/join_tourist_jitsi_meeting_v2?doctor_id={$jitsiMeeting->doctor_id}&room={$room}");
    }

    public static function CreateTouristLinkMail($appointment, $room, $endTimestamp){
        return $meetingUrl = url("/api/v1/tourist/join_tourist_meeting_mail?tourist_id={$appointment->tourist_id}&room={$room}");
    }

    public static function CreateTouristDoctorLinkMail($jitsiMeeting, $room, $endTimestamp){
        return $meetingUrl = url("/api/v1/tourist/join_tourist_meeting_mail?doctor_id={$jitsiMeeting->doctor_id}&room={$room}");
    }

    public static function appointmentIdFromJitsiRoomId(string $roomId): ?int
    {
        if (preg_match('/^(?:IN|UAE)_tourist_(\d+)$/', $roomId, $matches)) {
            return (int) $matches[1];
        }

        if (preg_match('/^(?:IN|UAE)_(\d+)$/', $roomId, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    public static function findJitsiMeetingByRoomOrRoomId(string $roomOrRoomId)
    {
        $meeting = JitsiMeeting::where('room', $roomOrRoomId)->first();
        if ($meeting) {
            return $meeting;
        }

        $appointmentId = self::appointmentIdFromJitsiRoomId($roomOrRoomId);
        if (!$appointmentId) {
            return null;
        }

        if (str_contains($roomOrRoomId, '_tourist_')) {
            return TouristJitsiMeeting::where('appointment_id', $appointmentId)->latest()->first();
        }

        return JitsiMeeting::where('appointment_id', $appointmentId)->latest()->first();
    }

    public static function verifyJitsiJwt(string $jwt): bool
    {
        $secret = env('JITSI_SECRET');
        if (!$secret) {
            return false;
        }

        try {
            JWT::decode($jwt, new Key($secret, 'HS256'));
            return true;
        } catch (\Exception $e) {
            Log::warning('Jitsi JWT verification failed', ['message' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Mobile app payload. Open "url" in WebView (wrapper.html) so recording matches web.
     * "room" + "token" alone (native SDK) will not show recording without extra config.
     */
    public static function jitsiLinkPayloadForApp(?string $wrapperUrl): ?array
    {
        if (empty($wrapperUrl)) {
            return null;
        }

        $wrapperUrl = self::normalizeJitsiWrapperUrl($wrapperUrl);
        $parts = parse_url($wrapperUrl);
        parse_str($parts['query'] ?? '', $query);

        return [
            'url' => $wrapperUrl,
            'serverURL' => ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? ''),
            'room' => $query['roomId'] ?? null,
            'token' => $query['jwt'] ?? null,
            'hiddenDomain' => $query['hiddenDomain'] ?? env('JITSI_HIDDEN_DOMAIN', 'recorder.vc.mulkmed.com'),
        ];
    }

    public static function jitsiJoinApiFields(?string $wrapperUrl): array
    {
        return self::jitsiLinkPayloadForApp($wrapperUrl) ?? [];
    }

    public static function parseJitsiRoomIdFromWrapperUrl(?string $wrapperUrl): ?string
    {
        if (empty($wrapperUrl)) {
            return null;
        }

        parse_str(parse_url($wrapperUrl, PHP_URL_QUERY) ?? '', $query);

        return $query['roomId'] ?? null;
    }

    public static function jitsiBothParticipantsJoined($meeting): bool
    {
        if ($meeting === null) {
            return false;
        }

        return (int) ($meeting->user_joined ?? 0) === 1
            && (int) ($meeting->doctor_joined ?? 0) === 1;
    }

    public static function jitsiEitherParticipantMissing($meeting): bool
    {
        if ($meeting === null) {
            return true;
        }

        return (int) ($meeting->user_joined ?? 0) !== 1
            || (int) ($meeting->doctor_joined ?? 0) !== 1;
    }

    /**
     * Silent post-join hook from Flutter (conference_joined=1 on join_jitsi_meeting).
     * Marks participant joined and triggers Jibri once via internal Jitsi API (not exposed to app).
     */
    public static function handleJitsiConferenceJoined(JitsiMeeting $meeting, Request $request): bool
    {
        if ($request->has('doctor_id')) {
            if ((int) $request->doctor_id !== (int) $meeting->doctor_id) {
                return false;
            }
            $meeting->doctor_joined = 1;
            $meeting->save();
        } elseif ($request->has('user_id')) {
            if ((int) $request->user_id !== (int) $meeting->user_id) {
                return false;
            }
            $meeting->user_joined = 1;
            $meeting->save();
        } else {
            return false;
        }

        self::triggerJibriRecordingIfNeeded($meeting, $request->input('jitsi_room_id'));

        return true;
    }

    /**
     * Server-side Jibri start (hidden recorder domain). Requires JITSI_JIBRI_INTERNAL_* on Jitsi host.
     */
    public static function triggerJibriRecordingIfNeeded(JitsiMeeting $meeting, ?string $jitsiRoomIdOverride = null): void
    {
        if (!filter_var(env('JITSI_RECORDING_ENABLED', true), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        $moderatorOnly = filter_var(env('JITSI_AUTO_RECORD_MODERATOR_ONLY', true), FILTER_VALIDATE_BOOLEAN);
        if ($moderatorOnly && (int) $meeting->doctor_joined !== 1) {
            return;
        }

        $appointmentId = (int) $meeting->appointment_id;
        $cacheKey = 'jitsi_jibri_recording:' . $appointmentId;
        if (!Cache::add($cacheKey, 1, now()->addHours(3))) {
            return;
        }

        $internalUrl = rtrim((string) env('JITSI_JIBRI_INTERNAL_URL', ''), '/');
        $internalSecret = (string) env('JITSI_JIBRI_INTERNAL_SECRET', '');
        if ($internalUrl === '' || $internalSecret === '') {
            Cache::forget($cacheKey);
            Log::info('Jitsi Jibri recording skipped: configure JITSI_JIBRI_INTERNAL_URL and JITSI_JIBRI_INTERNAL_SECRET');

            return;
        }

        $jitsiRoomId = $jitsiRoomIdOverride
            ?: self::parseJitsiRoomIdFromWrapperUrl($meeting->doctor_link)
            ?: self::parseJitsiRoomIdFromWrapperUrl($meeting->patient_link);

        if (!$jitsiRoomId) {
            Cache::forget($cacheKey);
            Log::warning('Jitsi Jibri recording skipped: no roomId on meeting links', [
                'appointment_id' => $appointmentId,
            ]);

            return;
        }

        $payload = self::buildJitsiJwtPayload((object) [
            'appointment_id' => $appointmentId,
            'role' => 'service',
            'moderator' => true,
        ], true, $jitsiRoomId);

        $jwt = self::encodeJitsiJwt($payload);

        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'X-Jitsi-Internal-Key' => $internalSecret,
                    'Accept' => 'application/json',
                ])
                ->post($internalUrl, [
                    'roomName' => $jitsiRoomId,
                    'jwt' => $jwt,
                    'domain' => env('JITSI_DOMAIN', 'vc.mulkmed.com'),
                    'hiddenDomain' => env('JITSI_HIDDEN_DOMAIN', 'recorder.vc.mulkmed.com'),
                    'mode' => 'file',
                    'appointment_id' => $appointmentId,
                ]);

            if (!$response->successful()) {
                Cache::forget($cacheKey);
                Log::warning('Jitsi Jibri recording trigger failed', [
                    'appointment_id' => $appointmentId,
                    'room' => $jitsiRoomId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Cache::forget($cacheKey);
            Log::warning('Jitsi Jibri recording trigger exception', [
                'appointment_id' => $appointmentId,
                'room' => $jitsiRoomId,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
