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
        'biomarkers',
        'status',
        'display_order',
    ];

    protected $casts = [
        'biomarkers' => 'array',
        'status' => 'integer',
        'display_order' => 'integer',
    ];

    // The price column was removed; API responses that still read $test->price get 0.
    public function getPriceAttribute($value): float
    {
        return (float) ($value ?? 0);
    }
}
