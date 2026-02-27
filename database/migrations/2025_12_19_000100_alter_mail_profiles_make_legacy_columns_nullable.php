<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('mail_profiles')) {
            return;
        }

        $statements = [];

        if (Schema::hasColumn('mail_profiles', 'host')) {
            $statements[] = "ALTER TABLE `mail_profiles` MODIFY `host` VARCHAR(255) NULL";
        }

        if (Schema::hasColumn('mail_profiles', 'port')) {
            $statements[] = "ALTER TABLE `mail_profiles` MODIFY `port` SMALLINT UNSIGNED NULL";
        }

        if (Schema::hasColumn('mail_profiles', 'username')) {
            $statements[] = "ALTER TABLE `mail_profiles` MODIFY `username` VARCHAR(255) NULL";
        }

        if (Schema::hasColumn('mail_profiles', 'password')) {
            $statements[] = "ALTER TABLE `mail_profiles` MODIFY `password` VARCHAR(255) NULL";
        }

        foreach ($statements as $sql) {
            DB::statement($sql);
        }
    }

    public function down(): void
    {
        // Non-destructive down.
    }
};
