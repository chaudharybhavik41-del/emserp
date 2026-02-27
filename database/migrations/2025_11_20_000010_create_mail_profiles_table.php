<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMailProfilesTable extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mail_profiles')) {
            return;
        }

        Schema::create('mail_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();        // e.g. DEFAULT, PURCHASE, STORE
            $table->string('name', 150);                // human readable

            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('department_id')->nullable();

            $table->string('from_name', 150)->nullable();
            $table->string('from_email', 150);
            $table->string('reply_to', 150)->nullable();

            // SMTP settings
            $table->string('smtp_host', 150);
            $table->unsignedSmallInteger('smtp_port')->default(587);
            $table->string('smtp_encryption', 20)->nullable(); // null, tls, ssl
            $table->string('smtp_username', 150);
            $table->text('smtp_password'); // store encrypted using Laravel encrypt()

            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);

            // basic health info
            $table->timestamp('last_tested_at')->nullable();
            $table->boolean('last_test_success')->nullable();
            $table->text('last_test_error')->nullable();

            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->nullOnDelete();
            $table->foreign('department_id')->references('id')->on('departments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_profiles');
    }
}
