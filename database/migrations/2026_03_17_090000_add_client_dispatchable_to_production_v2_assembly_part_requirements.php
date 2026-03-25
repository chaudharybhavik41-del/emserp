<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_v2_assembly_part_requirements', function (Blueprint $table) {
            $table->boolean('is_client_dispatchable')
                ->default(false)
                ->after('is_mandatory');
        });
    }

    public function down(): void
    {
        Schema::table('production_v2_assembly_part_requirements', function (Blueprint $table) {
            $table->dropColumn('is_client_dispatchable');
        });
    }
};
