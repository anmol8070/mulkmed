<?php

namespace App\BusinessTimeZoneHelper;

class BusinessTimeZoneHelper
{
    function businessNow($asString = false, $format = 'Y-m-d H:i:s') {
        $now = \Carbon\Carbon::now(config('app.business_timezone'));
        return $asString ? $now->format($format) : $now;
    }
    
}