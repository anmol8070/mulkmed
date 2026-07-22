<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HospitalImages extends Model
{
    use HasFactory;
    public $table = "hospital_images";

      // Only these can be mass-updated
    protected $fillable = [
        'hospital_id',
        'image',
        'is_deleted',
    ];
}
