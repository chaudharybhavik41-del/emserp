<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('machine_spare_consumptions', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('machine_maintenance_log_id')->constrained('machine_maintenance_logs')->cascadeOnDelete();
            $table->foreignId('machine_id')->constrained('machines')->cascadeOnDelete();
            
            $table->foreignId('store_issue_id')->nullable()->constrained('store_issues')->nullOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->foreignId('uom_id')->constrained('uoms')->restrictOnDelete();
            
            $table->decimal('qty_consumed', 10, 3);
            $table->decimal('unit_cost', 15, 2)->default(0);
            $table->decimal('total_cost', 15, 2)->default(0);
            
            $table->text('remarks')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index('machine_maintenance_log_id');
            $table->index('machine_id');
            $table->index('item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('machine_spare_consumptions');
    }
};
