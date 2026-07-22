<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhySecondOpinionMatters extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'image',
        'info',
        'url'

    ];

    public $table = "why_second_opinion_matters";
}
