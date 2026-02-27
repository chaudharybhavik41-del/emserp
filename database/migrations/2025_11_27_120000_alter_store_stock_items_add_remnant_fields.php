<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('store_stock_items')) {
            return;
        }

        Schema::table('store_stock_items', function (Blueprint $table) {
            if (! Schema::hasColumn('store_stock_items', 'is_remnant')) {
                $table->boolean('is_remnant')
                    ->default(false)
                    ->after('is_client_material');
            }

            if (! Schema::hasColumn('store_stock_items', 'mother_stock_item_id')) {
                $table->unsignedBigInteger('mother_stock_item_id')
                    ->nullable()
                    ->after('is_remnant');
            }

            if (! Schema::hasColumn('store_stock_items', 'client_party_id')) {
                $table->unsignedBigInteger('client_party_id')
                    ->nullable()
                    ->after('project_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('store_stock_items')) {
            return;
        }

        Schema::table('store_stock_items', function (Blueprint $table) {
            if (Schema::hasColumn('store_stock_items', 'is_remnant')) {
                $table->dropColumn('is_remnant');
            }
            if (Schema::hasColumn('store_stock_items', 'mother_stock_item_id')) {
                $table->dropColumn('mother_stock_item_id');
            }
            if (Schema::hasColumn('store_stock_items', 'client_party_id')) {
                $table->dropColumn('client_party_id');
            }
        });
    }
};
