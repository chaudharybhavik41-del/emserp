<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMailTemplatesTable extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mail_templates')) {
            return;
        }

        Schema::create('mail_templates', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();   // e.g. TEST_EMAIL, GENERAL_NOTIFICATION
            $table->string('name', 150);
            $table->string('type', 50)->default('general'); // general, system, purchase, etc.

            $table->unsignedBigInteger('mail_profile_id')->nullable(); // optional default profile
            $table->string('subject', 200);
            $table->longText('body'); // can store Blade-like placeholders later

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->foreign('mail_profile_id')->references('id')->on('mail_profiles')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_templates');
    }
}
