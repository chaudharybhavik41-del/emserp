<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * HR Employee Documents, Qualifications, Experience, and Dependents
     */
    public function up(): void
    {
        // Employee Documents
        Schema::create('hr_employee_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hr_employee_id')->constrained('hr_employees')->cascadeOnDelete();
            
            $table->enum('document_type', [
                'photo', 'aadhar', 'pan', 'passport', 'driving_license', 'voter_id',
                'birth_certificate', 'education_certificate', 'experience_letter',
                'relieving_letter', 'offer_letter', 'appointment_letter',
                'salary_slip', 'bank_statement', 'address_proof', 'police_verification',
                'medical_certificate', 'other'
            ])->default('other');
            $table->string('document_name', 150);
            $table->string('document_number', 100)->nullable();
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('issuing_authority', 150)->nullable();
            $table->string('file_path');
            $table->string('file_name', 255);
            $table->integer('file_size')->default(0);
            $table->string('mime_type', 50)->nullable();
            
            $table->boolean('is_verified')->default(false);
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('verified_at')->nullable();
            $table->text('remarks')->nullable();
            
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            
            $table->index(['hr_employee_id', 'document_type'], 'idx_hr_ed_emp_type');
        });
        
        // Educational Qualifications
        Schema::create('hr_employee_qualifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hr_employee_id')->constrained('hr_employees')->cascadeOnDelete();
            
            $table->enum('qualification_type', [
                'below_10th', '10th', '12th', 'diploma', 'iti', 
                'graduation', 'post_graduation', 'doctorate', 'professional', 'other'
            ])->default('graduation');
            $table->string('degree_name', 150);
            $table->string('specialization', 150)->nullable();
            $table->string('institution_name', 200);
            $table->string('university_board', 200)->nullable();
            $table->integer('year_of_passing')->nullable();
            $table->decimal('percentage_cgpa', 6, 2)->nullable();
            $table->enum('grade_type', ['percentage', 'cgpa', 'grade'])->default('percentage');
            $table->string('roll_number', 50)->nullable();
            $table->string('certificate_path')->nullable();
            
            $table->boolean('is_verified')->default(false);
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('verified_at')->nullable();
            $table->text('remarks')->nullable();
            
            $table->timestamps();
            
            $table->index('hr_employee_id', 'idx_hr_eq_emp');
        });
        
        // Work Experience / Previous Employment
        Schema::create('hr_employee_experiences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hr_employee_id')->constrained('hr_employees')->cascadeOnDelete();
            
            $table->string('company_name', 200);
            $table->string('designation', 150);
            $table->string('department', 100)->nullable();
            $table->date('from_date');
            $table->date('to_date')->nullable();
            $table->boolean('is_current')->default(false);
            $table->integer('experience_months')->default(0);
            
            $table->string('location', 150)->nullable();
            $table->string('reporting_to', 150)->nullable();
            $table->decimal('last_ctc', 15, 2)->nullable();
            $table->text('job_responsibilities')->nullable();
            $table->text('reason_for_leaving')->nullable();
            
            // Reference
            $table->string('reference_name', 150)->nullable();
            $table->string('reference_contact', 50)->nullable();
            $table->string('reference_email', 150)->nullable();
            $table->boolean('reference_verified')->default(false);
            
            $table->string('relieving_letter_path')->nullable();
            $table->string('experience_letter_path')->nullable();
            
            $table->boolean('is_verified')->default(false);
            $table->text('remarks')->nullable();
            
            $table->timestamps();
            
            $table->index('hr_employee_id', 'idx_hr_ex_emp');
        });
        
        // Family / Dependents
        Schema::create('hr_employee_dependents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hr_employee_id')->constrained('hr_employees')->cascadeOnDelete();
            
            $table->string('name', 200);
            $table->enum('relationship', [
                'spouse', 'son', 'daughter', 'father', 'mother',
                'father_in_law', 'mother_in_law', 'brother', 'sister', 'other'
            ])->default('other');
            $table->date('date_of_birth')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->string('aadhar_number', 20)->nullable();
            $table->string('phone', 20)->nullable();
            $table->text('address')->nullable();
            
            // For Insurance & Benefits
            $table->boolean('is_dependent_for_insurance')->default(false);
            $table->boolean('is_emergency_contact')->default(false);
            $table->boolean('is_nominee')->default(false);
            $table->decimal('nomination_percentage', 5, 2)->default(0);
            
            $table->string('occupation', 100)->nullable();
            $table->boolean('is_disabled')->default(false);
            
            $table->timestamps();
            
            $table->index(['hr_employee_id', 'relationship'], 'idx_hr_dep_emp_rel');
        });
        
        // Nominee Details (for PF, Gratuity, Insurance)
        Schema::create('hr_employee_nominees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hr_employee_id')->constrained('hr_employees')->cascadeOnDelete();
            
            $table->enum('nomination_for', ['pf', 'gratuity', 'insurance', 'superannuation', 'other'])->default('pf');
            $table->string('name', 200);
            $table->string('relationship', 100);
            $table->date('date_of_birth')->nullable();
            $table->text('address')->nullable();
            $table->string('aadhar_number', 20)->nullable();
            $table->decimal('share_percentage', 5, 2)->default(100);
            
            $table->boolean('is_minor')->default(false);
            $table->string('guardian_name', 200)->nullable();
            $table->string('guardian_relationship', 100)->nullable();
            $table->text('guardian_address')->nullable();
            
            $table->date('effective_from');
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();
            
            $table->index(['hr_employee_id', 'nomination_for'], 'idx_hr_nom_emp_for');
        });
        
        // Bank Accounts (multiple accounts support)
        Schema::create('hr_employee_bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hr_employee_id')->constrained('hr_employees')->cascadeOnDelete();
            
            $table->string('bank_name', 150);
            $table->string('branch_name', 150)->nullable();
            $table->string('account_number', 30);
            $table->string('ifsc_code', 15);
            $table->string('account_holder_name', 200);
            $table->enum('account_type', ['savings', 'current', 'salary'])->default('savings');
            
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);
            $table->string('cancelled_cheque_path')->nullable();
            
            $table->boolean('is_verified')->default(false);
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('verified_at')->nullable();
            
            $table->timestamps();
            
            $table->index(['hr_employee_id', 'is_primary'], 'idx_hr_ba_emp_primary');
        });
        
        // Employee Assets (Company-provided)
        Schema::create('hr_employee_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hr_employee_id')->constrained('hr_employees')->cascadeOnDelete();
            
            $table->string('asset_type', 50); // laptop, phone, id_card, uniform, etc.
            $table->string('asset_name', 150);
            $table->string('asset_code', 50)->nullable();
            $table->string('serial_number', 100)->nullable();
            $table->string('make', 100)->nullable();
            $table->string('model', 100)->nullable();
            
            $table->date('issued_date');
            $table->date('return_date')->nullable();
            $table->decimal('asset_value', 12, 2)->default(0);
            $table->decimal('deposit_amount', 12, 2)->default(0);
            
            $table->enum('status', ['issued', 'returned', 'lost', 'damaged'])->default('issued');
            $table->enum('condition_at_issue', ['new', 'good', 'fair'])->default('good');
            $table->enum('condition_at_return', ['good', 'fair', 'damaged'])->nullable();
            
            $table->text('remarks')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('returned_to')->nullable()->constrained('users')->nullOnDelete();
            
            $table->timestamps();
            
            $table->index(['hr_employee_id', 'status'], 'idx_hr_asset_emp_status');
        });
        
        // Employee Training Records
        Schema::create('hr_employee_trainings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hr_employee_id')->constrained('hr_employees')->cascadeOnDelete();
            
            $table->string('training_name', 200);
            $table->string('training_type', 50)->nullable(); // internal, external, online
            $table->string('trainer_name', 150)->nullable();
            $table->string('training_provider', 200)->nullable();
            
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->decimal('duration_hours', 6, 2)->default(0);
            $table->string('location', 200)->nullable();
            
            $table->decimal('cost', 12, 2)->default(0);
            $table->boolean('cost_borne_by_company')->default(true);
            
            $table->enum('status', ['scheduled', 'in_progress', 'completed', 'cancelled'])->default('scheduled');
            $table->decimal('score', 5, 2)->nullable();
            $table->string('grade', 10)->nullable();
            $table->string('certificate_path')->nullable();
            
            $table->text('feedback')->nullable();
            $table->text('remarks')->nullable();
            
            $table->timestamps();
            
            $table->index(['hr_employee_id', 'status'], 'idx_hr_train_emp_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_employee_trainings');
        Schema::dropIfExists('hr_employee_assets');
        Schema::dropIfExists('hr_employee_bank_accounts');
        Schema::dropIfExists('hr_employee_nominees');
        Schema::dropIfExists('hr_employee_dependents');
        Schema::dropIfExists('hr_employee_experiences');
        Schema::dropIfExists('hr_employee_qualifications');
        Schema::dropIfExists('hr_employee_documents');
    }
};
