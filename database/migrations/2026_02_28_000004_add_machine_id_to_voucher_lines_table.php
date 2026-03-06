<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('voucher_lines', function (Blueprint $table) {
            if (! Schema::hasColumn('voucher_lines', 'machine_id')) {
                $table->foreignId('machine_id')
                    ->nullable()
                    ->after('cost_center_id')
                    ->constrained('fixed_assets')
                    ->nullOnDelete();

                $table->index('machine_id');
                $table->index(['machine_id', 'voucher_id']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('voucher_lines', function (Blueprint $table) {
            if (Schema::hasColumn('voucher_lines', 'machine_id')) {
                $table->dropIndex(['machine_id', 'voucher_id']);
                $table->dropIndex(['machine_id']);
                $table->dropForeign(['machine_id']);
                $table->dropColumn('machine_id');
            }
        });
    }
};

