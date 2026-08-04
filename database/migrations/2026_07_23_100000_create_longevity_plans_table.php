<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLongevityPlansTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('longevity_plans')) {
            Schema::create('longevity_plans', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->string('subtitle')->nullable();
                $table->text('description')->nullable();
                $table->string('image')->nullable();
                $table->json('whats_included')->nullable();
                $table->json('benefits')->nullable();
                $table->integer('status')->default(1);
                $table->integer('display_order')->default(0);
                $table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('longevity_plans');
    }
}
