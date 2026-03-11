<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_bill_expense_lines', function (Blueprint $table) {
            if (! Schema::hasColumn('purchase_bill_expense_lines', 'machine_id')) {
                $table->foreignId('machine_id')
                    ->nullable()
                    ->after('project_id')
                    ->constrained('fixed_assets')
                    ->nullOnDelete();
                $table->index('machine_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchase_bill_expense_lines', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_bill_expense_lines', 'machine_id')) {
                $table->dropForeign(['machine_id']);
                $table->dropIndex(['machine_id']);
                $table->dropColumn('machine_id');
            }
        });
    }
};

