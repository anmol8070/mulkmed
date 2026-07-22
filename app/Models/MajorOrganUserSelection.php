<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MajorOrganUserSelection extends Model
{
    use HasFactory;

    public $table = 'major_organ_user_selections';

    protected $fillable = [
        'user_id',
        'selection_type',
        'package_id',
        'package_title',
        'package_badge',
        'package_price',
        'organ_health_check_count',
        'total_biomarkers',
        'selected_organ_tests',
        'selected_biomarkers',
        'total_amount',
        'status',
    ];

    protected $casts = [
        'selected_organ_tests' => 'array',
        'selected_biomarkers' => 'array',
        'package_price' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'organ_health_check_count' => 'integer',
        'total_biomarkers' => 'integer',
        'status' => 'integer',
    ];
}
