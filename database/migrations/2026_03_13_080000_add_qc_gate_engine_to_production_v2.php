<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('production_v2_route_template_steps')) {
            Schema::table('production_v2_route_template_steps', function (Blueprint $table) {
                if (! Schema::hasColumn('production_v2_route_template_steps', 'qc_gate_required')) {
                    $table->boolean('qc_gate_required')->default(false)->after('is_mandatory');
                }
                if (! Schema::hasColumn('production_v2_route_template_steps', 'qc_gate_mode')) {
                    $table->string('qc_gate_mode', 40)->nullable()->after('qc_gate_required');
                }
                if (! Schema::hasColumn('production_v2_route_template_steps', 'qc_gate_type')) {
                    $table->string('qc_gate_type', 60)->nullable()->after('qc_gate_mode');
                }
                if (! Schema::hasColumn('production_v2_route_template_steps', 'qc_gate_remarks')) {
                    $table->text('qc_gate_remarks')->nullable()->after('qc_gate_type');
                }
            });
        }

        if (Schema::hasTable('production_v2_part_route_steps')) {
            Schema::table('production_v2_part_route_steps', function (Blueprint $table) {
                if (! Schema::hasColumn('production_v2_part_route_steps', 'qc_gate_required')) {
                    $table->boolean('qc_gate_required')->default(false)->after('is_mandatory');
                }
                if (! Schema::hasColumn('production_v2_part_route_steps', 'qc_gate_mode')) {
                    $table->string('qc_gate_mode', 40)->nullable()->after('qc_gate_required');
                }
                if (! Schema::hasColumn('production_v2_part_route_steps', 'qc_gate_type')) {
                    $table->string('qc_gate_type', 60)->nullable()->after('qc_gate_mode');
                }
                if (! Schema::hasColumn('production_v2_part_route_steps', 'qc_gate_remarks')) {
                    $table->text('qc_gate_remarks')->nullable()->after('qc_gate_type');
                }
            });
        }

        if (Schema::hasTable('production_v2_assembly_route_steps')) {
            Schema::table('production_v2_assembly_route_steps', function (Blueprint $table) {
                if (! Schema::hasColumn('production_v2_assembly_route_steps', 'qc_gate_required')) {
                    $table->boolean('qc_gate_required')->default(false)->after('is_mandatory');
                }
                if (! Schema::hasColumn('production_v2_assembly_route_steps', 'qc_gate_mode')) {
                    $table->string('qc_gate_mode', 40)->nullable()->after('qc_gate_required');
                }
                if (! Schema::hasColumn('production_v2_assembly_route_steps', 'qc_gate_type')) {
                    $table->string('qc_gate_type', 60)->nullable()->after('qc_gate_mode');
                }
                if (! Schema::hasColumn('production_v2_assembly_route_steps', 'qc_gate_remarks')) {
                    $table->text('qc_gate_remarks')->nullable()->after('qc_gate_type');
                }
            });
        }

        if (! Schema::hasTable('production_v2_qc_gate_events')) {
            Schema::create('production_v2_qc_gate_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
                $table->foreignId('operation_master_id')->constrained('production_v2_operation_masters')->restrictOnDelete();
                $table->foreignId('part_route_step_id')->nullable()->constrained('production_v2_part_route_steps')->nullOnDelete();
                $table->foreignId('assembly_route_step_id')->nullable()->constrained('production_v2_assembly_route_steps')->nullOnDelete();
                $table->foreignId('part_definition_id')->nullable()->constrained('production_v2_part_definitions')->nullOnDelete();
                $table->foreignId('assembly_id')->nullable()->constrained('production_v2_assemblies')->nullOnDelete();
                $table->foreignId('related_dpr_id')->nullable()->constrained('production_dprs')->nullOnDelete();
                $table->date('gate_date');
                $table->string('gate_mode', 40)->nullable();
                $table->string('gate_type', 60)->nullable();
                $table->string('result', 40)->default('pending');
                $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('inspector_agency', 160)->nullable();
                $table->string('reference_no', 120)->nullable();
                $table->text('remarks')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['project_id', 'gate_date'], 'idx_pv2_qc_gate_events_project_date');
                $table->index(['assembly_route_step_id', 'gate_date'], 'idx_pv2_qc_gate_events_assembly_step');
                $table->index(['part_route_step_id', 'gate_date'], 'idx_pv2_qc_gate_events_part_step');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('production_v2_qc_gate_events');

        if (Schema::hasTable('production_v2_assembly_route_steps')) {
            Schema::table('production_v2_assembly_route_steps', function (Blueprint $table) {
                foreach (['qc_gate_remarks', 'qc_gate_type', 'qc_gate_mode', 'qc_gate_required'] as $column) {
                    if (Schema::hasColumn('production_v2_assembly_route_steps', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('production_v2_part_route_steps')) {
            Schema::table('production_v2_part_route_steps', function (Blueprint $table) {
                foreach (['qc_gate_remarks', 'qc_gate_type', 'qc_gate_mode', 'qc_gate_required'] as $column) {
                    if (Schema::hasColumn('production_v2_part_route_steps', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('production_v2_route_template_steps')) {
            Schema::table('production_v2_route_template_steps', function (Blueprint $table) {
                foreach (['qc_gate_remarks', 'qc_gate_type', 'qc_gate_mode', 'qc_gate_required'] as $column) {
                    if (Schema::hasColumn('production_v2_route_template_steps', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
