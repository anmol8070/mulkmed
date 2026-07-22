<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Appointments extends Model
{
    use HasFactory;
    public $table = "appointments";

    public function user()
    {
        return $this->hasOne(Users::class, 'id', 'user_id');
    }
    public function doctor()
    {
        $lang = request()->header('lang', 'en');

        $columndesignation = match ($lang) {
            'ar' => 'ar_designation',
            'fr' => 'fr_designation',
            'hi' => 'hi_designation',
            'ur' => 'ur_designation',
            default => 'designation',
        };

        $columnlanguages_spoken = match ($lang) {
            'ar' => 'ar_languages_spoken',
            'fr' => 'fr_languages_spoken',
            'hi' => 'hi_languages_spoken',
            'ur' => 'ur_languages_spoken',
            default => 'languages_spoken',
        };

        return $this->hasOne(Doctors::class, 'id', 'doctor_id')
            ->select('*', DB::raw("$columndesignation as designation"), DB::raw("$columnlanguages_spoken as languages_spoken"));
        return $this->hasOne(Doctors::class, 'id', 'doctor_id');
    }
    public function prescription()
    {
        return $this->hasOne(Prescriptions::class, 'appointment_id', 'id');
    }
    public function patient()
    {
        return $this->hasOne(AddedPatients::class, 'id', 'patient_id');
    }
    public function rating()
    {
        return $this->hasOne(DoctorReviews::class, 'appointment_id', 'id');
    }

    public function documents()
    {
        return $this->hasMany(AppointmentDocs::class, 'appointment_id', 'id')->where('is_from_admin', 0);
    }

    public function admindocuments()
    {
        return $this->hasMany(AppointmentDocs::class, 'appointment_id', 'id');
    }

    public function emrdocuments()
    {
        return $this->hasMany(AppointmentEmrs::class, 'appointment_id', 'id');
    }

    public function appointmentMeeting()
    {
        return $this->hasOne(JitsiMeeting::class, 'appointment_id', 'id');
    }

      public function user_plan()
    {
        return $this->belongsTo(UserPlan::class, 'user_plan_id', 'id');
    }
}
