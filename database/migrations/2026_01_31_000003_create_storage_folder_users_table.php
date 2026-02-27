<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('storage_folder_users')) {
            return;
        }

        Schema::create('storage_folder_users', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('storage_folder_id');
            $table->unsignedBigInteger('user_id');

            // USER-LEVEL folder permissions
            $table->boolean('can_view')->default(false);
            $table->boolean('can_upload')->default(false);
            $table->boolean('can_download')->default(false);
            $table->boolean('can_edit')->default(false);
            $table->boolean('can_delete')->default(false);
            $table->boolean('can_manage_access')->default(false);

            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['storage_folder_id', 'user_id']);

            $table->foreign('storage_folder_id')->references('id')->on('storage_folders')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storage_folder_users');
    }
};
