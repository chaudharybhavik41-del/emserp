<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('production_v2_dispatches')) {
            Schema::create('production_v2_dispatches', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
                $table->foreignId('client_party_id')->nullable()->constrained('parties')->nullOnDelete();
                $table->string('dispatch_number', 80)->unique();
                $table->date('dispatch_date')->nullable();
                $table->string('vehicle_number', 80)->nullable();
                $table->string('lr_number', 120)->nullable();
                $table->string('transporter_name', 180)->nullable();
                $table->string('gate_pass_ref', 120)->nullable();
                $table->decimal('total_qty', 14, 3)->default(0);
                $table->decimal('total_weight_kg', 14, 3)->default(0);
                $table->string('status', 40)->default('draft');
                $table->text('remarks')->nullable();
                $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('finalized_at')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['project_id', 'dispatch_date'], 'idx_pv2_dispatch_project_date');
                $table->index(['project_id', 'status'], 'idx_pv2_dispatch_project_status');
            });
        }

        if (! Schema::hasTable('production_v2_dispatch_lines')) {
            Schema::create('production_v2_dispatch_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('dispatch_id')->constrained('production_v2_dispatches')->cascadeOnDelete();
                $table->foreignId('assembly_id')->nullable()->constrained('production_v2_assemblies')->nullOnDelete();
                $table->decimal('qty', 14, 3)->default(0);
                $table->decimal('weight_kg', 14, 3)->default(0);
                $table->string('assembly_code_snapshot', 120)->nullable();
                $table->string('assembly_name_snapshot', 200)->nullable();
                $table->string('girder_no_snapshot', 80)->nullable();
                $table->string('segment_no_snapshot', 80)->nullable();
                $table->text('remarks')->nullable();
                $table->timestamps();

                $table->index(['dispatch_id'], 'idx_pv2_dispatch_line_dispatch');
                $table->index(['assembly_id'], 'idx_pv2_dispatch_line_assembly');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('production_v2_dispatch_lines');
        Schema::dropIfExists('production_v2_dispatches');
    }
};
