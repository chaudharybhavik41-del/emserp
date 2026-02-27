<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('machines', function (Blueprint $table) {
            $table->id();
            
            // Taxonomy Integration (links to existing material taxonomy)
            $table->foreignId('material_type_id')->constrained('material_types')->restrictOnDelete();
            $table->foreignId('material_category_id')->constrained('material_categories')->restrictOnDelete();
            $table->foreignId('material_subcategory_id')->nullable()->constrained('material_subcategories')->nullOnDelete();
            
            // Identity
            $table->string('code', 50)->unique()->comment('Auto-generated: CAT-YYYY-NNNN');
            $table->string('name', 200);
            $table->string('short_name', 100)->nullable();
            $table->string('serial_number', 100)->unique();
            
            // Specifications
            $table->string('make', 100)->nullable();
            $table->string('model', 100)->nullable();
            $table->string('grade', 100)->nullable()->comment('Capacity/Rating');
            $table->text('spec')->nullable();
            $table->integer('year_of_manufacture')->nullable();
            $table->string('rated_capacity', 100)->nullable();
            $table->string('power_rating', 100)->nullable();
            $table->enum('fuel_type', ['electric', 'diesel', 'gas', 'hydraulic', 'manual', 'other'])->nullable();
            
            // Purchase Details
            $table->foreignId('supplier_party_id')->nullable()->constrained('parties')->nullOnDelete();
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_price', 15, 2)->default(0);
            $table->string('purchase_invoice_no', 100)->nullable();
            $table->integer('warranty_months')->default(0);
            $table->date('warranty_expiry_date')->nullable();
            
            // Operational
            $table->decimal('operating_hours_total', 10, 2)->default(0);
            $table->string('current_location', 200)->nullable();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->enum('status', ['active', 'under_maintenance', 'breakdown', 'retired', 'disposed'])->default('active');
            
            // Assignment Tracking (Phase 2)
            $table->boolean('is_issued')->default(false);
            $table->enum('current_assignment_type', ['contractor', 'company_worker', 'unassigned'])->default('unassigned');
            $table->foreignId('current_contractor_party_id')->nullable()->constrained('parties')->nullOnDelete();
            $table->foreignId('current_worker_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('current_project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->date('assigned_date')->nullable();
            
            // Maintenance Tracking (Phase 3)
            $table->integer('maintenance_frequency_days')->default(90);
            $table->date('last_maintenance_date')->nullable();
            $table->date('next_maintenance_due_date')->nullable();
            $table->integer('maintenance_alert_days')->default(7);
            
            // Documents
            $table->string('manual_document_path')->nullable();
            $table->string('calibration_certificate_path')->nullable();
            $table->string('insurance_document_path')->nullable();
            
            // Status
            $table->boolean('is_active')->default(true);
            
            // Audit
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes - WITH SHORTENED NAMES
            $table->index('material_type_id', 'idx_machines_mat_type');
            $table->index('material_category_id', 'idx_machines_mat_cat');
            $table->index('code', 'idx_machines_code');
            $table->index('serial_number', 'idx_machines_serial');
            $table->index('status', 'idx_machines_status');
            $table->index('is_active', 'idx_machines_active');
            $table->index('is_issued', 'idx_machines_issued');
            $table->index('current_assignment_type', 'idx_machines_assign_type');
            $table->index('next_maintenance_due_date', 'idx_machines_maint_due');
            $table->index(['current_contractor_party_id', 'current_worker_user_id'], 'idx_machines_assign_ids');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('machines');
    }
};