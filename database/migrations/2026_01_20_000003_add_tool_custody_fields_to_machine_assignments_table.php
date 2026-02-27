<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('machine_assignments')) {
            return;
        }

        Schema::table('machine_assignments', function (Blueprint $table) {
            // Return disposition
            if (! Schema::hasColumn('machine_assignments', 'return_disposition')) {
                $table->string('return_disposition', 20)
                    ->default('returned')
                    ->after('condition_at_return')
                    ->comment('returned|scrapped');
            }

            // Damage / scrap settlement fields
            if (! Schema::hasColumn('machine_assignments', 'damage_borne_by')) {
                $table->string('damage_borne_by', 20)
                    ->nullable()
                    ->after('return_disposition')
                    ->comment('company|contractor|shared');
            }

            if (! Schema::hasColumn('machine_assignments', 'damage_recovery_amount')) {
                $table->decimal('damage_recovery_amount', 18, 2)
                    ->default(0)
                    ->after('damage_borne_by')
                    ->comment('Amount recoverable from contractor/worker for scrap/damage');
            }

            if (! Schema::hasColumn('machine_assignments', 'damage_loss_amount')) {
                $table->decimal('damage_loss_amount', 18, 2)
                    ->default(0)
                    ->after('damage_recovery_amount')
                    ->comment('Net loss booked to company (e.g., scrap loss)');
            }

            // Accounting voucher references (optional)
            if (! Schema::hasColumn('machine_assignments', 'issue_voucher_id')) {
                $table->unsignedBigInteger('issue_voucher_id')
                    ->nullable()
                    ->after('returned_by');
                $table->index('issue_voucher_id', 'idx_ma_issue_voucher');
            }

            if (! Schema::hasColumn('machine_assignments', 'return_voucher_id')) {
                $table->unsignedBigInteger('return_voucher_id')
                    ->nullable()
                    ->after('issue_voucher_id');
                $table->index('return_voucher_id', 'idx_ma_return_voucher');
            }
        });

        // Add foreign keys in a separate step (avoid issues if vouchers table isn't ready)
        Schema::table('machine_assignments', function (Blueprint $table) {
            if (Schema::hasColumn('machine_assignments', 'issue_voucher_id')) {
                try {
                    $table->foreign('issue_voucher_id')
                        ->references('id')
                        ->on('vouchers')
                        ->nullOnDelete();
                } catch (\Throwable $e) {
                    // ignore if FK already exists or cannot be added in this environment
                }
            }

            if (Schema::hasColumn('machine_assignments', 'return_voucher_id')) {
                try {
                    $table->foreign('return_voucher_id')
                        ->references('id')
                        ->on('vouchers')
                        ->nullOnDelete();
                } catch (\Throwable $e) {
                    // ignore if FK already exists or cannot be added
                }
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('machine_assignments')) {
            return;
        }

        Schema::table('machine_assignments', function (Blueprint $table) {
            // Drop FKs first
            try { $table->dropForeign(['issue_voucher_id']); } catch (\Throwable $e) {}
            try { $table->dropForeign(['return_voucher_id']); } catch (\Throwable $e) {}

            if (Schema::hasColumn('machine_assignments', 'return_voucher_id')) {
                $table->dropIndex('idx_ma_return_voucher');
                $table->dropColumn('return_voucher_id');
            }

            if (Schema::hasColumn('machine_assignments', 'issue_voucher_id')) {
                $table->dropIndex('idx_ma_issue_voucher');
                $table->dropColumn('issue_voucher_id');
            }

            if (Schema::hasColumn('machine_assignments', 'damage_loss_amount')) {
                $table->dropColumn('damage_loss_amount');
            }
            if (Schema::hasColumn('machine_assignments', 'damage_recovery_amount')) {
                $table->dropColumn('damage_recovery_amount');
            }
            if (Schema::hasColumn('machine_assignments', 'damage_borne_by')) {
                $table->dropColumn('damage_borne_by');
            }
            if (Schema::hasColumn('machine_assignments', 'return_disposition')) {
                $table->dropColumn('return_disposition');
            }
        });
    }
};
