<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add project_id for project-linked expense bills (Option-1: book expense lines to WIP-OTHER)
        if (!Schema::hasColumn('purchase_bills', 'project_id')) {
            Schema::table('purchase_bills', function (Blueprint $table) {
                // Keep nullable so existing bills are not forced to select a project
                $table->unsignedBigInteger('project_id')->nullable()->after('purchase_order_id');
                $table->index('project_id', 'purchase_bills_project_id_index');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('purchase_bills', 'project_id')) {
            Schema::table('purchase_bills', function (Blueprint $table) {
                // Drop index first (MySQL requirement)
                try {
                    $table->dropIndex('purchase_bills_project_id_index');
                } catch (\Throwable $e) {
                    // ignore
                }

                $table->dropColumn('project_id');
            });
        }
    }
};
