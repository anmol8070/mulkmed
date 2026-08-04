<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LabReport extends Model
{
    use HasFactory;

    public $table = 'lab_reports';

    protected $fillable = [
        'user_id',
        'document_path',
        'type',
        'ocr_text',
        'extraction_source',
        'analysis_response',
        'available_biomarkers',
        'missing_biomarkers',
        'available_count',
        'missing_count',
        'total_count',
        'to_pay',
        'overall_match_percentage',
        'confidence_score',
        'status',
        'senoclock_id',
        'senoclock_pdf_path',
        'senoclock_status',
    ];

    protected $casts = [
        'analysis_response' => 'array',
        'available_biomarkers' => 'array',
        'missing_biomarkers' => 'array',
        'available_count' => 'integer',
        'missing_count' => 'integer',
        'total_count' => 'integer',
        'to_pay' => 'decimal:2',
        'overall_match_percentage' => 'decimal:2',
        'confidence_score' => 'decimal:2',
        'status' => 'integer',
        'senoclock_status' => 'string',
    ];
}
