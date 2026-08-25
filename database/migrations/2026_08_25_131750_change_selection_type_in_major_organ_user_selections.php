<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ChangeSelectionTypeInMajorOrganUserSelections extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement("ALTER TABLE major_organ_user_selections MODIFY COLUMN selection_type ENUM('package', 'individual', 'longevity') DEFAULT NULL");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("ALTER TABLE major_organ_user_selections MODIFY COLUMN selection_type ENUM('package', 'individual') DEFAULT NULL");
    }
}
