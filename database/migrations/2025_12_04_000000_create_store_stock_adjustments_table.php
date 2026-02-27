<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('store_stock_adjustments')) {
            Schema::create('store_stock_adjustments', function (Blueprint $table) {
                $table->id();

                $table->date('adjustment_date');
                $table->string('adjustment_type', 30)->default('opening'); // opening, increase, decrease

                $table->string('reference_number', 50)->nullable()->index();

                $table->foreignId('project_id')
                    ->nullable()
                    ->constrained('projects')
                    ->nullOnDelete();

                $table->string('reason', 255)->nullable();
                $table->text('remarks')->nullable();

                $table->string('status', 30)->default('posted');

                $table->foreignId('created_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('store_stock_adjustments');
    }
};
