<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('purchase_indents')) {
            Schema::table('purchase_indents', function (Blueprint $table) {
                if (!Schema::hasColumn('purchase_indents', 'procurement_status')) {
                    $table->string('procurement_status', 30)->default('open');
                }
            });
        }

        if (Schema::hasTable('purchase_indent_items')) {
            Schema::table('purchase_indent_items', function (Blueprint $table) {
                if (!Schema::hasColumn('purchase_indent_items', 'rfq_qty_total')) {
                    $table->decimal('rfq_qty_total', 15, 3)->default(0);
                }
                if (!Schema::hasColumn('purchase_indent_items', 'po_qty_total')) {
                    $table->decimal('po_qty_total', 15, 3)->default(0);
                }
                if (!Schema::hasColumn('purchase_indent_items', 'received_qty_total')) {
                    $table->decimal('received_qty_total', 15, 3)->default(0);
                }
                if (!Schema::hasColumn('purchase_indent_items', 'fulfillment_status')) {
                    $table->string('fulfillment_status', 30)->default('open');
                }
            });
        }

        if (Schema::hasTable('purchase_rfq_items')) {
            Schema::table('purchase_rfq_items', function (Blueprint $table) {
                if (!Schema::hasColumn('purchase_rfq_items', 'allocated_indent_qty')) {
                    $table->decimal('allocated_indent_qty', 15, 3)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        // No destructive rollback for safety in production
    }
};
