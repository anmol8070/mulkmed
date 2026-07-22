<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmrSearchMaster extends Model
{
    use HasFactory;

    public $table = 'emr_search_masters';

    protected $fillable = [
        'category',
        'diagnosis_type',
        'name',
        'is_deleted',
    ];
}
