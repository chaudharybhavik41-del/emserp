<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * DEV-3: Subcontractor RA Bill - Database Migration
     * 
     * Per Development Plan v1.2:
     * - Subcontractor RA Bill posting to accounts and project WIP
     * - Dr Project WIP – Subcontractor (project-wise)
     * - Dr GST Input (if applicable)
     * - Cr Subcontractor Payable (party ledger)
     */
    public function up(): void
    {
        Schema::create('subcontractor_ra_bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            
            // Subcontractor (must be a party with is_contractor = true)
            $table->foreignId('subcontractor_id')->constrained('parties')->cascadeOnDelete();
            
            // Project linkage (required for WIP costing)
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            
            // Bill details
            $table->string('ra_number', 50)->comment('Running Account Bill Number');
            $table->string('bill_number', 100)->nullable()->comment('Subcontractor Invoice Number');
            $table->date('bill_date');
            $table->date('due_date')->nullable();
            $table->unsignedInteger('ra_sequence')->default(1)->comment('RA sequence for this subcontractor-project');
            
            // Period covered
            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();
            
            // Work Order / Contract reference
            $table->foreignId('work_order_id')->nullable()->comment('Link to work order if exists');
            $table->string('work_order_number', 100)->nullable();
            
            // Amount breakdown
            $table->decimal('gross_amount', 15, 2)->default(0)->comment('Gross bill value before deductions');
            $table->decimal('previous_amount', 15, 2)->default(0)->comment('Cumulative previous RA amount');
            $table->decimal('current_amount', 15, 2)->default(0)->comment('This RA amount (gross - previous)');
            
            // Deductions
            $table->decimal('retention_percent', 5, 2)->default(0);
            $table->decimal('retention_amount', 15, 2)->default(0);
            $table->decimal('advance_recovery', 15, 2)->default(0);
            $table->decimal('other_deductions', 15, 2)->default(0);
            $table->text('deduction_remarks')->nullable();
            
            // Net after deductions (before tax)
            $table->decimal('net_amount', 15, 2)->default(0)->comment('current_amount - deductions');
            
            // GST
            $table->decimal('cgst_rate', 5, 2)->default(0);
            $table->decimal('cgst_amount', 15, 2)->default(0);
            $table->decimal('sgst_rate', 5, 2)->default(0);
            $table->decimal('sgst_amount', 15, 2)->default(0);
            $table->decimal('igst_rate', 5, 2)->default(0);
            $table->decimal('igst_amount', 15, 2)->default(0);
            $table->decimal('total_gst', 15, 2)->default(0);
            
            // TDS
            $table->string('tds_section', 20)->nullable()->comment('e.g., 194C, 194J');
            $table->decimal('tds_rate', 5, 2)->default(0);
            $table->decimal('tds_amount', 15, 2)->default(0);
            
            // Final payable
            $table->decimal('total_amount', 15, 2)->default(0)->comment('net_amount + GST - TDS');
            
            // Accounting linkage
            $table->foreignId('voucher_id')->nullable()->constrained('vouchers')->nullOnDelete();
            
            // Status workflow
            $table->enum('status', ['draft', 'submitted', 'approved', 'posted', 'rejected', 'cancelled'])
                  ->default('draft');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            
            // Audit
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            
            // Indexes
            $table->unique(['company_id', 'ra_number'], 'subcon_ra_unique_number');
            $table->index(['subcontractor_id', 'project_id']);
            $table->index('status');
            $table->index('bill_date');
        });

        // RA Bill Line Items (BOQ-wise breakdown)
        Schema::create('subcontractor_ra_bill_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subcontractor_ra_bill_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('line_no')->default(1);
            
            // BOQ Item reference (if BOQ module exists)
            $table->foreignId('boq_item_id')->nullable();
            $table->string('boq_item_code', 50)->nullable();
            
            // Description
            $table->string('description', 500);
            $table->foreignId('uom_id')->nullable()->constrained('uoms')->nullOnDelete();
            
            // Quantities
            $table->decimal('contracted_qty', 15, 3)->default(0);
            $table->decimal('previous_qty', 15, 3)->default(0);
            $table->decimal('current_qty', 15, 3)->default(0);
            $table->decimal('cumulative_qty', 15, 3)->default(0);
            
            // Rates & Amounts
            $table->decimal('rate', 15, 2)->default(0);
            $table->decimal('previous_amount', 15, 2)->default(0);
            $table->decimal('current_amount', 15, 2)->default(0);
            $table->decimal('cumulative_amount', 15, 2)->default(0);
            
            $table->text('remarks')->nullable();
            $table->timestamps();
            
            $table->index('boq_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subcontractor_ra_bill_lines');
        Schema::dropIfExists('subcontractor_ra_bills');
    }
};
