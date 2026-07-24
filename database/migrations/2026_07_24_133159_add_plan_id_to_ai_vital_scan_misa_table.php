<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPlanIdToAiVitalScanMisaTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('ai_vital_scan_misa', function (Blueprint $table) {
            $table->unsignedBigInteger('plan_id')->nullable()->after('payment_status');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('ai_vital_scan_misa', function (Blueprint $table) {
            $table->dropColumn('plan_id');
        });
    }
}
