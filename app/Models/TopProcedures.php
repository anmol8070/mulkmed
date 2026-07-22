<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TopProcedures extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'image',
        'ar_name',
        'fr_name',
        'hi_name',
        'ur_name',
        'ar_description',
        'fr_description',
        'hi_description',
        'ur_description'
    ];
    public $table = "top_procedures";
}
