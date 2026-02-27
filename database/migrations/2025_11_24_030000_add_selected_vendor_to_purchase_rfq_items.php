<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_rfq_items', function (Blueprint $table) {
            $table->foreignId('selected_vendor_id')
                ->nullable()
                ->after('purchase_indent_item_id')
                ->constrained('purchase_rfq_vendors');

            $table->foreignId('selected_quote_id')
                ->nullable()
                ->after('selected_vendor_id')
                ->constrained('purchase_rfq_vendor_quotes');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_rfq_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('selected_quote_id');
            $table->dropConstrainedForeignId('selected_vendor_id');
        });
    }
};
