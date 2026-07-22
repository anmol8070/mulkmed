<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LongevityPlan extends Model
{
    use HasFactory;

    public $table = 'longevity_plans';

    protected $fillable = [
        'title',
        'subtitle',
        'description',
        'image',
        'whats_included',
        'benefits',
        'status',
        'display_order',
    ];

    protected $casts = [
        'whats_included' => 'array',
        'benefits' => 'array',
        'status' => 'integer',
        'display_order' => 'integer',
    ];
}
