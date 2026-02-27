<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_return_lines', function (Blueprint $table) {
            $table->index('store_issue_line_id', 'idx_store_return_lines_issue_line');
        });
    }

    public function down(): void
    {
        Schema::table('store_return_lines', function (Blueprint $table) {
            $table->dropIndex('idx_store_return_lines_issue_line');
        });
    }
};
