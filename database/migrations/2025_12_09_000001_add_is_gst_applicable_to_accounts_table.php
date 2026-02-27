<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('accounts', 'is_gst_applicable')) {
                $table->boolean('is_gst_applicable')
                    ->default(false)
                    ->after('pan');
            }
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            if (Schema::hasColumn('accounts', 'is_gst_applicable')) {
                $table->dropColumn('is_gst_applicable');
            }
        });
    }
};
