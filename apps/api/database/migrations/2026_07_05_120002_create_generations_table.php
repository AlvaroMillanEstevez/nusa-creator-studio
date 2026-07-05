<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('job_type');
            $table->string('provider')->nullable();
            $table->string('status')->default('pending');
            $table->decimal('cost_usd', 12, 4)->default(0);
            $table->unsignedInteger('credits_charged')->default(0);
            $table->json('request_payload')->nullable();
            $table->json('result_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['job_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generations');
    }
};
