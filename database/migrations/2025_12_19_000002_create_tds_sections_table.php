<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tds_sections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');

            // e.g. 194C, 194J
            $table->string('code', 20);

            // Short name shown in dropdown
            $table->string('name', 150);

            $table->string('description', 500)->nullable();

            // Default TDS rate (%)
            $table->decimal('default_rate', 8, 4)->default(0);

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'is_active']);

            $table->foreign('company_id')
                ->references('id')->on('companies')
                ->cascadeOnDelete();
        });

        // Seed a minimal default list for each company (safe for production)
        // NOTE: Rates can vary. Admin can edit in UI.
        $defaults = [
            ['code' => '194C', 'name' => 'Contractor / Subcontractor', 'default_rate' => 1.0000],
            ['code' => '194J', 'name' => 'Professional / Technical Services', 'default_rate' => 10.0000],
            ['code' => '194H', 'name' => 'Commission / Brokerage', 'default_rate' => 5.0000],
            ['code' => '194I', 'name' => 'Rent', 'default_rate' => 10.0000],
            ['code' => '194Q', 'name' => 'Purchase of Goods', 'default_rate' => 0.1000],
            ['code' => '194A', 'name' => 'Interest (Other than Securities)', 'default_rate' => 10.0000],
        ];

        try {
            $companyIds = DB::table('companies')->pluck('id');
            foreach ($companyIds as $companyId) {
                $companyId = (int) $companyId;
                $exists = DB::table('tds_sections')->where('company_id', $companyId)->exists();
                if ($exists) {
                    continue;
                }

                foreach ($defaults as $row) {
                    DB::table('tds_sections')->insert([
                        'company_id'    => $companyId,
                        'code'          => $row['code'],
                        'name'          => $row['name'],
                        'description'   => null,
                        'default_rate'  => $row['default_rate'],
                        'is_active'     => true,
                        'created_at'    => now(),
                        'updated_at'    => now(),
                    ]);
                }
            }
        } catch (Throwable $e) {
            // If companies table is not available yet for some reason, do not fail migration.
            // Admin can add TDS sections later from UI.
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tds_sections');
    }
};
