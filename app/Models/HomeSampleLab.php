<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HomeSampleLab extends Model
{
    protected $table = 'home_sample_labs';

    protected $fillable = [
        'name',
        'email',
        'status',
    ];

    protected $casts = [
        'status' => 'integer',
    ];
}
