<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * DEV-4: Client RA Bill / Sales Invoice - Database Migration
     * 
     * Per Development Plan v1.2:
     * - Post Client RA Bills / Sales invoices to accounts
     * - Dr Sundry Debtor
     * - Cr Fabrication Revenue (or other revenue ledgers)
     * - Cr Output GST (CGST/SGST/IGST via tax config)
     * - WIP → COGS handled via manual JV for Phase 1
     */
    public function up(): void
    {
        Schema::create('client_ra_bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            
            // Client (must be a party with is_client = true)
            $table->foreignId('client_id')->constrained('parties')->cascadeOnDelete();
            
            // Project linkage
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            
            // Bill details
            $table->string('ra_number', 50)->comment('Running Account Bill Number');
            $table->string('invoice_number', 100)->nullable()->comment('Tax Invoice Number');
            $table->date('bill_date');
            $table->date('due_date')->nullable();
            $table->unsignedInteger('ra_sequence')->default(1)->comment('RA sequence for this client-project');
            
            // Period covered
            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();
            
            // Contract reference
            $table->foreignId('contract_id')->nullable()->comment('Link to contract/work order if exists');
            $table->string('contract_number', 100)->nullable();
            $table->string('po_number', 100)->nullable()->comment('Client PO reference');
            
            // Revenue type
            $table->enum('revenue_type', ['fabrication', 'erection', 'supply', 'service', 'other'])
                  ->default('fabrication');
            
            // Amount breakdown
            $table->decimal('gross_amount', 15, 2)->default(0)->comment('Gross bill value (cumulative)');
            $table->decimal('previous_amount', 15, 2)->default(0)->comment('Cumulative previous RA amount');
            $table->decimal('current_amount', 15, 2)->default(0)->comment('This RA amount');
            
            // Deductions by client
            $table->decimal('retention_percent', 5, 2)->default(0);
            $table->decimal('retention_amount', 15, 2)->default(0);
            $table->decimal('other_deductions', 15, 2)->default(0);
            $table->text('deduction_remarks')->nullable();
            
            // Net after deductions (before tax)
            $table->decimal('net_amount', 15, 2)->default(0);
            
            // GST Output
            $table->decimal('cgst_rate', 5, 2)->default(0);
            $table->decimal('cgst_amount', 15, 2)->default(0);
            $table->decimal('sgst_rate', 5, 2)->default(0);
            $table->decimal('sgst_amount', 15, 2)->default(0);
            $table->decimal('igst_rate', 5, 2)->default(0);
            $table->decimal('igst_amount', 15, 2)->default(0);
            $table->decimal('total_gst', 15, 2)->default(0);
            
            // TDS deducted by client
            $table->string('tds_section', 20)->nullable();
            $table->decimal('tds_rate', 5, 2)->default(0);
            $table->decimal('tds_amount', 15, 2)->default(0);
            
            // Final receivable
            $table->decimal('total_amount', 15, 2)->default(0)->comment('net_amount + GST');
            $table->decimal('receivable_amount', 15, 2)->default(0)->comment('total_amount - TDS (expected receipt)');
            
            // Accounting linkage
            $table->foreignId('voucher_id')->nullable()->constrained('vouchers')->nullOnDelete();
            
            // E-Invoice details (future GST compliance)
            $table->string('irn', 100)->nullable()->comment('Invoice Reference Number');
            $table->text('qr_code')->nullable();
            $table->timestamp('einvoice_generated_at')->nullable();
            
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
            $table->unique(['company_id', 'ra_number'], 'client_ra_unique_number');
            $table->unique(['company_id', 'invoice_number'], 'client_ra_unique_invoice');
            $table->index(['client_id', 'project_id']);
            $table->index('status');
            $table->index('bill_date');
        });

        // RA Bill Line Items (BOQ-wise / milestone-wise breakdown)
        Schema::create('client_ra_bill_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_ra_bill_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('line_no')->default(1);
            
            // BOQ / Milestone reference
            $table->foreignId('boq_item_id')->nullable();
            $table->string('boq_item_code', 50)->nullable();
            
            // Revenue account (for different revenue types)
            $table->foreignId('revenue_account_id')->nullable()->constrained('accounts')->nullOnDelete();
            
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
            
            // SAC/HSN for GST
            $table->string('sac_hsn_code', 20)->nullable();
            
            $table->text('remarks')->nullable();
            $table->timestamps();
            
            $table->index('boq_item_id');
            $table->index('revenue_account_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_ra_bill_lines');
        Schema::dropIfExists('client_ra_bills');
    }
};
