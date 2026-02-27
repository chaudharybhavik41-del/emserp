<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('machines')) {
            return;
        }

        // Normalize any legacy values that may exist from earlier dev iterations
        DB::statement("UPDATE machines SET status='active' WHERE status IS NULL OR status='' OR status='0'");
        DB::statement("UPDATE machines SET status='active' WHERE status IN ('operational','issued')");

        // Enforce the enum that the code expects
        DB::statement("
            ALTER TABLE machines
            MODIFY COLUMN status
            ENUM('active','under_maintenance','breakdown','retired','disposed')
            NOT NULL DEFAULT 'active'
        ");
    }

    public function down(): void
    {
        // no-op (optional)
    }
};
