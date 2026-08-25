<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPlanIdToMajorOrganUserSelectionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('major_organ_user_selections', function (Blueprint $table) {
            $table->integer('plan_id')->nullable()->after('user_id');
            $table->string('order_id')->nullable()->after('status');
            $table->tinyInteger('payment_status')->default(0)->after('order_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * 
     * @return void
     */
    public function down()
    {
        Schema::table('major_organ_user_selections', function (Blueprint $table) {
            $table->dropColumn(['plan_id', 'order_id', 'payment_status']);
        });
    }
}
