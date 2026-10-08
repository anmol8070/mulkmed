<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HomeSampleCollection extends Model
{
    protected $table = 'home_sample_collections';

    protected $fillable = [
        'user_id',
        'selection_id',
        'order_id',
        'package_id',
        'package_title',
        'lab_id',
        'emirate',
        'area',
        'building',
        'flat_no',
        'latitude',
        'longitude',
        'country_code',
        'mobile_number',
        'preferred_date',
        'preferred_time_slot',
        'status',
        'notes',
    ];

    protected $casts = [
        'preferred_date' => 'date:Y-m-d',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public function lab()
    {
        return $this->belongsTo(HomeSampleLab::class, 'lab_id');
    }
}
