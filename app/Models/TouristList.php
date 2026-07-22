<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TouristList extends Model
{
    use HasFactory;
    public $table = "tourist_list";

    // 🔹 Add fillable columns
    protected $fillable = [
        'agent_id',
        'agent_type',
        'first_name',
        'last_name',
        'booking_id',
        'check_in_time',
        'check_out_time',
        'country_code',
        'contact_number',
        'service_type',
        'fly_in',
        'fly_out',
        'start_date',
        'import_log_id',
        'inbound_riders',
        'outbound_riders',
        'number_of_midas',
        'number_of_ai_health_check',
        'number_of_consultation',
        'visa_expiry_days'
    ];
}
