<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            if (! Schema::hasColumn('items', 'surface_area_per_meter')) {
                $table->decimal('surface_area_per_meter', 12, 4)
                    ->nullable()
                    ->after('weight_per_meter');
            }
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            if (Schema::hasColumn('items', 'surface_area_per_meter')) {
                $table->dropColumn('surface_area_per_meter');
            }
        });
    }
};
