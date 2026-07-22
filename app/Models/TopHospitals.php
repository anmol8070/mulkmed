<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TopHospitals extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
         'name',
       'image',
        'rating',
        'priority',
        'hospital_id'
    ];

    public $table = "top_hospitals";
}
