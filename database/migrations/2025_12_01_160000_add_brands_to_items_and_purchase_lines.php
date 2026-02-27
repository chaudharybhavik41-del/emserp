<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Store multiple brands per item as JSON in a TEXT column
        Schema::table('items', function (Blueprint $table) {
            $table->text('brands')->nullable()->after('description');
        });

        // Store chosen brand on each transaction line (simple string)
        Schema::table('purchase_indent_items', function (Blueprint $table) {
            $table->string('brand', 100)->nullable()->after('item_id');
        });

        Schema::table('purchase_rfq_items', function (Blueprint $table) {
            $table->string('brand', 100)->nullable()->after('item_id');
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->string('brand', 100)->nullable()->after('item_id');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropColumn('brand');
        });

        Schema::table('purchase_rfq_items', function (Blueprint $table) {
            $table->dropColumn('brand');
        });

        Schema::table('purchase_indent_items', function (Blueprint $table) {
            $table->dropColumn('brand');
        });

        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn('brands');
        });
    }
};
