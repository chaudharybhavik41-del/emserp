<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterDepartmentsAddDescriptionColumn extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('departments')) {
            return;
        }

        Schema::table('departments', function (Blueprint $table) {
            if (! Schema::hasColumn('departments', 'description')) {
                $table->string('description', 500)->nullable()->after('name');
            }
        });
    }

    public function down(): void
    {
        // Non-destructive: keep data if present
        Schema::table('departments', function (Blueprint $table) {
            // Intentionally left blank – we don't drop description to avoid losing data
        });
    }
}
