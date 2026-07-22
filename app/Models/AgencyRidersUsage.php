<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgencyRidersUsage extends Model
{
    protected $table = 'agency_riders_usage';

    protected $fillable = [
        'agency_id',
        'plan_id',
        'tourist_id',
        'service_type',
        'payment_type',
        'used_riders',
        'inbound_riders',
        'outbound_riders'
    ];
}
