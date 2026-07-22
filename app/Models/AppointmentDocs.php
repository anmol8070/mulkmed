<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class AppointmentDocs extends Model
{
    use HasFactory;
    public $table = "appointment_docs";

    public function user()
    {
        return $this->hasOne(Users::class, 'id', 'user_id');
    }
    public function appointment()
    {
        return $this->hasOne(Appointments::class, 'id', 'appointment_id');
    }
    protected static function booted()
    {
        static::addGlobalScope('not_deleted', function (Builder $builder) {
            $builder->where('is_deleted', false);
        });
    }
}
