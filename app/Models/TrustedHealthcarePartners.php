<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrustedHealthcarePartners extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'image',
        'rating',
        'ar_name',
        'fr_name',
        'hi_name',
        'ur_name'
    ];
    public $table = "trusted_healthcare_partners";
}
