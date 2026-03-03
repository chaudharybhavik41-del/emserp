<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fixed_asset_links')) {
            return;
        }

        Schema::create('fixed_asset_links', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('machine_id');
            $table->unsignedBigInteger('voucher_id')->nullable();
            $table->unsignedBigInteger('voucher_line_id')->nullable();

            $table->string('source_type', 50)->default('purchase_bill');
            $table->unsignedBigInteger('source_id')->nullable();
            $table->unsignedBigInteger('source_line_id')->nullable();

            $table->timestamps();

            $table->index('machine_id', 'fixed_asset_links_machine_idx');
            $table->index('voucher_id', 'fixed_asset_links_voucher_idx');
            $table->index('voucher_line_id', 'fixed_asset_links_voucher_line_idx');
            $table->index(['source_type', 'source_id'], 'fixed_asset_links_source_idx');
            $table->unique(['machine_id', 'source_type', 'source_line_id'], 'fixed_asset_links_machine_source_line_unique');

            $table->foreign('machine_id')->references('id')->on('machines')->cascadeOnDelete();
            $table->foreign('voucher_id')->references('id')->on('vouchers')->nullOnDelete();
            $table->foreign('voucher_line_id')->references('id')->on('voucher_lines')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_asset_links');
    }
};
