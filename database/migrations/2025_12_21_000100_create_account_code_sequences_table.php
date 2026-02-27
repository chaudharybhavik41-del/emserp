<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('account_code_sequences', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('series_key', 64);     // e.g. BANK_ACCOUNTS, SUNDRY_DEBTORS, DEFAULT_ASSETS
            $table->string('prefix', 16);         // e.g. 1001
            $table->unsignedInteger('next_number')->default(1);
            $table->unsignedTinyInteger('pad_width')->default(3); // 001,002...
            $table->timestamps();

            $table->unique(['company_id', 'series_key'], 'acc_code_seq_company_series_uq');
            $table->index(['company_id', 'series_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_code_sequences');
    }
};
