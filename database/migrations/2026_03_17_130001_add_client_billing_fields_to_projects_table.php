<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('client_billing_mode', 40)->nullable()->after('payment_terms_days');
            $table->string('client_billing_default_bill_kind', 40)->nullable()->after('client_billing_mode');
            $table->string('client_billing_source_basis', 40)->nullable()->after('client_billing_default_bill_kind');
            $table->string('client_billing_material_scope', 30)->nullable()->after('client_billing_source_basis');
            $table->boolean('client_billing_separate_material_service')->default(false)->after('client_billing_material_scope');
            $table->string('client_billing_tds_section', 20)->nullable()->after('client_billing_separate_material_service');
            $table->decimal('client_billing_tds_rate', 8, 4)->nullable()->after('client_billing_tds_section');
            $table->text('client_billing_notes')->nullable()->after('client_billing_tds_rate');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn([
                'client_billing_mode',
                'client_billing_default_bill_kind',
                'client_billing_source_basis',
                'client_billing_material_scope',
                'client_billing_separate_material_service',
                'client_billing_tds_section',
                'client_billing_tds_rate',
                'client_billing_notes',
            ]);
        });
    }
};
