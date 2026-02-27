<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (! Schema::hasColumn('projects', 'wip_to_cogs_voucher_id')) {
                $table->foreignId('wip_to_cogs_voucher_id')
                    ->nullable()
                    ->constrained('vouchers')
                    ->nullOnDelete()
                    ->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (Schema::hasColumn('projects', 'wip_to_cogs_voucher_id')) {
                try {
                    $table->dropForeign(['wip_to_cogs_voucher_id']);
                } catch (\Throwable $e) {
                    // ignore
                }

                $table->dropColumn('wip_to_cogs_voucher_id');
            }
        });
    }
};
