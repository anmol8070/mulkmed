<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TouristDoctorReviews extends Model
{
    use HasFactory;
    public $table = "tourist_doctor_reviews";
    public function doctor()
    {
        return $this->hasOne(Doctors::class, 'id', 'doctor_id');
    }
    public function tourist()
    {
        return $this->hasOne(TouristList::class, 'id', 'tourist_id');
    }
    public function appointment()
    {
        return $this->hasOne(TouristAppointments::class, 'id', 'appointment_id');
    }
}
