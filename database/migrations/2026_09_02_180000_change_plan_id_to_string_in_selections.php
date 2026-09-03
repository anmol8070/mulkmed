<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ChangePlanIdToStringInSelections extends Migration
{
    public function up()
    {
        if (Schema::hasTable('major_organ_user_selections')) {
            DB::statement("ALTER TABLE major_organ_user_selections MODIFY COLUMN plan_id VARCHAR(255) NULL DEFAULT NULL");
        }
        if (Schema::hasTable('user_longevity_plans')) {
            DB::statement("ALTER TABLE user_longevity_plans MODIFY COLUMN plan_id VARCHAR(255) NULL DEFAULT NULL");
        }
        if (Schema::hasTable('ai_vitals')) {
            DB::statement("ALTER TABLE ai_vitals MODIFY COLUMN plan_id VARCHAR(255) NULL DEFAULT NULL");
        }
    }

    public function down()
    {
    }
}
