
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            // Density in kg/m3 (e.g. 7850 for steel)
            $table->decimal('density', 10, 3)->nullable()->after('thickness');

            // Weight per meter in kg/m (for sections)
            $table->decimal('weight_per_meter', 10, 3)->nullable()->after('density');
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            if (Schema::hasColumn('items', 'weight_per_meter')) {
                $table->dropColumn('weight_per_meter');
            }
            if (Schema::hasColumn('items', 'density')) {
                $table->dropColumn('density');
            }
        });
    }
};
