<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RiderAllocation extends Model
{
    use HasFactory;
    public $table = "rider_allocations";

    public function product_plan()
    {
        return $this->hasOne(ProductPlan::class, 'id', 'product_plan_id');
    }
}
