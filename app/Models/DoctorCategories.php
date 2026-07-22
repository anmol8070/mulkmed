<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DoctorCategories extends Model
{
    use HasFactory;
    protected $fillable = [
        'title',
        'info',
        'image',
        'keywords',
        'ar_title',
        'fr_title',
        'hi_title',
        'ur_title',
        'ar_info',
        'fr_info',
        'hi_info',
        'ur_info',
    ];
    public $table = "doctor_cats";
}
