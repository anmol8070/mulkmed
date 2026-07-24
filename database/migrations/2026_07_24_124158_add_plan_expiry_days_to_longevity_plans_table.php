<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPlanExpiryDaysToLongevityPlansTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::table('longevity_plans', function (Blueprint $table) {
            $table->integer('plan_expiry_days')->nullable()->after('price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('longevity_plans', function (Blueprint $table) {
            $table->dropColumn('plan_expiry_days');
        });
    }
}
