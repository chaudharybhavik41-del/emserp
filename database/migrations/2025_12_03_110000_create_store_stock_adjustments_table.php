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
                $table->string('adjustment_type', 30)->default('opening');
                $table->string('reference_number', 50)->nullable()->unique();

                $table->foreignId('project_id')
                    ->nullable()
                    ->constrained('projects')
                    ->nullOnDelete();

                $table->string('reason', 100)->nullable();
                $table->text('remarks')->nullable();

                $table->foreignId('created_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->string('status', 20)->default('posted');

                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('store_stock_adjustments');
    }
};
