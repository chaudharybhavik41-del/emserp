<?php

use App\Models\ClientRaBill;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_ra_bills', function (Blueprint $table) {
            $table->string('bill_kind', 40)->nullable()->after('revenue_type');
            $table->string('source_basis', 40)->nullable()->after('bill_kind');
            $table->string('material_scope', 30)->nullable()->after('source_basis');
        });

        DB::table('client_ra_bills')
            ->select(['id', 'revenue_type'])
            ->orderBy('id')
            ->chunkById(200, function ($bills): void {
                foreach ($bills as $bill) {
                    $billKind = ClientRaBill::defaultBillKindFor($bill->revenue_type, false);

                    DB::table('client_ra_bills')
                        ->where('id', $bill->id)
                        ->update([
                            'bill_kind' => $billKind,
                            'source_basis' => ClientRaBill::defaultSourceBasisFor($billKind, false),
                            'material_scope' => ClientRaBill::defaultMaterialScopeFor($billKind),
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('client_ra_bills', function (Blueprint $table) {
            $table->dropColumn(['bill_kind', 'source_basis', 'material_scope']);
        });
    }
};
