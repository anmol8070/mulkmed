<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MajorOrganTest extends Model
{
    use HasFactory;

    public $table = 'major_organ_tests';

    protected $fillable = [
        'name',
        'icon',
        'price',
        'biomarkers',
        'status',
        'display_order',
    ];

    protected $casts = [
        'biomarkers' => 'array',
        'status' => 'integer',
        'display_order' => 'integer',
        'price' => 'decimal:2',
    ];
}
