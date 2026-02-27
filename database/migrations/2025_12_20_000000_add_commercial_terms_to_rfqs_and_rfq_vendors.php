<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // RFQ-level default commercial terms (already used in RFQ PDF template)
        if (Schema::hasTable('purchase_rfqs')) {
            $hasPay = Schema::hasColumn('purchase_rfqs', 'payment_terms_days');
            $hasDel = Schema::hasColumn('purchase_rfqs', 'delivery_terms_days');
            $hasFre = Schema::hasColumn('purchase_rfqs', 'freight_terms');

            if (!$hasPay || !$hasDel || !$hasFre) {
                Schema::table('purchase_rfqs', function (Blueprint $table) use ($hasPay, $hasDel, $hasFre) {
                    if (!$hasPay) {
                        $table->unsignedInteger('payment_terms_days')->nullable()->after('due_date');
                    }
                    if (!$hasDel) {
                        $table->unsignedInteger('delivery_terms_days')->nullable()->after('payment_terms_days');
                    }
                    if (!$hasFre) {
                        $table->string('freight_terms', 255)->nullable()->after('delivery_terms_days');
                    }
                });
            }
        }

        // Vendor-level quotation commercial terms (captured during quote compare, used for PO)
        if (Schema::hasTable('purchase_rfq_vendors')) {
            $hasPay = Schema::hasColumn('purchase_rfq_vendors', 'payment_terms_days');
            $hasDel = Schema::hasColumn('purchase_rfq_vendors', 'delivery_terms_days');
            $hasFre = Schema::hasColumn('purchase_rfq_vendors', 'freight_terms');

            if (!$hasPay || !$hasDel || !$hasFre) {
                Schema::table('purchase_rfq_vendors', function (Blueprint $table) use ($hasPay, $hasDel, $hasFre) {
                    if (!$hasPay) {
                        $table->unsignedInteger('payment_terms_days')->nullable()->after('contact_phone');
                    }
                    if (!$hasDel) {
                        $table->unsignedInteger('delivery_terms_days')->nullable()->after('payment_terms_days');
                    }
                    if (!$hasFre) {
                        $table->string('freight_terms', 255)->nullable()->after('delivery_terms_days');
                    }
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('purchase_rfq_vendors')) {
            Schema::table('purchase_rfq_vendors', function (Blueprint $table) {
                if (Schema::hasColumn('purchase_rfq_vendors', 'freight_terms')) {
                    $table->dropColumn('freight_terms');
                }
                if (Schema::hasColumn('purchase_rfq_vendors', 'delivery_terms_days')) {
                    $table->dropColumn('delivery_terms_days');
                }
                if (Schema::hasColumn('purchase_rfq_vendors', 'payment_terms_days')) {
                    $table->dropColumn('payment_terms_days');
                }
            });
        }

        if (Schema::hasTable('purchase_rfqs')) {
            Schema::table('purchase_rfqs', function (Blueprint $table) {
                if (Schema::hasColumn('purchase_rfqs', 'freight_terms')) {
                    $table->dropColumn('freight_terms');
                }
                if (Schema::hasColumn('purchase_rfqs', 'delivery_terms_days')) {
                    $table->dropColumn('delivery_terms_days');
                }
                if (Schema::hasColumn('purchase_rfqs', 'payment_terms_days')) {
                    $table->dropColumn('payment_terms_days');
                }
            });
        }
    }
};
