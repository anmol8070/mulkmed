<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLabReportsTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('lab_reports')) {
            Schema::create('lab_reports', function (Blueprint $table) {
                $table->id();
                $table->integer('user_id');
                $table->string('document_path')->nullable();
                $table->string('type')->nullable();
                $table->longText('ocr_text')->nullable();
                $table->string('extraction_source')->nullable();
                $table->longText('analysis_response')->nullable();
                $table->longText('available_biomarkers')->nullable();
                $table->longText('missing_biomarkers')->nullable();
                $table->integer('available_count')->default(0);
                $table->integer('missing_count')->default(0);
                $table->integer('total_count')->default(0);
                $table->decimal('to_pay', 10, 2)->default(0.00);
                $table->decimal('overall_match_percentage', 10, 2)->nullable();
                $table->decimal('confidence_score', 10, 2)->nullable();
                $table->integer('status')->default(1);
                $table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('lab_reports');
    }
}
