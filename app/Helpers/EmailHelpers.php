<?php

namespace App\Helpers;
use Illuminate\Support\Facades\Http;

class EmailHelpers
{
    public static function sendSms($mobile, $message)
    {
        $baseUrl = env('SMS_API_BASE_URL');
        $urlPath = env('SMS_API_BASE_PATH');
        
        // $unicodeHexMessage = strtoupper(bin2hex(mb_convert_encoding($message, 'UTF-16BE', 'UTF-8')));

         $unicodeHexMessage = $message;


        $params = [
            'User' => env('SMS_API_USERNAME'),
            'passwd' => env('SMS_API_PASSWORD'),
            'mobilenumber' => $mobile,
            'sid' => env('SMS_API_SENDER'),
            'mtype' => 'N',
            'DR' => 'Y',
            'message' => $unicodeHexMessage,
        ];

        $response = Http::baseUrl($baseUrl)
            ->asForm()
            ->post($urlPath, $params);

        if ($response->successful()) {
            // Log or process the response body if needed
            return $response->body(); // or return true;
        } else {
            // Log the error or handle failure
            return $response->status() . ' - ' . $response->body();
        }
    }
    
}