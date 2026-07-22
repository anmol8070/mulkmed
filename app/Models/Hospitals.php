<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Hospitals extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
       'image',
        'rating',
        'rating_count',
        'country',
        'address',
        'latitude',
        'longitude',
        'website',
        'contact_number',
        'category',
        'clinic_timing',
        'services_offered',
        'exclusive_mulkmed_benefits',
        'procedure_ids',
        'ar_name', 'fr_name', 'hi_name', 'ur_name',
        'ar_country', 'fr_country', 'hi_country', 'ur_country',
        'ar_address', 'fr_address', 'hi_address', 'ur_address',
        'ar_clinic_timing', 'fr_clinic_timing', 'hi_clinic_timing', 'ur_clinic_timing',
        'ar_services_offered', 'fr_services_offered', 'hi_services_offered', 'ur_services_offered',
        'ar_exclusive_mulkmed_benefits', 'fr_exclusive_mulkmed_benefits', 'hi_exclusive_mulkmed_benefits', 'ur_exclusive_mulkmed_benefits',
    ];
    public $table = "hospitals";
}
