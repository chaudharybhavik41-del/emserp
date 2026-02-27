<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // NOTE:
        // This project already ships a broader `create_production_tables` migration
        // that creates `production_activities` when the table is missing.
        // In existing databases (like the provided export), that migration has
        // already created this table, so running this migration must NOT error.
        if (Schema::hasTable('production_activities')) {
            return;
        }

        // Keep the schema consistent with the definition inside
        // `2025_12_20_100000_create_production_tables.php`.
        Schema::create('production_activities', function (Blueprint $table) {
            $table->id();

            $table->string('code', 50)->unique();
            $table->string('name', 200);

            // part | assembly | both
            $table->enum('applies_to', ['part', 'assembly', 'both'])->default('both');
            $table->unsignedInteger('default_sequence')->default(0);

            // Billing settings
            $table->foreignId('billing_uom_id')->nullable()->constrained('uoms')->nullOnDelete();
            // manual | kg_from_weight | meter_from_len | sqm_from_area | nos
            $table->string('calculation_method', 50)->default('manual');

            // Workflow flags
            $table->boolean('is_fitupp')->default(false); // creates assembly & consumes parts
            $table->boolean('requires_machine')->default(false);
            $table->boolean('requires_qc')->default(false);

            $table->boolean('is_active')->default(true);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'default_sequence'], 'idx_prod_act_active_seq');
            $table->index('applies_to', 'idx_prod_act_applies_to');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_activities');
    }
};
