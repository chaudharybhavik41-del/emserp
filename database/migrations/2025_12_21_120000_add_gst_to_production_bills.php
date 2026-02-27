<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Header GST fields
        if (Schema::hasTable('production_bills')) {
            Schema::table('production_bills', function (Blueprint $table) {
                if (!Schema::hasColumn('production_bills', 'gst_type')) {
                    // cgst_sgst or igst
                    $table->enum('gst_type', ['cgst_sgst', 'igst'])->default('cgst_sgst')->after('status');
                }
                if (!Schema::hasColumn('production_bills', 'gst_rate')) {
                    $table->decimal('gst_rate', 6, 2)->default(0)->after('gst_type');
                }
                if (!Schema::hasColumn('production_bills', 'cgst_total')) {
                    $table->decimal('cgst_total', 14, 2)->default(0)->after('tax_total');
                }
                if (!Schema::hasColumn('production_bills', 'sgst_total')) {
                    $table->decimal('sgst_total', 14, 2)->default(0)->after('cgst_total');
                }
                if (!Schema::hasColumn('production_bills', 'igst_total')) {
                    $table->decimal('igst_total', 14, 2)->default(0)->after('sgst_total');
                }
                if (!Schema::hasColumn('production_bills', 'finalized_by')) {
                    $table->foreignId('finalized_by')->nullable()->after('updated_by')->constrained('users')->nullOnDelete();
                }
                if (!Schema::hasColumn('production_bills', 'finalized_at')) {
                    $table->timestamp('finalized_at')->nullable()->after('finalized_by');
                }
            });
        }

        // Line GST fields
        if (Schema::hasTable('production_bill_lines')) {
            Schema::table('production_bill_lines', function (Blueprint $table) {
                if (!Schema::hasColumn('production_bill_lines', 'cgst_amount')) {
                    $table->decimal('cgst_amount', 14, 2)->default(0)->after('amount');
                }
                if (!Schema::hasColumn('production_bill_lines', 'sgst_amount')) {
                    $table->decimal('sgst_amount', 14, 2)->default(0)->after('cgst_amount');
                }
                if (!Schema::hasColumn('production_bill_lines', 'igst_amount')) {
                    $table->decimal('igst_amount', 14, 2)->default(0)->after('sgst_amount');
                }
                if (!Schema::hasColumn('production_bill_lines', 'line_total')) {
                    $table->decimal('line_total', 14, 2)->default(0)->after('igst_amount');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('production_bill_lines')) {
            Schema::table('production_bill_lines', function (Blueprint $table) {
                if (Schema::hasColumn('production_bill_lines', 'line_total')) {
                    $table->dropColumn(['cgst_amount', 'sgst_amount', 'igst_amount', 'line_total']);
                }
            });
        }

        if (Schema::hasTable('production_bills')) {
            Schema::table('production_bills', function (Blueprint $table) {
                if (Schema::hasColumn('production_bills', 'gst_type')) {
                    $table->dropColumn(['gst_type', 'gst_rate', 'cgst_total', 'sgst_total', 'igst_total']);
                }
                if (Schema::hasColumn('production_bills', 'finalized_by')) {
                    $table->dropConstrainedForeignId('finalized_by');
                }
                if (Schema::hasColumn('production_bills', 'finalized_at')) {
                    $table->dropColumn(['finalized_at']);
                }
            });
        }
    }
};
