<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DoctorAppointmentSlots extends Model
{
     protected $fillable = [
        'doctor_id',
        'time',
        'weekday',
        'booking_limit'
    ];
    use HasFactory;
    public $table = "doctor_appointment_slots";
    public function doctor()
    {
        return $this->hasOne(Doctors::class, 'id', 'doctor_id');
    }
}
