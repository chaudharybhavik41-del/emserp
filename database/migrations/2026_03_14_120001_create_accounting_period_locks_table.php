<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_period_locks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->string('title')->nullable();
            $table->date('from_date');
            $table->date('to_date');
            $table->text('reason')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'from_date', 'to_date'], 'accounting_period_locks_company_range_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_period_locks');
    }
};
