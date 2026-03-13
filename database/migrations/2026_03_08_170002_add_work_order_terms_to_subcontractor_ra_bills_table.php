<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('subcontractor_ra_bills')) {
            return;
        }

        Schema::table('subcontractor_ra_bills', function (Blueprint $table) {
            if (! Schema::hasColumn('subcontractor_ra_bills', 'payment_terms_days')) {
                $table->unsignedInteger('payment_terms_days')->nullable()->after('due_date');
            }

            if (! Schema::hasColumn('subcontractor_ra_bills', 'security_deposit_percent')) {
                $table->decimal('security_deposit_percent', 5, 2)->default(0)->after('retention_amount');
            }

            if (! Schema::hasColumn('subcontractor_ra_bills', 'security_deposit_amount')) {
                $table->decimal('security_deposit_amount', 15, 2)->default(0)->after('security_deposit_percent');
            }

            if (! Schema::hasColumn('subcontractor_ra_bills', 'other_terms')) {
                $table->text('other_terms')->nullable()->after('remarks');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('subcontractor_ra_bills')) {
            return;
        }

        Schema::table('subcontractor_ra_bills', function (Blueprint $table) {
            if (Schema::hasColumn('subcontractor_ra_bills', 'payment_terms_days')) {
                $table->dropColumn('payment_terms_days');
            }

            if (Schema::hasColumn('subcontractor_ra_bills', 'security_deposit_amount')) {
                $table->dropColumn('security_deposit_amount');
            }

            if (Schema::hasColumn('subcontractor_ra_bills', 'security_deposit_percent')) {
                $table->dropColumn('security_deposit_percent');
            }

            if (Schema::hasColumn('subcontractor_ra_bills', 'other_terms')) {
                $table->dropColumn('other_terms');
            }
        });
    }
};
