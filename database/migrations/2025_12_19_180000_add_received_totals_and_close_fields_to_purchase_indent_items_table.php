<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_indent_items', function (Blueprint $table) {
            // Running received total from QC-passed GRNs (weight or pcs depending on the line)
            if (!Schema::hasColumn('purchase_indent_items', 'received_qty_total')) {
                $table->decimal('received_qty_total', 14, 3)->default(0)->after('order_qty');
            }

            // Receipt progress status: null | partially_received | received
            if (!Schema::hasColumn('purchase_indent_items', 'receipt_status')) {
                $table->string('receipt_status', 30)->nullable()->after('received_qty_total');
            }

            // Auto-close flag when received >= order_qty
            if (!Schema::hasColumn('purchase_indent_items', 'is_closed')) {
                $table->boolean('is_closed')->default(false)->after('receipt_status');
            }

            if (!Schema::hasColumn('purchase_indent_items', 'closed_at')) {
                $table->timestamp('closed_at')->nullable()->after('is_closed');
            }

            if (!Schema::hasColumn('purchase_indent_items', 'closed_by')) {
                $table->unsignedBigInteger('closed_by')->nullable()->after('closed_at');
                $table->index('closed_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchase_indent_items', function (Blueprint $table) {
            // Drop in reverse order (if present)
            if (Schema::hasColumn('purchase_indent_items', 'closed_by')) {
                $table->dropIndex(['closed_by']);
                $table->dropColumn('closed_by');
            }
            if (Schema::hasColumn('purchase_indent_items', 'closed_at')) {
                $table->dropColumn('closed_at');
            }
            if (Schema::hasColumn('purchase_indent_items', 'is_closed')) {
                $table->dropColumn('is_closed');
            }
            if (Schema::hasColumn('purchase_indent_items', 'receipt_status')) {
                $table->dropColumn('receipt_status');
            }
            if (Schema::hasColumn('purchase_indent_items', 'received_qty_total')) {
                $table->dropColumn('received_qty_total');
            }
        });
    }
};
