<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixed_assets', function (Blueprint $table) {
            $table->id();
            $table->enum('asset_type', ['machinery'])->default('machinery');
            $table->string('asset_code')->unique();
            $table->string('name');

            $table->foreignId('item_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('machine_type', ['long_term', 'short_term'])->nullable();

            $table->string('serial_no')->nullable();
            $table->string('make')->nullable();
            $table->string('model')->nullable();
            $table->string('capacity')->nullable();

            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('location_id')->nullable();
            $table->foreignId('vendor_party_id')->nullable()->constrained('parties')->nullOnDelete();

            $table->date('purchase_date')->nullable();
            $table->date('put_to_use_date')->nullable();

            $table->decimal('opening_wdv', 14, 2)->default(0);
            $table->date('opening_as_of')->nullable();
            $table->decimal('original_cost', 14, 2)->nullable();
            $table->decimal('accum_dep_opening', 14, 2)->nullable();

            $table->enum('status', ['in_use', 'idle', 'sold', 'scrapped'])->default('in_use');

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['asset_type', 'status']);
            $table->index('project_id');
            $table->index('purchase_date');
            $table->index('put_to_use_date');
            $table->index('location_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_assets');
    }
};
