<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('material_receipts')) {
            Schema::create('material_receipts', function (Blueprint $table) {
                $table->id();

                $table->string('receipt_number', 50)->nullable();
                $table->date('receipt_date')->nullable();

                // Own vs client material
                $table->boolean('is_client_material')->default(false);

                // Purchase order reference (string for now, can later be FK)
                $table->string('po_number', 50)->nullable();

                // Supplier (for own material)
                $table->foreignId('supplier_id')
                    ->nullable()
                    ->constrained('parties')
                    ->nullOnDelete();

                // Project (optional, usually from PO or indent)
                $table->foreignId('project_id')
                    ->nullable()
                    ->constrained('projects')
                    ->nullOnDelete();

                // Client (for client-supplied material)
                $table->foreignId('client_party_id')
                    ->nullable()
                    ->constrained('parties')
                    ->nullOnDelete();

                // Invoice / challan details
                $table->string('invoice_number', 100)->nullable();
                $table->date('invoice_date')->nullable();
                $table->string('challan_number', 100)->nullable();
                $table->string('vehicle_number', 100)->nullable();

                // Status: draft, qc_pending, qc_passed, qc_rejected
                $table->string('status', 30)->default('draft');

                $table->foreignId('created_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->foreignId('qc_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->timestamp('qc_at')->nullable();

                $table->text('remarks')->nullable();

                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('material_receipts');
    }
};
