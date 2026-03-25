<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_v2_dispatch_lines', function (Blueprint $table) {
            $table->unsignedInteger('client_dispatch_part_count')
                ->default(0)
                ->after('segment_no_snapshot');
            $table->text('client_dispatch_part_codes_snapshot')
                ->nullable()
                ->after('client_dispatch_part_count');
            $table->text('client_dispatch_description_snapshot')
                ->nullable()
                ->after('client_dispatch_part_codes_snapshot');
        });

        Schema::table('client_ra_bill_lines', function (Blueprint $table) {
            $table->foreignId('production_v2_dispatch_id')
                ->nullable()
                ->after('revenue_account_id')
                ->constrained('production_v2_dispatches')
                ->nullOnDelete();
            $table->foreignId('production_v2_dispatch_line_id')
                ->nullable()
                ->after('production_v2_dispatch_id')
                ->constrained('production_v2_dispatch_lines')
                ->nullOnDelete();
            $table->index(['production_v2_dispatch_id'], 'idx_client_ra_line_pv2_dispatch');
            $table->index(['production_v2_dispatch_line_id'], 'idx_client_ra_line_pv2_dispatch_line');
        });
    }

    public function down(): void
    {
        Schema::table('client_ra_bill_lines', function (Blueprint $table) {
            $table->dropForeign(['production_v2_dispatch_id']);
            $table->dropForeign(['production_v2_dispatch_line_id']);
            $table->dropIndex('idx_client_ra_line_pv2_dispatch');
            $table->dropIndex('idx_client_ra_line_pv2_dispatch_line');
            $table->dropColumn([
                'production_v2_dispatch_id',
                'production_v2_dispatch_line_id',
            ]);
        });

        Schema::table('production_v2_dispatch_lines', function (Blueprint $table) {
            $table->dropColumn([
                'client_dispatch_part_count',
                'client_dispatch_part_codes_snapshot',
                'client_dispatch_description_snapshot',
            ]);
        });
    }
};
