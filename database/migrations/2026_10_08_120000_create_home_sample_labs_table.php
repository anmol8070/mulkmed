<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('home_sample_labs')) {
            Schema::create('home_sample_labs', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email');
                $table->tinyInteger('status')->default(1); // 1 = active, 0 = inactive
                $table->timestamps();
            });
        }

        if (Schema::hasTable('home_sample_collections') && !Schema::hasColumn('home_sample_collections', 'lab_id')) {
            Schema::table('home_sample_collections', function (Blueprint $table) {
                $table->unsignedBigInteger('lab_id')->nullable()->after('package_title');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('home_sample_collections', 'lab_id')) {
            Schema::table('home_sample_collections', function (Blueprint $table) {
                $table->dropColumn('lab_id');
            });
        }
        Schema::dropIfExists('home_sample_labs');
    }
};
