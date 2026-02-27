<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('production_dprs')) {
            return;
        }

        Schema::table('production_dprs', function (Blueprint $table) {
            if (! Schema::hasColumn('production_dprs', 'geo_override_reason')) {
                $table->string('geo_override_reason', 500)->nullable()->after('geo_status');
            }
        });
    }

    public function down(): void
    {
        // Non-destructive down: do not drop column to avoid data loss
    }
};
