<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientEmrReport extends Model
{
    use HasFactory;

    protected $table = 'patient_emr_reports';

    protected $fillable = [
        'appointment_id',
        'doctor_id',
        'is_finalized',
        'finalized_at',
        'vital_details',
        'chief_complaints',
        'symptoms',
        'allergies',
        'history_of_present_illness',
        'diagnosis',
        'lab_orders',
        'radiology_orders',
        'dhpo_prescription_document',
        'dhpo_prescriptions',
        'speciality_hospital_reference',
        'follow_up_date',
    ];

    protected $casts = [
        'is_finalized' => 'boolean',
        'finalized_at' => 'datetime',
        'follow_up_date' => 'date',
    ];

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(TouristAppointments::class, 'appointment_id', 'id');
    }
}
