<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('production_v2_trial_assembly_measurements') && ! Schema::hasColumn('production_v2_trial_assembly_measurements', 'assembly_id')) {
            Schema::table('production_v2_trial_assembly_measurements', function (Blueprint $table) {
                $table->foreignId('assembly_id')->nullable()->after('actual_dimension');
                $table->foreign('assembly_id', 'fk_pv2_tam_asm')
                    ->references('id')
                    ->on('production_v2_assemblies')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasTable('production_v2_trial_assembly_links')) {
            Schema::create('production_v2_trial_assembly_links', function (Blueprint $table) {
                $table->id();
                $table->foreignId('trial_assembly_id');
                $table->foreignId('assembly_id');
                $table->unsignedInteger('sequence_no')->default(0);
                $table->timestamps();

                $table->unique(['trial_assembly_id', 'assembly_id'], 'uq_pv2_trial_assembly_link');
                $table->foreign('trial_assembly_id', 'fk_pv2_tal_trial')
                    ->references('id')
                    ->on('production_v2_trial_assemblies')
                    ->cascadeOnDelete();
                $table->foreign('assembly_id', 'fk_pv2_tal_asm')
                    ->references('id')
                    ->on('production_v2_assemblies')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('production_v2_trial_assembly_links');

        if (Schema::hasTable('production_v2_trial_assembly_measurements') && Schema::hasColumn('production_v2_trial_assembly_measurements', 'assembly_id')) {
            Schema::table('production_v2_trial_assembly_measurements', function (Blueprint $table) {
                $table->dropForeign('fk_pv2_tam_asm');
                $table->dropColumn('assembly_id');
            });
        }
    }
};
