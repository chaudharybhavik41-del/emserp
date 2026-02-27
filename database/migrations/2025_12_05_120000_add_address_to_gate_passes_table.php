<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gate_passes', function (Blueprint $table) {
            if (! Schema::hasColumn('gate_passes', 'address')) {
                $table->string('address', 500)->nullable()->after('transport_mode');
            }
        });
    }

    public function down(): void
    {
        Schema::table('gate_passes', function (Blueprint $table) {
            if (Schema::hasColumn('gate_passes', 'address')) {
                $table->dropColumn('address');
            }
        });
    }
};
