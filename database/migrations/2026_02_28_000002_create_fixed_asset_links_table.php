<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixed_asset_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fixed_asset_id')->constrained('fixed_assets')->cascadeOnDelete();
            $table->foreignId('voucher_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('voucher_line_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('link_type', ['purchase_capitalization', 'opening', 'adjustment']);
            $table->timestamps();

            $table->index(['fixed_asset_id', 'link_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_asset_links');
    }
};
