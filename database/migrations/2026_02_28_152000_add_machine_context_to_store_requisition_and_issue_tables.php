<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('store_requisitions')) {
            Schema::table('store_requisitions', function (Blueprint $table) {
                if (! Schema::hasColumn('store_requisitions', 'issue_purpose')) {
                    $table->string('issue_purpose', 30)
                        ->default('general')
                        ->after('requisition_date');
                }

                if (! Schema::hasColumn('store_requisitions', 'machine_id')) {
                    $table->foreignId('machine_id')
                        ->nullable()
                        ->after('project_id')
                        ->constrained('machines')
                        ->nullOnDelete();
                }
            });
        }

        if (Schema::hasTable('store_issues')) {
            Schema::table('store_issues', function (Blueprint $table) {
                if (! Schema::hasColumn('store_issues', 'issue_purpose')) {
                    $table->string('issue_purpose', 30)
                        ->default('general')
                        ->after('issue_date');
                }

                if (! Schema::hasColumn('store_issues', 'machine_id')) {
                    $table->foreignId('machine_id')
                        ->nullable()
                        ->after('project_id')
                        ->constrained('machines')
                        ->nullOnDelete();
                }
            });
        }

        if (Schema::hasTable('store_issue_lines')) {
            Schema::table('store_issue_lines', function (Blueprint $table) {
                if (! Schema::hasColumn('store_issue_lines', 'machine_id')) {
                    $table->foreignId('machine_id')
                        ->nullable()
                        ->after('uom_id')
                        ->constrained('machines')
                        ->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('store_issue_lines')) {
            Schema::table('store_issue_lines', function (Blueprint $table) {
                if (Schema::hasColumn('store_issue_lines', 'machine_id')) {
                    $table->dropConstrainedForeignId('machine_id');
                }
            });
        }

        if (Schema::hasTable('store_issues')) {
            Schema::table('store_issues', function (Blueprint $table) {
                if (Schema::hasColumn('store_issues', 'machine_id')) {
                    $table->dropConstrainedForeignId('machine_id');
                }
                if (Schema::hasColumn('store_issues', 'issue_purpose')) {
                    $table->dropColumn('issue_purpose');
                }
            });
        }

        if (Schema::hasTable('store_requisitions')) {
            Schema::table('store_requisitions', function (Blueprint $table) {
                if (Schema::hasColumn('store_requisitions', 'machine_id')) {
                    $table->dropConstrainedForeignId('machine_id');
                }
                if (Schema::hasColumn('store_requisitions', 'issue_purpose')) {
                    $table->dropColumn('issue_purpose');
                }
            });
        }
    }
};

