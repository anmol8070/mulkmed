<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HospitalCategories extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'image',
        'ar_name',
        'fr_name',
        'hi_name',
        'ur_name'
    ];
    public $table = "hospital_categories";
}
