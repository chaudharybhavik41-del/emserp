<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            // Link reversals (Phase 6)
            $table->unsignedBigInteger('reversal_of_voucher_id')->nullable()->after('posted_at');
            $table->unsignedBigInteger('reversal_voucher_id')->nullable()->after('reversal_of_voucher_id');

            $table->unsignedBigInteger('reversed_by')->nullable()->after('reversal_voucher_id');
            $table->timestamp('reversed_at')->nullable()->after('reversed_by');
            $table->string('reversal_reason', 255)->nullable()->after('reversed_at');

            $table->index('reversal_of_voucher_id');
            $table->index('reversal_voucher_id');
            $table->index('reversed_at');
        });

        // Foreign keys as a separate step so the column add succeeds cleanly
        Schema::table('vouchers', function (Blueprint $table) {
            $table->foreign('reversal_of_voucher_id')
                ->references('id')
                ->on('vouchers')
                ->nullOnDelete();

            $table->foreign('reversal_voucher_id')
                ->references('id')
                ->on('vouchers')
                ->nullOnDelete();

            $table->foreign('reversed_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });

        // Data hygiene (Phase 6): some old vouchers are `posted` but missing posted_at.
        // Backfill posted_at from updated_at, falling back to created_at.
        DB::table('vouchers')
            ->where('status', 'posted')
            ->whereNull('posted_at')
            ->update([
                'posted_at' => DB::raw('COALESCE(updated_at, created_at)'),
            ]);

        // Backfill posted_by from created_by if missing (best-effort, not perfect)
        DB::table('vouchers')
            ->where('status', 'posted')
            ->whereNull('posted_by')
            ->whereNotNull('created_by')
            ->update([
                'posted_by' => DB::raw('created_by'),
            ]);
    }

    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            // Drop FKs first
            try {
                $table->dropForeign(['reversal_of_voucher_id']);
            } catch (Throwable $e) {
                // ignore
            }
            try {
                $table->dropForeign(['reversal_voucher_id']);
            } catch (Throwable $e) {
                // ignore
            }
            try {
                $table->dropForeign(['reversed_by']);
            } catch (Throwable $e) {
                // ignore
            }

            // Drop columns + indexes
            $table->dropColumn([
                'reversal_of_voucher_id',
                'reversal_voucher_id',
                'reversed_by',
                'reversed_at',
                'reversal_reason',
            ]);
        });
    }
};
