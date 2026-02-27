<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_rfqs', function (Blueprint $table) {
            $table->foreignId('purchase_indent_id')
                ->nullable()
                ->after('code')
                ->constrained('purchase_indents');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_rfqs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('purchase_indent_id');
        });
    }
};
