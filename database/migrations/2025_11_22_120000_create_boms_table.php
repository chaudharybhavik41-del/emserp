<?php

use App\Enums\BomStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('boms')) {
            Schema::create('boms', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')->constrained()->restrictOnDelete();

                $table->string('bom_number')->unique();
                $table->unsignedInteger('version')->default(1);

                $table->string('status', 20)->default(BomStatus::DRAFT->value);

                $table->decimal('total_weight', 12, 3)->default(0);

                $table->timestamp('finalized_date')->nullable();
                $table->foreignId('finalized_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->json('metadata')->nullable();

                $table->timestamps();
                $table->softDeletes();

                $table->index(['project_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('boms');
    }
};
