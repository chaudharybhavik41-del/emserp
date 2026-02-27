<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_quotations', function (Blueprint $table) {
            if (!Schema::hasColumn('crm_quotations', 'client_po_number')) {
                $table->string('client_po_number', 100)->nullable()->after('project_name');
            }

            if (!Schema::hasColumn('crm_quotations', 'payment_terms_days')) {
                // Numeric days for accounts reminders
                $table->unsignedInteger('payment_terms_days')->nullable()->after('payment_terms');
            }

            if (!Schema::hasColumn('crm_quotations', 'freight_terms')) {
                $table->string('freight_terms', 150)->nullable()->after('payment_terms_days');
            }

            if (!Schema::hasColumn('crm_quotations', 'project_special_notes')) {
                $table->text('project_special_notes')->nullable()->after('other_terms');
            }
        });
    }

    public function down(): void
    {
        Schema::table('crm_quotations', function (Blueprint $table) {
            foreach ([
                'client_po_number',
                'payment_terms_days',
                'freight_terms',
                'project_special_notes',
            ] as $col) {
                if (Schema::hasColumn('crm_quotations', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
