<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('material_subcategories')) {
            return;
        }

        Schema::table('material_subcategories', function (Blueprint $table) {
            if (! Schema::hasColumn('material_subcategories', 'item_code_prefix')) {
                $table->string('item_code_prefix', 10)
                    ->nullable()
                    ->after('code');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('material_subcategories')) {
            return;
        }

        Schema::table('material_subcategories', function (Blueprint $table) {
            if (Schema::hasColumn('material_subcategories', 'item_code_prefix')) {
                $table->dropColumn('item_code_prefix');
            }
        });
    }
};
