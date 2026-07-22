<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConsultRequest extends Model
{
    protected $fillable = [
        'doctor_id',
        'status',
        'retry_count',
        'expired_at',
        'consult_id',
        'appointment_id',
        'room'
    ];

    public function doctor()
    {
        return $this->belongsTo(Doctor::class);
    }

    public function appointment()
    {
        return $this->belongsTo(\App\Models\TouristAppointments::class, 'appointment_id');
    }
}