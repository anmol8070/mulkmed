<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSenoclockAiRequestToAiVitalsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('ai_vitals') && !Schema::hasColumn('ai_vitals', 'senoclock_ai_request')) {
            Schema::table('ai_vitals', function (Blueprint $table) {
                $table->longText('senoclock_ai_request')->nullable()->after('report');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasTable('ai_vitals') && Schema::hasColumn('ai_vitals', 'senoclock_ai_request')) {
            Schema::table('ai_vitals', function (Blueprint $table) {
                $table->dropColumn('senoclock_ai_request');
            });
        }
    }
}
