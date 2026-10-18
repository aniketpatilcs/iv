<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Searchable/filterable fields are real indexed columns; the full form
     * snapshot lives in `payload` so the form can evolve without migrations.
     */
    public function up(): void
    {
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->string('stock_number', 20)->nullable()->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('type', 20);
            $table->string('status', 20)->default('submitted');
            $table->string('claim_number', 50);
            $table->string('vin', 17)->nullable();
            $table->string('owner_name')->nullable();
            $table->string('vehicle_description')->nullable();
            $table->string('pickup_state', 40)->nullable();
            $table->date('loss_date')->nullable();
            $table->json('payload');
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index('claim_number');
            $table->index('vin');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignments');
    }
};
