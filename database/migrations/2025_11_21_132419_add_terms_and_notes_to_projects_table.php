<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            // Numeric payment terms for accounts (days)
            if (!Schema::hasColumn('projects', 'payment_terms_days')) {
                $table->unsignedInteger('payment_terms_days')->nullable();
            }

            // Freight terms text
            if (!Schema::hasColumn('projects', 'freight_terms')) {
                $table->string('freight_terms', 150)->nullable();
            }

            // Notes useful for execution / billing / site
            if (!Schema::hasColumn('projects', 'project_special_notes')) {
                $table->text('project_special_notes')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            foreach ([
                'payment_terms_days',
                'freight_terms',
                'project_special_notes',
            ] as $col) {
                if (Schema::hasColumn('projects', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
