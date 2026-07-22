<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class TouristAppointmentEmrs extends Model
{
    use HasFactory;
    public $table = "tourist_appointment_emrs";

    public function appointment()
    {
        return $this->hasOne(TouristAppointments::class, 'id', 'appointment_id');
    }
    protected static function booted()
    {
        static::addGlobalScope('not_deleted', function (Builder $builder) {
            $builder->where('is_deleted', false);
        });
    }
}
