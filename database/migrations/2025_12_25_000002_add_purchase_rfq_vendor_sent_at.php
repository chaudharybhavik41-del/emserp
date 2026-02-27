<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add sent_at to purchase_rfq_vendors to track RFQ email dispatch time.
     */
    public function up(): void
    {
        if (!Schema::hasTable('purchase_rfq_vendors')) {
            return;
        }

        if (!Schema::hasColumn('purchase_rfq_vendors', 'sent_at')) {
            Schema::table('purchase_rfq_vendors', function (Blueprint $table) {
                // Nullable because older RFQs / vendors may not have been emailed yet
                $table->timestamp('sent_at')->nullable()->after('status');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('purchase_rfq_vendors')) {
            return;
        }

        if (Schema::hasColumn('purchase_rfq_vendors', 'sent_at')) {
            Schema::table('purchase_rfq_vendors', function (Blueprint $table) {
                $table->dropColumn('sent_at');
            });
        }
    }
};
