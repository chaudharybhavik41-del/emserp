<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('machine_breakdown_register', function (Blueprint $table) {
            if (! Schema::hasColumn('machine_breakdown_register', 'resolved_by')) {
                $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('machine_breakdown_register', 'repair_notes')) {
                $table->text('repair_notes')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('machine_breakdown_register', function (Blueprint $table) {
            if (Schema::hasColumn('machine_breakdown_register', 'resolved_by')) {
                $table->dropConstrainedForeignId('resolved_by');
            }

            if (Schema::hasColumn('machine_breakdown_register', 'repair_notes')) {
                $table->dropColumn('repair_notes');
            }
        });
    }
};
