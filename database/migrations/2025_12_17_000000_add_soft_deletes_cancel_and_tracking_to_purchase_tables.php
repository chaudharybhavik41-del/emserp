<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'purchase_indents',
            'purchase_indent_items',
            'purchase_rfqs',
            'purchase_rfq_items',
            'purchase_rfq_vendors',
            'purchase_rfq_vendor_quotes',
            'purchase_orders',
            'purchase_order_items',
        ];

        foreach ($tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $table) {
                if (! Schema::hasColumn($table->getTable(), 'deleted_at')) {
                    $table->softDeletes();
                }

                if (! Schema::hasColumn($table->getTable(), 'cancelled_at')) {
                    $table->timestamp('cancelled_at')->nullable();
                }

                if (! Schema::hasColumn($table->getTable(), 'cancelled_by')) {
                    $table->unsignedBigInteger('cancelled_by')->nullable();
                    $table->index('cancelled_by');
                }

                if (! Schema::hasColumn($table->getTable(), 'cancel_reason')) {
                    $table->text('cancel_reason')->nullable();
                }
            });
        }

        // Indent item fulfillment tracking
        if (Schema::hasTable('purchase_indent_items')) {
            Schema::table('purchase_indent_items', function (Blueprint $table) {
                if (! Schema::hasColumn('purchase_indent_items', 'rfq_qty_total')) {
                    $table->decimal('rfq_qty_total', 14, 3)->default(0);
                }
                if (! Schema::hasColumn('purchase_indent_items', 'po_qty_total')) {
                    $table->decimal('po_qty_total', 14, 3)->default(0);
                }
                if (! Schema::hasColumn('purchase_indent_items', 'received_qty_total')) {
                    $table->decimal('received_qty_total', 14, 3)->default(0);
                }
                if (! Schema::hasColumn('purchase_indent_items', 'fulfillment_status')) {
                    $table->string('fulfillment_status', 30)->default('open');
                    $table->index('fulfillment_status');
                }
            });
        }

        // RFQ item allocation tracking (for multi-RFQ per indent)
        if (Schema::hasTable('purchase_rfq_items')) {
            Schema::table('purchase_rfq_items', function (Blueprint $table) {
                if (! Schema::hasColumn('purchase_rfq_items', 'allocated_indent_qty')) {
                    $table->decimal('allocated_indent_qty', 14, 3)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        // Intentionally non-destructive rollback (safe for production data).
        // If you want full rollback, we can add dropColumn blocks, but it's risky once data exists.
    }
};
