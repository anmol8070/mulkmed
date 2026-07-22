<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BestOfferPlanOrders extends Model
{
    use HasFactory;

    protected $fillable = [
        'status',
        'purchased_at'
    ];
    public $table = "best_offer_plan_orders";
}        