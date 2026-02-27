<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gate_pass_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gate_pass_id')->constrained('gate_passes')->cascadeOnDelete();
            $table->unsignedInteger('line_no')->default(1);
            $table->string('description', 255)->nullable();
            $table->foreignId('item_id')->nullable()->constrained('items')->nullOnDelete();
            $table->foreignId('uom_id')->nullable()->constrained('uoms')->nullOnDelete();
            $table->foreignId('machine_id')->nullable()->constrained('machines')->nullOnDelete();
            $table->decimal('qty', 15, 3)->default(0);
            $table->boolean('is_returnable')->default(false);
            $table->date('expected_return_date')->nullable();
            $table->decimal('returned_qty', 15, 3)->default(0);
            $table->date('returned_on')->nullable();
            $table->string('remarks', 255)->nullable();
            $table->foreignId('store_issue_line_id')->nullable()->constrained('store_issue_lines')->nullOnDelete();
            $table->foreignId('store_stock_item_id')->nullable()->constrained('store_stock_items')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gate_pass_lines');
    }
};
