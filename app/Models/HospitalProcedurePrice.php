<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HospitalProcedurePrice extends Model
{
    protected $fillable = [
        'hospital_id','procedure_id','price_type','price'
    ];

    public function hospital()
    {
        return $this->belongsTo(Hospitals::class);
    }
}
