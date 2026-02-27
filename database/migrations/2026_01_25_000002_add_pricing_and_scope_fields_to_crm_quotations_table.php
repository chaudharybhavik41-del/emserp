<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_quotations', function (Blueprint $table) {
            if (! Schema::hasColumn('crm_quotations', 'quote_mode')) {
                // item = tender / BOQ style (qty x rate)
                // rate_per_kg = rate offer (usually Rs/kg with scope)
                $table->string('quote_mode', 20)->default('item')->after('project_name');
            }

            if (! Schema::hasColumn('crm_quotations', 'is_rate_only')) {
                // When true, totals are not computed/shown (rate-only offer)
                $table->boolean('is_rate_only')->default(false)->after('quote_mode');
            }

            if (! Schema::hasColumn('crm_quotations', 'profit_percent')) {
                // Profit margin applied on direct cost (unit basis)
                $table->decimal('profit_percent', 5, 2)->default(0)->after('is_rate_only');
            }

            if (! Schema::hasColumn('crm_quotations', 'scope_of_work')) {
                // Scope to be shown in quotation (esp. for rate-per-kg offers)
                $table->text('scope_of_work')->nullable()->after('other_terms');
            }

            if (! Schema::hasColumn('crm_quotations', 'exclusions')) {
                $table->text('exclusions')->nullable()->after('scope_of_work');
            }
        });
    }

    public function down(): void
    {
        Schema::table('crm_quotations', function (Blueprint $table) {
            foreach ([
                'quote_mode',
                'is_rate_only',
                'profit_percent',
                'scope_of_work',
                'exclusions',
            ] as $col) {
                if (Schema::hasColumn('crm_quotations', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
