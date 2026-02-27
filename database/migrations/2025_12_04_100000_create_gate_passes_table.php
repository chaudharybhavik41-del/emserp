<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gate_passes', function (Blueprint $table) {
            $table->id();
            $table->string('gatepass_number', 30)->unique();
            $table->date('gatepass_date');
            $table->time('gatepass_time')->nullable();
            $table->string('type', 50)->index(); // project_material, machinery_maintenance, other
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contractor_party_id')->nullable()->constrained('parties')->nullOnDelete();
            $table->foreignId('to_party_id')->nullable()->constrained('parties')->nullOnDelete();
            $table->string('vehicle_number', 50)->nullable();
            $table->string('driver_name', 100)->nullable();
            $table->string('transport_mode', 50)->nullable();
            $table->boolean('is_returnable')->default(false);
            $table->string('status', 20)->default('out'); // draft, out, partially_returned, closed, cancelled
            $table->string('reason', 255)->nullable();
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gate_passes');
    }
};
