<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('production_audit_logs')) {
            Schema::create('production_audit_logs', function (Blueprint $table) {
                $table->id();

                $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();

                $table->string('event', 100); // e.g. plan.approve, dpr.submit, qc.pass
                $table->string('entity_type', 100)->nullable(); // ProductionPlan, ProductionDpr etc
                $table->unsignedBigInteger('entity_id')->nullable();

                $table->text('message')->nullable();
                $table->json('meta')->nullable();

                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('ip_address', 50)->nullable();
                $table->string('user_agent', 255)->nullable();

                $table->timestamps();

                $table->index(['project_id', 'event'], 'idx_prod_audit_project_event');
                $table->index(['entity_type', 'entity_id'], 'idx_prod_audit_entity');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('production_audit_logs');
    }
};
