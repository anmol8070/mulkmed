<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('home_sample_collections')) {
            return;
        }

        Schema::create('home_sample_collections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('selection_id')->nullable();
            $table->string('order_id')->nullable();
            $table->unsignedBigInteger('package_id')->nullable();
            $table->string('package_title')->nullable();
            $table->string('emirate');
            $table->string('area');
            $table->string('building');
            $table->string('flat_no')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('country_code', 8)->default('+971');
            $table->string('mobile_number', 20);
            $table->date('preferred_date');
            $table->string('preferred_time_slot');
            $table->string('status')->default('pending'); // pending, confirmed, collected, cancelled
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('home_sample_collections');
    }
};
