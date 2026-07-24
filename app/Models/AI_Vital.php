<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AI_Vital extends Model
{
    use HasFactory;
    public $table = "ai_vitals";

    protected $guarded = [];

    protected $casts = [
        'senoclock_ai_response' => 'array',
        'shen_ai' => 'array',
    ];
}