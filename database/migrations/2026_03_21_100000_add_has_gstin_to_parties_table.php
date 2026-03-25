<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('parties') || Schema::hasColumn('parties', 'has_gstin')) {
            return;
        }

        Schema::table('parties', function (Blueprint $table) {
            $table->boolean('has_gstin')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('parties') || ! Schema::hasColumn('parties', 'has_gstin')) {
            return;
        }

        Schema::table('parties', function (Blueprint $table) {
            $table->dropColumn('has_gstin');
        });
    }
};
