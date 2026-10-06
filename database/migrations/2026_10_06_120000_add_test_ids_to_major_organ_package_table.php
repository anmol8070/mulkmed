<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Organ tests included in each package. Null/empty means every active organ test.
     */
    public function up(): void
    {
        if (Schema::hasTable('major_organ_package') && !Schema::hasColumn('major_organ_package', 'test_ids')) {
            Schema::table('major_organ_package', function (Blueprint $table) {
                $table->json('test_ids')->nullable()->after('image');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('major_organ_package') && Schema::hasColumn('major_organ_package', 'test_ids')) {
            Schema::table('major_organ_package', function (Blueprint $table) {
                $table->dropColumn('test_ids');
            });
        }
    }
};
