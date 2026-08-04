<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAiVitalsTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('ai_vitals')) {
            Schema::create('ai_vitals', function (Blueprint $table) {
                $table->id();
                $table->integer('user_id');
                $table->integer('appointment_id')->default(0);
                $table->longText('report')->nullable();
                $table->longText('senoclock_ai_response')->nullable();
                $table->longText('shen_ai')->nullable();
                $table->dateTime('scan_date')->nullable();
                $table->string('pdf_file')->nullable();
                $table->integer('is_longevity')->default(0);
                $table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('ai_vitals');
    }
}
