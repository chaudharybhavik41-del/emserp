<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_candidates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->default(1);
            $table->string('candidate_code', 30)->unique();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('email', 150)->nullable();
            $table->string('phone', 20);
            $table->string('alternate_phone', 20)->nullable();
            $table->string('current_location', 150)->nullable();
            $table->string('position_applied', 150)->nullable();
            $table->string('current_company', 150)->nullable();
            $table->string('current_designation', 150)->nullable();
            $table->unsignedInteger('total_experience_months')->default(0);
            $table->unsignedSmallInteger('notice_period_days')->nullable();
            $table->decimal('current_ctc', 12, 2)->nullable();
            $table->decimal('expected_ctc', 12, 2)->nullable();
            $table->string('source', 100)->nullable();
            $table->string('status', 50)->default('new');
            $table->date('interview_date')->nullable();
            $table->text('skills')->nullable();
            $table->text('remarks')->nullable();
            $table->string('resume_path')->nullable();
            $table->string('resume_file_name')->nullable();
            $table->unsignedBigInteger('resume_file_size')->nullable();
            $table->string('resume_mime_type', 100)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('candidate_code', 'idx_hr_candidates_code');
            $table->index('status', 'idx_hr_candidates_status');
            $table->index('email', 'idx_hr_candidates_email');
            $table->index('phone', 'idx_hr_candidates_phone');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_candidates');
    }
};
