<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_vendor_returns', function (Blueprint $table) {
            $table->id();

            $table->foreignId('material_receipt_id')
                ->constrained('material_receipts')
                ->cascadeOnDelete();

            $table->string('vendor_return_number')->nullable()->unique();

            $table->date('return_date');

            // Party receiving the return (Supplier for own material, Client for client material)
            $table->foreignId('to_party_id')
                ->nullable()
                ->constrained('parties')
                ->nullOnDelete();

            $table->foreignId('project_id')
                ->nullable()
                ->constrained('projects')
                ->nullOnDelete();

            // Accounting linkage (only for own material and when posted purchase bills exist)
            $table->foreignId('voucher_id')
                ->nullable()
                ->constrained('vouchers')
                ->nullOnDelete();

            $table->string('reason', 255)->nullable();
            $table->text('remarks')->nullable();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_vendor_returns');
    }
};
