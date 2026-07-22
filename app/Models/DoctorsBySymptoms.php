<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DoctorsBySymptoms extends Model
{
    use HasFactory;
    public $table = "doctors_by_symptoms";
}
