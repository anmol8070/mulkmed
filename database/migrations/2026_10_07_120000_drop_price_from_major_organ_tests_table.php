<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('major_organ_tests', 'price')) {
            Schema::table('major_organ_tests', function (Blueprint $table) {
                $table->dropColumn('price');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('major_organ_tests', 'price')) {
            Schema::table('major_organ_tests', function (Blueprint $table) {
                $table->decimal('price', 10, 2)->default(0)->after('icon');
            });
        }
    }
};
