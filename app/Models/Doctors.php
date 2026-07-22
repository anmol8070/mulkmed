<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Http\Request;

class Doctors extends Model
{
    use HasFactory;
    public $table = "doctors";

//     public function getImageAttribute($value)
// {
//     if (!$value) return null;

//     $path = Str::of($value)->ltrim('/')->replaceFirst('public/', '');
//     // Force the desired pattern (will work only if Option 1 or 2 above is set up)
//     return config('app.url') . '/storage/' . ltrim($path, '/');
// }

    public static function generateDoctorFullData($doctorId)
    {
        $doctor = Doctor::find($doctorId);

        if ($doctor) {
            // overwrite image with raw DB value
            $doctor->image = $doctor->image;
        }

        return $doctor;
    }

    public function category()
    {
        return $this->hasOne(DoctorCategories::class, 'id', 'category_id');
    }
    public function bankAccount()
    {
        return $this->hasOne(DoctorBankAccount::class, 'doctor_id', 'id');
    }
    public function reviews()
    {
        return $this->hasMany(DoctorReviews::class, 'doctor_id', 'id');
    }
    public function holidays()
    {
        return $this->hasMany(DoctorHolidays::class, 'doctor_id', 'id');
    }
    public function slots()
    {
        return $this->hasMany(DoctorAppointmentSlots::class, 'doctor_id', 'id');
    }
    public function awards()
    {
        return $this->hasMany(DoctorAwards::class, 'doctor_id', 'id');
    }
    public function services()
    {
        return $this->hasMany(DoctorServices::class, 'doctor_id', 'id');
    }
    public function expertise()
    {
        $lang = request()->header('lang', 'en');
        if($lang == 'en'){
            return $this->hasMany(DoctorExpertise::class, 'doctor_id', 'id')
                                ->select('id', 'doctor_id', 'title'); 
        }
        if($lang == 'ar'){
            return $this->hasMany(DoctorExpertise::class, 'doctor_id', 'id')
                                ->select('id', 'doctor_id', 'ar_title as title'); 
        }
        if($lang == 'ur'){
            return $this->hasMany(DoctorExpertise::class, 'doctor_id', 'id')
                                ->select('id', 'doctor_id', 'ur_title as title'); 
        }
        if($lang == 'fr'){
            return $this->hasMany(DoctorExpertise::class, 'doctor_id', 'id')
                                ->select('id', 'doctor_id','fr_title as title'); 
        }
        if($lang == 'hi'){
            return $this->hasMany(DoctorExpertise::class, 'doctor_id', 'id')
                                ->select('id', 'doctor_id', 'hi_title as title'); 
        }

        
    }
    public function experience()
    {
        return $this->hasMany(DoctorExperience::class, 'doctor_id', 'id');
    }
    public function serviceLocations()
    {
        return $this->hasMany(DoctorServiceLocations::class, 'doctor_id', 'id');
    }

    public function avgRating()
    {
        return $this->reviews->avg('rating');
    }
}
