<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAiVitalScanMisaTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('ai_vital_scan_misa')) {
            Schema::create('ai_vital_scan_misa', function (Blueprint $table) {
                $table->id();
                $table->integer('user_id');
                $table->integer('appointment_id')->default(0);
                $table->string('payment_status')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('ai_vital_scan_misa');
    }
}
