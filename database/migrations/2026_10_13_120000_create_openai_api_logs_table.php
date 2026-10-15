<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('openai_api_logs')) {
            Schema::create('openai_api_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('purpose', 60)->index(); // e.g. pdf_analysis_file_id, file_upload
                $table->string('method', 10);
                $table->string('endpoint');
                $table->string('model', 60)->nullable();
                $table->tinyInteger('is_paid')->default(0); // 1 = model call billed for tokens
                $table->unsignedSmallInteger('http_status')->nullable(); // null = no response (timeout/connection error)
                $table->tinyInteger('success')->default(0); // 1 = 2xx response
                $table->unsignedInteger('input_tokens')->nullable();
                $table->unsignedInteger('output_tokens')->nullable();
                $table->unsignedInteger('total_tokens')->nullable();
                $table->unsignedInteger('duration_ms')->nullable();
                $table->string('filename')->nullable();
                $table->text('error_message')->nullable();
                $table->timestamps();
                $table->index('created_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('openai_api_logs');
    }
};
