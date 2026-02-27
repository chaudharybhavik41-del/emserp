<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('purchase_rfq_vendor_quotes')) {
            return;
        }

        Schema::table('purchase_rfq_vendor_quotes', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_rfq_vendor_quotes', 'revision_no')) {
                $table->unsignedInteger('revision_no')->default(1);
            }
            if (!Schema::hasColumn('purchase_rfq_vendor_quotes', 'is_active')) {
                $table->boolean('is_active')->default(true);
            }
            if (!Schema::hasColumn('purchase_rfq_vendor_quotes', 'revised_at')) {
                $table->timestamp('revised_at')->nullable();
            }
            if (!Schema::hasColumn('purchase_rfq_vendor_quotes', 'revised_by')) {
                $table->unsignedBigInteger('revised_by')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('purchase_rfq_vendor_quotes')) {
            return;
        }

        Schema::table('purchase_rfq_vendor_quotes', function (Blueprint $table) {
            // Safe: do not drop columns on down to avoid data loss
        });
    }
};
