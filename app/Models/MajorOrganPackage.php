<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MajorOrganPackage extends Model
{
    use HasFactory;

    public $table = 'major_organ_package';

    protected $fillable = [
        'title',
        'badge',
        'description',
        'price',
        'image',
        'status',
    ];

    protected $casts = [
        'status' => 'integer',
        'price' => 'decimal:2',
    ];
}
