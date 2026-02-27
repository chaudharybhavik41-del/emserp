<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('store_returns')) {
            return;
        }

        Schema::table('store_returns', function (Blueprint $table) {
            // Link to accounting voucher created when return is posted to accounts
            if (! Schema::hasColumn('store_returns', 'voucher_id')) {
                $table->foreignId('voucher_id')
                    ->nullable()
                    ->after('created_by')
                    ->constrained('vouchers')
                    ->nullOnDelete();
            }

            // Accounting posting lifecycle
            if (! Schema::hasColumn('store_returns', 'accounting_status')) {
                $table->string('accounting_status', 20)
                    ->default('pending') // pending, posted, not_required
                    ->after('voucher_id');
                $table->index('accounting_status');
            }

            if (! Schema::hasColumn('store_returns', 'accounting_posted_by')) {
                $table->foreignId('accounting_posted_by')
                    ->nullable()
                    ->after('accounting_status')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('store_returns', 'accounting_posted_at')) {
                $table->timestamp('accounting_posted_at')
                    ->nullable()
                    ->after('accounting_posted_by');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('store_returns')) {
            return;
        }

        Schema::table('store_returns', function (Blueprint $table) {
            if (Schema::hasColumn('store_returns', 'accounting_posted_at')) {
                $table->dropColumn('accounting_posted_at');
            }

            if (Schema::hasColumn('store_returns', 'accounting_posted_by')) {
                $table->dropConstrainedForeignId('accounting_posted_by');
            }

            if (Schema::hasColumn('store_returns', 'accounting_status')) {
                $table->dropIndex(['accounting_status']);
                $table->dropColumn('accounting_status');
            }

            if (Schema::hasColumn('store_returns', 'voucher_id')) {
                $table->dropConstrainedForeignId('voucher_id');
            }
        });
    }
};
