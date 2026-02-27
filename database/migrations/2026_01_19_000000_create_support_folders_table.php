<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('support_folders')) {
            return;
        }

        Schema::create('support_folders', function (Blueprint $table) {
            $table->id();

            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('support_folders')
                ->nullOnDelete();

            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['parent_id', 'sort_order']);
        });

        // Create a default root folder so the module is usable immediately.
        DB::table('support_folders')->insert([
            'parent_id'   => null,
            'name'        => 'General',
            'description' => 'Default folder for standard documents.',
            'sort_order'  => 0,
            'is_active'   => true,
            'created_by'  => null,
            'updated_by'  => null,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('support_folders');
    }
};
