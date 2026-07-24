<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserLongevityPlan extends Model
{
    use HasFactory;
    
    protected $table = 'user_longevity_plans';

    protected $fillable = [
        'user_id',
        'plan_id',
        'order_id',
        'amount',
        'status',
        'expiry_date',
    ];

    public function plan()
    {
        return $this->belongsTo(LongevityPlan::class, 'plan_id', 'id');
    }
}
