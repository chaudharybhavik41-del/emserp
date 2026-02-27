<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voucher_series', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');

            // Internal key used by posting services, e.g. purchase, sales, payment
            $table->string('key', 50);

            // Human label for UI
            $table->string('name', 100)->nullable();

            // Printed prefix, must be UNIQUE within the company across all series
            $table->string('prefix', 20);

            // If true: PREFIX/{FY}/{SEQ}; else: PREFIX{sep}{SEQ}
            $table->boolean('use_financial_year')->default(true);

            // Separator for joining parts ("/" or "-" etc)
            $table->string('separator', 5)->default('/');

            // Sequence zero-padding length
            $table->unsignedTinyInteger('pad_length')->default(4);

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();

            $table->unique(['company_id', 'key']);
            $table->unique(['company_id', 'prefix']);
        });

        Schema::create('voucher_series_counters', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('voucher_series_id');

            // FY code like "2025-26" OR "NA" for non-FY series
            $table->string('fy_code', 20)->default('NA');
            $table->unsignedBigInteger('next_number')->default(1);
            $table->timestamps();

            $table->foreign('voucher_series_id')
                ->references('id')
                ->on('voucher_series')
                ->cascadeOnDelete();

            $table->unique(['voucher_series_id', 'fy_code']);
            $table->index(['voucher_series_id', 'fy_code']);
        });

        // Seed default series definitions for all companies.
        // Counters are created lazily on first generation (and seeded from existing vouchers).
        $seriesConfig = (array) Config::get('accounting.voucher_series', []);

        if (! empty($seriesConfig) && Schema::hasTable('companies')) {
            $companyIds = DB::table('companies')->pluck('id')->all();
            $now = now();

            foreach ($companyIds as $companyId) {
                foreach ($seriesConfig as $key => $prefix) {
                    $key = (string) $key;
                    $prefix = (string) $prefix;

                    // Keep backward compatibility:
                    // - purchase vouchers historically used PB-000001
                    // - store issues used ISS-000001 (or issue_number)
                    $noFy = in_array($key, ['purchase', 'store_issue'], true);

                    DB::table('voucher_series')->updateOrInsert(
                        ['company_id' => $companyId, 'key' => $key],
                        [
                            'name'               => ucwords(str_replace('_', ' ', $key)),
                            'prefix'             => $prefix,
                            'use_financial_year' => ! $noFy,
                            'separator'          => $noFy ? '-' : '/',
                            'pad_length'         => $noFy ? 6 : 4,
                            'is_active'          => 1,
                            'created_at'         => $now,
                            'updated_at'         => $now,
                        ]
                    );
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('voucher_series_counters');
        Schema::dropIfExists('voucher_series');
    }
};
