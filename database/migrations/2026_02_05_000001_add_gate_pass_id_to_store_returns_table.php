<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('store_returns')) {
            return;
        }

        Schema::table('store_returns', function (Blueprint $table) {
            if (! Schema::hasColumn('store_returns', 'gate_pass_id')) {
                $table->foreignId('gate_pass_id')
                    ->nullable()
                    ->after('store_issue_id')
                    ->constrained('gate_passes')
                    ->nullOnDelete();
            }
        });

        /*
         * Backfill for existing returns auto-created from Gate Pass return registration (Phase 1).
         * Those rows contain reason like: "Gate Pass Return: GP-0001"
         */
        if (Schema::hasColumn('store_returns', 'gate_pass_id') && Schema::hasTable('gate_passes')) {
            $rows = DB::table('store_returns')
                ->select('id', 'reason')
                ->whereNull('gate_pass_id')
                ->where('reason', 'like', 'Gate Pass Return:%')
                ->orderBy('id')
                ->limit(10000)
                ->get();

            foreach ($rows as $row) {
                $reason = (string) ($row->reason ?? '');
                $gpNo = trim(str_replace('Gate Pass Return:', '', $reason));

                if ($gpNo === '') {
                    continue;
                }

                $gpId = DB::table('gate_passes')
                    ->where('gatepass_number', $gpNo)
                    ->value('id');

                if (! empty($gpId)) {
                    DB::table('store_returns')
                        ->where('id', $row->id)
                        ->update(['gate_pass_id' => $gpId]);
                }
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('store_returns')) {
            return;
        }

        Schema::table('store_returns', function (Blueprint $table) {
            if (Schema::hasColumn('store_returns', 'gate_pass_id')) {
                $table->dropConstrainedForeignId('gate_pass_id');
            }
        });
    }
};
