<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class MovePlanIdToAiVitals extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('ai_vital_scan_misa', function (Blueprint $table) {
            $table->dropColumn('plan_id');
        });

        Schema::table('ai_vitals', function (Blueprint $table) {
            $table->unsignedBigInteger('plan_id')->nullable()->after('appointment_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('ai_vitals', function (Blueprint $table) {
            $table->dropColumn('plan_id');
        });

        Schema::table('ai_vital_scan_misa', function (Blueprint $table) {
            $table->unsignedBigInteger('plan_id')->nullable()->after('payment_status');
        });
    }
}
