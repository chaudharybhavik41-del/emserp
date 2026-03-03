<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fuel_issues')) {
            return;
        }

        Schema::create('fuel_issues', function (Blueprint $table) {
            $table->id();

            $table->string('issue_number', 50)->nullable()->unique();
            $table->date('issue_date');

            $table->foreignId('machine_id')->constrained('machines')->restrictOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();

            $table->foreignId('store_stock_item_id')->constrained('store_stock_items')->restrictOnDelete();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();
            $table->foreignId('uom_id')->nullable()->constrained('uoms')->nullOnDelete();

            $table->decimal('qty', 15, 3);
            $table->decimal('unit_rate', 15, 2)->default(0);
            $table->decimal('amount', 15, 2)->default(0);

            $table->decimal('opening_meter_reading', 15, 3)->nullable();
            $table->decimal('closing_meter_reading', 15, 3)->nullable();

            $table->string('status', 30)->default('posted');
            $table->text('remarks')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();

            $table->foreignId('voucher_id')->nullable()->constrained('vouchers')->nullOnDelete();
            $table->string('accounting_status', 20)->default('pending'); // pending, posted, not_required
            $table->foreignId('accounting_posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('accounting_posted_at')->nullable();

            $table->timestamps();

            $table->index(['issue_date', 'project_id']);
            $table->index(['machine_id', 'issue_date']);
            $table->index('accounting_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_issues');
    }
};

