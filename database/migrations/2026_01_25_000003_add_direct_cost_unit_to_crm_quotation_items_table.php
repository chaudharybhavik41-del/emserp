<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_quotation_items', function (Blueprint $table) {
            if (! Schema::hasColumn('crm_quotation_items', 'direct_cost_unit')) {
                // Direct cost per unit (excluding profit) computed from cost breakup
                $table->decimal('direct_cost_unit', 15, 2)->default(0)->after('unit_price');
            }
        });
    }

    public function down(): void
    {
        Schema::table('crm_quotation_items', function (Blueprint $table) {
            if (Schema::hasColumn('crm_quotation_items', 'direct_cost_unit')) {
                $table->dropColumn('direct_cost_unit');
            }
        });
    }
};
