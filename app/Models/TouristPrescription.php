<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TouristPrescription extends Model
{
    use HasFactory;
    public $table = "tourist_prescriptions";
    public function appointment()
    {
        return $this->hasOne(TouristAppointments::class, 'id', 'appointment_id');
    }
    public function tourist()
    {
        return $this->hasOne(TouristList::class, 'id', 'tourist_id');
    }
}
