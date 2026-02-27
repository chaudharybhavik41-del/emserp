<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterMailProfilesAddCoreColumns extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mail_profiles')) {
            return;
        }

        Schema::table('mail_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('mail_profiles', 'code')) {
                $table->string('code', 50)->unique();
            }

            if (! Schema::hasColumn('mail_profiles', 'name')) {
                $table->string('name', 150)->nullable();
            }

            if (! Schema::hasColumn('mail_profiles', 'company_id')) {
                $table->unsignedBigInteger('company_id')->nullable();
            }

            if (! Schema::hasColumn('mail_profiles', 'department_id')) {
                $table->unsignedBigInteger('department_id')->nullable();
            }

            if (! Schema::hasColumn('mail_profiles', 'from_name')) {
                $table->string('from_name', 150)->nullable();
            }

            if (! Schema::hasColumn('mail_profiles', 'from_email')) {
                $table->string('from_email', 150)->nullable();
            }

            if (! Schema::hasColumn('mail_profiles', 'reply_to')) {
                $table->string('reply_to', 150)->nullable();
            }

            if (! Schema::hasColumn('mail_profiles', 'smtp_host')) {
                $table->string('smtp_host', 150)->nullable();
            }

            if (! Schema::hasColumn('mail_profiles', 'smtp_port')) {
                $table->unsignedSmallInteger('smtp_port')->default(587);
            }

            if (! Schema::hasColumn('mail_profiles', 'smtp_encryption')) {
                $table->string('smtp_encryption', 20)->nullable();
            }

            if (! Schema::hasColumn('mail_profiles', 'smtp_username')) {
                $table->string('smtp_username', 150)->nullable();
            }

            if (! Schema::hasColumn('mail_profiles', 'smtp_password')) {
                $table->text('smtp_password')->nullable();
            }

            if (! Schema::hasColumn('mail_profiles', 'is_default')) {
                $table->boolean('is_default')->default(false);
            }

            if (! Schema::hasColumn('mail_profiles', 'is_active')) {
                $table->boolean('is_active')->default(true);
            }

            if (! Schema::hasColumn('mail_profiles', 'last_tested_at')) {
                $table->timestamp('last_tested_at')->nullable();
            }

            if (! Schema::hasColumn('mail_profiles', 'last_test_success')) {
                $table->boolean('last_test_success')->nullable();
            }

            if (! Schema::hasColumn('mail_profiles', 'last_test_error')) {
                $table->text('last_test_error')->nullable();
            }
        });
    }

    public function down(): void
    {
        // Non-destructive down: we don't drop columns to avoid data loss
        Schema::table('mail_profiles', function (Blueprint $table) {
            // intentionally left blank
        });
    }
}
