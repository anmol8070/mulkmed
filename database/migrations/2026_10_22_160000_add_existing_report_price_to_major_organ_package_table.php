<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Price charged on "Have an existing lab report?". Separate from the package price.
     */
    public function up(): void
    {
        if (Schema::hasTable('major_organ_package') && !Schema::hasColumn('major_organ_package', 'existing_report_price')) {
            Schema::table('major_organ_package', function (Blueprint $table) {
                $table->decimal('existing_report_price', 10, 2)->nullable()->after('price');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('major_organ_package') && Schema::hasColumn('major_organ_package', 'existing_report_price')) {
            Schema::table('major_organ_package', function (Blueprint $table) {
                $table->dropColumn('existing_report_price');
            });
        }
    }
};
