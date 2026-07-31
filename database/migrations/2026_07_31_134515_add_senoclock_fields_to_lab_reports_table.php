<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSenoclockFieldsToLabReportsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('lab_reports', function (Blueprint $table) {
            $table->string('senoclock_id')->nullable()->after('status');
            $table->string('senoclock_pdf_path')->nullable()->after('senoclock_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('lab_reports', function (Blueprint $table) {
            $table->dropColumn(['senoclock_id', 'senoclock_pdf_path']);
        });
    }
}
