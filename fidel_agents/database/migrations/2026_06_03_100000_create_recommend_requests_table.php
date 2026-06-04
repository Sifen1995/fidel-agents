<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recommend_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('student_id')->index();
            $table->string('grade_level');
            $table->text('request');
            $table->text('response');
            $table->string('focus_subject')->nullable();
            $table->string('llm_provider')->nullable();
            $table->string('llm_model')->nullable();
            $table->boolean('processed_offline')->default(false);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recommend_requests');
    }
};
